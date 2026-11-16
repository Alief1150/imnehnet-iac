# Testing dan Known Issues Progress 2

## Hasil targeted DevOps testing

Hasil yang dilaporkan operator:

| Test | PASS | Assertions |
| --- | ---: | ---: |
| OpenTofu Workspace | 3 | 14 |
| Two-Phase Provisioning | 4 | 13 |
| **Total** | **7** | **27** |

Targeted tests memakai test database/fake driver. Hasil tersebut membuktikan
perilaku aplikasi pada ruang lingkup test, tetapi tidak menjalankan provisioning
nyata pada Incus.

## Full suite sebelumnya

```text
308 PASS
55 FAIL
```

Full suite tidak dijalankan ulang saat finalisasi submission. Semua 55 kegagalan
dicatat sebagai **Known Issues / Deferred to Next Development Iteration** dan
tidak ditutupi oleh hasil targeted testing.

Raw daftar 55 kegagalan per-test tidak tersimpan dalam artefak audit ini, sehingga
laporan tidak mengarang jumlah per modul. Kelompok investigasi berikut disusun
berdasarkan modul test dan failure evidence yang tersedia:

1. **Web terminal dan process runner** — ShellRunner, TerminalPage,
   TerminalService, admin terminal, dan embed/session handling.
2. **Filament dan rendering portal** — dashboard, widget, page/resource
   rendering, panel configuration, dan state UI.
3. **Environment lifecycle dan authorization** — create/start/stop/reset,
   ownership, quota, output parsing, dan two-phase status transitions.
4. **Guided CTF lifecycle** — provisioning pair, reset/delete, teardown,
   firewall abstraction, terminal assignment, dan objective flow.
5. **Infrastructure health/integration** — provider probes, cache warming,
   command result validation, serta perbedaan environment test dan runtime.

Jumlah 55 tetap merupakan total authoritative yang dilaporkan operator; item di
atas adalah grouping tindak lanjut, bukan pembagian angka yang telah diverifikasi.

## Batas klaim end-to-end

Terdapat bukti historis smoke test Guided CTF terbatas pada 19 Oktober 2026,
namun submission ini tidak mengklaim fresh E2E provisioning nyata. Belum ada
verifikasi lengkap untuk rangkaian portal → queue → OpenTofu apply → Ansible →
firewall/micro-segmentation → terminal → monitoring/audit → cleanup.

Status container `RUNNING`, syntax-check, lint, dan targeted tests tidak boleh
dipakai sebagai pengganti bukti E2E live.
