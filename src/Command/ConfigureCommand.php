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
            $this->showSplashScreen($io);
            $mode = $this->askMode($io);
            $this->showCommandOverview($io, $mode);
        }

        // Default to partner for backward compatibility (stdin without --mode)
        $mode = $mode ?? 'partner';

        if ($mode === 'user') {
            return $this->handleUserMode($input, $io);
        }

        return $this->handlePartnerMode($input, $io);
    }

    private function showSplashScreen(SymfonyStyle $io): void
    {
        $io->writeln('');
        $io->writeln('<fg=cyan>   _  _   _ _____</><fg=blue> ___ </><fg=cyan> __  __   _ _____ _____ ___ ___</>');
        $io->writeln('<fg=cyan>  /_\| | | |_   _</><fg=blue>/ _ </><fg=cyan>\|  \/  | /_\_   _|_   _|_ _/ __|</>');
        $io->writeln('<fg=cyan> / _ \ |_| | | |</><fg=blue>| (/) |</><fg=cyan> |\/| |/ _ \| |   | |  | | (__</>');
        $io->writeln('<fg=cyan>/_/ \_\___/  |_| </><fg=blue> \___/</><fg=cyan>|_|  |_/_/ \_\_|   |_| |___\___|</>');
        $io->writeln('');
        $io->writeln('  <fg=white;options=bold>dn-cli</> by Automattic · Domain Name CLI');
        $io->writeln('<fg=gray>  Manage domains from your terminal.</>');
        $io->writeln('');
        $io->writeln('  Choose an authentication mode to get started.');
        $io->writeln('');
        $io->writeln('  <fg=yellow;options=bold>[P] Partner Mode</>');
        $io->writeln('      Direct API access via Automattic Domain Services.');
        $io->writeln('      <fg=gray>Requires:</>  API Key + API User credentials');
        $io->writeln('      <fg=gray>Best for:</>  Registrars, resellers, and API integrations');
        $io->writeln('');
        $io->writeln('  <fg=yellow;options=bold>[U] User Mode</>');
        $io->writeln('      WordPress.com OAuth authentication.');
        $io->writeln('      <fg=gray>Requires:</>  WordPress.com account');
        $io->writeln('      <fg=gray>Best for:</>  Personal domain management and purchases');
        $io->writeln('');
    }

    private function askMode(SymfonyStyle $io): string
    {
        return $io->choice(
            'Select mode (type <fg=yellow>P</> or <fg=yellow>U</>, or use arrow keys)',
            ['partner', 'user'],
            'partner'
        );
    }

    private function showCommandOverview(SymfonyStyle $io, string $mode): void
    {
        $io->writeln('');

        if ($mode === 'partner') {
            $io->writeln('<fg=cyan;options=bold>  Partner Mode — Available Commands</>');
            $io->writeln('');
            $io->writeln('  <fg=yellow>SETUP</>');
            $io->writeln('    dn configure                  Set up API credentials');
            $io->writeln('');
            $io->writeln('  <fg=yellow>DISCOVERY</>');
            $io->writeln('    dn check <domain>...          Check availability and pricing');
            $io->writeln('    dn suggest <query>            Get domain name suggestions');
            $io->writeln('');
            $io->writeln('  <fg=yellow>REGISTRATION</>');
            $io->writeln('    dn register <domain>          Register a new domain');
            $io->writeln('    dn renew <domain>             Renew a domain');
            $io->writeln('    dn delete <domain>            Delete a domain');
            $io->writeln('    dn restore <domain>           Restore a deleted domain');
            $io->writeln('    dn transfer <domain>          Transfer a domain in');
            $io->writeln('');
            $io->writeln('  <fg=yellow>MANAGEMENT</>');
            $io->writeln('    dn info <domain>              Get detailed domain info');
            $io->writeln('    dn dns:get <domain>           Get DNS records');
            $io->writeln('    dn dns:set <domain>           Set a DNS record');
            $io->writeln('    dn contacts:set <domain>      Update contact information');
            $io->writeln('    dn privacy <domain> <on|off>  Set WHOIS privacy');
            $io->writeln('    dn transferlock <domain> <on|off>');
            $io->writeln('                                  Set transfer lock');
        } else {
            $io->writeln('<fg=cyan;options=bold>  User Mode — Available Commands</>');
            $io->writeln('');
            $io->writeln('  <fg=yellow>SETUP</>');
            $io->writeln('    dn configure                  Set up WordPress.com OAuth');
            $io->writeln('');
            $io->writeln('  <fg=yellow>DISCOVERY</>');
            $io->writeln('    dn check <domain>...          Check availability and pricing');
            $io->writeln('    dn suggest <query>            Get domain name suggestions');
            $io->writeln('');
            $io->writeln('  <fg=yellow>PURCHASE</>');
            $io->writeln('    dn register <domain>          Add a domain to your cart');
            $io->writeln('    dn cart                       View your shopping cart');
            $io->writeln('    dn checkout                   Open browser checkout');
            $io->writeln('');
            $io->writeln('  <fg=yellow>MANAGEMENT</>');
            $io->writeln('    Managed via WordPress.com — visit:');
            $io->writeln('    https://wordpress.com/domains/manage');
        }

        $io->writeln('');
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
