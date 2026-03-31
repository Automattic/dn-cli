<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Register;
use Automattic\Domain_Services_Client\Entity\Contact_Information;
use Automattic\Domain_Services_Client\Entity\Domain_Contact;
use Automattic\Domain_Services_Client\Entity\Domain_Contacts;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Entity\Whois_Privacy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RegisterCommand extends BaseCommand
{
    use UserModeCheckoutTrait;
    protected function configure(): void
    {
        $this
            ->setName('register')
            ->setDescription('Register a new domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to register')
            ->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'Registration period in years', '1')
            ->addOption('privacy', null, InputOption::VALUE_REQUIRED, 'Privacy setting: on, off, redact', 'on')
            ->addOption('price', null, InputOption::VALUE_REQUIRED, 'Price for premium domains (in cents)')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Contact first name')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Contact last name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Contact email')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Contact phone (e.g. +1.5551234567)')
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'Organization')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Street address')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'State/province')
            ->addOption('postal-code', null, InputOption::VALUE_REQUIRED, 'Postal code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code (e.g. US)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site slug for checkout (user mode, e.g. mysite.wordpress.com)')
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
        $period = (int) $input->getOption('period');

        // Collect contact info — prompt for anything not provided
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
            null, // address_2
            $postalCode,
            $city,
            $state,
            $country,
            $email,
            $phone,
            null  // fax
        );

        $contact = new Domain_Contact($contactInfo);
        $contacts = new Domain_Contacts($contact);

        // Privacy setting
        $privacyOption = $input->getOption('privacy');
        $privacySetting = match ($privacyOption) {
            'off' => Whois_Privacy::DISCLOSE_CONTACT_INFO,
            'redact' => Whois_Privacy::REDACT_CONTACT_INFO,
            default => Whois_Privacy::ENABLE_PRIVACY_SERVICE,
        };

        // Price for premium domains
        $price = $input->getOption('price') !== null ? (int) $input->getOption('price') : null;

        if (!$io->confirm("Proceed with registering {$domainName}?", true)) {
            $io->text('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $api = $this->createApi();
            $command = new Register(
                new Domain_Name($domainName),
                $contacts,
                $period,
                null,  // nameservers
                null,  // dns records
                $privacySetting,
                $price
            );
            $response = $this->withSpinner("Registering {$domainName}...", fn() => $api->post($command));

            if ($response->is_success()) {
                $io->success("Registration request for {$domainName} has been submitted. Check events for completion status.");
            } else {
                $io->error('Registration failed: ' . $response->get_status_description());
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

        try {
            $client = $this->createWPcomClient();

            // Check availability
            $check = $this->withSpinner("Checking {$domainName}...", fn() => $client->get("rest/v1.3/domains/{$domainName}/is-available"));

            if (($check['status'] ?? '') !== 'available') {
                $io->error("{$domainName} is not available for registration.");
                return self::FAILURE;
            }

            // Add to cart
            $productSlug = $this->getDomainProductSlug($domainName);
            $extra = [
                'privacy' => true,
                'privacy_available' => true,
            ];
            if ($isDomainOnly) {
                $extra['isDomainOnlySitelessCheckout'] = true;
            }

            $cartBody = [
                'temporary' => false,
                'products' => [
                    [
                        'product_slug' => $productSlug,
                        'meta' => $domainName,
                        'is_domain_registration' => true,
                        'extra' => $extra,
                    ],
                ],
            ];
            if ($isDomainOnly) {
                $cartBody['blog_id'] = 0;
                $cartBody['cart_key'] = 'no-site';
            }

            $cartResponse = $this->withSpinner("Adding {$domainName} to cart...", fn() => $client->post("rest/v1.1/me/shopping-cart/{$site}", $cartBody));

            // Attempt auto-checkout if enabled
            $autoMode = $this->resolveAutoCheckoutMode($input);
            if ($autoMode !== null) {
                $result = $this->attemptAutoCheckout($client, $cartResponse, $autoMode, $domainName, $input, $io);
                if ($result !== null) {
                    return $result;
                }
                // Fall through to checkout URL on failure
            }

            $checkoutUrl = $this->buildCheckoutUrl($site, $isDomainOnly);
            $io->success("Added {$domainName} to cart.");
            $io->text("Complete your purchase: <info>{$checkoutUrl}</info>");
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function getDomainProductSlug(string $domain): string
    {
        $tld = strtolower(substr($domain, (int) strrpos($domain, '.') + 1));

        if ($tld === 'com') {
            return 'domain_reg';
        }

        return 'dot' . $tld . '_domain';
    }
}
