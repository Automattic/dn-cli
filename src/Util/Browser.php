<?php

declare(strict_types=1);

namespace DnCli\Util;

class Browser
{
    public static function open(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => 'open',
            'Windows' => 'start',
            default => 'xdg-open',
        };

        exec(sprintf('%s %s > /dev/null 2>&1 &', $command, escapeshellarg($url)));
    }
}
