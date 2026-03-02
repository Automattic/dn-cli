<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Info as InfoResponse;
use DnCli\Command\InfoCommand;

class InfoCommandTest extends CommandTestCase
{
    private function makeInfoResponse(array $data = []): InfoResponse
    {
        $defaults = [
            'auth_code' => 'ABC123',
            'contacts' => [
                'owner' => [
                    'contact_information' => [
                        'first_name' => 'Jane',
                        'last_name' => 'Doe',
                        'organization' => '',
                        'address_1' => '123 Main St',
                        'address_2' => '',
                        'postal_code' => '94110',
                        'city' => 'San Francisco',
                        'state' => 'CA',
                        'country_code' => 'US',
                        'email' => 'jane@example.com',
                        'phone' => '+1.5551234567',
                        'fax' => '',
                    ],
                ],
            ],
            'created_date' => '2020-01-15 10:00:00',
            'expiration_date' => '2026-01-15 10:00:00',
            'updated_date' => '2025-06-01 12:00:00',
            'paid_until' => '2026-01-15 10:00:00',
            'dnssec' => null,
            'domain_status' => ['clientTransferProhibited'],
            'name_servers' => ['ns1.wordpress.com', 'ns2.wordpress.com'],
            'privacy_setting' => 'enable_privacy_service',
            'renewal_mode' => 'autorenew',
            'rgp_status' => null,
            'transferlock' => true,
            'transfer_mode' => 'autodeny',
        ];

        return new InfoResponse($this->successData([
            'data' => array_merge($defaults, $data),
        ]));
    }

    public function test_full_domain_info(): void
    {
        $response = $this->makeInfoResponse();
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('ABC123', $output);
        $this->assertStringContainsString('autorenew', $output);
        $this->assertStringContainsString('autodeny', $output);
        $this->assertStringContainsString('On', $output); // transferlock
    }

    public function test_displays_nameservers(): void
    {
        $response = $this->makeInfoResponse();
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('ns1.wordpress.com', $output);
        $this->assertStringContainsString('ns2.wordpress.com', $output);
    }

    public function test_displays_contacts(): void
    {
        $response = $this->makeInfoResponse();
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Jane Doe', $output);
        $this->assertStringContainsString('jane@example.com', $output);
    }

    public function test_displays_epp_status(): void
    {
        $response = $this->makeInfoResponse();
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('clientTransferProhibited', $output);
    }

    public function test_null_dates_show_dash(): void
    {
        $response = $this->makeInfoResponse([
            'created_date' => null,
            'expiration_date' => null,
            'updated_date' => null,
            'paid_until' => null,
        ]);
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_transferlock_off(): void
    {
        $response = $this->makeInfoResponse(['transferlock' => false]);
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Off', $output);
    }

    public function test_api_error(): void
    {
        $response = new InfoResponse($this->errorData('Domain not found'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'nonexistent.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Domain not found', $tester->getDisplay());
    }

    public function test_exception_handling(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Network error'));

        $tester = $this->createTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Network error', $tester->getDisplay());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new InfoCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
