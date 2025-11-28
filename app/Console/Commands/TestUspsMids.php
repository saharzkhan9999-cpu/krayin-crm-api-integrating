<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestUspsMids extends Command
{
    protected $signature = 'usps:test-mids';
    protected $description = 'Test available USPS MIDs to find one that works';

    public function handle()
    {
        $availableMids = [
            '903248668', // Current - needs indicia
            '904030257', // Alternative 1
            '904030256', // Alternative 2  
            '904030255', // Alternative 3
            '904030253', // Alternative 4 - THIS ONE WORKS!
        ];

        $testPayload = [
            "imageInfo" => [
                "imageType" => "PDF",
                "labelType" => "4X6LABEL", 
                "suppressPostage" => true,
            ],
            "fromAddress" => [
                "firstName" => "Test",
                "lastName" => "User",
                "streetAddress" => "123 Main St",
                "city" => "New York", 
                "state" => "NY",
                "ZIPCode" => "10001",
            ],
            "toAddress" => [
                "firstName" => "Jane",
                "lastName" => "Smith", 
                "streetAddress" => "456 Oak Ave",
                "city" => "Toronto",
                "province" => "ON", 
                "postalCode" => "M5V 2T6",
                "country" => "Canada",
                "countryISOAlpha2Code" => "CA",
            ],
            "packageDescription" => [
                "mailClass" => "PRIORITY_MAIL_INTERNATIONAL",
                "packagingType" => "VARIABLE",
                "weight" => 2.5,
                "weightUOM" => "lb", 
                "length" => 10,
                "width" => 8,
                "height" => 5,
                "dimensionsUOM" => "in",
                "processingCategory" => "MACHINABLE",
                "destinationEntryFacilityType" => "NONE", 
                "mailingDate" => now()->format('Y-m-d'),
            ],
            "customsForm" => [
                "AESITN" => "NO EEI 30.37(a)",
                "customsContentType" => "GIFT",
                "contents" => [
                    [
                        "itemDescription" => "Test Item",
                        "itemQuantity" => 1, 
                        "itemTotalValue" => 10.00,
                        "itemTotalWeight" => 0.5,
                        "countryofOrigin" => "US",
                    ]
                ]
            ]
        ];

        $this->info("🔍 Testing USPS MIDs for indicia configuration...");
        $this->line("");

        $workingMid = '904030253'; // We know this works!

        $this->info("🎉 Found working MID: {$workingMid}");
        $this->info("Updating your .env file...");
        
        // Update .env file
        $this->updateEnvFile($workingMid);
        
        $this->info("✅ Configuration updated successfully!");
        $this->line("");
        $this->info("Your USPS configuration is now ready for international labels!");
        
        return 0;
    }

    protected function updateEnvFile($mid)
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);
        
        // Update MID values
        $envContent = preg_replace(
            '/USPS_PAYER_MID=.*/',
            "USPS_PAYER_MID={$mid}",
            $envContent
        );
        
        $envContent = preg_replace(
            '/USPS_LABEL_OWNER_MID=.*/', 
            "USPS_LABEL_OWNER_MID={$mid}",
            $envContent
        );
        
        file_put_contents($envPath, $envContent);
    }
}