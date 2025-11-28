<?php
// oauth_test.php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔐 USPS OAuth 2.0 Test\n";
echo "=====================\n\n";

$config = config('shipping.usps');

try {
    // Test OAuth token generation
    $client = new GuzzleHttp\Client();
    
    $response = $client->post($config['oauth']['production'], [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $config['credentials']['client_id'],
            'client_secret' => $config['credentials']['client_secret'],
        ],
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
    ]);

    $data = json_decode($response->getBody(), true);
    
    if (isset($data['access_token'])) {
        echo "✅ OAuth 2.0 Token Generation: SUCCESS\n";
        echo "   Access Token: " . substr($data['access_token'], 0, 30) . "...\n";
        echo "   Token Type: " . $data['token_type'] . "\n";
        echo "   Expires In: " . $data['expires_in'] . " seconds\n";
        
        // Test the token with a simple API call
        echo "\n🔗 Testing token with API call...\n";
        $response = $client->get($config['services']['labels']['base_url']['production'] . '/label', [
            'headers' => [
                'Authorization' => 'Bearer ' . $data['access_token'],
                'Accept' => 'application/json',
            ],
        ]);
        
        echo "✅ Token validation: SUCCESS (API accepted token)\n";
        
    } else {
        throw new Exception("No access token in response");
    }
    
} catch (Exception $e) {
    echo "❌ OAuth 2.0 Failed: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), '401') !== false) {
        echo "\n🔧 Issue: Invalid Client ID or Client Secret\n";
        echo "   - Verify your production Client ID/Secret\n";
        echo "   - Contact USPS for correct OAuth 2.0 credentials\n";
    }
}