<?php
// check_api_status.php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "📊 USPS API Status Check\n";
echo "=======================\n\n";

$config = config('shipping.usps');
$token = $config['credentials']['auth_token'];

// Decode the JWT token to see scopes
$parts = explode('.', $token);
$payload = json_decode(base64_decode($parts[1]), true);

echo "🔐 Token Scopes Analysis:\n";
$scopes = explode(' ', $payload['scope'] ?? '');
foreach ($scopes as $scope) {
    echo "   - {$scope}\n";
}

echo "\n🏢 Account Details:\n";
echo "   Company: " . ($payload['company_name'] ?? 'N/A') . "\n";
echo "   CRID: " . ($payload['crid'] ?? 'N/A') . "\n";
echo "   MIDs: " . ($payload['mail_owners'][0]['mids'] ?? 'N/A') . "\n";

echo "\n🎯 APIs with 'labels' scope:\n";
if (in_array('labels', $scopes)) {
    echo "   ✅ Labels API: Scope granted\n";
} else {
    echo "   ❌ Labels API: Scope missing\n";
}

echo "\n🚨 Next Steps:\n";
echo "1. The token has the 'labels' scope but API returns 401\n";
echo "2. This means USPS needs to ACTIVATE the Labels API for your account\n";
echo "3. Contact USPS support with the email template provided\n";
echo "4. This typically takes 1-2 business days to resolve\n";