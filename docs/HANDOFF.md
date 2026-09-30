# IMNEHNET Portal — Handoff

Position in the master repair prompt, what is done, what is not, and the
traps already paid for. Read this before changing anything.

Last updated: 2026-09-30.

---

## 1. Where the work stands

| Phase (spec §38) | State |
|---|---|
| 1 — Audit existing app | done |
| 2 — Repair architecture | done |
| 3 — UI upgrade | in progress, roughly 60% |
| 4 — Live infrastructure integration | services exist, not wired to the UI, not proven live |
| 5 — Web terminal | not started; `ttyd` is installed |
| 6 — E2E verification | not started |

Master prompt acceptance list (§40): 13 of 26 done.

## 2. Ground rules that must not be broken

* `lab-tofu-test` (10.20.0.202) is the protected reference environment. Never
  destroy, reset, rename, adopt or repurpose it. It exists to be a baseline.
* The OpenTofu workspace `default` holds that container's state. Never use it
  for a portal environment.
* Both are enforced in code by `App\Support\EnvironmentWorkspace`, driven by
  `infrastructure.protected_workspaces` and `infrastructure.protected_containers`.
  The check runs before the directory lock is taken, so a rejected request
  costs nothing and has no side effects.
* No environment has been created through the portal yet. Everything live so
  far was done by hand during the audit.

## 3. Commands that matter

```bash
cd /home/iaas/projects/webportal-iaas

php artisan test                 # must stay green: 116 tests
npm run build                    # needs Node 24; login shell picks it up via nvm
php artisan migrate --force

./scripts/queue-worker.sh        # the queue worker; refuses a second instance
php artisan serve --host=0.0.0.0 --port=8000
```

Read the actual state of the compute plane:

```bash
ssh -i ~/.ssh/imnehnet_ansible lab@192.168.100.20 \
  'sudo -n /usr/local/sbin/imnehnet-incus list -c ns4'

cd /home/iaas/imnehnet-iac/tofu && tofu plan -input=false -no-color
```

## 4. Layout

| Path | Holds |
|---|---|
| `app/Support/EnvironmentWorkspace.php` | protected names, name validation, workspace derivation |
| `app/Support/Lucide.php` | generated icon set; regenerate with `tools/build-lucide-icons.php` |
| `app/Data/ProcessResult.php` | exit code, both output streams, duration, redaction |
| `app/Contracts/LifecycleDriver.php` | the boundary between portal and infrastructure |
| `app/Services/OpenTofuService.php` | workspace isolation, the directory lock, command construction |
| `app/Services/AnsibleService.php` | bootstrap by extra variable, no default container name |
| `app/Drivers/LiveLifecycleDriver.php` | the real driver: OpenTofu provisions, Ansible configures |
| `app/Services/EnvironmentLifecycleService.php` | state guards, settle/fail, reconciliation |
| `app/Jobs/*Environment.php` | five job classes, one per lifecycle action |
| `app/Filament/Resources/Environments/` | the operational table, detail page, create page |

The infrastructure repository is separate: `/home/iaas/imnehnet-iac`.

## 5. What is left, in the order it should be done

1. **Wire the lifecycle actions into the environment table.** The services,
   guards and jobs exist and are tested; the table currently offers no Start,
   Stop, Reset, Delete or Terminal action. This is what makes the portal
   usable rather than merely correct.
2. **Theme and Lenis** (spec §14, §16). `npm run build` works, the packages
   are installed, and `resources/js/app.js` is still empty.
3. **Infrastructure probes** for OpenTofu, Ansible and MySQL (spec §23). The
   management and compute planes are probed already; the three missing ones
   should report `not_configured` rather than be invented.
4. **Reconciliation in the UI** (spec §33): a read-only Refresh action that
   calls the driver and surfaces a state mismatch.
5. **Web terminal** (spec §24–26): a `TerminalService` issuing short-lived
   sessions, `ttyd` bound to loopback, resolved from the database so the
   browser never chooses a container name, and the shell as `student`.
6. **E2E smoke test** (spec §34) on a disposable `lab-web-smoke`, then verify
   `lab-tofu-test` is unchanged afterwards.

## 6. Traps already paid for

Framework behaviour that cost real debugging time. These are properties of
Laravel 13, Filament 5 and Blade Icons as installed, not preferences.

* **`Filament\Panel::subheading()` does not exist.** The panel is constructed
  on every request, so one bad method takes down every page with a 500 rather
  than one page.
* **An icon name is resolved through the blade-icons default set**, which only
  knows Heroicons. Passing `lucide-server` raises `SvgNotFound`. Icons from a
  different set must be passed as markup, which is what `Lucide::icon()`
  returns.
* **`Stat::icon()` and `Stat::descriptionIcon()` accept only `BackedEnum|string`.**
  Markup is not accepted there, so the dashboard tiles carry no icon rather
  than the wrong one.
* **`TextColumn::icon()` does accept markup**, so table badges use it.
* **`TableWidget::$heading` is static. `StatsOverviewWidget::$heading` is
  not.** Getting either wrong is a fatal redeclaration.
* **`TextColumn::seconds()` does not exist** in this version.
* **`$navigationGroup` must be typed `string|\UnitEnum|null`.** Narrowing it to
  `?string` is a fatal type error at class load.
* **Filament 5 login is a Livewire component** at `Filament\Auth\Pages\Login`.
  A `POST` to `/admin/login` is a 405, and the component does not move off
  `about:blank`, so it has to be tested with `Livewire::test()`.
* **Two `:memory:` SQLite connections are two separate databases.** The
  inventory connection name is read from configuration and aimed at the
  suite's own database during tests, so the schema, the factory and the
  transaction rollback all agree.
* **An observer cannot keep state between model events.** The event dispatcher
  resolves a `Class@method` listener into a fresh object on every dispatch, so
  a property set during `saving` is gone by `updated`. The previous values
  live in a static map keyed by record.
* **Pass arguments to external commands as an array**, never a string. Nothing
  from the working directory or the environment can then be reinterpreted by a
  shell.
* **Redaction has to happen where text leaves the process.** A failure summary
  stored unredacted put a credential from an OpenTofu error line into a field
  an operator can read.

## 7. Safety net

```bash
git tag -l                                        # SEBELUM-OVRHAUL-2026-09-30
/home/iaas/projects/webportal-iaas.bak-20260930    # folder copy before the rebuild
/home/iaas/db-backup-20260930.sql                 # database dump
```

There is no remote. Both repositories exist only on this host, so a tag is the
only way back to a known good state.

## 8. State at the time of writing

```
tests        116 passed (439 assertions)
app /up      200
queue worker running, started at boot from the user crontab
node         v24.21.0 (nvm), login shells select it
Incus        lab-test 10.20.0.31, lab-tofu-test 10.20.0.202
tofu         workspace default, state incus_instance.student_lab
```

`lab-test` is a leftover from an earlier debugging session and belongs to no
record in the inventory. It can go, but nothing depends on it and nothing
removes it automatically.

The web process is `php artisan serve`, started by hand. It does not come back
after a reboot on its own. The queue worker does.
