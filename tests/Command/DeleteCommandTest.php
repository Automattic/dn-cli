<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Delete as DeleteResponse;
use DnCli\Command\DeleteCommand;

class DeleteCommandTest extends CommandTestCase
{
    public function test_delete_confirmed(): void
    {
        $response = new DeleteResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DeleteCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Delete request', $tester->getDisplay());
    }

    public function test_delete_cancelled(): void
    {
        $tester = $this->createTester(new DeleteCommand());
        $tester->setInputs(['no']);
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Cancelled', $tester->getDisplay());
    }

    public function test_delete_api_error(): void
    {
        $response = new DeleteResponse($this->errorData('Cannot delete'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new DeleteCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Cannot delete', $tester->getDisplay());
    }

    public function test_delete_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('Error'));

        $tester = $this->createTester(new DeleteCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new DeleteCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new DeleteCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
