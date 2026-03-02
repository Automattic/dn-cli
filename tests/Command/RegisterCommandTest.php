<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Register as RegisterResponse;
use DnCli\Command\RegisterCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RegisterCommandTest extends CommandTestCase
{
    public function test_register_with_options(): void
    {
        $response = new RegisterResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RegisterCommand());
        $tester->setInputs(['yes']); // confirmation prompt
        $tester->execute([
            'domain' => 'newdomain.com',
            '--first-name' => 'Jane',
            '--last-name' => 'Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1.5551234567',
            '--organization' => 'Acme',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
            '--period' => '2',
            '--privacy' => 'on',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Registration request', $tester->getDisplay());
        $this->assertStringContainsString('newdomain.com', $tester->getDisplay());
    }

    public function test_register_cancelled(): void
    {
        $tester = $this->createTester(new RegisterCommand());
        $tester->setInputs(['no']); // decline confirmation
        $tester->execute([
            'domain' => 'newdomain.com',
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
        $this->assertStringContainsString('Cancelled', $tester->getDisplay());
    }

    public function test_register_api_error(): void
    {
        $response = new RegisterResponse($this->errorData('Domain already registered'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        $tester->execute([
            'domain' => 'taken.com',
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
        $this->assertStringContainsString('Domain already registered', $tester->getDisplay());
    }

    public function test_register_exception(): void
    {
        $this->api->method('post')->willThrowException(new \RuntimeException('API down'));

        $tester = $this->createTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        $tester->execute([
            'domain' => 'test.com',
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
        $this->assertStringContainsString('API down', $tester->getDisplay());
    }

    public function test_not_configured(): void
    {
        $tester = $this->createUnconfiguredTester(new RegisterCommand());
        $tester->execute(['domain' => 'test.com'], ['interactive' => false]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_register_available(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->with('rest/v1.3/domains/newdomain.com/is-available')
            ->willReturn(['status' => 'available']);

        $openedUrl = null;
        $command = new RegisterCommand(null, null, $this->wpcomClient, function (string $url) use (&$openedUrl) {
            $openedUrl = $url;
        });

        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=test-token');

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('register'));
        $tester->execute(['domain' => 'newdomain.com']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Checkout opened for newdomain.com', $output);
        $this->assertStringContainsString('domain_reg:newdomain.com', $openedUrl);
        $this->assertStringContainsString('isDomainOnly=1', $openedUrl);
    }

    public function test_user_mode_register_unavailable(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->willReturn(['status' => 'not_available']);

        $command = new RegisterCommand(null, null, $this->wpcomClient, function () {});

        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=test-token');

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('register'));
        $tester->execute(['domain' => 'taken.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not available', $tester->getDisplay());
    }

    public function test_user_mode_register_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Network error'));

        $command = new RegisterCommand(null, null, $this->wpcomClient, function () {});

        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=test-token');

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('register'));
        $tester->execute(['domain' => 'test.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Network error', $tester->getDisplay());
    }
}
