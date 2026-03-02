<?php

declare(strict_types=1);

namespace DnCli\Command;

use DnCli\Auth\OAuthFlow;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigureCommand extends BaseCommand
{
    private ?OAuthFlow $oauthFlow;

    public function __construct(?OAuthFlow $oauthFlow = null)
    {
        $this->oauthFlow = $oauthFlow;
        parent::__construct();
    }

    protected function requiresConfig(): bool
    {
        return false;
    }

    protected function configure(): void
    {
        $this
            ->setName('configure')
            ->setDescription('Set up credentials for dn CLI')
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'Authentication mode: partner or user')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Read credentials from stdin')
            ->addOption('api-url', null, InputOption::VALUE_OPTIONAL, 'API base URL (optional override, partner mode only)');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $mode = $input->getOption('mode');

        if ($mode === null && !$input->getOption('stdin')) {
            $mode = $io->choice('Select mode', ['partner', 'user'], 'partner');
        }

        // Default to partner for backward compatibility (stdin without --mode)
        $mode = $mode ?? 'partner';

        if ($mode === 'user') {
            return $this->handleUserMode($input, $io);
        }

        return $this->handlePartnerMode($input, $io);
    }

    private function handlePartnerMode(InputInterface $input, SymfonyStyle $io): int
    {
        $apiUrl = $input->getOption('api-url');

        if ($input->getOption('stdin')) {
            $stream = $this->getInputStream($input);
            $apiKey = trim((string) fgets($stream));
            $apiUser = trim((string) fgets($stream));
        } else {
            $apiKey = (string) $io->askHidden('API Key (X-DSAPI-KEY)');
            $apiUser = (string) $io->askHidden('API User (X-DSAPI-USER)');

            if ($apiUrl === null) {
                $apiUrl = $io->ask('API URL (leave blank for default)', '');
                if ($apiUrl === '') {
                    $apiUrl = null;
                }
            }
        }

        if ($apiKey === '' || $apiUser === '') {
            $io->error('API key and user are required.');
            return self::FAILURE;
        }

        $this->configManager->save($apiKey, $apiUser, $apiUrl);

        $io->success('Configuration saved.');

        return self::SUCCESS;
    }

    private function handleUserMode(InputInterface $input, SymfonyStyle $io): int
    {
        if ($input->getOption('stdin')) {
            $stream = $this->getInputStream($input);
            $token = trim((string) fgets($stream));
        } else {
            $io->text('Authenticating with WordPress.com...');

            $flow = $this->oauthFlow ?? new OAuthFlow();
            $token = $flow->authenticate();
        }

        if ($token === '') {
            $io->error('OAuth token is required.');
            return self::FAILURE;
        }

        $this->configManager->saveUserMode($token);

        $io->success('Configuration saved.');

        return self::SUCCESS;
    }

    /**
     * @return resource
     */
    private function getInputStream(InputInterface $input)
    {
        if ($input instanceof StreamableInputInterface && $input->getStream()) {
            return $input->getStream();
        }

        return fopen('php://stdin', 'r');
    }
}
