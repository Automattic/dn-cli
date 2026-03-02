<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Set\Transferlock;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TransferlockCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('transferlock')
            ->setDescription('Set transfer lock for a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name')
            ->addArgument('state', InputArgument::REQUIRED, 'Lock state: on or off');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Transfer lock settings');
        }

        $domainName = $input->getArgument('domain');
        $state = strtolower($input->getArgument('state'));

        if (!in_array($state, ['on', 'off'], true)) {
            $io->error('Invalid state. Use: on or off');
            return self::FAILURE;
        }

        $lock = $state === 'on';

        try {
            $api = $this->createApi();
            $command = new Transferlock(new Domain_Name($domainName), $lock);
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Transfer lock set to '{$state}' for {$domainName}.");
            } else {
                $io->error('Failed to update transfer lock: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
