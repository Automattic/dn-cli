<?php

declare(strict_types=1);

namespace DnCli\Command;

use DnCli\Api\WPcomClient;
use DnCli\Service\CheckoutService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared auto-checkout logic for user-mode commands that add items to the WPCOM cart.
 *
 * Used by RegisterCommand and TransferCommand. Requires BaseCommand as the host class
 * (uses $this->configManager and $this->sanitizeErrorMessage()).
 */
trait UserModeCheckoutTrait
{
    private function resolveAutoCheckoutMode(InputInterface $input): ?string
    {
        if ($input->getOption('auto-checkout')) {
            return 'both';
        }
        if ($input->getOption('auto-pay-credits')) {
            return 'credits';
        }
        if ($input->getOption('auto-pay-card')) {
            return 'card';
        }

        return $this->configManager->getAutoCheckout();
    }

    private function attemptAutoCheckout(
        WPcomClient $client,
        array $cartResponse,
        string $mode,
        string $domainName,
        InputInterface $input,
        SymfonyStyle $io,
        string $successLabel = 'registered',
    ): ?int {
        $checkout = new CheckoutService($client);
        $skipConfirm = $input->getOption('yes');
        $totalCost = (int) ($cartResponse['total_cost_integer'] ?? 0);
        $credits = (int) ($cartResponse['credits_integer'] ?? 0);
        $currency = $cartResponse['currency'] ?? 'USD';

        // Fetch domain contact info (required for all auto-checkout paths)
        try {
            $contactInfo = $this->withSpinner('Fetching contact information...', fn() => $checkout->getDomainContactInfo());
        } catch (\Exception $e) {
            $io->warning('Could not fetch contact information: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return null;
        }

        if (!$checkout->hasRequiredContactFields($contactInfo)) {
            $io->warning('Incomplete contact information on file. Please complete checkout in browser to provide your details.');
            return null;
        }

        // Try credits first
        if (in_array($mode, ['credits', 'both'], true) && $credits >= $totalCost) {
            return $this->checkoutWithCredits($checkout, $cartResponse, $contactInfo, $domainName, $totalCost, $currency, $skipConfirm, $io, $successLabel);
        }

        // Try stored card
        if (in_array($mode, ['card', 'both'], true)) {
            return $this->checkoutWithCard($checkout, $cartResponse, $contactInfo, $domainName, $totalCost, $currency, $skipConfirm, $io, $successLabel);
        }

        if ($mode === 'credits') {
            $io->warning('Insufficient credits for auto-checkout. Falling back to browser checkout.');
        }

        return null;
    }

    private function checkoutWithCredits(
        CheckoutService $checkout,
        array $cartResponse,
        array $contactInfo,
        string $domainName,
        int $totalCostInteger,
        string $currency,
        bool $skipConfirm,
        SymfonyStyle $io,
        string $successLabel = 'registered',
    ): ?int {
        try {
            $costDisplay = $this->formatCost($totalCostInteger, $currency);
            $io->text("Domain: <info>{$domainName}</info> — Cost: <info>{$costDisplay}</info> — Payment: <info>Account credits</info>");

            if (!$skipConfirm && !$io->confirm('Complete purchase?', false)) {
                $io->text('Cancelled.');
                return self::SUCCESS;
            }

            $payment = $checkout->buildCreditsPayment($contactInfo);
            $this->withSpinner('Processing payment...', fn() => $checkout->submitTransaction($cartResponse, $payment, $contactInfo));

            $io->success("Domain {$domainName} {$successLabel} successfully!");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $io->warning('Auto-checkout failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return null;
        }
    }

    private function checkoutWithCard(
        CheckoutService $checkout,
        array $cartResponse,
        array $contactInfo,
        string $domainName,
        int $totalCostInteger,
        string $currency,
        bool $skipConfirm,
        SymfonyStyle $io,
        string $successLabel = 'registered',
    ): ?int {
        try {
            $methods = $this->withSpinner('Fetching payment methods...', fn() => $checkout->getPaymentMethods());
            $methods = array_filter($methods, fn(array $m) => !($m['is_expired'] ?? false));

            if (empty($methods)) {
                $io->warning('No stored payment methods found. Falling back to browser checkout.');
                return null;
            }

            $methods = array_values($methods);
            $selectedMethod = $methods[0];

            if (count($methods) > 1) {
                $choices = [];
                foreach ($methods as $i => $m) {
                    $choices[$i] = $checkout->formatCardLabel($m);
                }
                $selectedKey = $io->choice('Select payment method', $choices);
                $selectedMethod = $methods[array_search($selectedKey, $choices, true)];
            }

            $costDisplay = $this->formatCost($totalCostInteger, $currency);
            $cardLabel = $checkout->formatCardLabel($selectedMethod);
            $io->text("Domain: <info>{$domainName}</info> — Cost: <info>{$costDisplay}</info> — Payment: <info>{$cardLabel}</info>");

            if (!$skipConfirm && !$io->confirm('Complete purchase?', false)) {
                $io->text('Cancelled.');
                return self::SUCCESS;
            }

            $payment = $checkout->buildStoredCardPayment($selectedMethod);
            $this->withSpinner('Processing payment...', fn() => $checkout->submitTransaction($cartResponse, $payment, $contactInfo));

            $io->success("Domain {$domainName} {$successLabel} successfully!");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $io->warning('Auto-checkout failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return null;
        }
    }

    private function buildCheckoutUrl(string $site, bool $isDomainOnly): string
    {
        $url = 'https://wordpress.com/checkout/' . rawurlencode($site);
        if ($isDomainOnly) {
            $url .= '?' . http_build_query([
                'isDomainOnly' => '1',
                'signup' => '0',
            ]);
        }

        return $url;
    }

    private function formatCost(int $costInteger, string $currency): string
    {
        $amount = $costInteger / 100;
        $symbol = $currency === 'USD' ? '$' : $currency . ' ';

        return $symbol . number_format($amount, 2);
    }
}
