<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class UserPresence
{
    public static function online(int $id): bool
    {
        return Cache::has('imnehnet:user:online:'.$id);
    }

    public static function heartbeat(int $id): void
    {
        // Online presence expires after three minutes without a heartbeat.
        Cache::put(
            'imnehnet:user:online:'.$id,
            true,
            now()->addMinutes(3)
        );

        // Last Seen persists after the online cache expires.
        // Query Builder prevents unnecessary model observer events.
        try {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $id)
                ->update([
                    'last_seen_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning(
                'Unable to update last seen',
                [
                    'user_id' => $id,
                    'exception' => get_class($exception),
                ]
            );
        }
    }

    public static function script(): string
    {
        return <<<'HTML'
<script>
(() => {
  if (window.__imnehnetPresenceInstalled) return;
  window.__imnehnetPresenceInstalled = true;
  const beat = () => {
    if (document.visibilityState !== 'visible') return;
    fetch('/portal/presence', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-CSRF-TOKEN': window.__imnehnetPresenceCsrf || '', 'Accept': 'application/json'},
      cache: 'no-store', keepalive: true
    }).catch(() => {});
  };
  beat();
  setInterval(beat, 60000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') beat();
  });
})();
</script>
HTML;
    }
}
