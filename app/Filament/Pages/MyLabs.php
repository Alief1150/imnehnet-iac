<?php

namespace App\Filament\Pages;

use Illuminate\Support\Facades\Cache;

use App\Jobs\ProvisionLabSession;
use App\Jobs\DeleteLabSession;
use App\Jobs\ResetLabSession;
use App\Services\GuidedCtf\LabFirewall;
use App\Enums\LabSessionStatus;
use App\Support\Lucide;
use Illuminate\Contracts\Support\Htmlable;
use App\Models\Course;
use App\Models\LabSession;
use App\Services\GuidedCtf\LabSessionManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Queue;
use Throwable;

class MyLabs extends Page
{
    public bool $showDeleteConfirmation = false;

    public bool $showStopConfirmation = false;

    public bool $showResetConfirmation = false;

    protected static ?string $navigationLabel = 'My Labs';

    protected static ?string $title = 'My Labs';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'LAB MANAGEMENT';

    protected string $view = 'filament.pages.my-labs';

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('server');
    }

    public function course(): ?Course
    {
        return Course::webServerBreach();
    }

    public function currentSession(): ?LabSession
    {
        $user = auth()->user();
        $course = $this->course();

        if (! $user || ! $course) {
            return null;
        }

        return app(LabSessionManager::class)
            ->activeSessionFor($user, $course);
    }

    public function startLab(): void
    {
        $user = auth()->user();
        $course = $this->course();

        if (! $user || ! $course) {
            Notification::make()
                ->title('Course tidak tersedia')
                ->danger()
                ->send();

            return;
        }

        // Reopen an existing session. Never enqueue a duplicate job.
        if ($this->currentSession()) {
            Notification::make()
                ->title('Limit lab tercapai')
                ->body('Kamu sudah memiliki 1 paired lab. Selesaikan atau hapus lab sebelumnya sebelum membuat lab baru.')
                ->warning()
                ->send();

            return;
        }

        try {
            $session = app(LabSessionManager::class)->start($user, $course);

            if (! $session->wasRecentlyCreated) {
                Notification::make()
                    ->title('Lab sudah tersedia')
                    ->body('Sesi lab yang sudah ada akan dibuka.')
                    ->warning()
                    ->send();

                $this->redirect(GuidedCtf::getUrl(panel: 'student'));

                return;
            }

            Queue::push(new ProvisionLabSession($session->getKey()));

            Notification::make()
                ->title('Provisioning dimulai')
                ->body('LAB-ATK dan LAB-ENV sedang disiapkan.')
                ->success()
                ->send();

            $this->redirect(GuidedCtf::getUrl(panel: 'student'));
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Gagal memulai lab')
                ->body('Periksa ketersediaan infrastruktur atau hubungi administrator.')
                ->danger()
                ->send();
        }
    }

    public function requestDelete(): void
    {
        if ($this->currentSession() === null) {
            return;
        }

        $this->showDeleteConfirmation = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirmation = false;
    }

    public function confirmDelete(): void
    {
        $this->showDeleteConfirmation = false;

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        try {
            $sessionId = Cache::lock(
                'imnehnet:ctf:user-start:'.$user->getKey(),
                60
            )->block(15, function () use ($user): ?int {
                $session = $this->currentSession();

                if (
                    $session === null
                    || (int) $session->user_id !== (int) $user->getKey()
                    || $session->status === LabSessionStatus::Deleted
                ) {
                    return null;
                }

                return Cache::lock(
                    'imnehnet:ctf:session-lifecycle:'.$session->getKey(),
                    2100
                )->block(15, function () use ($session, $user): ?int {
                    $session->refresh();

                    if (
                        (int) $session->user_id !== (int) $user->getKey()
                        || $session->status === LabSessionStatus::Deleted
                    ) {
                        return null;
                    }

                    if ($session->status !== LabSessionStatus::Deleting) {
                        $session->forceFill([
                            'status' => LabSessionStatus::Deleting,
                            'last_error' => null,
                        ])->save();
                    }

                    return (int) $session->getKey();
                });
            });

            if ($sessionId === null) {
                return;
            }

            Queue::push(new DeleteLabSession($sessionId));

            Notification::make()
                ->title('Penghapusan lab dijadwalkan')
                ->body('Kuota tetap 1/1 sampai kedua container berhasil dihapus.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Gagal menjadwalkan Delete')
                ->body('Lab tetap tercatat. Coba lagi atau hubungi administrator.')
                ->danger()
                ->send();
        }
    }

    public function requestStop(): void
    {
        $session = $this->currentSession();

        if ($session && in_array($session->status, [
            LabSessionStatus::Ready,
            LabSessionStatus::Running,
        ], true)) {
            $this->showStopConfirmation = true;
        }
    }

    public function cancelStop(): void
    {
        $this->showStopConfirmation = false;
    }

    public function stopLab(): void
    {
        $this->showStopConfirmation = false;

        $user = auth()->user();

        if (! $user) {
            return;
        }

        try {
            Cache::lock(
                'imnehnet:ctf:session-lifecycle:'.$this->currentSession()?->getKey(),
                2100
            )->block(15, function () use ($user): void {
                $session = $this->currentSession();

                if (! $session ||
                    (int) $session->user_id !== (int) $user->getKey() ||
                    ! in_array($session->status, [
                        LabSessionStatus::Ready,
                        LabSessionStatus::Running,
                    ], true)) {
                    return;
                }

                app(LabFirewall::class)->revoke($session);

                $session->forceFill([
                    'status' => LabSessionStatus::Stopped,
                ])->save();
            });

            Notification::make()
                ->title('Lab dihentikan sementara')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Stop Lab gagal')
                ->danger()
                ->send();
        }
    }

    public function resumeLab(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        try {
            $session = $this->currentSession();

            if (! $session) {
                return;
            }

            Cache::lock(
                'imnehnet:ctf:session-lifecycle:'.$session->getKey(),
                2100
            )->block(15, function () use ($user): void {
                $session = $this->currentSession();

                if (! $session ||
                    (int) $session->user_id !== (int) $user->getKey() ||
                    $session->status !== LabSessionStatus::Stopped) {
                    return;
                }

                app(LabFirewall::class)->permit($session);

                $session->forceFill([
                    'status' => LabSessionStatus::Ready,
                ])->save();
            });

            Notification::make()
                ->title('Lab dilanjutkan')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Resume Lab gagal')
                ->danger()
                ->send();
        }
    }


    public function requestReset(): void
    {
        $session = $this->currentSession();

        if ($session && in_array($session->status, [
            LabSessionStatus::Ready,
            LabSessionStatus::Running,
            LabSessionStatus::Completed,
        ], true)) {
            $this->showResetConfirmation = true;
        }
    }

    public function cancelReset(): void
    {
        $this->showResetConfirmation = false;
    }

    public function confirmReset(): void
    {
        $this->showResetConfirmation = false;

        $user = auth()->user();
        $session = $this->currentSession();

        if (! $user || ! $session) {
            return;
        }

        try {
            $sessionId = Cache::lock(
                'imnehnet:ctf:session-lifecycle:'.$session->getKey(),
                2100
            )->block(15, function () use ($user): ?int {
                $session = $this->currentSession();

                if (! $session
                    || (int) $session->user_id !== (int) $user->getKey()
                    || ! in_array($session->status, [
                        LabSessionStatus::Ready,
                        LabSessionStatus::Running,
                        LabSessionStatus::Completed,
                    ], true)) {
                    return null;
                }

                $session->forceFill([
                    'status' => LabSessionStatus::Resetting,
                    'last_error' => null,
                ])->save();

                return (int) $session->getKey();
            });

            if ($sessionId === null) {
                return;
            }

            Queue::push(new ResetLabSession($sessionId));

            Notification::make()
                ->title('Reset Lab dijadwalkan')
                ->body('Skenario akan dipulihkan dan progres direset ke 0/6.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Gagal menjadwalkan Reset')
                ->danger()
                ->send();
        }
    }

    public function openWorkspace(): void
    {
        $session = $this->currentSession();

        if (! $session || ! $session->status->allowsTerminal()) {
            return;
        }

        $this->redirect(GuidedCtf::getUrl(panel: 'student'));
    }
}
