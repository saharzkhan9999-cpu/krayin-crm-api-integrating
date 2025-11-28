<?php

namespace Webkul\Shipping\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Shipping\Facades\USPSAddresses;

class TestUSPSAddressesConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usps:test-addresses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test USPS Addresses API connection and configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🧪 Testing USPS Addresses API Connection...');
        $this->line('');

        // Check service status
        $status = USPSAddresses::getConfigInfo();
        
        $this->info('🔧 Service Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Service', $status['service']],
                ['Environment', $status['environment']],
                ['Base URL', $status['base_url']],
                ['OAuth URL', $status['oauth_url']],
                ['Credentials Configured', $status['credentials_configured'] ? '✅ Yes' : '❌ No'],
                ['Timeout', $status['timeout'] . 's'],
                ['Retry Attempts', $status['retry_attempts']],
                ['Cache TTL', $status['cache_ttl'] . 's'],
            ]
        );

        if (!$status['credentials_configured']) {
            $this->error('❌ USPS Addresses credentials are not configured!');
            $this->line('');
            $this->line('Please set these environment variables in your .env file:');
            $this->line('USPS_ADDRESSES_CLIENT_ID=your_consumer_key_here');
            $this->line('USPS_ADDRESSES_CLIENT_SECRET=your_consumer_secret_here');
            $this->line('');
            $this->line('Get your credentials from: https://developer.usps.com/');
            $this->line('Make sure your app has "Addresses" API access');
            return 1;
        }

        $this->info('🔐 Testing OAuth Token Retrieval and API Connection...');
        
        try {
            $result = USPSAddresses::testConnection();
            
            if ($result['success']) {
                $this->info('✅ ' . $result['message']);
                $this->info('🌍 Environment: ' . $result['environment']);
                
                if (isset($result['data']['city'])) {
                    $this->info('📮 Test Address Lookup Results:');
                    $this->table(
                        ['ZIP Code', 'City', 'State'],
                        [[
                            '90210',
                            $result['data']['city'],
                            $result['data']['state']
                        ]]
                    );
                }
                
                $this->line('');
                $this->info('🎉 USPS Addresses API integration is working correctly!');
                
            } else {
                $this->error('❌ ' . $result['message']);
                $this->error('Error: ' . $result['error']);
                $this->line('');
                $this->line('🔧 Troubleshooting tips:');
                $this->line('• Verify USPS_ADDRESSES_CLIENT_ID and USPS_ADDRESSES_CLIENT_SECRET in .env');
                $this->line('• Check if your USPS app has "Addresses" API product enabled');
                $this->line('• Ensure credentials match the environment (testing/production)');
                $this->line('• Verify network connectivity to USPS APIs');
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Connection test failed: ' . $e->getMessage());
            $this->line('');
            $this->line('🔧 Common issues:');
            $this->line('• Incorrect OAuth credentials');
            $this->line('• USPS API service temporarily unavailable');
            $this->line('• Network/firewall restrictions');
            return 1;
        }

        $this->line('');
        $this->info('🚀 Ready to use USPS Addresses API!');
        $this->line('Use: USPSAddresses::validateAddress($addressData)');
        
        return 0;
    }
}