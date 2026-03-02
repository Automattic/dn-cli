<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Register as RegisterResponse;
use DnCli\Command\RegisterCommand;

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

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/shopping-cart/no-site', $this->callback(function (array $body) {
                return $body['blog_id'] === 0
                    && $body['products'][0]['product_slug'] === 'domain_reg'
                    && $body['products'][0]['meta'] === 'newdomain.com'
                    && $body['products'][0]['is_domain_registration'] === true
                    && $body['products'][0]['extra']['isDomainOnlySitelessCheckout'] === true;
            }))
            ->willReturn(['cart_key' => 'no-site']);

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'newdomain.com']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Added newdomain.com to cart', $output);
        $this->assertStringContainsString('wordpress.com/checkout/no-site', $output);
    }

    public function test_user_mode_register_blog_domain(): void
    {
        $this->wpcomClient->method('get')
            ->willReturn(['status' => 'available']);

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/shopping-cart/no-site', $this->callback(function (array $body) {
                return $body['products'][0]['product_slug'] === 'dotblog_domain';
            }))
            ->willReturn(['cart_key' => 'no-site']);

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_user_mode_register_with_site(): void
    {
        $this->wpcomClient->method('get')
            ->willReturn(['status' => 'available']);

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/shopping-cart/ttl.blog', $this->callback(function (array $body) {
                return !isset($body['blog_id'])
                    && !isset($body['products'][0]['extra']['isDomainOnlySitelessCheckout']);
            }))
            ->willReturn(['cart_key' => '123']);

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'newdomain.com', '--site' => 'ttl.blog']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/checkout/ttl.blog', $output);
        $this->assertStringNotContainsString('isDomainOnly', $output);
    }

    public function test_user_mode_register_unavailable(): void
    {
        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->willReturn(['status' => 'not_available']);

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'taken.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not available', $tester->getDisplay());
    }

    public function test_user_mode_register_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Network error'));

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'test.com']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Network error', $tester->getDisplay());
    }
}
