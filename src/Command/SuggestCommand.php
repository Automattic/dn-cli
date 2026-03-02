<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Suggestions;
use Automattic\Domain_Services_Client\Response\Domain\Suggestions as SuggestionsResponse;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SuggestCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('suggest')
            ->setDescription('Get domain name suggestions')
            ->addArgument('query', InputArgument::REQUIRED, 'Search term for suggestions')
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Number of suggestions', '10')
            ->addOption('tlds', 't', InputOption::VALUE_REQUIRED, 'Comma-separated TLDs to filter (e.g. com,net,org)')
            ->addOption('exact', null, InputOption::VALUE_NONE, 'Exact match only');
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
        $query = $input->getArgument('query');
        $count = (int) $input->getOption('count');
        $tldsOption = $input->getOption('tlds');
        $exact = $input->getOption('exact');

        $tlds = null;
        if ($tldsOption !== null) {
            $tlds = array_map('trim', explode(',', $tldsOption));
        }

        try {
            $api = $this->createApi();
            $command = new Suggestions($query, $count, $tlds, $exact);
            /** @var SuggestionsResponse $response */
            $response = $api->post($command);

            if (!$response->is_success()) {
                $io->error('API error: ' . $response->get_status_description());
                return self::FAILURE;
            }

            $suggestionsEntity = $response->get_suggestions();
            $rows = [];

            foreach ($suggestionsEntity->get_suggestions() as $suggestion) {
                $rows[] = [
                    (string) $suggestion->get_domain_name(),
                    $suggestion->is_available() ? '<fg=green>Yes</>' : '<fg=red>No</>',
                    '$' . number_format($suggestion->get_reseller_register_fee() / 100, 2),
                    '$' . number_format($suggestion->get_reseller_renewal_fee() / 100, 2),
                    $suggestion->is_premium() ? 'Yes' : 'No',
                ];
            }

            $io->table(
                ['Domain', 'Available', 'Register Fee', 'Renewal Fee', 'Premium'],
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
        $query = $input->getArgument('query');
        $count = (int) $input->getOption('count');

        try {
            $client = $this->createWPcomClient();
            $result = $client->get('rest/v1.1/domains/suggestions', [
                'query' => $query,
                'quantity' => $count,
            ]);

            $rows = [];
            foreach ($result as $suggestion) {
                $rows[] = [
                    $suggestion['domain_name'] ?? '-',
                    $suggestion['cost'] ?? '-',
                    !empty($suggestion['is_premium']) ? 'Yes' : 'No',
                    $suggestion['relevance'] ?? '-',
                ];
            }

            $io->table(
                ['Domain', 'Cost', 'Premium', 'Relevance'],
                $rows
            );
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
