<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use DnCli\Command\CheckoutCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CheckoutCommandTest extends CommandTestCase
{
    public function test_checkout_opens_browser_default_site(): void
    {
        $openedUrl = null;
        $command = new CheckoutCommand(function (string $url) use (&$openedUrl) {
            $openedUrl = $url;
        });

        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=test-token');

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('checkout'));
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Checkout opened', $tester->getDisplay());
        $this->assertStringStartsWith('https://wordpress.com/checkout/no-site?', $openedUrl);
        $this->assertStringContainsString('isDomainOnly=1', $openedUrl);
        $this->assertStringContainsString('signup=1', $openedUrl);
    }

    public function test_checkout_with_site_option(): void
    {
        $openedUrl = null;
        $command = new CheckoutCommand(function (string $url) use (&$openedUrl) {
            $openedUrl = $url;
        });

        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=test-token');

        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find('checkout'));
        $tester->execute(['--site' => 'ttl.blog']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Checkout opened', $tester->getDisplay());
        $this->assertSame('https://wordpress.com/checkout/ttl.blog', $openedUrl);
    }

    public function test_checkout_partner_mode_errors(): void
    {
        $tester = $this->createTester(new CheckoutCommand());
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('only available in user mode', $tester->getDisplay());
    }
}
