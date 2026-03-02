<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Renew;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RenewCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('renew')
            ->setDescription('Renew a domain registration')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to renew')
            ->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'Renewal period in years', '1')
            ->addOption('expiration-year', null, InputOption::VALUE_REQUIRED, 'Current expiration year (required)')
            ->addOption('fee', null, InputOption::VALUE_REQUIRED, 'Fee amount for premium domains');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Domain renewals');
        }

        $domainName = $input->getArgument('domain');
        $period = (int) $input->getOption('period');

        $expirationYear = $input->getOption('expiration-year');
        if ($expirationYear === null) {
            $expirationYear = $io->ask('Current expiration year');
        }
        $expirationYear = (int) $expirationYear;

        $fee = $input->getOption('fee') !== null ? (float) $input->getOption('fee') : null;

        $io->text("Renewing <info>{$domainName}</info> for {$period} year(s)...");

        try {
            $api = $this->createApi();
            $command = new Renew(
                new Domain_Name($domainName),
                $expirationYear,
                $period,
                $fee
            );
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Renewal request for {$domainName} has been submitted.");
            } else {
                $io->error('Renewal failed: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
