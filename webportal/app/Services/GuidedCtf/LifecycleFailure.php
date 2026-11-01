<?php

namespace App\Services\GuidedCtf;

use App\Models\LabSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class LifecycleFailure
{
    public static function record(int $id, string $action, Throwable $error): string
    {
        $ref = (string) Str::uuid();
        $message = $action.' gagal. Referensi: '.$ref.'. Coba ulang atau hubungi administrator.';
        Log::error('CTF lifecycle failure', ['reference' => $ref, 'session_id' => $id, 'action' => $action, 'exception' => $error]);
        LabSession::query()->whereKey($id)->where('status', '!=', 'DELETED')
            ->update(['last_error' => $message]);

        return $message;
    }
}
