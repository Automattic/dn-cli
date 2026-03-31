<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Check;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Entity\Domain_Names;
use Automattic\Domain_Services_Client\Response\Domain\Check as CheckResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Check domain availability and pricing')
            ->addArgument('domains', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Domain name(s) to check');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->handleUserMode($input, $io);
        }

        return $this->handlePartnerMode($input, $io);
    }

    private function handlePartnerMode(InputInterface $input, SymfonyStyle $io): int
    {
        $domainArgs = $input->getArgument('domains');
        $domainNames = new Domain_Names();

        foreach ($domainArgs as $name) {
            $domainNames->add_domain_name(new Domain_Name($name));
        }

        try {
            $api = $this->createApi();
            $command = new Check($domainNames);
            /** @var CheckResponse $response */
            $response = $this->withSpinner('Checking domain availability...', fn() => $api->post($command));

            if (!$response->is_success()) {
                $io->error('API error: ' . $response->get_status_description());
                return self::FAILURE;
            }

            $domains = $response->get_domains();

            $rows = [];
            foreach ($domains as $domain => $info) {
                $rows[] = [
                    $domain,
                    $info['available'] ? '<fg=green>Yes</>' : '<fg=red>No</>',
                    $info['fee_class'] ?? '-',
                    isset($info['fee_amount']) ? '$' . number_format((float) $info['fee_amount'], 2) : '-',
                    ($info['zone_is_active'] ?? false) ? 'Yes' : 'No',
                    ($info['tld_in_maintenance'] ?? false) ? 'Yes' : 'No',
                ];
            }

            $io->table(
                ['Domain', 'Available', 'Fee Class', 'Price', 'Zone Active', 'TLD Maintenance'],
                $rows
            );
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function handleUserMode(InputInterface $input, SymfonyStyle $io): int
    {
        $domainArgs = $input->getArgument('domains');

        try {
            $client = $this->createWPcomClient();
            $rows = [];

            foreach ($domainArgs as $domain) {
                $result = $this->withSpinner("Checking {$domain}...", fn() => $client->get("rest/v1.3/domains/{$domain}/is-available"));

                $available = ($result['status'] ?? '') === 'available';

                $rows[] = [
                    $domain,
                    $available ? '<fg=green>Yes</>' : '<fg=red>No</>',
                    $result['status'] ?? '-',
                    $result['cost'] ?? '-',
                    !empty($result['supports_privacy']) ? 'Yes' : 'No',
                ];
            }

            $io->table(
                ['Domain', 'Available', 'Status', 'Cost', 'Privacy'],
                $rows
            );
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
