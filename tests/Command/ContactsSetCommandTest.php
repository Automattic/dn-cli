<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Set\Contacts as ContactsResponse;
use DnCli\Command\ContactsSetCommand;

class ContactsSetCommandTest extends CommandTestCase
{
    public function test_set_owner_contact(): void
    {
        $response = new ContactsResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new ContactsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'owner',
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
        $this->assertStringContainsString('Contact (owner) updated', $tester->getDisplay());
    }

    public function test_set_admin_contact(): void
    {
        $response = new ContactsResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new ContactsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--type' => 'admin',
            '--first-name' => 'Admin',
            '--last-name' => 'User',
            '--email' => 'admin@example.com',
            '--phone' => '+1.5551234567',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Contact (admin) updated', $tester->getDisplay());
    }

    public function test_transferlock_opt_out(): void
    {
        $response = new ContactsResponse($this->successData());
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new ContactsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--first-name' => 'Jane',
            '--last-name' => 'Doe',
            '--email' => 'jane@example.com',
            '--phone' => '+1.5551234567',
            '--address' => '123 Main St',
            '--city' => 'SF',
            '--state' => 'CA',
            '--postal-code' => '94110',
            '--country' => 'US',
            '--transferlock-opt-out' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_api_error(): void
    {
        $response = new ContactsResponse($this->errorData('Invalid contact'));
        $this->api->method('post')->willReturn($response);

        $tester = $this->createTester(new ContactsSetCommand());
        $tester->execute([
            'domain' => 'example.com',
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
        $tester = $this->createUnconfiguredTester(new ContactsSetCommand());
        $tester->execute(['domain' => 'example.com'], ['interactive' => false]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_user_mode_redirects_to_wpcom(): void
    {
        $tester = $this->createUserModeTester(new ContactsSetCommand());
        $tester->execute(['domain' => 'example.com']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/domains', $tester->getDisplay());
    }
}
