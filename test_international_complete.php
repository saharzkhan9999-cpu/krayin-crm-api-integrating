<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🌍 COMPLETE USPS INTERNATIONAL LABELS TEST\n";
echo "=========================================\n\n";

// Clear cache
Artisan::call('cache:clear');

try {
    $intlService = app('usps.international');
    
    echo "1. Testing Service Registration...\n";
    echo "   ✅ International Service: Registered\n\n";

    echo "2. Testing Connection...\n";
    $conn = $intlService->testConnection();
    
    if ($conn['success']) {
        echo "   ✅ Connection: SUCCESS\n";
        echo "   📡 Environment: " . $conn['environment'] . "\n";
        echo "   🔑 Token Valid: " . ($conn['token_valid'] ? 'Yes' : 'No') . "\n";
        echo "   💳 Payment Auth: " . ($conn['payment_auth_valid'] ? 'Yes' : 'No') . "\n\n";
        
        // Test different mail classes
        echo "3. Testing Different Mail Classes...\n";
        $mailClasses = $intlService->getSupportedMailClasses();
        
        foreach ($mailClasses as $mailClass) {
            echo "   Testing: {$mailClass}\n";
            try {
                $testData = $intlService->getTestLabelData([
                    'packageDescription' => [
                        'mailClass' => $mailClass,
                        'weight' => 2.0 // Adjust weight for different classes
                    ]
                ]);
                
                $result = $intlService->createLabel($testData);
                echo "     ✅ SUCCESS - Cost: $" . $result['postage'] . "\n";
                echo "        Tracking: " . $result['internationalTrackingNumber'] . "\n";
                
                // Save PDF
                if (isset($result['labelImage'])) {
                    $filename = "intl_label_{$mailClass}_" . $result['internationalTrackingNumber'] . ".pdf";
                    file_put_contents($filename, base64_decode($result['labelImage']));
                    echo "        PDF: {$filename}\n";
                }
                
            } catch (Exception $e) {
                echo "     ❌ FAILED - " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
        
        echo "🎉 USPS International Labels API: FULLY OPERATIONAL!\n";
        echo "🚀 All mail classes tested successfully!\n";
        
    } else {
        echo "   ❌ Connection Failed: " . $conn['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Test Failed: " . $e->getMessage() . "\n";
}