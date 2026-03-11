<?php

declare(strict_types=1);

namespace DnCli\Service;

use DnCli\Api\WPcomClient;

class CheckoutService
{
    public function __construct(private WPcomClient $client)
    {
    }

    /**
     * @return array<int, array{stored_details_id: string, card: string, card_type: string, name: string, expiry: string, mp_ref: string, payment_partner: string, is_expired: bool, meta: array, tax_location: array}>
     */
    public function getPaymentMethods(): array
    {
        return $this->client->get('rest/v1.1/me/payment-methods');
    }

    /**
     * Fetch cached domain contact information (from previous registrations).
     * @return array{first_name: string, last_name: string, email: string, phone: string, address_1: string, city: string, state: ?string, postal_code: string, country_code: string, organization: ?string}
     */
    public function getDomainContactInfo(): array
    {
        return $this->client->get('rest/v1.1/me/domain-contact-information');
    }

    /**
     * @return array Transaction response with receipt_id, purchases, etc.
     */
    public function submitTransaction(array $cart, array $payment, array $domainDetails): array
    {
        return $this->client->post('rest/v1.1/me/transactions', [
            'cart' => $cart,
            'payment' => $payment,
            'domain_details' => $domainDetails,
        ]);
    }

    public function buildCreditsPayment(array $contactInfo): array
    {
        $name = trim(($contactInfo['first_name'] ?? '') . ' ' . ($contactInfo['last_name'] ?? ''));

        return [
            'payment_method' => 'WPCOM_Billing_WPCOM',
            'name' => $name,
            'zip' => $contactInfo['postal_code'] ?? '',
            'postal_code' => $contactInfo['postal_code'] ?? '',
            'country' => $contactInfo['country_code'] ?? '',
            'country_code' => $contactInfo['country_code'] ?? '',
        ];
    }

    public function buildStoredCardPayment(array $paymentMethod): array
    {
        $countryCode = '';
        $postalCode = '';
        foreach ($paymentMethod['meta'] ?? [] as $meta) {
            if ($meta['meta_key'] === 'country_code') {
                $countryCode = $meta['meta_value'];
            }
            if ($meta['meta_key'] === 'card_zip') {
                $postalCode = $meta['meta_value'];
            }
        }

        return [
            'payment_method' => 'WPCOM_Billing_MoneyPress_Stored',
            'stored_details_id' => $paymentMethod['stored_details_id'],
            'payment_key' => $paymentMethod['mp_ref'] ?? '',
            'payment_partner' => $paymentMethod['payment_partner'] ?? '',
            'name' => $paymentMethod['name'] ?? '',
            'zip' => $postalCode,
            'postal_code' => $postalCode,
            'country' => $countryCode,
            'country_code' => $countryCode,
        ];
    }

    /**
     * Validate that contact info has the required fields for domain registration.
     */
    public function hasRequiredContactFields(array $contactInfo): bool
    {
        $required = ['first_name', 'last_name', 'email', 'address_1', 'city', 'postal_code', 'country_code', 'phone'];

        foreach ($required as $field) {
            if (empty($contactInfo[$field])) {
                return false;
            }
        }

        return true;
    }

    public function formatCardLabel(array $paymentMethod): string
    {
        $type = ucfirst($paymentMethod['card_type'] ?? 'Card');
        $last4 = $paymentMethod['card'] ?? '****';

        return "{$type} ••{$last4}";
    }
}
