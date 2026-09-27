<?php

use App\Models\Admin;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;

function lawhatiCodeUrl(Employee $employee): string
{
    return panelUrl('/api/employees/'.$employee->id.'/lawhati-code');
}

function issueLawhatiCode(Admin $admin, Employee $employee): string
{
    $code = test()->actingAs($admin, 'admin')
        ->postJson(lawhatiCodeUrl($employee))
        ->assertCreated()
        ->json('data.code');

    // The next request should carry only the code, not the admin's session.
    app('auth')->forgetGuards();

    return $code;
}

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

// ---- the app's side --------------------------------------------------------

test('the lawhati api rejects anyone without a code', function () {
    $this->getJson('/api/lawhati/me')->assertUnauthorized();
    $this->getJson('/api/lawhati/team')->assertUnauthorized();
});

test('a task token cannot read the team through lawhati', function () {
    Sanctum::actingAs(Employee::factory()->create(), ['tasks:self']);

    $this->getJson('/api/lawhati/team')->assertForbidden();
});

test('a code names the employee it was issued for', function () {
    $employee = Employee::factory()->create(['name' => 'سارة', 'role' => 'مسوّقة']);
    Sanctum::actingAs($employee, [Employee::LAWHATI_TOKEN]);

    $this->getJson('/api/lawhati/me')
        ->assertOk()
        ->assertJsonPath('data.id', $employee->id)
        ->assertJsonPath('data.name', 'سارة')
        ->assertJsonPath('data.role', 'مسوّقة')
        ->assertJsonMissingPath('data.phone');
});

test('the team lists everyone in order and marks who left', function () {
    $second = Employee::factory()->create(['name' => 'ب', 'sort_order' => 2]);
    $first = Employee::factory()->create(['name' => 'أ', 'sort_order' => 1]);
    $gone = Employee::factory()->inactive()->create(['name' => 'ج', 'sort_order' => 3]);
    Sanctum::actingAs($first, [Employee::LAWHATI_TOKEN]);

    $team = $this->getJson('/api/lawhati/team')->assertOk()->json('data');

    expect(array_column($team, 'id'))->toBe([$first->id, $second->id, $gone->id])
        ->and(array_column($team, 'is_active'))->toBe([true, true, false])
        ->and(array_keys($team[0]))->toBe(['id', 'name', 'role', 'is_active']);
});

// ---- issuing codes from the panel -------------------------------------------

test('guests cannot issue or revoke codes', function () {
    $employee = Employee::factory()->create();

    $this->postJson(lawhatiCodeUrl($employee))->assertUnauthorized();
    $this->deleteJson(lawhatiCodeUrl($employee))->assertUnauthorized();
});

test('an issued code signs the employee in to lawhati', function () {
    $employee = Employee::factory()->create();

    $code = issueLawhatiCode($this->admin, $employee);

    $this->withToken($code)->getJson('/api/lawhati/me')->assertOk()->assertJsonPath('data.id', $employee->id);
});

test('a code cannot reach the task api', function () {
    $code = issueLawhatiCode($this->admin, Employee::factory()->create());

    $this->withToken($code)->getJson('/api/me/tasks')->assertForbidden();
});

test('a new code retires the previous one and leaves other tokens alone', function () {
    $employee = Employee::factory()->create();
    $personalSite = $employee->createToken('personal-site', ['tasks:self'])->plainTextToken;

    $old = issueLawhatiCode($this->admin, $employee);
    $new = issueLawhatiCode($this->admin, $employee);

    $this->withToken($old)->getJson('/api/lawhati/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($new)->getJson('/api/lawhati/me')->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($personalSite)->getJson('/api/me')->assertOk();

    expect($employee->tokens()->count())->toBe(2);
});

test('revoking a code signs the employee out everywhere', function () {
    $employee = Employee::factory()->create();
    $code = issueLawhatiCode($this->admin, $employee);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(lawhatiCodeUrl($employee))
        ->assertOk()
        ->assertJsonPath('data.revoked', 1);
    app('auth')->forgetGuards();

    $this->withToken($code)->getJson('/api/lawhati/me')->assertUnauthorized();
});

test('the code comes with a sign-in link and a whatsapp message to the employee', function () {
    $employee = Employee::factory()->create(['phone' => '055 123 4567']);

    $data = $this->actingAs($this->admin, 'admin')->postJson(lawhatiCodeUrl($employee))->assertCreated()->json('data');

    expect($data['link'])->toBe('https://panel.localhost/lawhati/sign-in?code='.rawurlencode($data['code']))
        ->and($data['whatsapp_url'])->toStartWith('https://wa.me/966551234567?text=')
        ->and(urldecode($data['whatsapp_url']))->toContain($data['link']);
});

test('an employee without a phone gets no whatsapp link', function () {
    $employee = Employee::factory()->create(['phone' => null]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(lawhatiCodeUrl($employee))
        ->assertCreated()
        ->assertJsonPath('data.whatsapp_url', null);
});

test('the employees list tells who has a code', function () {
    $linked = Employee::factory()->create();
    $other = Employee::factory()->create();
    $other->createToken('personal-site', ['tasks:self']);
    issueLawhatiCode($this->admin, $linked);

    $employees = collect($this->actingAs($this->admin, 'admin')->getJson(panelUrl('/api/employees'))->assertOk()->json('data'))
        ->keyBy('id');

    expect($employees[$linked->id]['has_lawhati_code'])->toBeTrue()
        ->and($employees[$other->id]['has_lawhati_code'])->toBeFalse();
});
