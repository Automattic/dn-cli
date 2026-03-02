<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Set\Privacy as PrivacyResponse;
use DnCli\Command\PrivacySetCommand;

class PrivacySetCommandTest extends CommandTestCase
{
    public function test_privacy_on(): void
    {
        $response = new PrivacyResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'on']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Privacy set to 'on'", $tester->getDisplay());
    }

    public function test_privacy_off(): void
    {
        $response = new PrivacyResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'off']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Privacy set to 'off'", $tester->getDisplay());
    }

    public function test_privacy_redact(): void
    {
        $response = new PrivacyResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'redact']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Privacy set to 'redact'", $tester->getDisplay());
    }

    public function test_privacy_invalid_setting(): void
    {
        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'invalid']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid privacy setting', $tester->getDisplay());
    }

    public function test_privacy_case_insensitive(): void
    {
        $response = new PrivacyResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'ON']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_privacy_api_error(): void
    {
        $response = new PrivacyResponse($this->errorData('Not allowed'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'on']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'on']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new PrivacySetCommand());
        $tester->execute(['domain' => 'example.com', 'setting' => 'on']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
