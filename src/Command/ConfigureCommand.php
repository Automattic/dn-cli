<?php

declare(strict_types=1);

namespace DnCli\Command;

use DnCli\Auth\OAuthFlow;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
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

        // Default to user mode when no mode specified (e.g. stdin without --mode)
        $mode = $mode ?? 'user';

        if ($mode === 'user') {
            return $this->handleUserMode($input, $io);
        }

        return $this->handlePartnerMode($input, $io);
    }

    private function showSplashScreen(SymfonyStyle $io): void
    {
        $io->writeln('');
        $io->writeln('            ##########             <options=bold>dn-cli</> by Automattic');
        $io->writeln('        ##################');
        $io->writeln('      #####            #####       <fg=gray>Domain Name CLI — Manage domains from your terminal.</>');
        $io->writeln('    #####                #####');
        $io->writeln('   ####          ####      ####    <fg=yellow;options=bold>[U] User Mode</>');
        $io->writeln('  ####          ####        ####       WordPress.com OAuth authentication.');
        $io->writeln('  ####         ####         ####       <fg=gray>Requires:</>  WordPress.com account');
        $io->writeln('  ####        ####          ####       <fg=gray>Best for:</>  Personal domain management and purchases');
        $io->writeln('   ####      ####          ####');
        $io->writeln('    #####                #####     <fg=yellow;options=bold>[P] Partner Mode</>');
        $io->writeln('      #####            #####           Direct API access via Automattic Domain Services.');
        $io->writeln('        ##################             <fg=gray>Requires:</>  API Key + API User credentials');
        $io->writeln('            ##########                 <fg=gray>Best for:</>  Registrars, resellers, and API integrations');
        $io->writeln('');
    }

    private function askMode(SymfonyStyle $io): string
    {
        $modes = ['U' => 'user', 'P' => 'partner'];
        $key = $this->caseInsensitiveChoice(
            $io,
            'Select mode (type <fg=yellow>U</> or <fg=yellow>P</>, or use arrow keys)',
            $modes,
            'U'
        );

        return $modes[$key] ?? $key;
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

        $autoCheckout = null;
        if (!$input->getOption('stdin')) {
            $autoCheckout = $this->askAutoCheckoutPreference($io);
        }

        $this->configManager->saveUserMode($token, $autoCheckout);

        $io->success('Configuration saved.');

        return self::SUCCESS;
    }

    private function askAutoCheckoutPreference(SymfonyStyle $io): ?string
    {
        $io->writeln('');
        $io->writeln('<fg=cyan;options=bold>  Auto-Checkout Preference</>');
        $io->writeln('');
        $io->writeln('  When registering domains, auto-checkout can complete the');
        $io->writeln('  purchase from the terminal without opening a browser.');
        $io->writeln('');
        $io->writeln('  <fg=yellow>[N]</> None — always open checkout in browser');
        $io->writeln('  <fg=yellow>[C]</> Credits — auto-checkout when credits cover the full amount');
        $io->writeln('  <fg=yellow>[S]</> Stored card — auto-checkout using a saved payment method');
        $io->writeln('  <fg=yellow>[B]</> Both — try credits first, then stored card');
        $io->writeln('');

        $options = [
            'N' => 'none',
            'C' => 'credits',
            'S' => 'stored card',
            'B' => 'both',
        ];

        $key = $this->caseInsensitiveChoice($io, 'Auto-checkout preference', $options, 'N');
        $selected = $options[$key] ?? $key;

        return match ($selected) {
            'credits' => 'credits',
            'stored card' => 'card',
            'both' => 'both',
            default => null,
        };
    }

    private function caseInsensitiveChoice(SymfonyStyle $io, string $question, array $choices, string $default): string
    {
        $choiceQuestion = new ChoiceQuestion($question, $choices, $default);
        $choiceQuestion->setNormalizer(function (?string $value) {
            if ($value !== null && strlen($value) === 1) {
                return strtoupper($value);
            }
            return $value;
        });

        return $io->askQuestion($choiceQuestion);
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
