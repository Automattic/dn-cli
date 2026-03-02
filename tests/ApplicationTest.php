<?php

declare(strict_types=1);

namespace DnCli\Tests;

use DnCli\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application();
    }

    public function test_name(): void
    {
        $this->assertSame('dn', $this->app->getName());
    }

    public function test_version(): void
    {
        $this->assertSame('1.0.0', $this->app->getVersion());
    }

    #[DataProvider('commandNamesProvider')]
    public function test_command_registered(string $commandName): void
    {
        $this->assertTrue($this->app->has($commandName), "Command '{$commandName}' should be registered");
    }

    public static function commandNamesProvider(): array
    {
        return [
            'configure' => ['configure'],
            'check' => ['check'],
            'suggest' => ['suggest'],
            'info' => ['info'],
            'register' => ['register'],
            'renew' => ['renew'],
            'delete' => ['delete'],
            'restore' => ['restore'],
            'transfer' => ['transfer'],
            'dns:get' => ['dns:get'],
            'dns:set' => ['dns:set'],
            'contacts:set' => ['contacts:set'],
            'privacy' => ['privacy'],
            'transferlock' => ['transferlock'],
            'cart' => ['cart'],
            'checkout' => ['checkout'],
        ];
    }

    public function test_total_custom_commands(): void
    {
        $commands = $this->app->all();

        // Filter out built-in Symfony commands (help, list, completion, _complete)
        $custom = array_filter($commands, function ($cmd) {
            return !in_array($cmd->getName(), ['help', 'list', 'completion', '_complete']);
        });

        $this->assertCount(16, $custom);
    }
}
