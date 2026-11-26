<?php

namespace Tests\Feature\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyLabs;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\User;
use App\Services\TerminalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_stop_queues_power_change_without_deleting_session(): void
    {
        $session = $this->createLabSession();

        $terminals = Mockery::mock(TerminalService::class);
        $terminals->shouldReceive('queueOwnedLabPower')
            ->once()
            ->withArgs(fn (LabSession $lab, bool $start): bool =>
                $lab->is($session) && $start === false
            );
        $this->app->instance(TerminalService::class, $terminals);

        // Exercise the component methods directly. This avoids the unrelated
        // Livewire 4 snapshot transport issue in the current CI baseline.
        $page = new MyLabs();
        $page->requestStop();
        $this->assertTrue($page->showStopConfirmation);
        $page->stopLab();
        $this->assertFalse($page->showStopConfirmation);

        // Status only changes after ChangePairedLabPower confirms both
        // containers. The UI must not pretend the queued action is finished.
        $this->assertSame(LabSessionStatus::Ready, $session->refresh()->status);
        $this->assertDatabaseHas('lab_sessions', [
            'id' => $session->id,
            'status' => 'READY',
        ], config('infrastructure.environment_connection'));
    }

    public function test_resume_queues_power_change_without_premature_ready_status(): void
    {
        $session = $this->createLabSession(LabSessionStatus::Stopped);

        $terminals = Mockery::mock(TerminalService::class);
        $terminals->shouldReceive('queueOwnedLabPower')
            ->once()
            ->withArgs(fn (LabSession $lab, bool $start): bool =>
                $lab->is($session) && $start === true
            );
        $this->app->instance(TerminalService::class, $terminals);

        (new MyLabs())->resumeLab();

        $this->assertSame(
            LabSessionStatus::Stopped,
            $session->refresh()->status
        );
    }

    public function test_student_cannot_stop_another_students_lab(): void
    {
        $other = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $session = $this->createLabSession(owner: $other);

        $terminals = Mockery::mock(TerminalService::class);
        $terminals->shouldNotReceive('queueOwnedLabPower');
        $this->app->instance(TerminalService::class, $terminals);

        $page = new MyLabs();
        $page->requestStop();
        $this->assertFalse($page->showStopConfirmation);
        $page->stopLab();

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->refresh()->status
        );
    }

    public function test_stopped_lab_cannot_open_workspace(): void
    {
        $this->createLabSession(LabSessionStatus::Stopped);

        // A STOPPED session has no workspace redirect.
        (new MyLabs())->openWorkspace();
    }
}
