<?php
// verify_token.php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔐 Verifying USPS Token\n";
echo "======================\n\n";

$config = config('shipping.usps');
$token = $config['credentials']['auth_token'] ?? 'NOT SET';

if ($token === 'NOT SET') {
    echo "❌ Token not set in config\n";
    exit;
}

echo "✅ Token is set in config\n";
echo "📏 Token length: " . strlen($token) . " characters\n";
echo "🔑 First 50 chars: " . substr($token, 0, 50) . "...\n";

// Test if it's a valid JWT format
$parts = explode('.', $token);
if (count($parts) === 3) {
    echo "✅ Valid JWT format\n";
    
    // Decode payload (middle part)
    $payload = json_decode(base64_decode($parts[1]), true);
    echo "🏢 Company: " . ($payload['company_name'] ?? 'Not found') . "\n";
    echo "📅 Expires: " . date('Y-m-d H:i:s', $payload['exp'] ?? 0) . "\n";
    echo "🔐 Scope: " . ($payload['scope'] ?? 'Not found') . "\n";
} else {
    echo "❌ Invalid JWT format\n";
}