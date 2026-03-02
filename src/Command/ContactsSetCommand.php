<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Set\Contacts;
use Automattic\Domain_Services_Client\Entity\Contact_Information;
use Automattic\Domain_Services_Client\Entity\Domain_Contact;
use Automattic\Domain_Services_Client\Entity\Domain_Contacts;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ContactsSetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('contacts:set')
            ->setDescription('Update domain contact information')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Contact type: owner, admin, tech, billing', 'owner')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone')
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'Organization')
            ->addOption('address', null, InputOption::VALUE_REQUIRED, 'Street address')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City')
            ->addOption('state', null, InputOption::VALUE_REQUIRED, 'State/province')
            ->addOption('postal-code', null, InputOption::VALUE_REQUIRED, 'Postal code')
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code')
            ->addOption('transferlock-opt-out', null, InputOption::VALUE_NONE, 'Opt out of automatic transfer lock after contact change');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Contact management');
        }

        $domainName = $input->getArgument('domain');
        $contactType = $input->getOption('type');

        $firstName = $input->getOption('first-name') ?? $io->ask('First name');
        $lastName = $input->getOption('last-name') ?? $io->ask('Last name');
        $email = $input->getOption('email') ?? $io->ask('Email');
        $phone = $input->getOption('phone') ?? $io->ask('Phone');
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
        $contacts = new Domain_Contacts();
        $contacts->set_by_key($contactType, $contact);

        $transferlockOptOut = $input->getOption('transferlock-opt-out');

        try {
            $api = $this->createApi();
            $command = new Contacts(
                new Domain_Name($domainName),
                $contacts,
                $transferlockOptOut
            );
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Contact ({$contactType}) updated for {$domainName}.");
            } else {
                $io->error('Failed to update contacts: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
