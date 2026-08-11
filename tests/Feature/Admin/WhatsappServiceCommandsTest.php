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

it('exits non-zero from status when the gateway is down', function () {
    $this->mock(WhatsappServiceProcess::class)->shouldReceive('runningPid')->once()->andReturn(null);

    $this->artisan('whatsapp:status')
        ->expectsOutputToContain('whatsapp:start')
        ->assertFailed();
});
