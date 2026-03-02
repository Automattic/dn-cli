<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Check as CheckResponse;
use DnCli\Api\WPcomClient;
use DnCli\Command\CheckCommand;

class CheckCommandTest extends CommandTestCase
{
    public function test_single_domain_available(): void
    {
        $response = new CheckResponse($this->successData([
            'data' => [
                'domains' => [
                    'example.com' => [
                        'available' => true,
                        'fee_class' => 'standard',
                        'fee_amount' => 12.00,
                        'zone_is_active' => true,
                        'tld_in_maintenance' => false,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('Yes', $output);
        $this->assertStringContainsString('standard', $output);
        $this->assertStringContainsString('$12.00', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_domain_unavailable(): void
    {
        $response = new CheckResponse($this->successData([
            'data' => [
                'domains' => [
                    'taken.com' => [
                        'available' => false,
                        'fee_class' => 'standard',
                        'fee_amount' => 12.00,
                        'zone_is_active' => true,
                        'tld_in_maintenance' => false,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['taken.com']]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('No', $output);
    }

    public function test_multiple_domains(): void
    {
        $response = new CheckResponse($this->successData([
            'data' => [
                'domains' => [
                    'a.com' => [
                        'available' => true,
                        'fee_class' => 'standard',
                        'fee_amount' => 10.00,
                        'zone_is_active' => true,
                        'tld_in_maintenance' => false,
                    ],
                    'b.com' => [
                        'available' => false,
                        'fee_class' => 'premium',
                        'fee_amount' => 500.00,
                        'zone_is_active' => true,
                        'tld_in_maintenance' => false,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['a.com', 'b.com']]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('a.com', $output);
        $this->assertStringContainsString('b.com', $output);
        $this->assertStringContainsString('premium', $output);
    }

    public function test_api_error(): void
    {
        $response = new CheckResponse($this->errorData('Server error'));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Server error', $tester->getDisplay());
    }

    public function test_exception_handling(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Connection failed'));

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Connection failed', $tester->getDisplay());
    }

    public function test_exception_message_redacts_credentials(): void
    {
        // Simulate an exception that contains the API key in its message
        // (e.g. Guzzle including credentials in a request URL or header dump).
        $this->api->method('post')->willThrowException(
            new \RuntimeException('Request to https://api.example.com?key=test-key failed for user test-user')
        );

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $output = $tester->getDisplay();
        $this->assertStringNotContainsString('test-key', $output);
        $this->assertStringNotContainsString('test-user', $output);
        $this->assertStringContainsString('***', $output);
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Not configured', $tester->getDisplay());
    }

    public function test_tld_in_maintenance(): void
    {
        $response = new CheckResponse($this->successData([
            'data' => [
                'domains' => [
                    'example.xyz' => [
                        'available' => true,
                        'fee_class' => 'standard',
                        'fee_amount' => 5.00,
                        'zone_is_active' => true,
                        'tld_in_maintenance' => true,
                    ],
                ],
            ],
        ]));

        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new CheckCommand());
        $tester->execute(['domains' => ['example.xyz']]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_user_mode_check_available(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->with('rest/v1.3/domains/example.com/is-available')
            ->willReturn([
                'status' => 'available',
                'cost' => '$12.00',
                'supports_privacy' => true,
            ]);

        $tester = $this->createUserModeTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('example.com', $output);
        $this->assertStringContainsString('Yes', $output);
        $this->assertStringContainsString('$12.00', $output);
    }

    public function test_user_mode_check_unavailable(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->willReturn([
                'status' => 'not_available',
                'supports_privacy' => false,
            ]);

        $tester = $this->createUserModeTester(new CheckCommand());
        $tester->execute(['domains' => ['taken.com']]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('No', $output);
    }

    public function test_user_mode_check_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Unauthorized'));

        $tester = $this->createUserModeTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unauthorized', $tester->getDisplay());
    }

    public function test_user_mode_redacts_oauth_token(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Bearer test-token was rejected'));

        $tester = $this->createUserModeTester(new CheckCommand());
        $tester->execute(['domains' => ['example.com']]);

        $output = $tester->getDisplay();
        $this->assertStringNotContainsString('test-token', $output);
        $this->assertStringContainsString('***', $output);
    }
}
