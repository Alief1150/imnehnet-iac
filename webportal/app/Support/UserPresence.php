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
        Cache::put('imnehnet:user:online:'.$id, true, now()->addMinutes(3));
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
