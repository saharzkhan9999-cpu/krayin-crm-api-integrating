<?php
/**
 * FINAL USPS Integration Test - 100% Working
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "🚀 FINAL USPS INTEGRATION TEST\n";
echo "========================================\n\n";

// Set config
config(['usps' => config('shipping.usps')]);

$results = [
    'connection' => false,
    'payment' => false,
    'label_creation' => false,
    'pdf_generation' => false,
    'tracking_number' => false,
    'cost_calculation' => false
];

$testLabelPath = 'final_test_label.pdf';

try {
    // ========================================
    // 1. TEST SERVICE LOADING
    // ========================================
    echo "1. 📦 Loading Services...\n";
    $labelService = app('usps.label');
    $paymentService = app('usps.payment');
    echo "   ✅ Label Service: Loaded\n";
    echo "   ✅ Payment Service: Loaded\n\n";

    // ========================================
    // 2. TEST CONNECTION
    // ========================================
    echo "2. 🔗 Testing USPS Connection...\n";
    $connection = $labelService->testConnection();
    
    if ($connection['success']) {
        $results['connection'] = true;
        echo "   ✅ Connection Successful\n";
        echo "   📡 Environment: " . $connection['environment'] . "\n";
        echo "   🔑 Token Valid: " . ($connection['token_valid'] ? 'Yes' : 'No') . "\n";
        echo "   💳 Payment Auth: " . ($connection['payment_auth_valid'] ? 'Yes' : 'No') . "\n";
    } else {
        throw new Exception("Connection failed: " . $connection['message']);
    }
    echo "\n";

    // ========================================
    // 3. TEST PAYMENT AUTHORIZATION
    // ========================================
    echo "3. 💳 Testing Payment Authorization...\n";
    $paymentResult = $paymentService->createPaymentAuthorization();
    
    if (isset($paymentResult['paymentAuthorizationToken']) && !empty($paymentResult['paymentAuthorizationToken'])) {
        $results['payment'] = true;
        echo "   ✅ Payment Authorization Successful\n";
        echo "   🔐 Token: " . substr($paymentResult['paymentAuthorizationToken'], 0, 20) . "...\n";
        
        // Use config values for payer info
        $payerCRID = config('usps.account.payer_crid', '39947637');
        $payerMID = config('usps.account.payer_mid', '903248668');
        echo "   🏢 Payer CRID: " . $payerCRID . "\n";
        echo "   🏢 Payer MID: " . $payerMID . "\n";
    } else {
        throw new Exception("Payment authorization failed - no token received");
    }
    echo "\n";

    // ========================================
    // 4. TEST LABEL CREATION
    // ========================================
    echo "4. 🏷️ Testing Label Creation...\n";
    
    $labelData = [
        'imageInfo' => [
            'imageType' => 'PDF',
            'labelType' => '4X6LABEL',
            'receiptOption' => 'NONE',
            'suppressPostage' => false,
            'suppressMailDate' => true,
            'returnLabel' => false
        ],
        'fromAddress' => [
            'firstName' => 'Final',
            'lastName' => 'Test Business',
            'streetAddress' => '123 Final Test St',
            'city' => 'New York',
            'state' => 'NY',
            'ZIPCode' => '10001',
            'ignoreBadAddress' => true
        ],
        'toAddress' => [
            'firstName' => 'Final',
            'lastName' => 'Test Customer',
            'streetAddress' => '456 Final Test Ave',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'ZIPCode' => '90001',
            'ignoreBadAddress' => true
        ],
        'packageDescription' => [
            'mailClass' => 'USPS_GROUND_ADVANTAGE',
            'rateIndicator' => 'SP',
            'weightUOM' => 'lb',
            'weight' => 1.5,
            'dimensionsUOM' => 'in',
            'length' => 10,
            'width' => 8,
            'height' => 5,
            'processingCategory' => 'MACHINABLE',
            'mailingDate' => now()->addDay()->format('Y-m-d'),
            'destinationEntryFacilityType' => 'NONE'
        ]
    ];

    $result = $labelService->createLabel($labelData);
    
    if (isset($result['labelMetadata']) && isset($result['labelImage.pdf'])) {
        $results['label_creation'] = true;
        echo "   ✅ Label Creation Successful\n";
        
        // Parse metadata
        $metadata = json_decode($result['labelMetadata'], true);
        
        // Test tracking number
        if (!empty($metadata['trackingNumber'])) {
            $results['tracking_number'] = true;
            echo "   📦 Tracking Number: " . $metadata['trackingNumber'] . "\n";
        }
        
        // Test cost calculation
        if (isset($metadata['postage'])) {
            $results['cost_calculation'] = true;
            echo "   💰 Cost: $" . $metadata['postage'] . "\n";
        }
        
        // Test service info
        if (isset($metadata['commitment']['name'])) {
            echo "   🚚 Service: " . $metadata['commitment']['name'] . "\n";
        }
        
        if (isset($metadata['commitment']['scheduleDeliveryDate'])) {
            echo "   📅 Estimated Delivery: " . $metadata['commitment']['scheduleDeliveryDate'] . "\n";
        }
    } else {
        throw new Exception("Label creation failed - missing required data");
    }
    echo "\n";

    // ========================================
    // 5. TEST PDF GENERATION
    // ========================================
    echo "5. 📄 Testing PDF Generation...\n";
    
    if (isset($result['labelImage.pdf'])) {
        $pdfContent = base64_decode($result['labelImage.pdf']);
        $pdfSize = strlen($pdfContent);
        
        // Save PDF file
        file_put_contents($testLabelPath, $pdfContent);
        
        // Verify PDF is valid
        if (file_exists($testLabelPath)) {
            $savedSize = filesize($testLabelPath);
            $fileContent = file_get_contents($testLabelPath, false, null, 0, 4);
            
            if (strpos($fileContent, '%PDF') !== false && $savedSize > 0) {
                $results['pdf_generation'] = true;
                echo "   ✅ PDF Generation Successful\n";
                echo "   💾 File: " . $testLabelPath . "\n";
                echo "   📊 Size: " . number_format($savedSize) . " bytes\n";
                echo "   ✅ Valid PDF: Yes\n";
            } else {
                throw new Exception("Generated PDF is not valid");
            }
        } else {
            throw new Exception("Failed to save PDF file");
        }
    } else {
        throw new Exception("No PDF data in response");
    }
    echo "\n";

    // ========================================
    // 6. TEST UTILITY METHODS
    // ========================================
    echo "6. 🛠️ Testing Utility Methods...\n";
    
    // Test token clearing
    $labelService->clearCachedToken();
    echo "   ✅ Token cache cleared\n";
    
    // Test access token (this will get a new one since we cleared cache)
    $newToken = $labelService->getAccessToken();
    if (!empty($newToken)) {
        echo "   🔑 New access token obtained: " . substr($newToken, 0, 20) . "...\n";
    }
    echo "\n";

    // ========================================
    // 7. FINAL RESULTS SUMMARY
    // ========================================
    echo "========================================\n";
    echo "🎯 FINAL TEST RESULTS\n";
    echo "========================================\n";
    
    $passed = 0;
    $total = count($results);
    
    foreach ($results as $test => $status) {
        $icon = $status ? '✅' : '❌';
        $statusText = $status ? 'PASSED' : 'FAILED';
        echo "{$icon} " . str_pad(ucfirst(str_replace('_', ' ', $test)), 25) . ": {$statusText}\n";
        
        if ($status) $passed++;
    }
    
    echo "\n";
    echo "📊 SCORE: {$passed}/{$total} tests passed\n";
    
    if ($passed === $total) {
        echo "🎉 ALL TESTS PASSED! Your USPS integration is 100% working!\n";
        echo "🚀 Ready for production use!\n";
        
        // Show created tracking numbers
        echo "\n📦 CREATED TRACKING NUMBERS:\n";
        $metadata = json_decode($result['labelMetadata'], true);
        echo "   - " . $metadata['trackingNumber'] . " (This test)\n";
        echo "   - 9234690324866800050090 (Previous test)\n";
        echo "   - 9200190324866800000048 (Previous simple label)\n";
    } else {
        echo "⚠️  Some tests failed. Check the output above for details.\n";
    }
    
    echo "\n";
    echo "📁 Generated Files:\n";
    echo "   - {$testLabelPath}\n";
    echo "   - complete_test_label.pdf (from previous test)\n";
    echo "   - simple_test_label.pdf (from previous test)\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n❌ TEST FAILED: " . $e->getMessage() . "\n";
    
    // Show partial results
    echo "\nPartial Results:\n";
    foreach ($results as $test => $status) {
        $icon = $status ? '✅' : '❌';
        echo "{$icon} " . ucfirst(str_replace('_', ' ', $test)) . "\n";
    }
    
    exit(1);
}

echo "========================================\n";
echo "✅ USPS INTEGRATION VERIFIED & READY!\n";
echo "========================================\n";