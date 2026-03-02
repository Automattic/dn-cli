<?php

declare(strict_types=1);

namespace DnCli\Tests\Factory;

use DnCli\Api\WPcomClient;
use DnCli\Config\ConfigManager;
use DnCli\Factory\WPcomClientFactory;
use PHPUnit\Framework\TestCase;

class WPcomClientFactoryTest extends TestCase
{
    private string $savedHome = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedHome = getenv('HOME') ?: '';
        putenv('HOME=' . sys_get_temp_dir() . '/dn-cli-wpcom-factory-test-' . uniqid());
        putenv('DN_OAUTH_TOKEN');
        putenv('DN_MODE');
    }

    protected function tearDown(): void
    {
        putenv('DN_OAUTH_TOKEN');
        putenv('DN_MODE');
        if ($this->savedHome !== '') {
            putenv('HOME=' . $this->savedHome);
        } else {
            putenv('HOME');
        }
        parent::tearDown();
    }

    public function test_creates_client_with_token(): void
    {
        putenv('DN_OAUTH_TOKEN=my-token');

        $config = new ConfigManager();
        $client = WPcomClientFactory::create($config);

        $this->assertInstanceOf(WPcomClient::class, $client);
    }

    public function test_throws_when_token_missing(): void
    {
        $config = new ConfigManager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth token not configured');

        WPcomClientFactory::create($config);
    }
}
