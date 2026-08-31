<?php

use App\Livewire\ContactWizard;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sits on the home page behind the hero button', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSeeLivewire(ContactWizard::class)
        ->assertSee('open-contact-wizard', false)
        ->assertSee('التواصل معنا بصفتك', false);
});

it('still leads somewhere with scripting off', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('href="'.route('contact-us').'"', false);
});

it('offers the three ways a visitor can introduce themselves', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('عميل أبحث عن مسكن', false)
        ->assertSee('مسوّق عقاري', false)
        ->assertSee('أبحث عن فرصة شراكة', false);
});

it('offers a buyer the three kinds of home', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('ما الذي تبحث عنه؟', false)
        ->assertSee('فلل', false)
        ->assertSee('أدوار', false)
        ->assertSee('شقق', false);
});

// ---- what gets saved -----------------------------------------------------

it('records a buyer under the kind of home they asked for', function (string $type, string $formName) {
    Livewire::test(ContactWizard::class)
        ->set('role', 'client')
        ->set('unitType', $type)
        ->set('first_name', 'فيصل')
        ->set('last_name', 'باخشب')
        ->set('phone', '0564364261')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('contact-wizard-saved');

    expect(Visitor::query()->first())
        ->form_name->toBe($formName)
        ->first_name->toBe('فيصل')
        ->phone->toBe('0564364261');
})->with([
    'villa' => ['villa', 'hero_client_villa'],
    'floor' => ['floor', 'hero_client_floor'],
    'apartment' => ['apartment', 'hero_client_apartment'],
]);

it('records an agent and a partnership without asking what they want to buy', function (string $role) {
    Livewire::test(ContactWizard::class)
        ->set('role', $role)
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', '+966 50 123 4567')
        ->call('save')
        ->assertHasNoErrors();

    expect(Visitor::query()->first()->form_name)->toBe('hero_'.$role);
})->with(['marketer', 'partnership']);

it('keeps an optional email when it is given, and nothing when it is not', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', '0501234567')
        ->set('email', 'sara@example.com')
        ->call('save');

    expect(Visitor::query()->first()->email)->toBe('sara@example.com');

    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'خالد')
        ->set('last_name', 'العتيبي')
        ->set('phone', '0501234567')
        ->call('save');

    expect(Visitor::query()->latest('id')->first()->email)->toBeNull();
});

it('clears the form after sending so a second enquiry starts empty', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', '0501234567')
        ->call('save')
        ->assertSet('first_name', '')
        ->assertSet('phone', '')
        ->assertSet('role', '');
});

// ---- what gets refused ---------------------------------------------------

it('asks a buyer what they are looking for before it accepts the enquiry', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'client')
        ->set('first_name', 'فيصل')
        ->set('last_name', 'باخشب')
        ->set('phone', '0564364261')
        ->call('save')
        ->assertHasErrors(['unitType' => 'required']);

    expect(Visitor::query()->count())->toBe(0);
});

it('does not ask an agent what they are looking for', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', '0501234567')
        ->call('save')
        ->assertHasNoErrors('unitType');
});

it('refuses an enquiry with no name or no number', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->call('save')
        ->assertHasErrors(['first_name', 'last_name', 'phone']);

    expect(Visitor::query()->count())->toBe(0);
});

it('refuses a number that could not be dialled', function (string $phone) {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', $phone)
        ->call('save')
        ->assertHasErrors('phone');
})->with([
    'too short' => '05123',
    'letters' => 'اتصل بي',
    'html' => '<script>0501234567</script>',
]);

it('accepts a number however it was typed', function (string $phone) {
    Livewire::test(ContactWizard::class)
        ->set('role', 'marketer')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', $phone)
        ->call('save')
        ->assertHasNoErrors('phone');
})->with([
    'local' => '0564364261',
    'spaced' => '056 436 4261',
    'international' => '+966564364261',
    'dashed' => '056-436-4261',
]);

it('refuses a made-up role', function () {
    Livewire::test(ContactWizard::class)
        ->set('role', 'investor')
        ->set('first_name', 'سارة')
        ->set('last_name', 'الأحمدي')
        ->set('phone', '0501234567')
        ->call('save')
        ->assertHasErrors('role');
});
