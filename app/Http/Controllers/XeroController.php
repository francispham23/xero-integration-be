<?php

namespace App\Http\Controllers;

use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Api\IdentityApi;
use XeroAPI\XeroPHP\Api\AccountingApi;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class XeroController extends Controller
{
    private $provider;

    public function __construct()
    {
        $this->provider = new GenericProvider([
            'clientId'                => config('xero.oauth.client_id'),
            'clientSecret'            => config('xero.oauth.client_secret'),
            'redirectUri'             => config('xero.oauth.redirect_uri'),
            'urlAuthorize'           => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken'         => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
        ]);
    }

    private function getXeroConfig()
    {
        $tokenData = Session::get('xero_token');

        if (!$tokenData) {
            // Try to get from storage
            $tokenJson = Storage::get('xero/tokens/token.json');
            if ($tokenJson) {
                $tokenData = json_decode($tokenJson, true);
            }
        }

        if (!$tokenData || Carbon::createFromTimestamp($tokenData['expires'])->isPast()) {
            throw new \Exception('Xero token is missing or expired. Please authenticate first.');
        }

        return Configuration::getDefaultConfiguration()->setAccessToken($tokenData['access_token']);
    }

    public function authorize(Request $request)
    {
        $state = bin2hex(random_bytes(16));

        // Store state in both session and cache
        Session::put('oauth2state', $state);
        Cache::put('oauth2state_' . $state, $state, now()->addMinutes(5));

        // Force the session to be saved immediately
        Session::save();

        Log::info('Xero OAuth - State generated and stored', [
            'state' => $state,
            'session_id' => Session::getId(),
            'cache_key' => 'oauth2state_' . $state
        ]);

        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'scope' => config('xero.oauth.scopes'),
            'state' => $state
        ]);

        return redirect($authorizationUrl);
    }

    public function callback(Request $request)
    {
        $received_state = $request->state;
        $stored_state = session('oauth2state');
        $cached_state = Cache::get('oauth2state_' . $received_state);

        Log::info('Xero OAuth - Callback received', [
            'received_state' => $received_state,
            'stored_state' => $stored_state,
            'cached_state' => $cached_state,
            'session_id' => Session::getId()
        ]);

        if (empty($received_state) || ($received_state !== $stored_state && $received_state !== $cached_state)) {
            Log::error('Xero OAuth - State mismatch', [
                'received_state' => $received_state,
                'stored_state' => $stored_state,
                'cached_state' => $cached_state,
                'session_id' => Session::getId()
            ]);

            session()->forget('oauth2state');
            Cache::forget('oauth2state_' . $received_state);

            return response()->json(['error' => 'Invalid state', 'details' => [
                'received' => $received_state,
                'stored' => $stored_state,
                'cached' => $cached_state
            ]], 401);
        }

        // Clean up the state from cache
        Cache::forget('oauth2state_' . $received_state);

        try {
            // Get access token
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->code
            ]);

            $config = Configuration::getDefaultConfiguration()->setAccessToken( (string)$token->getToken() );
            $identityInstance = new IdentityApi(
        new Client(),
        $config
            );

            $result = $identityInstance->getConnections();

            $tokenData = [
                'access_token' => $token->getToken(),
                'refresh_token' => $token->getRefreshToken(),
                'expires' => $token->getExpires(),
                'tenant_id' => $result[0]->getTenantId(),
                'id_token' => $token->getValues()['id_token']
            ];

            Session::put('xero_token', $tokenData);

            // Optional: Also store in file system for persistence
            Storage::put('xero/tokens/token.json', json_encode($tokenData));

            return response()->json(['message' => 'Successfully authenticated with Xero']);

        } catch (IdentityProviderException $e) {
            echo "Callback failed";
            return response()->json(['error' => $e->getMessage()], 500);

        }
    }

    public function getVendors()
    {
        try {
            $config = $this->getXeroConfig();
            $accountingApi = new AccountingApi(
                new Client(),
                $config
            );

            $tokenData = Session::get('xero_token');
            if (!$tokenData) {
                $tokenJson = Storage::get('xero/tokens/token.json');
                $tokenData = json_decode($tokenJson, true);
            }

            $result = $accountingApi->getContacts($tokenData['tenant_id']);

            // Transform and filter the response to include only suppliers
            $contacts = array_values(array_filter(
                array_map(function($contact) {
                    $balances = $contact->getBalances();
                    $accountsPayable = $balances ? $balances->getAccountsPayable() : null;

                    return [
                        'id' => $contact->getContactId(),
                        'name' => $contact->getName(),
                        'status' => $contact->getContactStatus(),
                        'isSupplier' => $contact->getIsSupplier(),
                        'balances' => [
                            'accountsPayable' => $accountsPayable ? [
                                'outstanding' => $accountsPayable->getOutStanding(),
                                'overDue' => $accountsPayable->getOverDue()
                            ] : [
                                'outstanding' => 0,
                                'overDue' => 0,
                            ],
                        ]
                    ];
                }, $result->getContacts()),
                function($contact) {
                    return $contact['isSupplier'] === true;
                }
            ));

            // Store the vendors in a local JSON file
            Storage::put('xero/data/vendors.json', json_encode([
                'last_updated' => Carbon::now()->toIso8601String(),
                'vendors' => $contacts
            ], JSON_PRETTY_PRINT));

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'file_path' => Storage::path('xero/data/vendors.json')
            ]);

        } catch (\Exception $e) {
            Log::error('Xero API - Failed to fetch vendors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLocalVendors()
    {
        try {
            $content = Storage::get('xero/data/vendors.json');
            if ($content) {
                return json_decode($content, true);
            }
            return response()->json([
                'success' => true,
                'data' => $content,
                'file_path' => Storage::path('xero/data/vendors.json')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to read local vendors file', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'No local vendors data found.'
            ], 404);
        }
    }

    public function getAccounts()
    {
        try {
            $config = $this->getXeroConfig();
            $accountingApi = new AccountingApi(
                new Client(),
                $config
            );

            $tokenData = Session::get('xero_token');
            if (!$tokenData) {
                $tokenJson = Storage::get('xero/tokens/token.json');
                $tokenData = json_decode($tokenJson, true);
            }

            $result = $accountingApi->getAccounts($tokenData['tenant_id']);

            // Transform the response to include only necessary fields
            $accounts = array_values(array_filter(
            array_map(function($account) {
                return [
                    'id' => $account->getAccountId(),
                    'code' => $account->getCode(),
                    'name' => $account->getName(),
                    'type' => $account->getType(),
                    'status' => $account->getStatus(),
                    'description' => $account->getDescription(),
                ];
            }, $result->getAccounts()),
            function($accounts) {
                    return $accounts['type'] === 'EXPENSE';
                }
            ));

            // Store the accounts in a local JSON file
            Storage::put('xero/data/accounts.json', json_encode([
                'last_updated' => Carbon::now()->toIso8601String(),
                'accounts' => $accounts
            ], JSON_PRETTY_PRINT));

            return response()->json([
                'success' => true,
                'data' => $accounts,
                'file_path' => Storage::path('xero/data/accounts.json')
            ]);

        } catch (\Exception $e) {
            Log::error('Xero API - Failed to fetch accounts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLocalAccounts()
    {
        try {
            $content = Storage::get('xero/data/accounts.json');
            if ($content) {
                return json_decode($content, true);
            }
            return response()->json([
                'success' => true,
                'data' => $content,
                'file_path' => Storage::path('xero/data/accounts.json')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to read local accounts file', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'No local accounts data found.'
            ], 404);
        }
    }
}
