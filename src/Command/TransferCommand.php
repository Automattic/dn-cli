<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Transfer;
use Automattic\Domain_Services_Client\Entity\Contact_Information;
use Automattic\Domain_Services_Client\Entity\Domain_Contact;
use Automattic\Domain_Services_Client\Entity\Domain_Contacts;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TransferCommand extends BaseCommand
{
    use UserModeCheckoutTrait;

    protected function configure(): void
    {
        $this
            ->setName('transfer')
            ->setDescription('Transfer a domain in')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to transfer')
            ->addOption('auth-code', null, InputOption::VALUE_REQUIRED, 'EPP authorization code')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Contact first name')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Contact last name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Contact email')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Contact phone')
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'Organization')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Street address')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'State/province')
            ->addOption('postal-code', null, InputOption::VALUE_REQUIRED, 'Postal code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code (e.g. US)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site slug for checkout (user mode)')
            ->addOption('auto-checkout', null, InputOption::VALUE_NONE, 'Auto-checkout: try credits first, then stored card (user mode)')
            ->addOption('auto-pay-credits', null, InputOption::VALUE_NONE, 'Auto-checkout with account credits (user mode)')
            ->addOption('auto-pay-card', null, InputOption::VALUE_NONE, 'Auto-checkout with stored credit card (user mode)')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->handleUserMode($input, $io);
        }

        return $this->handlePartnerMode($input, $output, $io);
    }

    private function handlePartnerMode(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $domainName = $input->getArgument('domain');

        $authCode = $input->getOption('auth-code') ?? $io->askHidden('EPP Authorization Code');
        if ($authCode === null) {
            $io->error('Authorization code is required for transfers.');
            return self::FAILURE;
        }

        // Collect contact info
        $firstName = $input->getOption('first-name') ?? $io->ask('First name');
        $lastName = $input->getOption('last-name') ?? $io->ask('Last name');
        $email = $input->getOption('email') ?? $io->ask('Email');
        $phone = $input->getOption('phone') ?? $io->ask('Phone (e.g. +1.5551234567)');
        $org = $input->getOption('organization') ?? $io->ask('Organization (leave blank if none)', '');
        $address = $input->getOption('address') ?? $io->ask('Street address');
        $city = $input->getOption('city') ?? $io->ask('City');
        $state = $input->getOption('state') ?? $io->ask('State/province');
        $postalCode = $input->getOption('postal-code') ?? $io->ask('Postal code');
        $country = $input->getOption('country') ?? $io->ask('Country code (e.g. US)');

        $contactInfo = new Contact_Information(
            $firstName,
            $lastName,
            $org ?: null,
            $address,
            null,
            $postalCode,
            $city,
            $state,
            $country,
            $email,
            $phone,
            null
        );

        $contact = new Domain_Contact($contactInfo);
        $contacts = new Domain_Contacts($contact);

        try {
            $api = $this->createApi();
            $command = new Transfer(
                new Domain_Name($domainName),
                $authCode,
                $contacts
            );
            $response = $this->withSpinner("Submitting transfer for {$domainName}...", fn() => $api->post($command));

            if ($response->is_success()) {
                $io->success("Transfer request for {$domainName} has been submitted. Check events for completion status.");
            } else {
                $io->error('Transfer failed: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function handleUserMode(InputInterface $input, SymfonyStyle $io): int
    {
        $domainName = $input->getArgument('domain');
        $site = $input->getOption('site') ?? 'no-site';
        $isDomainOnly = $site === 'no-site';

        $authCode = $input->getOption('auth-code') ?? $io->askHidden('EPP Authorization Code');
        if ($authCode === null) {
            $io->error('Authorization code is required for transfers.');
            return self::FAILURE;
        }

        try {
            $client = $this->createWPcomClient();

            // Validate auth code
            $authCheck = $this->withSpinner('Validating auth code...', fn() => $client->get(
                "rest/v1.1/domains/{$domainName}/inbound-transfer-check-auth-code",
                ['auth_code' => $authCode]
            ));

            if (!($authCheck['success'] ?? false)) {
                $io->error('Invalid authorization code for ' . $domainName);
                return self::FAILURE;
            }

            // Check transferability
            $check = $this->withSpinner("Checking {$domainName}...", fn() => $client->get("rest/v1.3/domains/{$domainName}/is-available"));

            $transferrability = $check['transferrability'] ?? $check['status'] ?? '';
            if ($transferrability !== 'transferrable') {
                $io->error("{$domainName} is not available for transfer.");
                return self::FAILURE;
            }

            $supportsPrivacy = $check['supports_privacy'] ?? false;

            // Add to cart
            $extra = [
                'auth_code' => $authCode,
                'privacy_available' => $supportsPrivacy,
            ];
            if ($supportsPrivacy) {
                $extra['privacy'] = true;
            }
            if ($isDomainOnly) {
                $extra['isDomainOnlySitelessCheckout'] = true;
            }

            $cartBody = [
                'temporary' => false,
                'products' => [
                    [
                        'product_slug' => 'domain_transfer',
                        'meta' => $domainName,
                        'is_domain_registration' => false,
                        'extra' => $extra,
                    ],
                ],
            ];
            if ($isDomainOnly) {
                $cartBody['blog_id'] = 0;
                $cartBody['cart_key'] = 'no-site';
            }

            $cartResponse = $this->withSpinner("Adding {$domainName} to transfer cart...", fn() => $client->post("rest/v1.1/me/shopping-cart/{$site}", $cartBody));

            // Attempt auto-checkout if enabled
            $autoMode = $this->resolveAutoCheckoutMode($input);
            if ($autoMode !== null) {
                $result = $this->attemptAutoCheckout($client, $cartResponse, $autoMode, $domainName, $input, $io, 'transfer submitted');
                if ($result !== null) {
                    return $result;
                }
                // Fall through to checkout URL on failure
            }

            $checkoutUrl = $this->buildCheckoutUrl($site, $isDomainOnly);
            $io->success("Added {$domainName} to transfer cart.");
            $io->text("Complete your purchase: <info>{$checkoutUrl}</info>");
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
