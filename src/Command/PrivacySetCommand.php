<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Command\Domain\Set\Privacy;
use Automattic\Domain_Services_Client\Entity\Domain_Name;
use Automattic\Domain_Services_Client\Entity\Whois_Privacy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PrivacySetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('privacy')
            ->setDescription('Set WHOIS privacy for a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name')
            ->addArgument('setting', InputArgument::REQUIRED, 'Privacy setting: on, off, or redact');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if ($this->isUserMode()) {
            return $this->redirectToWordPressCom($io, 'Privacy settings');
        }

        $domainName = $input->getArgument('domain');
        $setting = strtolower($input->getArgument('setting'));

        $privacySetting = match ($setting) {
            'on' => new Whois_Privacy(Whois_Privacy::ENABLE_PRIVACY_SERVICE),
            'off' => new Whois_Privacy(Whois_Privacy::DISCLOSE_CONTACT_INFO),
            'redact' => new Whois_Privacy(Whois_Privacy::REDACT_CONTACT_INFO),
            default => null,
        };

        if ($privacySetting === null) {
            $io->error('Invalid privacy setting. Use: on, off, or redact');
            return self::FAILURE;
        }

        try {
            $api = $this->createApi();
            $command = new Privacy(new Domain_Name($domainName), $privacySetting);
            $response = $api->post($command);

            if ($response->is_success()) {
                $io->success("Privacy set to '{$setting}' for {$domainName}.");
            } else {
                $io->error('Failed to update privacy: ' . $response->get_status_description());
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
