<?php

declare(strict_types=1);

namespace DnCli\Command;

use DnCli\Util\Browser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckoutCommand extends BaseCommand
{
    /** @var callable(string): void */
    private $browserOpener;

    public function __construct(?callable $browserOpener = null)
    {
        $this->browserOpener = $browserOpener ?? [Browser::class, 'open'];
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('checkout')
            ->setDescription('Open WordPress.com checkout in your browser (user mode only)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Site slug to checkout for (e.g. mysite.wordpress.com)');
    }

    protected function handle(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        if (!$this->isUserMode()) {
            $io->error('The checkout command is only available in user mode. Run `dn configure` and select user mode.');
            return self::FAILURE;
        }

        $site = $input->getOption('site');

        if ($site !== null) {
            $url = 'https://wordpress.com/checkout/' . rawurlencode($site);
        } else {
            $url = 'https://wordpress.com/checkout/no-site?' . http_build_query([
                'isDomainOnly' => '1',
                'signup' => '0',
            ]);
        }

        ($this->browserOpener)($url);
        $io->text("Checkout opened: <info>{$url}</info>");

        return self::SUCCESS;
    }
}
