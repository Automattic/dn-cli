<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Restore;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestoreCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('restore')
            ->setDescription('Restore a deleted domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to restore');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Domain restoration');
        }

        $domainName = $input->getArgument('domain');

        try {
            $api = $this->createApi();
            $command = new Restore(new Domain_Name($domainName));
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Restore request for {$domainName} has been submitted.");
            } else {
                $io->error('Restore failed: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
