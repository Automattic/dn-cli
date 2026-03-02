<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Dns\Get;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Response\Dns\Get as DnsGetResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DnsGetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('dns:get')
            ->setDescription('Get DNS records for a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to query');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'DNS management');
        }

        $domainName = $input->getArgument('domain');

        try {
            $api = $this->createApi();
            $command = new Get(new Domain_Name($domainName));
            /** @var DnsGetResponse $response */
            $response = $api->post($command);

            if (!$response->is_success()) {
                $io->error('API error: ' . $response->get_status_description());
                return self::FAILURE;
            }

            $dnsRecords = $response->get_dns_records();
            $recordSets = $dnsRecords->get_record_sets();

            $rows = [];
            foreach ($recordSets as $recordSet) {
                $data = $recordSet->get_data();
                $rows[] = [
                    (string) $recordSet->get_type(),
                    $recordSet->get_name(),
                    implode(', ', $data),
                    $recordSet->get_ttl(),
                ];
            }

            if (empty($rows)) {
                $io->text('No DNS records found.');
            } else {
                $io->table(['Type', 'Name', 'Value', 'TTL'], $rows);
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
