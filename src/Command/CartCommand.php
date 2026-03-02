<?php

declare(strict_types=1);

namespace DnCli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CartCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('cart')
            ->setDescription('View your WordPress.com shopping cart (user mode only)');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if (!$this->isUserMode()) {
            $io->error('The cart command is only available in user mode. Run `dn configure` and select user mode.');
            return self::FAILURE;
        }

        try {
            $client = $this->createWPcomClient();
            $cart = $client->get('rest/v1.1/me/shopping-cart/no-site');

            $products = $cart['products'] ?? [];

            if (empty($products)) {
                $io->text('Your cart is empty.');
                return self::SUCCESS;
            }

            $rows = [];
            foreach ($products as $product) {
                $rows[] = [
                    $product['meta'] ?? '-',
                    $product['product_name'] ?? $product['product_slug'] ?? '-',
                    $product['cost_display'] ?? (isset($product['cost']) ? '$' . number_format((float) $product['cost'], 2) : '-'),
                ];
            }

            $io->table(['Domain', 'Product', 'Cost'], $rows);
        } catch (\Exception $e) {
            $io->error('Error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
