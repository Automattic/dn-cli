<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Delete;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Delete a domain registration')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name to delete');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Domain deletion');
        }

        $domainName = $input->getArgument('domain');

        $io->caution("You are about to delete the domain: {$domainName}");

        if (!$io->confirm('Are you sure you want to proceed?', false)) {
            $io->text('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $api = $this->createApi();
            $command = new Delete(new Domain_Name($domainName));
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Delete request for {$domainName} has been submitted.");
            } else {
                $io->error('Delete failed: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
