<?php

namespace App\Http\Controllers;

use XeroAPI\XeroPHP\Configuration;
use XeroAPI\XeroPHP\Api\IdentityApi;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

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
}
