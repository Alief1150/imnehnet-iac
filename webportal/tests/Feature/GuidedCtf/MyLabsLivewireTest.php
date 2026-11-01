<?php

namespace Tests\Feature\GuidedCtf;

use App\Enums\LabSessionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MyLabs;
use App\Jobs\DeleteLabSession;
use App\Models\Course;
use App\Models\LabSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MyLabsLivewireTest extends TestCase
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
        Queue::fake();
    }

    private function createLabSession(
        ?User $owner = null,
        LabSessionStatus $status = LabSessionStatus::Ready
    ): LabSession {
        return LabSession::query()->create([
            'user_id' => ($owner ?? $this->student)->id,
            'course_id' => $this->course->id,
            'attacker_container' => 'attacker-test-' . uniqid(),
            'attacker_remote' => 'lab-atk',
            'victim_container' => 'victim-test-' . uniqid(),
            'victim_remote' => 'lab-env',
            'status' => $status,
            'current_objective' => 1,
        ]);
    }

    public function test_delete_confirmation_and_cancel_do_not_modify_session(): void
    {
        $session = $this->createLabSession();

        Livewire::test(MyLabs::class)
            ->call('requestDelete')
            ->assertSet('showDeleteConfirmation', true)
            ->call('cancelDelete')
            ->assertSet('showDeleteConfirmation', false);

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->refresh()->status
        );

        Queue::assertNotPushed(DeleteLabSession::class);
    }

    public function test_confirm_delete_marks_deleting_and_queues_job(): void
    {
        $session = $this->createLabSession();

        Livewire::test(MyLabs::class)
            ->call('requestDelete')
            ->assertSet('showDeleteConfirmation', true)
            ->call('confirmDelete')
            ->assertSet('showDeleteConfirmation', false);

        $this->assertSame(
            LabSessionStatus::Deleting,
            $session->refresh()->status
        );

        Queue::assertPushed(DeleteLabSession::class, 1);

        Queue::assertPushed(
            DeleteLabSession::class,
            fn (DeleteLabSession $job) => $job->sessionId === $session->id
        );
    }

    public function test_retry_delete_keeps_quota_occupied(): void
    {
        $session = $this->createLabSession(
            status: LabSessionStatus::Deleting
        );

        Livewire::test(MyLabs::class)
            ->call('requestDelete')
            ->call('confirmDelete');

        $this->assertSame(
            LabSessionStatus::Deleting,
            $session->refresh()->status
        );

        $this->assertDatabaseHas('lab_sessions', [
            'id' => $session->id,
            'status' => 'DELETING',
        ], config('infrastructure.environment_connection'));

        Queue::assertPushed(DeleteLabSession::class, 1);
    }

    public function test_student_cannot_delete_another_students_lab(): void
    {
        $other = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $session = $this->createLabSession(owner: $other);

        Livewire::test(MyLabs::class)
            ->call('requestDelete')
            ->assertSet('showDeleteConfirmation', false)
            ->call('confirmDelete');

        $this->assertSame(
            LabSessionStatus::Ready,
            $session->refresh()->status
        );

        Queue::assertNotPushed(DeleteLabSession::class);
    }

    public function test_my_labs_route_is_accessible_to_student(): void
    {
        $this->get('/student/my-labs')
            ->assertSuccessful();
    }
}
