<?php
// config_check.php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 USPS Configuration Check\n";
echo "==========================\n\n";

$config = config('shipping.usps');

echo "Environment: " . ($config['environment'] ?? 'NOT SET') . "\n";
echo "Auth Token: " . (isset($config['auth_token']) ? substr($config['auth_token'], 0, 20) . '...' : 'NOT SET') . "\n";
echo "Payer CRID: " . ($config['account']['payer_crid'] ?? 'NOT SET') . "\n";
echo "Payer MID: " . ($config['account']['payer_mid'] ?? 'NOT SET') . "\n";
echo "Base URL: " . ($config['base_url'] ?? 'NOT SET') . "\n";

// Test if we can create the service
try {
    $labelService = app('usps.label');
    echo "\n✅ Service instantiation: SUCCESS\n";
} catch (Exception $e) {
    echo "\n❌ Service instantiation: FAILED - " . $e->getMessage() . "\n";
}