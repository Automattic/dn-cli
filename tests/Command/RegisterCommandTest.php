<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Register as RegisterResponse;
use DnCli\Command\RegisterCommand;

class RegisterCommandTest extends CommandTestCase
{
    private function cartResponseWithCredits(int $totalCostInteger = 220, int $creditsInteger = 500000): array
    {
        return [
            'blog_id' => 0,
            'cart_key' => 'no-site',
            'products' => [
                [
                    'product_slug' => 'dotblog_domain',
                    'meta' => 'example.blog',
                    'cost' => $totalCostInteger / 100,
                    'currency' => 'USD',
                    'is_domain_registration' => true,
                    'item_subtotal_integer' => 220,
                ],
            ],
            'total_cost' => $totalCostInteger / 100,
            'total_cost_integer' => $totalCostInteger,
            'sub_total_integer' => 220,
            'credits' => $creditsInteger / 100,
            'credits_integer' => $creditsInteger,
            'currency' => 'USD',
            'allowed_payment_methods' => [
                'WPCOM_Billing_MoneyPress_Stored',
                'WPCOM_Billing_WPCOM',
            ],
            'tax' => ['location' => []],
            'coupon' => '',
        ];
    }

    private function paymentMethodFixture(): array
    {
        return [
            'stored_details_id' => '12345',
            'card' => '4242',
            'card_type' => 'visa',
            'name' => 'Test User',
            'expiry' => '2028-12-31',
            'mp_ref' => 'stripe:pm_test',
            'payment_partner' => 'stripe',
            'is_expired' => false,
            'meta' => [
                ['stored_details_id' => '12345', 'meta_key' => 'country_code', 'meta_value' => 'US'],
                ['stored_details_id' => '12345', 'meta_key' => 'card_zip', 'meta_value' => '94110'],
            ],
        ];
    }

    private function domainContactFixture(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'organization' => null,
            'address_1' => '123 Main St',
            'address_2' => null,
            'postal_code' => '94110',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country_code' => 'US',
            'email' => 'test@example.com',
            'phone' => '+1.5551234567',
            'fax' => null,
        ];
    }

    /**
     * Set up wpcomClient mock to handle multiple get/post calls by URL.
     */
    private function mockWpcomCalls(array $getCalls, array $postCalls): void
    {
        $this->wpcomClient->method('get')
            ->willReturnCallback(function (string $url) use ($getCalls) {
                foreach ($getCalls as $pattern => $response) {
                    if (str_contains($url, $pattern)) {
                        if ($response instanceof \Exception) {
                            throw $response;
                        }
                        return $response;
                    }
                }
                throw new \RuntimeException("Unexpected GET: {$url}");
            });

        $this->wpcomClient->method('post')
            ->willReturnCallback(function (string $url) use ($postCalls) {
                foreach ($postCalls as $pattern => $response) {
                    if (str_contains($url, $pattern)) {
                        if ($response instanceof \Exception) {
                            throw $response;
                        }
                        return $response;
                    }
                }
                throw new \RuntimeException("Unexpected POST: {$url}");
            });
    }

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
        putenv('DN_AUTO_CHECKOUT=off');

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
        putenv('DN_AUTO_CHECKOUT=off');

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
        putenv('DN_AUTO_CHECKOUT=off');

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

    // --- Auto-checkout tests ---

    public function test_auto_pay_credits_success(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'rest/v1.1/me/payment' => [$this->paymentMethodFixture()],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(220, 500000),
                'transactions' => ['receipt_id' => '999', 'purchases' => []],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']); // confirm purchase
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('registered successfully', $output);
        $this->assertStringContainsString('Account credits', $output);
    }

    public function test_auto_pay_credits_insufficient_falls_back(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 100),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Insufficient credits', $output);
        $this->assertStringContainsString('Added example.blog to cart', $output);
        $this->assertStringContainsString('wordpress.com/checkout', $output);
    }

    public function test_auto_pay_card_success(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'rest/v1.1/me/payment' => [$this->paymentMethodFixture()],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 0),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']); // confirm purchase
        $tester->execute(['domain' => 'example.blog', '--auto-pay-card' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('registered successfully', $output);
        $this->assertStringContainsString('Visa', $output);
        $this->assertStringContainsString('4242', $output);
        // stored_details_id must never appear in output
        $this->assertStringNotContainsString('12345', $output);
    }

    public function test_auto_pay_card_no_methods_falls_back(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
                'rest/v1.1/me/payment' => [],
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 0),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog', '--auto-pay-card' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No stored payment methods', $output);
        $this->assertStringContainsString('Added example.blog to cart', $output);
    }

    public function test_auto_pay_card_expired_card_skipped(): void
    {
        $expired = $this->paymentMethodFixture();
        $expired['is_expired'] = true;

        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
                'rest/v1.1/me/payment' => [$expired],
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 0),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog', '--auto-pay-card' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No stored payment methods', $output);
    }

    public function test_auto_checkout_tries_credits_first_then_card(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'rest/v1.1/me/payment' => [$this->paymentMethodFixture()],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 0),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']); // confirm
        $tester->execute(['domain' => 'example.blog', '--auto-checkout' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        // Credits insufficient (0 < 2200), should use card
        $this->assertStringContainsString('Visa', $output);
        $this->assertStringContainsString('registered successfully', $output);
    }

    public function test_auto_checkout_uses_credits_when_sufficient(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.blog', '--auto-checkout' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Account credits', $output);
        $this->assertStringContainsString('registered successfully', $output);
    }

    public function test_auto_pay_cancelled_by_user(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['no']); // decline confirmation
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Cancelled', $output);
    }

    public function test_auto_pay_with_yes_skips_confirmation(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        // No setInputs — --yes should skip the confirmation
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true, '--yes' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('registered successfully', $output);
        $this->assertStringNotContainsString('Complete purchase?', $output);
    }

    public function test_auto_pay_transaction_failure_falls_back(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
                'transactions' => new \RuntimeException('Payment declined'),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Auto-checkout failed', $output);
        $this->assertStringContainsString('Payment declined', $output);
        $this->assertStringContainsString('Added example.blog to cart', $output);
    }

    public function test_auto_pay_config_preference(): void
    {
        putenv('DN_AUTO_CHECKOUT=credits');

        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.blog']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('registered successfully', $output);

        putenv('DN_AUTO_CHECKOUT');
    }

    public function test_auto_pay_flag_overrides_config(): void
    {
        putenv('DN_AUTO_CHECKOUT=credits');

        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'rest/v1.1/me/payment' => [$this->paymentMethodFixture()],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(2200, 0),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->setInputs(['yes']);
        // Flag says card, config says credits — flag wins
        $tester->execute(['domain' => 'example.blog', '--auto-pay-card' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Visa', $output);

        putenv('DN_AUTO_CHECKOUT');
    }

    public function test_auto_pay_error_sanitizes_token(): void
    {
        $this->mockWpcomCalls(
            [
                'is-available' => ['status' => 'available'],
                'domain-contact' => new \RuntimeException('Error with token test-token in response'),
            ],
            [
                'shopping-cart' => $this->cartResponseWithCredits(0, 500000),
            ],
        );

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog', '--auto-pay-credits' => true, '--yes' => true]);

        $output = $tester->getDisplay();
        // Token should be redacted
        $this->assertStringNotContainsString('test-token', $output);
    }

    public function test_auto_pay_without_flag_uses_normal_flow(): void
    {
        putenv('DN_AUTO_CHECKOUT=off');

        $this->wpcomClient->expects($this->once())
            ->method('get')
            ->with('rest/v1.3/domains/example.blog/is-available')
            ->willReturn(['status' => 'available']);

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->willReturn($this->cartResponseWithCredits());

        $tester = $this->createUserModeTester(new RegisterCommand());
        $tester->execute(['domain' => 'example.blog']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Added example.blog to cart', $output);
        $this->assertStringContainsString('wordpress.com/checkout', $output);
        $this->assertStringNotContainsString('registered successfully', $output);
    }
}
