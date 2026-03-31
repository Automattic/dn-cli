<?php

declare(strict_types=1);

namespace DnCli\Command;

use Automattic\Domain_Services_Client\Api;
use DnCli\Api\WPcomClient;
use DnCli\Config\ConfigManager;
use DnCli\Factory\ApiClientFactory;
use DnCli\Factory\WPcomClientFactory;
use DnCli\Service\Spinner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    protected ConfigManager $configManager;
    private ?Api $api;
    private ?WPcomClient $wpcomClient;

    public function __construct(?ConfigManager $configManager = null, ?Api $api = null, ?WPcomClient $wpcomClient = null)
    {
        $this->configManager = $configManager ?? new ConfigManager();
        $this->api = $api;
        $this->wpcomClient = $wpcomClient;
        parent::__construct();
    }

    protected function requiresConfig(): bool
    {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->requiresConfig() && !$this->configManager->isConfigured()) {
            $io->warning('Not configured. Run `dn configure` first.');
            return Command::FAILURE;
        }

        return $this->handle($input, $output, $io);
    }

    abstract protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int;

    protected function createApi(): Api
    {
        if ($this->api !== null) {
            return $this->api;
        }

        return ApiClientFactory::create($this->configManager);
    }

    protected function createWPcomClient(): WPcomClient
    {
        if ($this->wpcomClient !== null) {
            return $this->wpcomClient;
        }

        return WPcomClientFactory::create($this->configManager);
    }

    protected function isUserMode(): bool
    {
        return $this->configManager->getMode() === 'user';
    }

    protected function redirectToWordPressCom(SymfonyStyle $io, string $feature): int
    {
        $io->text("{$feature} in user mode is managed through WordPress.com.");
        $io->text('Visit: https://wordpress.com/domains/manage');

        return self::SUCCESS;
    }

    /**
     * @template T
     * @param callable(): T $task
     * @return T
     */
    protected function withSpinner(string $message, callable $task): mixed
    {
        return (new Spinner())->spin($message, $task);
    }

    /**
     * Redact known credential values from error messages to prevent
     * accidental leakage via exception output (e.g. Guzzle including
     * API keys in request URLs or headers).
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        $apiKey = $this->configManager->getApiKey();
        $apiUser = $this->configManager->getApiUser();
        $oauthToken = $this->configManager->getOAuthToken();

        if ($apiKey !== null) {
            $message = str_replace($apiKey, '***', $message);
        }
        if ($apiUser !== null) {
            $message = str_replace($apiUser, '***', $message);
        }
        if ($oauthToken !== null) {
            $message = str_replace($oauthToken, '***', $message);
        }

        return $message;
    }
}
