<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Shipping\Facades\USPSPayment;

class TestUSPS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usps:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test USPS Payment Integration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing USPS Payment Integration...');
        $this->line('');
        
        try {
            // Test 1: Configuration
            $this->info('1. 📋 Checking configuration...');
            $config = USPSPayment::getConfigInfo();
            $this->line('   ✅ Environment: ' . $config['environment']);
            $this->line('   ✅ Base URL: ' . $config['base_url']);
            $this->line('   ✅ Timeout: ' . $config['timeout'] . ' seconds');
            $this->line('   ✅ Cache TTL: ' . $config['cache_ttl'] . ' seconds');
            $this->line('   ✅ Has Credentials: ' . ($config['has_credentials'] ? 'YES' : 'NO'));
            
            // Test 2: Connection
            $this->info('2. 🔌 Testing connection...');
            $connection = USPSPayment::testConnection();
            if ($connection['success']) {
                $this->line('   ✅ ' . $connection['message']);
            } else {
                $this->error('   ❌ ' . $connection['message']);
                return 1;
            }
            
            $this->line('');
            
            // Test 3: Payment Authorization
            $this->info('3. 💳 Testing payment authorization...');
            $paymentAuth = USPSPayment::createPaymentAuthorization();
            
            if (isset($paymentAuth['paymentAuthorizationToken'])) {
                $this->line('   ✅ Payment token generated successfully!');
                $this->line('   🔑 Token: ' . substr($paymentAuth['paymentAuthorizationToken'], 0, 50) . '...');
                $this->line('   ⏱️  Token expires in: 8 hours');
            } else {
                $this->error('   ❌ Failed to generate payment token');
                return 1;
            }
            
            $this->line('');
            $this->info('🎉 All tests passed! USPS Payment integration is working correctly.');
            $this->line('You can now use USPSPayment facade throughout your application.');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('');
            $this->info('🔧 Troubleshooting steps:');
            $this->line('   1. Check your USPS credentials in .env file');
            $this->line('   2. Verify your CRID, MID, and account numbers');
            $this->line('   3. Ensure your USPS account is properly configured');
            $this->line('   4. Check storage/logs/laravel.log for detailed errors');
            
            return 1;
        }
    }
}