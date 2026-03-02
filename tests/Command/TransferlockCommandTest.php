<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Set\Transferlock as TransferlockResponse;
use DnCli\Command\TransferlockCommand;

class TransferlockCommandTest extends CommandTestCase
{
    public function test_transferlock_on(): void
    {
        $response = new TransferlockResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'on']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Transfer lock set to 'on'", $tester->getDisplay());
    }

    public function test_transferlock_off(): void
    {
        $response = new TransferlockResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'off']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Transfer lock set to 'off'", $tester->getDisplay());
    }

    public function test_transferlock_invalid_state(): void
    {
        $tester = $this->createTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'maybe']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid state', $tester->getDisplay());
    }

    public function test_transferlock_case_insensitive(): void
    {
        $response = new TransferlockResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'ON']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_transferlock_api_error(): void
    {
        $response = new TransferlockResponse($this->errorData('Failed'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'on']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'on']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new TransferlockCommand());
        $tester->execute(['domain' => 'example.com', 'state' => 'on']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
