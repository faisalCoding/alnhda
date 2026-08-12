<?php

namespace App\Services;

/**
 * Supervises the local Node gateway as a detached background process, so it
 * survives closing the terminal and outlives `composer run dev`. On a real
 * server systemd should own the process; this covers local use and lets an
 * admin restart the gateway from the panel without shell access.
 */
class WhatsappServiceProcess
{
    public function port(): int
    {
        $port = parse_url((string) config('services.whatsapp.url'), PHP_URL_PORT);

        return is_int($port) ? $port : 3000;
    }

    public function directory(): string
    {
        return base_path('whatsapp-service');
    }

    public function logPath(): string
    {
        return $this->directory().'/node.log';
    }

    /**
     * Whether something is serving the gateway port. Deliberately a plain TCP
     * connect rather than a shell probe: minimal servers ship without lsof and
     * shared hosts disable shell_exec, and either would make the panel report a
     * healthy service as down.
     */
    public function isRunning(): bool
    {
        $host = parse_url((string) config('services.whatsapp.url'), PHP_URL_HOST) ?: '127.0.0.1';
        $socket = @fsockopen($host, $this->port(), $errno, $error, 1.0);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * The pid is read back from whoever holds the port rather than a pid file,
     * so the service is found no matter how it was started (artisan, the panel
     * button, systemd, or by hand). Only stopping needs it, and each probe is
     * tried in turn because no single one ships everywhere.
     */
    public function runningPid(): ?int
    {
        $probes = [
            // -a ANDs the filters; without it lsof ORs them and matches every node process.
            'lsof -nP -a -t -c node -i :'.$this->port().' 2>/dev/null',
            'ss -lptnH "sport = :'.$this->port().'" 2>/dev/null | grep -o "pid=[0-9]*" | head -1 | cut -d= -f2',
            'fuser '.$this->port().'/tcp 2>/dev/null',
        ];

        foreach ($probes as $probe) {
            $pid = trim((string) strtok(trim((string) $this->run($probe)), "\n "));

            if (ctype_digit($pid)) {
                return (int) $pid;
            }
        }

        return null;
    }

    /**
     * @return 'started'|'already_running'|'unavailable'
     */
    public function start(): string
    {
        if (! $this->canRunCommands()) {
            return 'unavailable';
        }

        if ($this->isRunning()) {
            return 'already_running';
        }

        if (! function_exists('proc_open')) {
            return 'unavailable';
        }

        // Every descriptor is a file, never a pipe: shell_exec/popen would block
        // until the spawned service exits, because the child keeps the pipe open.
        // proc_close then only waits for the shell, which returns as soon as it
        // has backgrounded node.
        $descriptors = [
            ['file', '/dev/null', 'r'],
            ['file', $this->logPath(), 'a'],
            ['file', $this->logPath(), 'a'],
        ];

        $handle = @proc_open('nohup node index.js >> node.log 2>&1 &', $descriptors, $pipes, $this->directory());

        if (! is_resource($handle)) {
            return 'unavailable';
        }

        proc_close($handle);

        return $this->waitUntilRunning() ? 'started' : 'unavailable';
    }

    /**
     * Node needs a moment to bind the port; report success only once it has.
     */
    private function waitUntilRunning(int $attempts = 20): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if ($this->isRunning()) {
                return true;
            }

            usleep(250_000);
        }

        return false;
    }

    /**
     * @return 'stopped'|'not_running'|'pid_unknown'|'unavailable'
     */
    public function stop(): string
    {
        if (! $this->canRunCommands()) {
            return 'unavailable';
        }

        $pid = $this->runningPid();

        if ($pid === null) {
            // Serving the port but no probe could name the owner: say so rather
            // than claiming it is stopped.
            return $this->isRunning() ? 'pid_unknown' : 'not_running';
        }

        $this->run('kill '.$pid.' 2>/dev/null');

        return 'stopped';
    }

    /**
     * Path of the Chromium puppeteer resolves to, or null when it cannot be
     * resolved (packages not installed, or the browser was never downloaded).
     */
    public function browserPath(): ?string
    {
        $script = escapeshellarg('try{console.log(require("puppeteer").executablePath())}catch(e){}');
        $path = trim((string) $this->run('cd '.escapeshellarg($this->directory()).' && node -e '.$script.' 2>/dev/null'));

        return $path === '' ? null : $path;
    }

    /**
     * Runs the browser binary directly. On a bare Linux server this is what
     * surfaces "error while loading shared libraries", the usual reason the QR
     * never appears there while everything works locally.
     */
    public function browserCheck(): string
    {
        $path = $this->browserPath();

        if ($path === null) {
            return 'المتصفح غير مثبت — شغّل: npx puppeteer browsers install chrome';
        }

        if (! is_file($path)) {
            return 'مسار المتصفح غير موجود: '.$path;
        }

        $output = trim((string) $this->run(escapeshellarg($path).' --version 2>&1'));

        return str_contains($output, 'Chrome') ? 'ok: '.$output : 'فشل تشغيل المتصفح: '.$output;
    }

    public function nodeVersion(): ?string
    {
        $version = trim((string) $this->run('node -v 2>/dev/null'));

        return $version === '' ? null : $version;
    }

    public function isInstalled(): bool
    {
        return is_dir($this->directory().'/node_modules');
    }

    /**
     * Last lines of the service log. Only the tail of the file is read, so the
     * log can grow without the panel paying for it.
     *
     * @return array<int, string>
     */
    public function tailLog(int $lines = 200): array
    {
        $path = $this->logPath();

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $chunk = 64 * 1024;
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $seeked = $size > $chunk;

        if ($seeked) {
            fseek($handle, -$chunk, SEEK_END);
        }

        $content = (string) stream_get_contents($handle);
        fclose($handle);

        $all = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];

        // Seeking mid-file almost certainly lands inside a line; drop that scrap.
        if ($seeked && count($all) > 1) {
            array_shift($all);
        }

        return array_values(array_slice($all, -max(1, $lines)));
    }

    private function canRunCommands(): bool
    {
        return function_exists('shell_exec')
            && ! in_array('shell_exec', $this->disabledFunctions(), true);
    }

    /**
     * @return array<int, string>
     */
    private function disabledFunctions(): array
    {
        return array_map('trim', explode(',', (string) ini_get('disable_functions')));
    }

    private function run(string $command): ?string
    {
        if (! $this->canRunCommands()) {
            return null;
        }

        return shell_exec($command);
    }
}
