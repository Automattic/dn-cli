<?php

declare(strict_types=1);

namespace DnCli\Tests\Command;

use DnCli\Command\ConfigureCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigureCommandTest extends TestCase
{
    private string $tempDir;
    private string $savedHome = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/dn-cli-configure-test-' . uniqid();
        mkdir($this->tempDir, 0700, true);

        $this->savedHome = getenv('HOME') ?: '';
        putenv('HOME=' . $this->tempDir);
        putenv('DN_API_KEY');
        putenv('DN_API_USER');
        putenv('DN_API_URL');
        putenv('DN_MODE');
        putenv('DN_OAUTH_TOKEN');
    }

    protected function tearDown(): void
    {
        if ($this->savedHome !== '') {
            putenv('HOME=' . $this->savedHome);
        } else {
            putenv('HOME');
        }
        putenv('DN_API_KEY');
        putenv('DN_API_USER');
        putenv('DN_API_URL');
        putenv('DN_MODE');
        putenv('DN_OAUTH_TOKEN');

        // Cleanup
        $configDir = $this->tempDir . '/.config/dn';
        if (is_file($configDir . '/config.json')) {
            unlink($configDir . '/config.json');
        }
        if (is_dir($configDir)) {
            rmdir($configDir);
        }
        if (is_dir($this->tempDir . '/.config')) {
            rmdir($this->tempDir . '/.config');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    private function createTester(): CommandTester
    {
        $command = new ConfigureCommand();
        $app = new Application();
        $app->add($command);

        return new CommandTester($app->find('configure'));
    }

    public function test_configure_via_stdin(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['my-key', 'my-user']);
        $tester->execute(['--stdin' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration saved', $tester->getDisplay());

        $configFile = $this->tempDir . '/.config/dn/config.json';
        $this->assertFileExists($configFile);

        $data = json_decode(file_get_contents($configFile), true);
        $this->assertSame('my-key', $data['api_key']);
        $this->assertSame('my-user', $data['api_user']);
        $this->assertSame('partner', $data['mode']);
    }

    public function test_configure_stdin_with_api_url(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['key', 'user']);
        $tester->execute([
            '--stdin' => true,
            '--api-url' => 'https://custom.api.com',
        ]);

        $this->assertSame(0, $tester->getStatusCode());

        $data = json_decode(file_get_contents($this->tempDir . '/.config/dn/config.json'), true);
        $this->assertSame('https://custom.api.com', $data['api_url']);
    }

    public function test_configure_stdin_empty_fails(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['', '']);
        $tester->execute(['--stdin' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function test_configure_interactive(): void
    {
        $tester = $this->createTester();
        // First input: mode choice (partner=0), then key, user, URL
        $tester->setInputs(['partner', 'interactive-key', 'interactive-user', '']);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());

        $data = json_decode(file_get_contents($this->tempDir . '/.config/dn/config.json'), true);
        $this->assertSame('interactive-key', $data['api_key']);
        $this->assertSame('interactive-user', $data['api_user']);
    }

    public function test_does_not_require_prior_config(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['key', 'user']);
        $tester->execute(['--stdin' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_success_message_does_not_reveal_config_path(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['key', 'user']);
        $tester->execute(['--stdin' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Configuration saved', $output);
        $this->assertStringNotContainsString('config.json', $output);
        $this->assertStringNotContainsString('.config/dn', $output);
    }

    public function test_configure_user_mode_via_stdin(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['my-oauth-token']);
        $tester->execute(['--mode' => 'user', '--stdin' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration saved', $tester->getDisplay());

        $configFile = $this->tempDir . '/.config/dn/config.json';
        $data = json_decode(file_get_contents($configFile), true);
        $this->assertSame('user', $data['mode']);
        $this->assertSame('my-oauth-token', $data['oauth_token']);
    }

    public function test_configure_user_mode_empty_token_fails(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['']);
        $tester->execute(['--mode' => 'user', '--stdin' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function test_configure_partner_mode_via_mode_flag(): void
    {
        $tester = $this->createTester();
        $tester->setInputs(['key', 'user']);
        $tester->execute(['--mode' => 'partner', '--stdin' => true]);

        $this->assertSame(0, $tester->getStatusCode());

        $data = json_decode(file_get_contents($this->tempDir . '/.config/dn/config.json'), true);
        $this->assertSame('partner', $data['mode']);
        $this->assertSame('key', $data['api_key']);
    }
}
