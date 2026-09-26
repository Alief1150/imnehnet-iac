<?php

use App\Enums\UserRole;
use App\Filament\Pages\MyLab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('my lab page is available to active students and hidden from admins', function () {
    $student = User::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($student);
    expect(MyLab::canAccess())->toBeTrue();

    $this->actingAs($admin);
    expect(MyLab::canAccess())->toBeFalse();
});

test('student without a lab sees an explicit empty state', function () {
    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(MyLab::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('No Lab Environment has been provisioned yet');
});
