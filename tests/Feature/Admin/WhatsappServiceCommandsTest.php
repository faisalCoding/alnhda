<?php

use App\Services\WhatsappServiceProcess;

it('starts the gateway in the background', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('start')->once()->andReturn('started');
        $mock->shouldReceive('port')->andReturn(3000);
        $mock->shouldReceive('logPath')->andReturn('/tmp/node.log');
    });

    $this->artisan('whatsapp:start')
        ->expectsOutputToContain('تم تشغيل الخدمة في الخلفية')
        ->assertSuccessful();
});

it('does not start a second copy when the port is already served', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('start')->once()->andReturn('already_running');
        $mock->shouldReceive('port')->andReturn(3000);
        $mock->shouldReceive('logPath')->andReturn('/tmp/node.log');
    });

    $this->artisan('whatsapp:start')
        ->expectsOutputToContain('تعمل مسبقًا')
        ->assertSuccessful();
});

it('refuses to start before the node packages are installed', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(false);
        $mock->shouldNotReceive('start');
    });

    $this->artisan('whatsapp:start')
        ->expectsOutputToContain('npm install --prefix whatsapp-service')
        ->assertFailed();
});

it('fails when the host forbids spawning processes', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('start')->once()->andReturn('unavailable');
    });

    $this->artisan('whatsapp:start')->assertFailed();
});

it('stops the gateway', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('stop')->once()->andReturn('stopped');

    $this->artisan('whatsapp:stop')
        ->expectsOutputToContain('تم إيقاف الخدمة')
        ->assertSuccessful();
});

it('reports an already stopped gateway without failing', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('stop')->once()->andReturn('not_running');

    $this->artisan('whatsapp:stop')->assertSuccessful();
});

it('reports a running gateway with its pid', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('runningPid')->once()->andReturn(4242);
        $mock->shouldReceive('port')->andReturn(3000);
        $mock->shouldReceive('logPath')->andReturn('/tmp/node.log');
    });

    $this->artisan('whatsapp:status')
        ->expectsOutputToContain('4242')
        ->assertSuccessful();
});

it('says so when the service is up but its pid cannot be resolved', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('stop')->once()->andReturn('pid_unknown');

    $this->artisan('whatsapp:stop')
        ->expectsOutputToContain('تعذر تحديد رقم عمليتها')
        ->assertFailed();
});

it('detects a running service without shelling out', function () {
    // fsockopen against a socket we control proves isRunning() needs no lsof.
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $port = (int) explode(':', stream_socket_get_name($server, false))[1];
    config()->set('services.whatsapp.url', 'http://127.0.0.1:'.$port);

    expect(app(WhatsappServiceProcess::class)->isRunning())->toBeTrue();

    fclose($server);

    expect(app(WhatsappServiceProcess::class)->isRunning())->toBeFalse();
});

it('reports a healthy setup', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('nodeVersion')->andReturn('v22.0.0');
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('browserCheck')->andReturn('ok: Chrome for Testing 146');
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('tailLog')->andReturn([]);
    });

    config()->set('services.whatsapp.key', 'a-key');

    // The service is not listening, so one check fails and the command exits non-zero.
    $this->artisan('whatsapp:doctor')
        ->expectsOutputToContain('whatsapp:start')
        ->assertFailed();
});

it('points at the missing chromium libraries when the browser will not run', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('nodeVersion')->andReturn('v22.0.0');
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('browserCheck')
            ->andReturn('فشل تشغيل المتصفح: error while loading shared libraries: libnss3.so');
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('tailLog')->andReturn([]);
    });

    $this->artisan('whatsapp:doctor')
        ->expectsOutputToContain('libnss3')
        ->expectsOutputToContain('google-chrome-stable_current_amd64.deb')
        ->expectsOutputToContain('whatsapp:start')
        ->assertFailed();
});

it('does not print the browser fix when the browser is fine', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('nodeVersion')->andReturn('v22.0.0');
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('browserCheck')->andReturn('ok: Chrome for Testing 146');
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('tailLog')->andReturn([]);
    });

    $this->artisan('whatsapp:doctor')
        ->doesntExpectOutputToContain('google-chrome-stable_current_amd64.deb')
        ->assertFailed();
});

it('exits non-zero from status when the gateway is down', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('runningPid')->once()->andReturn(null);

    $this->artisan('whatsapp:status')
        ->expectsOutputToContain('whatsapp:start')
        ->assertFailed();
});
