<?php

declare(strict_types=1);

namespace DnCli\Tests\Service;

use DnCli\Api\WPcomClient;
use DnCli\Service\CheckoutService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutServiceTest extends TestCase
{
    private WPcomClient&MockObject $client;
    private CheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(WPcomClient::class);
        $this->service = new CheckoutService($this->client);
    }

    public function test_get_payment_methods(): void
    {
        $methods = [['stored_details_id' => '123', 'card' => '4242']];
        $this->client->expects($this->once())
            ->method('get')
            ->with('rest/v1.1/me/payment-methods')
            ->willReturn($methods);

        $this->assertSame($methods, $this->service->getPaymentMethods());
    }

    public function test_get_domain_contact_info(): void
    {
        $contact = ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'address_1' => '123 Main St'];
        $this->client->expects($this->once())
            ->method('get')
            ->with('rest/v1.1/me/domain-contact-information')
            ->willReturn($contact);

        $this->assertSame($contact, $this->service->getDomainContactInfo());
    }

    public function test_submit_transaction(): void
    {
        $cart = ['cart_key' => 'no-site'];
        $payment = ['payment_method' => 'WPCOM_Billing_WPCOM'];
        $domainDetails = ['first_name' => 'Jane'];

        $this->client->expects($this->once())
            ->method('post')
            ->with('rest/v1.1/me/transactions', [
                'cart' => $cart,
                'payment' => $payment,
                'domain_details' => $domainDetails,
            ])
            ->willReturn(['receipt_id' => '999']);

        $result = $this->service->submitTransaction($cart, $payment, $domainDetails);
        $this->assertSame('999', $result['receipt_id']);
    }

    public function test_build_credits_payment(): void
    {
        $contactInfo = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'country_code' => 'US',
            'postal_code' => '94110',
        ];
        $payment = $this->service->buildCreditsPayment($contactInfo);

        $this->assertSame('WPCOM_Billing_WPCOM', $payment['payment_method']);
        $this->assertSame('Jane Doe', $payment['name']);
        $this->assertSame('US', $payment['country_code']);
        $this->assertSame('94110', $payment['postal_code']);
        $this->assertArrayNotHasKey('stored_details_id', $payment);
    }

    public function test_build_stored_card_payment(): void
    {
        $method = [
            'stored_details_id' => '12345',
            'name' => 'Jane Doe',
            'mp_ref' => 'stripe:pm_test',
            'payment_partner' => 'stripe',
            'meta' => [
                ['stored_details_id' => '12345', 'meta_key' => 'country_code', 'meta_value' => 'US'],
                ['stored_details_id' => '12345', 'meta_key' => 'card_zip', 'meta_value' => '94110'],
            ],
        ];

        $payment = $this->service->buildStoredCardPayment($method);

        $this->assertSame('WPCOM_Billing_MoneyPress_Stored', $payment['payment_method']);
        $this->assertSame('12345', $payment['stored_details_id']);
        $this->assertSame('stripe:pm_test', $payment['payment_key']);
        $this->assertSame('stripe', $payment['payment_partner']);
        $this->assertSame('US', $payment['country_code']);
        $this->assertSame('94110', $payment['postal_code']);
    }

    public function test_build_stored_card_payment_missing_meta(): void
    {
        $method = [
            'stored_details_id' => '12345',
            'name' => 'Jane',
            'meta' => [],
        ];

        $payment = $this->service->buildStoredCardPayment($method);

        $this->assertSame('', $payment['country_code']);
        $this->assertSame('', $payment['postal_code']);
    }

    public function test_has_required_contact_fields_complete(): void
    {
        $contact = [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'j@x.com',
            'address_1' => '123 St', 'city' => 'SF', 'postal_code' => '94110',
            'country_code' => 'US', 'phone' => '+1.555',
        ];
        $this->assertTrue($this->service->hasRequiredContactFields($contact));
    }

    public function test_has_required_contact_fields_missing_address(): void
    {
        $contact = [
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'j@x.com',
            'address_1' => '', 'city' => 'SF', 'postal_code' => '94110',
            'country_code' => 'US', 'phone' => '+1.555',
        ];
        $this->assertFalse($this->service->hasRequiredContactFields($contact));
    }

    public function test_has_required_contact_fields_missing_key(): void
    {
        $contact = ['first_name' => 'Jane'];
        $this->assertFalse($this->service->hasRequiredContactFields($contact));
    }

    public function test_format_card_label(): void
    {
        $this->assertSame('Visa ••4242', $this->service->formatCardLabel([
            'card_type' => 'visa',
            'card' => '4242',
        ]));
    }

    public function test_format_card_label_mastercard(): void
    {
        $this->assertSame('Mastercard ••1234', $this->service->formatCardLabel([
            'card_type' => 'mastercard',
            'card' => '1234',
        ]));
    }

    public function test_api_error_propagates(): void
    {
        $this->client->method('get')
            ->willThrowException(new \RuntimeException('API error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API error');
        $this->service->getPaymentMethods();
    }
}
