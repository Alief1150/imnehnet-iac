<?php

namespace Tests\Feature\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Enums\ObjectiveStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyLabs;
use App\Jobs\ResetLabSession;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\User;
use App\Services\GuidedCtf\LabFirewall;
use App\Services\GuidedCtf\LabScenario;
use Database\Seeders\GuidedCtfSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class MyLabsResetTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GuidedCtfSeeder::class);

        $this->course = Course::webServerBreach();

        $this->assertNotNull($this->course);
        $this->assertSame(6, $this->course->objectives()->count());

        $this->student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $this->actingAs($this->student);
    }

    private function createLabSession(
        LabSessionStatus $status = LabSessionStatus::Ready
    ): LabSession {
        return LabSession::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'attacker_container' => 'attacker-reset-test',
            'attacker_remote' => 'lab-atk',
            'victim_container' => 'victim-reset-test',
            'victim_remote' => 'lab-env',
            'attacker_ip' => '10.30.0.250',
            'victim_ip' => '10.20.0.250',
            'status' => $status,
            'current_objective' => 4,
        ]);
    }

    public function test_confirm_reset_queues_job_without_running_it(): void
    {
        Queue::fake();

        $session = $this->createLabSession();

        Livewire::test(MyLabs::class)
            ->call('requestReset')
            ->assertSet('showResetConfirmation', true)
            ->call('confirmReset')
            ->assertSet('showResetConfirmation', false);

        $this->assertSame(
            LabSessionStatus::Resetting,
            $session->refresh()->status
        );

        Queue::assertPushed(
            ResetLabSession::class,
            fn (ResetLabSession $job) =>
                $job->sessionId === $session->id
        );
    }

    public function test_reset_job_clears_all_six_objectives(): void
    {
        $session = $this->createLabSession(
            LabSessionStatus::Resetting
        );

        $objectives = $this->course->objectives()
            ->orderBy('sequence')
            ->get();

        foreach ($objectives as $objective) {
            $session->progress()->create([
                'objective_id' => $objective->id,
                'status' => ObjectiveStatus::Completed,
                'completed_at' => now(),
                'evidence' => ['test' => 'old-evidence'],
            ]);
        }

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldReceive('permits')
            ->once()
            ->andReturn(true);

        $scenario = Mockery::mock(LabScenario::class);
        $scenario->shouldReceive('apply')->once();

        (new ResetLabSession($session->id))
            ->handle($scenario, $firewall);

        $session->refresh();

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->status
        );

        $this->assertSame(1, $session->current_objective);
        $this->assertNull($session->completed_at);
        $this->assertNull($session->last_error);

        $progress = $session->progress()
            ->get()
            ->keyBy('objective_id');

        $this->assertCount(6, $progress);

        foreach ($objectives as $objective) {
            $item = $progress->get($objective->id);

            $this->assertNotNull($item);

            $this->assertSame(
                $objective->sequence === 1
                    ? ObjectiveStatus::Active
                    : ObjectiveStatus::Locked,
                $item->status
            );

            $this->assertNull($item->completed_at);
            $this->assertNull($item->evidence);
        }

        $this->assertSame(
            0,
            $progress->filter(
                fn ($item) =>
                    $item->status === ObjectiveStatus::Completed
            )->count()
        );
    }

    public function test_reset_job_does_not_touch_deleting_lab(): void
    {
        $session = $this->createLabSession(
            LabSessionStatus::Deleting
        );

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldNotReceive('permits');

        $scenario = Mockery::mock(LabScenario::class);
        $scenario->shouldNotReceive('apply');

        (new ResetLabSession($session->id))
            ->handle($scenario, $firewall);

        $this->assertSame(
            LabSessionStatus::Deleting,
            $session->refresh()->status
        );
    }

    public function test_reset_job_fails_closed_when_progress_is_incomplete(): void
    {
        $session = $this->createLabSession(LabSessionStatus::Resetting);

        $objectives = $this->course->objectives()
            ->orderBy('sequence')
            ->get();

        foreach ($objectives->take(5) as $objective) {
            $session->progress()->create([
                'objective_id' => $objective->id,
                'status' => ObjectiveStatus::Completed,
            ]);
        }

        $firewall = Mockery::mock(LabFirewall::class);
        $firewall->shouldReceive('permits')->once()->andReturn(true);

        $scenario = Mockery::mock(LabScenario::class);
        $scenario->shouldReceive('apply')->once();

        (new ResetLabSession($session->id))->handle($scenario, $firewall);

        $session->refresh();

        $this->assertSame(LabSessionStatus::Failed, $session->status);
        $this->assertNotNull($session->last_error);
    }
}
