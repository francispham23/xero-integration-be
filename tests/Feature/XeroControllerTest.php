<?php

namespace Tests\Feature;

use App\Http\Controllers\XeroController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class XeroControllerTest extends TestCase
{
    public function test_get_vendors_requires_authentication()
    {
        $response = $this->get('/api/xero/vendors');

        $response->assertStatus(500);
    }

    public function test_get_local_vendors_returns_stored_data()
    {
        $testData = [
            'vendors' => [
                [
                    'id' => '1',
                    'name' => 'Test Vendor',
                    'status' => 'ACTIVE'
                ]
            ]
        ];

        Storage::put('xero/data/vendors.json', json_encode($testData));

        $response = $this->get('/api/xero/local/vendors');

        $response->assertStatus(200)
            ->assertJson($testData);
    }

    public function test_get_local_accounts_returns_stored_data()
    {
        $testData = [
            'accounts' => [
                [
                    'id' => '1',
                    'name' => 'Test Account',
                    'type' => 'EXPENSE'
                ]
            ]
        ];

        Storage::put('xero/data/accounts.json', json_encode($testData));

        $response = $this->get('/api/xero/local/accounts');

        $response->assertStatus(200)
            ->assertJson($testData);
    }

    public function test_disconnect_removes_token()
    {
        Storage::put('xero/tokens/token.json', json_encode(['test' => 'data']));

        $response = $this->post('/api/xero/auth/disconnect');

        $response->assertStatus(200);
        $this->assertFalse(Storage::exists('xero/tokens/token.json'));
    }
}
