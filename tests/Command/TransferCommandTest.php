<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Transfer as TransferResponse;
use DnCli\Command\TransferCommand;

class TransferCommandTest extends CommandTestCase
{
    public function test_transfer_with_options(): void
    {
        $response = new TransferResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--auth-code' => 'EPP-CODE-123',
            '--first-name' => 'Jane',
            '--last-name' => 'Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1.5551234567',
            '--organization' => '',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Transfer request', $tester->getDisplay());
    }

    public function test_transfer_api_error(): void
    {
        $response = new TransferResponse($this->errorData('Transfer denied'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--auth-code' => 'WRONG-CODE',
            '--first-name' => 'Jane',
            '--last-name' => 'Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1.5551234567',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Transfer denied', $tester->getDisplay());
    }

    public function test_transfer_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Network error'));

        $tester = $this->createTester(new TransferCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--auth-code' => 'CODE',
            '--first-name' => 'Jane',
            '--last-name' => 'Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1.5551234567',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com'], ['interactive' => false]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
