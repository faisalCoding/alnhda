<?php

use App\Services\WhatsappServiceProcess;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

it('restarts the gateway so a deployed change takes effect', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('restart')->once()->andReturn('started');
        $mock->shouldReceive('port')->andReturn(3000);
    });

    $this->artisan('whatsapp:restart')
        ->expectsOutputToContain('أُعيد تشغيل الخدمة')
        ->assertSuccessful();
});

it('reports when the old gateway could not be stopped', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('restart')->once()->andReturn('stop_failed');
    });

    $this->artisan('whatsapp:restart')
        ->expectsOutputToContain('تعذر إيقاف الخدمة القديمة')
        ->assertFailed();
});

it('tells the operator to restart when the running service predates /health', function () {
    $this->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('nodeVersion')->andReturn('v18.19.1');
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('browserCheck')->andReturn('ok: Chrome 146');
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('tailLog')->andReturn([]);
    });

    Illuminate\Support\Facades\Http::fake(['*/health' => Illuminate\Support\Facades\Http::response('Not Found', 404)]);

    $this->artisan('whatsapp:doctor')
        ->expectsOutputToContain('whatsapp:restart')
        ->assertFailed();
});

/**
 * @param  array<string, mixed>  $health
 */
function doctorWithGateway(array $health, array $acks): void
{
    test()->mock(WhatsappServiceProcess::class, function ($mock) {
        $mock->shouldReceive('nodeVersion')->andReturn('v22.0.0');
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('browserCheck')->andReturn('ok: Chrome 146');
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('tailLog')->andReturn([]);
    });

    Illuminate\Support\Facades\Http::fake([
        '*/health' => Illuminate\Support\Facades\Http::response($health + ['contract' => 2]),
        '*/acks' => Illuminate\Support\Facades\Http::response(['acks' => $acks]),
    ]);
}

it('points at the sync command when the gateway holds the missing acknowledgements', function () {
    $recipient = App\Models\WhatsappMessageRecipient::factory()->sent()->create();

    doctorWithGateway(['acks_tracked' => 3], [$recipient->provider_message_id => 2]);

    $this->artisan('whatsapp:doctor')->expectsOutputToContain('whatsapp:sync-acks');
})->group('doctor');

it('flags an id mismatch when the gateway tracked acknowledgements it cannot match', function () {
    App\Models\WhatsappMessageRecipient::factory()->sent()->create();

    // The gateway saw acknowledgements, but under ids Laravel never stored —
    // that is a bug in the pairing, not a configuration problem.
    doctorWithGateway(['acks_tracked' => 7], []);

    $this->artisan('whatsapp:doctor')->expectsOutputToContain('لا تطابق المخزّنة');
})->group('doctor');

it('blames a restart when the gateway never saw any acknowledgement', function () {
    App\Models\WhatsappMessageRecipient::factory()->sent()->create();

    doctorWithGateway(['acks_tracked' => 0], []);

    $this->artisan('whatsapp:doctor')->expectsOutputToContain('whatsapp:restart');
})->group('doctor');

it('detects a gateway too old to return message ids', function () {
    doctorWithGateway(['contract' => 1, 'acks_tracked' => 0], []);

    $this->artisan('whatsapp:doctor')
        ->expectsOutputToContain('لا تُرجع معرّفات الرسائل')
        ->assertFailed();
})->group('doctor');

it('accepts a gateway on the current contract', function () {
    doctorWithGateway(['contract' => 2, 'acks_tracked' => 0], []);

    $this->artisan('whatsapp:doctor')->doesntExpectOutputToContain('لا تُرجع معرّفات الرسائل');
})->group('doctor');

it('surfaces rows that were sent without a message id', function () {
    // These predate the gateway returning ids: nothing can ever confirm them,
    // and the earlier check skipped them entirely, reporting "nothing waiting"
    // while the panel showed messages stuck on "sent".
    App\Models\WhatsappMessageRecipient::factory()->create([
        'status' => App\Models\WhatsappMessageRecipient::STATUS_SENT,
        'provider_message_id' => null,
        'sent_at' => now(),
    ]);

    doctorWithGateway(['acks_tracked' => 0], []);

    $this->artisan('whatsapp:doctor')
        ->expectsOutputToContain('1 رسالة أُرسلت دون تسجيل معرّفها')
        ->assertFailed();
})->group('doctor');

it('warns when no callback url is configured', function () {
    config()->set('services.whatsapp.callback_url', '');
    doctorWithGateway(['acks_tracked' => 0], []);

    $this->artisan('whatsapp:doctor')->expectsOutputToContain('WHATSAPP_CALLBACK_URL');
})->group('doctor');

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
