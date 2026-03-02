<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Info;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Response\Domain\Info as InfoResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InfoCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('info')
            ->setDescription('Get detailed information about a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to query');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Domain info');
        }

        $domainName = $input->getArgument('domain');

        try {
            $api = $this->createApi();
            $command = new Info(new Domain_Name($domainName));
            /** @var InfoResponse $response */
            $response = $api->post($command);

            if (!$response->is_success()) {
                $io->error('API error: ' . $response->get_status_description());
                return self::FAILURE;
            }

            $io->title('Domain: ' . $domainName);

            $rows = [];

            $created = $response->get_created_date();
            $rows[] = ['Created', $created ? $created->format('Y-m-d H:i:s') : '-'];

            $expiration = $response->get_expiration_date();
            $rows[] = ['Expires', $expiration ? $expiration->format('Y-m-d H:i:s') : '-'];

            $updated = $response->get_updated_date();
            $rows[] = ['Updated', $updated ? $updated->format('Y-m-d H:i:s') : '-'];

            $paidUntil = $response->get_paid_until();
            $rows[] = ['Paid Until', $paidUntil ? $paidUntil->format('Y-m-d H:i:s') : '-'];

            $rows[] = ['Auth Code', $response->get_auth_code() ?: '-'];
            $rows[] = ['Renewal Mode', $response->get_renewal_mode() ?? '-'];
            $rows[] = ['Transfer Mode', $response->get_transfer_mode() ?? '-'];
            $rows[] = ['RGP Status', $response->get_rgp_status() ?? '-'];

            $transferlock = $response->get_transferlock();
            $rows[] = ['Transfer Lock', $transferlock === null ? '-' : ($transferlock ? 'On' : 'Off')];

            $privacy = $response->get_privacy_setting();
            $rows[] = ['Privacy', $privacy !== null ? $privacy->get_setting() : '-'];

            $dnssec = $response->get_dnssec();
            $rows[] = ['DNSSEC', $dnssec ?? '-'];

            $io->table(['Property', 'Value'], $rows);

            // Nameservers
            $nameservers = $response->get_name_servers();
            if ($nameservers !== null) {
                $nsArray = $nameservers->to_array();
                if (!empty($nsArray)) {
                    $io->section('Nameservers');
                    $io->listing($nsArray);
                }
            }

            // EPP Status
            $eppStatus = $response->get_domain_status();
            if ($eppStatus !== null) {
                $statusArray = $eppStatus->to_array();
                if (!empty($statusArray)) {
                    $io->section('EPP Status Codes');
                    $io->listing($statusArray);
                }
            }

            // Contacts
            $contacts = $response->get_contacts();
            if ($contacts !== null && !$contacts->is_empty()) {
                $io->section('Contacts');
                foreach (['owner', 'admin', 'tech', 'billing'] as $type) {
                    $contact = $contacts->get_by_key($type);
                    if ($contact !== null) {
                        $contactInfo = $contact->get_contact_information();
                        if ($contactInfo !== null) {
                            $io->text("<info>{$type}:</info> " .
                                trim(($contactInfo->get_first_name() ?? '') . ' ' . ($contactInfo->get_last_name() ?? '')) .
                                ' <' . ($contactInfo->get_email() ?? '') . '>');
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
