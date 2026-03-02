<?php

declare(strict_types=1);

namespace DnCli\Tests\Config;

use DnCli\Config\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    private string $tempDir;
    private string $savedApiKey = '';
    private string $savedApiUser = '';
    private string $savedApiUrl = '';
    private string $savedHome = '';

    private string $savedMode = '';
    private string $savedOAuthToken = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/dn-cli-test-' . uniqid();
        mkdir($this->tempDir, 0700, true);

        // Save original env
        $this->savedApiKey = getenv('DN_API_KEY') ?: '';
        $this->savedApiUser = getenv('DN_API_USER') ?: '';
        $this->savedApiUrl = getenv('DN_API_URL') ?: '';
        $this->savedHome = getenv('HOME') ?: '';
        $this->savedMode = getenv('DN_MODE') ?: '';
        $this->savedOAuthToken = getenv('DN_OAUTH_TOKEN') ?: '';

        // Clear env vars and point HOME to temp
        putenv('DN_API_KEY');
        putenv('DN_API_USER');
        putenv('DN_API_URL');
        putenv('DN_MODE');
        putenv('DN_OAUTH_TOKEN');
        putenv('HOME=' . $this->tempDir);
    }

    protected function tearDown(): void
    {
        // Restore env
        $this->restoreEnv('DN_API_KEY', $this->savedApiKey);
        $this->restoreEnv('DN_API_USER', $this->savedApiUser);
        $this->restoreEnv('DN_API_URL', $this->savedApiUrl);
        $this->restoreEnv('HOME', $this->savedHome);
        $this->restoreEnv('DN_MODE', $this->savedMode);
        $this->restoreEnv('DN_OAUTH_TOKEN', $this->savedOAuthToken);

        // Cleanup temp dir
        $this->removeDir($this->tempDir);

        parent::tearDown();
    }

    private function restoreEnv(string $name, string $value): void
    {
        if ($value !== '') {
            putenv("{$name}={$value}");
        } else {
            putenv($name);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_env_vars_take_priority(): void
    {
        putenv('DN_API_KEY=env-key');
        putenv('DN_API_USER=env-user');

        $config = new ConfigManager();

        $this->assertSame('env-key', $config->getApiKey());
        $this->assertSame('env-user', $config->getApiUser());
    }

    public function test_reads_from_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'api_key' => 'file-key',
            'api_user' => 'file-user',
        ]));

        $config = new ConfigManager();

        $this->assertSame('file-key', $config->getApiKey());
        $this->assertSame('file-user', $config->getApiUser());
    }

    public function test_env_vars_override_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'api_key' => 'file-key',
            'api_user' => 'file-user',
        ]));

        putenv('DN_API_KEY=env-key');

        $config = new ConfigManager();

        $this->assertSame('env-key', $config->getApiKey());
        $this->assertSame('file-user', $config->getApiUser());
    }

    public function test_returns_null_when_not_configured(): void
    {
        $config = new ConfigManager();

        $this->assertNull($config->getApiKey());
        $this->assertNull($config->getApiUser());
        $this->assertNull($config->getApiUrl());
    }

    public function test_is_configured_true_when_both_set(): void
    {
        putenv('DN_API_KEY=key');
        putenv('DN_API_USER=user');

        $config = new ConfigManager();
        $this->assertTrue($config->isConfigured());
    }

    public function test_is_configured_false_when_missing_key(): void
    {
        putenv('DN_API_USER=user');

        $config = new ConfigManager();
        $this->assertFalse($config->isConfigured());
    }

    public function test_is_configured_false_when_missing_user(): void
    {
        putenv('DN_API_KEY=key');

        $config = new ConfigManager();
        $this->assertFalse($config->isConfigured());
    }

    public function test_save_creates_config_file(): void
    {
        $config = new ConfigManager();
        $config->save('saved-key', 'saved-user');

        $path = $this->tempDir . '/.config/dn/config.json';
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('saved-key', $data['api_key']);
        $this->assertSame('saved-user', $data['api_user']);
    }

    public function test_save_with_api_url(): void
    {
        $config = new ConfigManager();
        $config->save('key', 'user', 'https://custom.api.com');

        $path = $this->tempDir . '/.config/dn/config.json';
        $data = json_decode(file_get_contents($path), true);

        $this->assertSame('https://custom.api.com', $data['api_url']);
    }

    public function test_save_sets_restrictive_permissions(): void
    {
        $config = new ConfigManager();
        $config->save('key', 'user');

        $path = $this->tempDir . '/.config/dn/config.json';
        $perms = fileperms($path) & 0777;
        $this->assertSame(0600, $perms);
    }

    public function test_save_resets_cached_config(): void
    {
        putenv('DN_API_KEY');
        putenv('DN_API_USER');

        $config = new ConfigManager();
        $this->assertNull($config->getApiKey());

        $config->save('new-key', 'new-user');

        $this->assertSame('new-key', $config->getApiKey());
        $this->assertSame('new-user', $config->getApiUser());
    }

    public function test_handles_invalid_json_in_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', 'not valid json{{{');

        $config = new ConfigManager();

        $this->assertNull($config->getApiKey());
        $this->assertNull($config->getApiUser());
    }

    public function test_get_config_path(): void
    {
        $config = new ConfigManager();
        $expected = $this->tempDir . '/.config/dn/config.json';

        $this->assertSame($expected, $config->getConfigPath());
    }

    public function test_api_url_from_env(): void
    {
        putenv('DN_API_URL=https://env.api.com');

        $config = new ConfigManager();
        $this->assertSame('https://env.api.com', $config->getApiUrl());
    }

    public function test_api_url_from_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'api_key' => 'key',
            'api_user' => 'user',
            'api_url' => 'https://file.api.com',
        ]));

        $config = new ConfigManager();
        $this->assertSame('https://file.api.com', $config->getApiUrl());
    }

    public function test_empty_env_var_treated_as_null(): void
    {
        putenv('DN_API_KEY=');

        $config = new ConfigManager();
        $this->assertNull($config->getApiKey());
    }

    public function test_save_creates_file_with_restrictive_permissions_before_writing(): void
    {
        // Verify the TOCTOU fix: file should never exist with loose permissions.
        // After save(), the file must have 0600 and contain the correct data.
        $config = new ConfigManager();
        $config->save('secret-key', 'secret-user');

        $path = $config->getConfigPath();

        // Permissions must be 0600
        $perms = fileperms($path) & 0777;
        $this->assertSame(0600, $perms);

        // Content must be correct (written after chmod)
        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('secret-key', $data['api_key']);
        $this->assertSame('secret-user', $data['api_user']);
    }

    public function test_save_overwrites_existing_config(): void
    {
        $config = new ConfigManager();
        $config->save('old-key', 'old-user');
        $config->save('new-key', 'new-user');

        $path = $config->getConfigPath();
        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('new-key', $data['api_key']);

        // Permissions must still be restrictive after overwrite
        $perms = fileperms($path) & 0777;
        $this->assertSame(0600, $perms);
    }

    public function test_default_mode_is_partner(): void
    {
        $config = new ConfigManager();
        $this->assertSame('partner', $config->getMode());
    }

    public function test_mode_from_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'mode' => 'user',
            'oauth_token' => 'test-token',
        ]));

        $config = new ConfigManager();
        $this->assertSame('user', $config->getMode());
    }

    public function test_mode_from_env_var_overrides_config(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'mode' => 'partner',
            'api_key' => 'key',
            'api_user' => 'user',
        ]));

        putenv('DN_MODE=user');

        $config = new ConfigManager();
        $this->assertSame('user', $config->getMode());
    }

    public function test_oauth_token_from_config_file(): void
    {
        $configDir = $this->tempDir . '/.config/dn';
        mkdir($configDir, 0700, true);
        file_put_contents($configDir . '/config.json', json_encode([
            'mode' => 'user',
            'oauth_token' => 'file-token',
        ]));

        $config = new ConfigManager();
        $this->assertSame('file-token', $config->getOAuthToken());
    }

    public function test_oauth_token_from_env_var(): void
    {
        putenv('DN_OAUTH_TOKEN=env-token');

        $config = new ConfigManager();
        $this->assertSame('env-token', $config->getOAuthToken());
    }

    public function test_is_configured_user_mode_with_token(): void
    {
        putenv('DN_MODE=user');
        putenv('DN_OAUTH_TOKEN=my-token');

        $config = new ConfigManager();
        $this->assertTrue($config->isConfigured());
    }

    public function test_is_configured_user_mode_without_token(): void
    {
        putenv('DN_MODE=user');

        $config = new ConfigManager();
        $this->assertFalse($config->isConfigured());
    }

    public function test_save_user_mode_creates_config(): void
    {
        $config = new ConfigManager();
        $config->saveUserMode('my-oauth-token');

        $path = $this->tempDir . '/.config/dn/config.json';
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('user', $data['mode']);
        $this->assertSame('my-oauth-token', $data['oauth_token']);
    }

    public function test_save_user_mode_sets_permissions(): void
    {
        $config = new ConfigManager();
        $config->saveUserMode('token');

        $path = $this->tempDir . '/.config/dn/config.json';
        $perms = fileperms($path) & 0777;
        $this->assertSame(0600, $perms);
    }

    public function test_save_writes_partner_mode(): void
    {
        $config = new ConfigManager();
        $config->save('key', 'user');

        $path = $this->tempDir . '/.config/dn/config.json';
        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('partner', $data['mode']);
    }
}
