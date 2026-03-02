<?php

declare(strict_types=1);

namespace DnCli\Factory;

use DnCli\Api\WPcomClient;
use DnCli\Config\ConfigManager;

class WPcomClientFactory
{
    public static function create(ConfigManager $config): WPcomClient
    {
        $token = $config->getOAuthToken();
        if ($token === null) {
            throw new \RuntimeException('OAuth token not configured. Run `dn configure` in user mode first.');
        }

        return new WPcomClient($token);
    }
}
