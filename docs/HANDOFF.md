# IMNEHNET Portal — Handoff

Position, what is done, what is not, and the traps already paid for. Read this
before changing anything.

Last updated: 2026-10-02. Head at time of writing: `81e6dd7`.

---

## 1. Where the work stands

| Area | State |
|---|---|
| Laravel 13 + Filament 5 repair (§1–§13) | done |
| Dark theme, Lucide icons, scoped Lenis (§14–§16) | done |
| Dashboard, sidebar, environment table, detail page (§17–§22) | done |
| Live infrastructure probes (§23) | done, 11 components |
| Web terminal (§24–§26) | built; **Apache gate still unloaded** |
| Honest states, badges, error UX, reconciliation (§27–§33) | done |
| E2E smoke test (§34) | **done and passed** — see §8 |
| LAB-ATK guided CTF pair | **blocked on LAB-ATK access** — see §5 |
| UI quality gate (§36) | not done; needs a real screenshot review |

Master prompt acceptance list: 25 of 26, item 36 outstanding.

## 2. Ground rules that must not be broken

* `lab-tofu-test` (10.20.0.202) is the protected reference environment. Never
  destroy, reset, rename, adopt or repurpose it. It exists to be a baseline.
* The OpenTofu workspace `default` holds that container's state. Never use it
  for a portal environment.
* Both are enforced in code by `App\Support\EnvironmentWorkspace`, driven by
  `infrastructure.protected_workspaces` and `infrastructure.protected_containers`.
  The check runs before the directory lock is taken, so a rejected request costs
  nothing and has no side effects.
* Never fake data. An unobserved value is `NOT OBSERVED` / `UNKNOWN` /
  `NOT CONFIGURED`, never a plausible guess.
* No secrets in this repository. `.gitignore` covers 14 forbidden artefact
  patterns; the credentials the operator has supplied are deliberately not
  written anywhere, including this file.

## 3. Commands that matter

```bash
cd /home/iaas/projects/webportal-iaas

php artisan test                 # must stay green; 168 tests
npm run build                    # needs Node 24; use scripts/build-assets.sh
bash scripts/reload-app.sh       # required after EVERY code change
bash scripts/build-assets.sh     # required after every asset change
```

`scripts/reload-app.sh` matters more than it looks. The queue worker caches
classes at start, so background jobs run the code as it was when the worker
booted. A fix that is not reloaded is a fix that does not happen.

Read the actual state of the compute plane:

```bash
ssh -i ~/.ssh/imnehnet_ansible lab@192.168.100.20 \
  'sudo -n /usr/local/sbin/imnehnet-incus list -c ns4'
```

## 4. Layout

| Path | Holds |
|---|---|
| `app/Support/EnvironmentWorkspace.php` | protected names, name validation, workspace derivation |
| `app/Support/Lucide.php` | generated icon set; regenerate with `tools/build-lucide-icons.php` |
| `app/Data/ProcessResult.php` | exit code, both output streams, duration, redaction |
| `app/Contracts/LifecycleDriver.php` | the boundary between portal and infrastructure |
| `app/Services/OpenTofuService.php` | workspace isolation, the lock, `flattenOutputs()` |
| `app/Services/AnsibleService.php` | bootstrap by extra variable, no default container name |
| `app/Drivers/LiveLifecycleDriver.php` | the real driver: OpenTofu provisions, Ansible configures |
| `app/Services/EnvironmentLifecycleService.php` | guards, `settle()`, `fail()`, `reconcile()` |
| `app/Jobs/*Environment.php` | five job classes, one per lifecycle action |
| `app/Services/TerminalService.php` | slot leases, uid resolution, `browserUrl()` |
| `app/Filament/Resources/Environments/` | the operational table, detail page, create page |
| `tests/Filament` → `tests/Feature/` | Pest suites by area |

The infrastructure repository is separate: `/home/iaas/imnehnet-iac`.
The lab pair contract is in `imnehnet-iac/docs/LAB-AS-A-SERVICE-MVP.md`.

## 5. What is left

1. **Reload the Apache vhost.** Requires the operator's password:

   ```bash
   cd /home/iaas/projects/webportal-iaas && sudo bash deploy/reload-apache-vhost.sh
   ```

   Until this runs, every static file 404s on port 80 and the terminal token
   gate answers 503 instead of 403. The script refuses to report success unless
   `/up` is 200, `/build/manifest.json` is 200, the manifest's theme CSS is 200,
   and `/terminal/38000/` is 403.

2. **LAB-ATK access. This is the blocker for the whole guided CTF pair.**

   Measured from IAAS-MGMT, both refused:

   | Path | Result |
   |---|---|
   | `incus` API 192.168.100.30:8443 | closed |
   | `incus` API 100.108.204.76:8443 | closed |
   | SSH, both keys held here | denied |
   | SSH, password, 6 usernames | denied |

   The sshd offers `publickey,password`, so password auth is enabled and the
   supplied password is simply not valid for any account tried. LAB-ENV is
   reachable the whole time on 192.168.100.20:8443, so the tooling is not the
   problem.

   Three commands on the LAB-ATK console are enough to unblock the API, after
   which OpenTofu can create the attacker container without SSH at all:

   ```bash
   sudo incus config set core.https_address 192.168.100.30:8443
   sudo systemctl restart incus
   sudo incus config trust add-token    # send the printed token back
   ```

   If `restart incus` fails with "Unit not found", Incus is not installed there
   and it is a different problem.

   Then, still needing SSH: the Ansible bootstrap for the `student` account, and
   adding each pair to the `allowed_pairs` set on LAB-ATK and `victim_demo` on
   LAB-ENV. `ctf-lab-pair.yml` launches the containers but never touches those
   sets — the document itself calls that a manual step, and one click per student
   has to automate it.

   Code that has to change once reachable:
   * `tofu/main.tf` hardcodes `remote = "lab-env"`, has no static IP and no ACL.
     The remote needs to be a variable; the attacker needs a `nic` device with
     `ipv4.address` and `security.acls = ctf-attacker` (LAB-ATK is Incus 7.0.1
     and does support ACLs, unlike LAB-ENV at 6.0.0).
   * `ansible/inventory/hosts.ini` has no `lab_atk` group at all, and
     `ansible.cfg` sets `host_key_checking = True`.
   * Address allocation: the `.100+n` convention has `victim-demo-a`/`.100` and
     `victim-demo-b`/`.101` taken, so the next free pair is `.102`.

3. **Review the student-facing pages with eyes, not just tests.** The flow is
   proven by HTTP tests, but nobody has looked at the rendering. Item §36.

4. **Reconsider `APP_ENV=local` / `APP_DEBUG=true`** before wider exposure.

## 6. Traps already paid for

Framework and tooling behaviour that cost real debugging time. These are
properties of what is installed, not preferences.

* **`tofu output -json` returns descriptors, not values:**

  ```json
  "container_ipv4": { "sensitive": false, "type": "string", "value": "10.20.0.171" }
  ```

  Casting one to string threw `Array to string conversion` inside `settle()`,
  which is the only place a result becomes state — so the address was never
  stored and the record stayed `PROVISIONING` for ever. Flatten at
  `OpenTofuService::flattenOutputs()`, and drop `sensitive` entries rather than
  keeping the envelope.

* **A crashed Pest suite prints no `⨯` and no `Tests:` line.** Treat the absence
  of a `Tests:` line as a failure. A parse error in one test file is invisible
  otherwise.

* **Pest test helpers are global across the suite.** Declaring a helper that
  already exists in another file is a fatal redeclaration that takes down every
  test at once, with no failing test to point at it.

* **`$this` cannot be reassigned inside a Pest file**, even in a plain function.
  Name the variable after the thing it holds.

* **A Filament `TableWidget` renders no rows in the initial HTTP response.**
  Assert through `Livewire::test(...)->assertCanSeeTableRecords()`, not
  `assertSee()` on a GET.

* **A record outside a resource's scoped query returns 404, not 403.** That is
  the better answer: a 403 would confirm that someone else's record exists.

* **A test must not depend on real infrastructure being unreachable.** One
  terminal test asserted a refusal that only happened because Incus was
  unreachable from the suite. Creating a real environment made the lookup
  succeed, walked past the guard, and failed elsewhere for a reason it never
  described. Point such a lookup at a binary that does not exist.

* **`Filament\Panel::subheading()` does not exist.** The panel is constructed on
  every request, so one bad method 500s every page.

* **Icons resolve through blade-icons' default set, which only knows Heroicons.**
  `lucide-server` raises `SvgNotFound`. A different set must be passed as
  markup, which is what `Lucide::icon()` returns.

* **`Stat::icon()` and `Stat::descriptionIcon()` accept only
  `BackedEnum|string`.** `TextColumn::icon()` does accept markup.

* **`TableWidget::$heading` is static, `StatsOverviewWidget::$heading` is not.**

* **`$navigationGroup` must be typed `string|\UnitEnum|null`.**

* **Filament 5 login is a Livewire component.** A `POST` to `/admin/login` is a
  405; test it with `Livewire::test()`.

* **Never edit a formatted PHP file by pattern or regex.** A greedy match once
  deleted `getPages`, `getRelations` and `getNavigationIcon`. Match exact text
  including indentation, and read the file first. A regex that starts at a
  method signature and runs to the closing brace will eat the `{`. After scripted
  edits, check for duplicate `use` imports and lint every touched file.

* **An observer cannot keep state between model events.** The dispatcher resolves
  a `Class@method` listener into a fresh object each dispatch. Previous values
  live in a static map keyed by record.

* **Pass external command arguments as an array**, never a string.

* **Redaction has to happen where text leaves the process.**

## 7. Safety net

```bash
git tag -l                                  # SEBELUM-OVRHAUL, pre-labs-removal
/home/iaas/projects/webportal-iaas.bak-20260930
/home/iaas/db-backup-20260930.sql
```

There is no git remote. Both repositories exist only on this host, so a tag is
the only way back to a known good state.

## 8. State at the time of writing

```
tests        168 passed (621 assertions), 1 pre-existing warning
HEAD         81e6dd7, working tree clean
queue worker supervised by scripts/queue-supervisor.sh, started by @reboot cron
Incus lab-env   lab-test 10.20.0.31
                lab-tofu-test 10.20.0.202  (protected, untouched)
                lab-web-smoke 10.20.0.217  (disposable smoke environment, student uid 1001)
tofu         workspace env-lab-web-smoke active; default holds lab-tofu-test
```

The one warning is `TerminalSlotGrantsTest`, "writing a map to an unwritable
location says so instead of failing". It is pre-existing and unrelated.

`lab-web-smoke` is disposable: created and destroyed through the portal's own
lifecycle during the smoke test, and left running because it is the only
environment with a real bootstrapped `student` account. Delete it from the list
when it is no longer needed.

Changes made **outside** this repository, for the next session to know about:

* `sshpass` installed on IAAS-MGMT via `sudo apt-get install`.
* `/home/iaas/bin/sshpw.py`, a `pty`-based SSH helper that answers a password
  prompt. Largely superseded by `sshpass`.
* `~/.ssh/known_hosts` extended with the MagicDNS names `lab-atk` and `lab-env`;
  a backup of the original is at `~/.ssh/known_hosts.bak-20261002-190410`.

The web process is Apache on port 80 plus `php artisan serve` on 8000. Apache's
vhost is installed but stale, so `:8000` currently serves correctly while port 80
serves unstyled pages. See §5 item 1.