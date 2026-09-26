<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Lab;
use App\Models\User;
use App\Policies\LabPolicy;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only active accounts can access the filament panel', function () {
    $panel = Panel::make()->id('admin');
    $activeStudent = User::factory()->create();
    $disabledAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'status' => UserStatus::Disabled,
    ]);

    expect($activeStudent->canAccessPanel($panel))->toBeTrue()
        ->and($disabledAdmin->canAccessPanel($panel))->toBeFalse();
});

test('admin can manage every lab while student can only control their own lab', function () {
    $policy = new LabPolicy;
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create();
    $otherStudent = User::factory()->create();
    $lab = Lab::factory()->for($owner, 'owner')->create();

    expect($policy->view($admin, $lab))->toBeTrue()
        ->and($policy->provision($admin, $owner))->toBeTrue()
        ->and($policy->start($admin, $lab))->toBeTrue()
        ->and($policy->delete($admin, $lab))->toBeTrue()
        ->and($policy->view($owner, $lab))->toBeTrue()
        ->and($policy->start($owner, $lab))->toBeTrue()
        ->and($policy->stop($owner, $lab))->toBeTrue()
        ->and($policy->reset($owner, $lab))->toBeTrue()
        ->and($policy->delete($owner, $lab))->toBeFalse()
        ->and($policy->view($otherStudent, $lab))->toBeFalse()
        ->and($policy->start($otherStudent, $lab))->toBeFalse()
        ->and($policy->reset($otherStudent, $lab))->toBeFalse();
});

test('disabled users cannot control a lab even when they own it', function () {
    $policy = new LabPolicy;
    $student = User::factory()->create(['status' => UserStatus::Disabled]);
    $lab = Lab::factory()->for($student, 'owner')->create();

    expect($policy->view($student, $lab))->toBeFalse()
        ->and($policy->start($student, $lab))->toBeFalse()
        ->and($policy->stop($student, $lab))->toBeFalse()
        ->and($policy->reset($student, $lab))->toBeFalse();
});
