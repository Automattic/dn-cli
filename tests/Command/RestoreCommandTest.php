<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Restore as RestoreResponse;
use DnCli\Command\RestoreCommand;

class RestoreCommandTest extends CommandTestCase
{
    public function test_restore_success(): void
    {
        $response = new RestoreResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RestoreCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Restore request', $tester->getDisplay());
    }

    public function test_restore_api_error(): void
    {
        $response = new RestoreResponse($this->errorData('Cannot restore'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RestoreCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Cannot restore', $tester->getDisplay());
    }

    public function test_restore_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Fail'));

        $tester = $this->createTester(new RestoreCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new RestoreCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new RestoreCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
