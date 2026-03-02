<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Dns\Set;
use Automattic\Domain_Services_Client\Entity\Dns_Record_Set;
use Automattic\Domain_Services_Client\Entity\Dns_Record_Sets;
use Automattic\Domain_Services_Client\Entity\Dns_Record_Type;
use Automattic\Domain_Services_Client\Entity\Dns_Records;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DnsSetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('dns:set')
            ->setDescription('Set a DNS record for a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Record type (A, AAAA, CNAME, MX, TXT, etc.)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Record name (e.g. @ or subdomain)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Record value(s)')
            ->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'TTL in seconds', '3600');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'DNS management');
        }

        $domainName = $input->getArgument('domain');
        $type = $input->getOption('type') ?? $io->ask('Record type (A, AAAA, CNAME, MX, TXT, etc.)');
        $name = $input->getOption('name') ?? $io->ask('Record name (e.g. @ or subdomain)');
        $values = $input->getOption('value');
        $ttl = (int) $input->getOption('ttl');

        if (empty($values)) {
            $value = $io->ask('Record value');
            $values = [$value];
        }

        $type = strtoupper($type);

        try {
            $domain = new Domain_Name($domainName);
            $recordSet = new Dns_Record_Set(
                $name,
                new Dns_Record_Type($type),
                $ttl,
                $values
            );
            $recordSets = new Dns_Record_Sets($recordSet);
            $dnsRecords = new Dns_Records($domain, $recordSets);

            $api = $this->createApi();
            $command = new Set($domain, $dnsRecords);
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("DNS record set for {$domainName}: {$type} {$name} -> " . implode(', ', $values));
            } else {
                $io->error('Failed to set DNS record: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
