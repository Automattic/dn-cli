<?php

declare(strict_types=1);

namespace DnCli\Service;

class Spinner
{
    private const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    private const INTERVAL_US = 160000;

    /**
     * Run $task while showing an animated spinner. Returns the task's return value.
     * Falls back to a plain call (no spinner) when pcntl is unavailable or stdout
     * is not a TTY, so non-interactive and test environments are unaffected.
     *
     * @template T
     * @param callable(): T $task
     * @return T
     */
    public function spin(string $message, callable $task): mixed
    {
        if (!$this->canFork()) {
            return $task();
        }

        fwrite(STDOUT, "\033[?25l"); // hide cursor

        $pid = pcntl_fork();
        if ($pid === -1) {
            fwrite(STDOUT, "\033[?25h");
            return $task();
        }

        if ($pid === 0) {
            // Child: animate until killed by parent
            $i = 0;
            $frames = self::FRAMES;
            $count = count($frames);
            while (true) {
                fwrite(STDOUT, "\r  " . $frames[$i % $count] . "  " . $message);
                usleep(self::INTERVAL_US);
                $i++;
            }
            exit(0); // unreachable
        }

        // Parent: run the task, then kill the spinner child
        try {
            $result = $task();
        } finally {
            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            fwrite(STDOUT, "\r" . str_repeat(' ', strlen($message) + 6) . "\r");
            fwrite(STDOUT, "\033[?25h"); // restore cursor
        }

        return $result;
    }

    private function canFork(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('posix_kill')
            && stream_isatty(STDOUT);
    }
}
