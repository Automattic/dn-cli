<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use Automattic\Domain_Services_Client\Response\Domain\Transfer as TransferResponse;
use DnCli\Command\TransferCommand;

class TransferCommandTest extends CommandTestCase
{
    private function cartResponseForTransfer(int $totalCostInteger = 899, int $creditsInteger = 500000): array
    {
        return [
            'blog_id' => 0,
            'cart_key' => 'no-site',
            'products' => [
                [
                    'product_slug' => 'domain_transfer',
                    'meta' => 'example.com',
                    'cost' => $totalCostInteger / 100,
                    'currency' => 'USD',
                    'is_domain_registration' => false,
                    'item_subtotal_integer' => $totalCostInteger,
                ],
            ],
            'total_cost' => $totalCostInteger / 100,
            'total_cost_integer' => $totalCostInteger,
            'sub_total_integer' => $totalCostInteger,
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

    // --- Partner mode tests ---

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

    // --- User mode tests ---

    public function test_user_mode_transfer_success(): void
    {
        putenv('DN_AUTO_CHECKOUT=off');

        $this->wpcomClient->expects($this->atLeast(2))
            ->method('get')
            ->willReturnCallback(function (string $url) {
                if (str_contains($url, 'inbound-transfer-check-auth-code')) {
                    return ['success' => true];
                }
                if (str_contains($url, 'is-available')) {
                    return ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true];
                }
                throw new \RuntimeException("Unexpected GET: {$url}");
            });

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/shopping-cart/no-site', $this->callback(function (array $body) {
                return $body['blog_id'] === 0
                    && $body['products'][0]['product_slug'] === 'domain_transfer'
                    && $body['products'][0]['meta'] === 'example.com'
                    && $body['products'][0]['is_domain_registration'] === false
                    && $body['products'][0]['extra']['auth_code'] === 'EPP-CODE-123'
                    && $body['products'][0]['extra']['privacy_available'] === true
                    && $body['products'][0]['extra']['isDomainOnlySitelessCheckout'] === true;
            }))
            ->willReturn(['cart_key' => 'no-site']);

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'EPP-CODE-123']);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Added example.com to transfer cart', $output);
        $this->assertStringContainsString('wordpress.com/checkout/no-site', $output);
    }

    public function test_user_mode_transfer_invalid_auth_code(): void
    {
        $this->wpcomClient->method('get')
            ->willReturnCallback(function (string $url) {
                if (str_contains($url, 'inbound-transfer-check-auth-code')) {
                    return ['success' => false];
                }
                throw new \RuntimeException("Unexpected GET: {$url}");
            });

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'WRONG-CODE']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid authorization code', $tester->getDisplay());
    }

    public function test_user_mode_transfer_not_transferrable(): void
    {
        $this->wpcomClient->method('get')
            ->willReturnCallback(function (string $url) {
                if (str_contains($url, 'inbound-transfer-check-auth-code')) {
                    return ['success' => true];
                }
                if (str_contains($url, 'is-available')) {
                    return ['status' => 'not_available'];
                }
                throw new \RuntimeException("Unexpected GET: {$url}");
            });

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'EPP-CODE-123']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not available for transfer', $tester->getDisplay());
    }

    public function test_user_mode_transfer_with_site(): void
    {
        putenv('DN_AUTO_CHECKOUT=off');

        $this->wpcomClient->method('get')
            ->willReturnCallback(function (string $url) {
                if (str_contains($url, 'inbound-transfer-check-auth-code')) {
                    return ['success' => true];
                }
                if (str_contains($url, 'is-available')) {
                    return ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true];
                }
                throw new \RuntimeException("Unexpected GET: {$url}");
            });

        $this->wpcomClient->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/shopping-cart/mysite.wordpress.com', $this->callback(function (array $body) {
                return !isset($body['blog_id'])
                    && !isset($body['products'][0]['extra']['isDomainOnlySitelessCheckout']);
            }))
            ->willReturn(['cart_key' => '123']);

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--auth-code' => 'EPP-CODE-123',
            '--site' => 'mysite.wordpress.com',
        ]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('wordpress.com/checkout/mysite.wordpress.com', $output);
    }

    public function test_user_mode_transfer_api_error(): void
    {
        $this->wpcomClient->method('get')
            ->willThrowException(new \RuntimeException('Network error'));

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'CODE']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Network error', $tester->getDisplay());
    }

    public function test_user_mode_auto_pay_credits(): void
    {
        $this->mockWpcomCalls(
            [
                'inbound-transfer-check-auth-code' => ['success' => true],
                'is-available' => ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseForTransfer(899, 500000),
                'transactions' => ['receipt_id' => '999', 'purchases' => []],
            ],
        );

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'EPP-CODE', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('transfer submitted successfully', $output);
        $this->assertStringContainsString('Account credits', $output);
    }

    public function test_user_mode_auto_pay_card(): void
    {
        $this->mockWpcomCalls(
            [
                'inbound-transfer-check-auth-code' => ['success' => true],
                'is-available' => ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true],
                'rest/v1.1/me/payment' => [$this->paymentMethodFixture()],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseForTransfer(899, 0),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'EPP-CODE', '--auto-pay-card' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('transfer submitted successfully', $output);
        $this->assertStringContainsString('Visa', $output);
        $this->assertStringContainsString('4242', $output);
        // stored_details_id must never appear in output
        $this->assertStringNotContainsString('12345', $output);
    }

    public function test_user_mode_auto_checkout_fallback(): void
    {
        $this->mockWpcomCalls(
            [
                'inbound-transfer-check-auth-code' => ['success' => true],
                'is-available' => ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseForTransfer(899, 500000),
                'transactions' => new \RuntimeException('Payment declined'),
            ],
        );

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->setInputs(['yes']);
        $tester->execute(['domain' => 'example.com', '--auth-code' => 'EPP-CODE', '--auto-pay-credits' => true]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Auto-checkout failed', $output);
        $this->assertStringContainsString('Payment declined', $output);
        $this->assertStringContainsString('Added example.com to transfer cart', $output);
    }

    public function test_user_mode_auto_checkout_with_yes(): void
    {
        $this->mockWpcomCalls(
            [
                'inbound-transfer-check-auth-code' => ['success' => true],
                'is-available' => ['status' => 'mapped_domain', 'transferrability' => 'transferrable', 'supports_privacy' => true],
                'domain-contact-information' => $this->domainContactFixture(),
            ],
            [
                'shopping-cart' => $this->cartResponseForTransfer(899, 500000),
                'transactions' => ['receipt_id' => '999'],
            ],
        );

        $tester = $this->createUserModeTester(new TransferCommand());
        $tester->execute([
            'domain' => 'example.com',
            '--auth-code' => 'EPP-CODE',
            '--auto-pay-credits' => true,
            '--yes' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('transfer submitted successfully', $output);
        $this->assertStringNotContainsString('Complete purchase?', $output);
    }
}
