<?php

namespace Tests\Feature\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyLabs;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\LabFirewall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MyLabsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $this->course = Course::query()->firstOrCreate(
            ['slug' => 'imnehnet-web-server-breach'],
            [
                'title' => 'IMNEHNET Web Server Breach',
                'difficulty' => 'Beginner',
            ]
        );

        $this->actingAs($this->student);
    }

    private function createLabSession(
        LabSessionStatus $status = LabSessionStatus::Ready,
        ?User $owner = null
    ): LabSession {
        return LabSession::query()->create([
            'user_id' => ($owner ?? $this->student)->id,
            'course_id' => $this->course->id,
            'attacker_container' => 'attacker-lifecycle-test',
            'attacker_remote' => 'lab-atk',
            'victim_container' => 'victim-lifecycle-test',
            'victim_remote' => 'lab-env',
            'attacker_ip' => '10.30.0.250',
            'victim_ip' => '10.20.0.250',
            'status' => $status,
            'current_objective' => 1,
        ]);
    }

    public function test_stop_changes_status_without_deleting_session(): void
    {
        $session = $this->createLabSession();

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldReceive('revoke')->once();
        $this->app->instance(LabFirewall::class, $firewall);

        Livewire::test(MyLabs::class)
            ->call('requestStop')
            ->assertSet('showStopConfirmation', true)
            ->call('stopLab')
            ->assertSet('showStopConfirmation', false);

        $this->assertSame(
            LabSessionStatus::Stopped,
            $session->refresh()->status
        );

        $this->assertDatabaseHas('lab_sessions', [
            'id' => $session->id,
            'status' => 'STOPPED',
        ], config('infrastructure.environment_connection'));
    }

    public function test_resume_changes_stopped_session_to_ready(): void
    {
        $session = $this->createLabSession(LabSessionStatus::Stopped);

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldReceive('permit')->once();
        $this->app->instance(LabFirewall::class, $firewall);

        Livewire::test(MyLabs::class)
            ->call('resumeLab');

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->refresh()->status
        );
    }

    public function test_student_cannot_stop_another_students_lab(): void
    {
        $other = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $session = $this->createLabSession(owner: $other);

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldNotReceive('revoke');
        $this->app->instance(LabFirewall::class, $firewall);

        Livewire::test(MyLabs::class)
            ->call('requestStop')
            ->assertSet('showStopConfirmation', false)
            ->call('stopLab');

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->refresh()->status
        );
    }

    public function test_stopped_lab_cannot_open_workspace(): void
    {
        $this->createLabSession(LabSessionStatus::Stopped);

        Livewire::test(MyLabs::class)
            ->call('openWorkspace')
            ->assertNoRedirect();
    }
}
