<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('shows the dashboard with a nav menu listing the admin pages', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Failed jobs')
        ->assertSee(route('failed-jobs.index'))
        ->assertSee('Group settings')
        ->assertSee(route('yaarpool.group-settings.index'))
        ->assertSee('User settings')
        ->assertSee(route('yaarpool.user-settings.index'));
});

it('shows the failed and queued job counts', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Review and retry from the failed jobs page.');
});

it('redirects guests to the login screen', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});
