<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 QUICK FINAL USPS TEST (TEM Environment)\n";
echo "==========================================\n\n";

// FORCE TESTING ENVIRONMENT
config(['shipping.usps.environment' => 'testing']);

try {
    $labelService = app('usps.label');
    
    echo "1. Testing connection...\n";
    $conn = $labelService->testConnection();
    echo "   ✅ " . $conn['message'] . "\n\n";
    
    echo "2. Creating label...\n";
    $result = $labelService->createLabel([
        'imageInfo' => [
            'imageType' => 'PDF', 
            'labelType' => '4X6LABEL', 
            'receiptOption' => 'NONE',
            'suppressPostage' => false, 
            'suppressMailDate' => true, 
            'returnLabel' => false
        ],
        'fromAddress' => [
            'firstName' => 'Quick', 
            'lastName' => 'Test', 
            'streetAddress' => '123 Quick St',
            'city' => 'NY', 
            'state' => 'NY', 
            'ZIPCode' => '10001', 
            'ignoreBadAddress' => true
        ],
        'toAddress' => [
            'firstName' => 'Quick', 
            'lastName' => 'Customer', 
            'streetAddress' => '456 Quick Ave',
            'city' => 'LA', 
            'state' => 'CA', 
            'ZIPCode' => '90001', 
            'ignoreBadAddress' => true
        ],
        'packageDescription' => [
            'mailClass' => 'USPS_GROUND_ADVANTAGE',
            'rateIndicator' => 'SP',
            'weightUOM' => 'lb',
            'weight' => 1.0,
            'dimensionsUOM' => 'in',
            'length' => 10,
            'width' => 8,
            'height' => 5,
            'processingCategory' => 'MACHINABLE',
            'mailingDate' => date('Y-m-d', strtotime('+1 day')),
            'destinationEntryFacilityType' => 'NONE'
        ]
    ]);
    
    $meta = json_decode($result['labelMetadata'], true);
    echo "   ✅ Label Created!\n";
    echo "   📦 Tracking: " . $meta['trackingNumber'] . "\n";
    echo "   💰 Cost: $" . $meta['postage'] . "\n";
    echo "   🚚 Service: " . $meta['commitment']['name'] . "\n";
    
    file_put_contents('quick_final_test.pdf', base64_decode($result['labelImage.pdf']));
    echo "   📄 PDF saved: quick_final_test.pdf\n\n";
    
    echo "🎉 SUCCESS! Everything is working perfectly in TEM!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}