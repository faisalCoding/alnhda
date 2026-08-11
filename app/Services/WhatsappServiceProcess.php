<?php

namespace App\Services;

/**
 * Starts the local Node service from the panel, so an admin can bring the
 * gateway back up without shell access. On a real server systemd should own the
 * process; this only fills the gap when nothing is listening yet.
 */
class WhatsappServiceProcess
{
    public function port(): int
    {
        $port = parse_url((string) config('services.whatsapp.url'), PHP_URL_PORT);

        return is_int($port) ? $port : 3000;
    }

    public function isRunning(): bool
    {
        $output = $this->run('lsof -nP -i :'.$this->port().' 2>/dev/null');

        return $output !== null && str_contains($output, 'node');
    }

    /**
     * @return 'started'|'already_running'|'unavailable'
     */
    public function start(): string
    {
        if (! function_exists('popen') || ! function_exists('shell_exec')) {
            return 'unavailable';
        }

        if ($this->isRunning()) {
            return 'already_running';
        }

        $directory = escapeshellarg(base_path('whatsapp-service'));

        // pclose(popen(…)) returns immediately instead of blocking on the server.
        $handle = popen("cd {$directory} && nohup node index.js > node.log 2>&1 &", 'r');

        if ($handle === false) {
            return 'unavailable';
        }

        pclose($handle);

        return 'started';
    }

    private function run(string $command): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        return shell_exec($command);
    }
}
