# 03 — Admin Panel IMNEHNET

**PIC:** Septihadi Malik · **Audit final:** 23 November 2026 · **Source:** [commit `b3370b5`](https://github.com/Alief1150/imnehnet-iac/commit/b3370b58f5cda76e6b982cc788a1cf42ef75ecdf).

## Modul dan Dokumentasi

| No | Modul | Fokus audit source final | Bukti screenshot |
|---|---|---|---|
| 01 | [Dashboard](01-dashboard.md) | KPI, System Health lima hasil, Course Status, recent activity | [Folder](../../images/03-admin/01-dashboard/) |
| 02 | [Terminal](02-terminal.md) | Open shell, Livewire, session, audit, ttyd, fullscreen | [Folder](../../images/03-admin/02-terminal/) |
| 03 | [Infrastructure](03-infrastructure.md) | Topology, host, probe dan status infrastruktur | [Folder](../../images/03-admin/03-infrastructure/) |
| 04 | [Monitoring](04-monitoring.md) | Host/VM telemetry, grafik dan freshness | [Folder](../../images/03-admin/04-monitoring/) |
| 05 | [Security](05-security.md) | Alert, filter, collector, integrasi | [Folder](../../images/03-admin/05-security/) |
| 06 | [Environments](06-environments.md) | Provisioning/runtime, operasi Start/Stop/Reset/Delete | [Folder](../../images/03-admin/06-environments/) |
| 07 | [Users](07-users.md) | Role, status, Presence, Last Seen | [Folder](../../images/03-admin/07-users/) |
| 08 | [CTF Management](08-ctf-management.md) | Current/history sessions, inspector, course/objective | [Folder](../../images/03-admin/08-ctf-management/) |
| 09 | [Activity Logs](09-activity-logs.md) | Audit trail, pencarian, filter, export | [Folder](../../images/03-admin/09-activity-logs/) |
| 10 | [System Settings](10-system-settings.md) | Metadata, profile, password | [Folder](../../images/03-admin/10-system-settings/) |

## Metode Verifikasi

Implementasi dibahas dari source `main` terbaru; cuplikan source tertaut ke GitHub. Screenshot dapat membuktikan rendering, sementara uji hasil backend, otorisasi, data uji, dan error path harus didokumentasikan per skenario. **Jangan mengubah status seluruh pengujian menjadi PASS tanpa log atau bukti hasil**. Hindari menjalankan aksi terminal atau container produksi demi screenshot.
