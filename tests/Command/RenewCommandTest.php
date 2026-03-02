<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Renew as RenewResponse;
use DnCli\Command\RenewCommand;

class RenewCommandTest extends CommandTestCase
{
    public function test_renew_success(): void
    {
        $response = new RenewResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RenewCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--expiration-year' => '2026',
            '--period' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Renewal request', $tester->getDisplay());
    }

    public function test_renew_with_fee(): void
    {
        $response = new RenewResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RenewCommand());
        $tester->execute([
            'domain' => 'premium.com',
            '--expiration-year' => '2026',
            '--period' => '2',
            '--fee' => '99.99',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_renew_interactive_expiration_year(): void
    {
        $response = new RenewResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RenewCommand());
        $tester->setInputs(['2026']);
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_renew_api_error(): void
    {
        $response = new RenewResponse($this->errorData('Domain expired'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RenewCommand());
        $tester->execute([
            'domain' => 'expired.com',
            '--expiration-year' => '2025',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Domain expired', $tester->getDisplay());
    }

    public function test_renew_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Timeout'));

        $tester = $this->createTester(new RenewCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--expiration-year' => '2026',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new RenewCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--expiration-year' => '2026',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new RenewCommand());
        $tester->execute(['domain' => 'example.com', '--expiration-year' => '2026']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
