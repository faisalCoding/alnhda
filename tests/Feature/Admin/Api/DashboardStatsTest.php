<?php

use App\Models\Admin;
use App\Models\Article;
use App\Models\Project;
use App\Models\Properties;
use App\Models\Visitor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('rejects guests from the stats api', function () {
    $this->getJson(panelUrl('/api/dashboard/stats'))->assertUnauthorized();
});

it('returns counts and latest items', function () {
    $admin = Admin::factory()->create();
    Project::factory()->count(2)->create();
    Properties::factory()->create();
    Article::factory()->count(7)->create();
    Visitor::factory()->count(3)->create();

    $this->actingAs($admin, 'admin')
        ->getJson(panelUrl('/api/dashboard/stats'))
        ->assertSuccessful()
        ->assertJsonPath('data.counts.projects', 3)
        ->assertJsonPath('data.counts.properties', 1)
        ->assertJsonPath('data.counts.articles', 7)
        ->assertJsonPath('data.counts.visitors', 3)
        ->assertJsonCount(5, 'data.latest.articles')
        ->assertJsonCount(1, 'data.latest.properties');
});
