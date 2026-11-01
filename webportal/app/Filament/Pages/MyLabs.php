<?php

namespace App\Filament\Pages;

use App\Enums\LabSessionStatus;
use App\Jobs\DeleteLabSession;
use App\Jobs\ProvisionLabSession;
use App\Jobs\ResetLabSession;
use App\Models\Course;
use App\Models\LabSession;
use App\Services\GuidedCtf\LabSessionManager;
use App\Services\GuidedCtf\LifecycleFailure;
use App\Services\GuidedCtf\ProvisioningTimeline;
use App\Services\Terminal\AdminTerminalService;
use App\Services\TerminalService;
use App\Support\Lucide;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Throwable;

class MyLabs extends Page
{


    /**

     * My Labs is a student navigation item.

     * Keep the page registered without showing it in the Admin sidebar.

     */

    /**

     * My Labs belongs exclusively to the Student panel.

     */

    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && parent::canAccess();

    }



    public static function shouldRegisterNavigation(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student';

    }




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

            ProvisioningTimeline::record((int) $session->id, 'queued',
                'Create diterima. User #'.$session->user_id.'; session #'.$session->id.
                '; victim environment #'.$session->victim_environment_id.
                '; attacker environment #'.$session->attacker_environment_id.'.');
            Queue::push(new ProvisionLabSession($session->getKey()));

            Notification::make()
                ->title('Provisioning dimulai')
                ->body('LAB-ATK dan LAB-ENV sedang disiapkan.')
                ->success()
                ->send();

            $this->redirect(static::getUrl(panel: 'student'));
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

                    if ($session->status === LabSessionStatus::Deleting) {
                        return null;
                    }

                    if ($session->status !== LabSessionStatus::Deleted) {
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

            ProvisioningTimeline::record($sessionId, 'delete-queued', 'Delete diterima. Menunggu worker; permintaan ganda ditolak.');
            Queue::push(new DeleteLabSession($sessionId));

            Notification::make()
                ->title('Penghapusan lab dijadwalkan')
                ->body('Kuota tetap 1/1 sampai kedua container berhasil dihapus.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            if (isset($sessionId) && $sessionId !== null) {
                LifecycleFailure::record($sessionId, 'Menjadwalkan Delete', $exception);
                LabSession::query()->whereKey($sessionId)->where('user_id', $user->id)
                    ->where('status', LabSessionStatus::Deleting->value)
                    ->update(['status' => LabSessionStatus::Failed->value]);
            }
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
        $this->queuePower(false);
    }

    public function resumeLab(): void
    {
        $this->queuePower(true);
    }

    private function queuePower(bool $start): void
    {
        $lab = $this->currentSession();
        if (! $lab) {
            return;
        }
        try {
            app(TerminalService::class)->queueOwnedLabPower($lab, $start);
            Notification::make()->title(($start ? 'Resume' : 'Stop').' dijadwalkan')
                ->body('Status berubah setelah kedua container diverifikasi.')->info()->send();
        } catch (Throwable $error) {
            $message = LifecycleFailure::record((int) $lab->id, $start ? 'Resume Lab' : 'Stop Lab', $error);
            Notification::make()->title('Operasi belum dijalankan')->body($message)->danger()->send();
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

                if (Cache::has('imnehnet:terminal-hold:'.$session->id)) {
                    throw new \RuntimeException('Operasi power masih diproses.');
                }
                app(TerminalService::class)->closeOwnedLab($session);
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

    // IMNEHNET_DUAL_TERMINAL_V1
    #[Locked]
    public array $dualTerminal = [];

    #[Locked]
    public array $dualTerminalErrors = [];

    public function connectLabTerminals(): void
    {
        $lab = $this->currentSession();
        $user = auth()->user();
        if (! $user || ! $user->isActive() || ! $lab
            || (int) $lab->user_id !== (int) $user->getKey()
            || ! $lab->status->allowsTerminal()
            || Cache::has('imnehnet:terminal-hold:'.$lab->id)) {
            $this->dualTerminal = [];
            $this->dualTerminalErrors = ['general' => 'Lab belum siap untuk terminal.'];

            return;
        }

        $service = app(TerminalService::class);
        $this->dualTerminalErrors = [];
        foreach (['attacker' => $lab->attackerEnvironment, 'victim' => $lab->victimEnvironment] as $role => $environment) {
            if (! $environment || (int) $environment->user_id !== (int) $user->getKey()) {
                $this->dualTerminalErrors[$role] = 'Environment tidak tersedia atau bukan milik pengguna.';
                unset($this->dualTerminal[$role]);

                continue;
            }
            try {
                $terminal = $service->open($user, $environment, 'sandbox');
                $ticket = $service->embedTicketFor($terminal);
                $url = $service->browserUrl($terminal);
                if (! $ticket || ! $url) {
                    throw new \RuntimeException('Embed terminal belum tersedia.');
                }
                Cookie::queue(cookie(
                    AdminTerminalService::embedCookieName((int) $terminal->ttyd_port),
                    $ticket,
                    max(1, (int) ceil(($terminal->expires_at->getTimestamp() - time()) / 60)),
                    '/embed/', null, request()->isSecure(), true, false, 'Lax'
                ));
                $this->dualTerminal[$role] = ['id' => (string) $terminal->id, 'url' => $url];
            } catch (Throwable $error) {
                $reference = (string) Str::uuid();
                Log::error('My Labs embedded terminal '.$reference, [
                    'lab_session_id' => $lab->id, 'role' => $role,
                    'user_id' => $user->id, 'exception' => $error,
                ]);
                unset($this->dualTerminal[$role]);
                $this->dualTerminalErrors[$role] = 'Gagal menghubungkan terminal. Ref: '.$reference;
            }
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
