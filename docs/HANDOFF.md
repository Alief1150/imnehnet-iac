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
| Student self-registration | done, at `/login/regist` |
| Student panel | done, at `/student` — one panel per role |
| Dashboard redesign | done — Recent Activity removed, two tiles added |
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

bash scripts/verify.sh           # the gate. Run this before calling anything done.
php artisan test                 # a subset of what verify.sh checks; see below
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

1. **Five containers are running with no portal record.** The Needs attention
   tile now names them, and it is the only part of the portal that could ever
   have found them, because every other view asks the database what exists:

   ```
   lab-test             10.20.0.31    leftover from an earlier debugging session
   victim-demo-a        10.20.0.100   from the LaaS demo pair
   victim-demo-b        10.20.0.101   from the LaaS demo pair
   student-a-victim     10.20.0.102   built by hand, has full CTF content
   student-b-victim     10.20.0.103   built by hand, has full CTF content
   ```

   The last two matter: somebody already built CTF victims by hand, with the
   `student` account, `/opt/ctf` and `ctf-http` running. That is the victim half of
   `ctf-lab-pair.yml` working in practice, so the pair model is worth finishing
   once LAB-ATK is reachable.

   Decide per container: register it, or delete it. Do not leave them unowned.

2. **Reload the Apache vhost after touching it.** The installed copy was older
   than the repository, so the terminal token gate was not the deployed
   behaviour even though the reload script had been run. Compare content, not
   modification time. Current verified state on port 80:

   ```
   /up                            200
   /build/manifest.json           200
   /build/assets/theme-*.css      200
   /terminal/<slot>/              403   (stable, and with a bogus token)
   /login/regist                  200
   ```

3. **LAB-ATK access. This is the blocker for the whole guided CTF pair.**

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

4. **Review the student-facing pages with eyes, not just tests.** The flow is
   proven by HTTP tests, but nobody has looked at the rendering. Item §36.

5. **Reconsider `APP_ENV=local` / `APP_DEBUG=true`** before wider exposure.

6. **The web terminal is disabled by default.** `infrastructure.terminal.browser_access_enabled`
   reads `TERMINAL_BROWSER_ACCESS`, which is unset, so the portal hands out no
   terminal address at all. The machinery is built and the gate works; it is one
   environment variable away from being on.

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

* **The vhost's deny list must not cover Vite's outputs.** `\.json` in
  `<FilesMatch>` denied `manifest.json`, so every built asset was requested as a
  path the application could not resolve and the portal rendered unstyled — while
  the reload script asserted that same manifest was 200, which could never be
  true while the rule was in place.

* **An installed config file can be older than the one in the repository.**
  Checking them by modification time reported them as identical. They were not.
  Compare content.

* **Pointing a route at a Livewire component class makes it look for a layout
  named `layouts`.** Use `Route::view()` with a Blade layout that calls
  `@livewire(...)`.

* **Tailwind only ships classes it saw when it built.** New Blade means
  `scripts/build-assets.sh`, or the page renders unstyled with no error anywhere.
  `resources/css/app.css` used to scan only `storage/framework/views`, so a Blade
  file that had never been rendered was invisible to it. It scans
  `resources/views/**/*.blade.php` and `app/Filament` as well now.

* **A Grep for a Tailwind class has to account for the escaping.** `bg-teal-600`
  is written `.bg-teal-600` but `hover:text-teal-300` is written
  `.hover\:text-teal-300`, and `tracking-[0.35em]` is
  `.tracking-\[0\.35em\]`. Searching for the unescaped form finds nothing and
  reads as a missing class. Search for the value, not the selector.

* **Where a middleware sits decides whether it can work at all, and both obvious
  placements are wrong.** A panel-access redirect cannot go in
  `Panel::middleware()`, because Filament injects its own `Authenticate` ahead of
  that list and that one aborts with 403 before the redirect runs. It cannot be
  global either: global middleware runs before `StartSession`, so `$request->user()`
  is null, the middleware concludes there is nobody to redirect, and Filament's
  403 goes out a moment later. It works only as the panel's **authMiddleware**,
  which means subclassing `Filament\Http\Middleware\Authenticate` so the check
  replaces that middleware rather than sitting beside it.

* **A subclass of a framework middleware must reproduce the parent's signature
  and visibility exactly.** `handle($request, Closure $next, ...$guards)` —
  leaving the variadic off is a fatal declaration error, not a warning, and so is
  narrowing a public method to protected. Both take down every page of both panels.

* **`php -l` and `php artisan test` both miss class-loading fatals, for different
  reasons, and neither can be made to catch them.** `php -l` parses a file and a
  class that cannot be loaded still parses. The suite is worse: the class is loaded
  while the application boots, so the fatal happens before the first test and the
  suite dies printing no `Tests:` line at all. `scripts/verify.sh` exists because
  of this — it loads every class in a subprocess first, where the fault is an exit
  code rather than a corpse. Use it, not `php artisan test` alone.

* **`actingAs()` cannot catch a middleware-ordering bug.** It puts the user
  straight on the guard, so a test that signs a user in never goes through the
  session start the real request depends on, and a middleware placed before
  `StartSession` looks correct in every test. The fault was only visible in a
  browser. The tests that guard this assert the ordering and the global stack
  structurally, and their teeth were checked by putting the bug back.

* **Filament's login refuses an account that may not enter the current panel, and
  reports it as "these credentials do not match our records."** With two panels
  that is a lie for a student who typed a correct password into `/admin/login`,
  and it sends them to reset a password that is fine. The login form here answers
  yes and lets `RedirectToOwnPanel` decide where the account belongs.

* **`Route::hasNamedRoute()` is not on the `Route` facade** in this Laravel
  version; it forwards to the registrar and throws
  `Attribute [hasNamedRoute] does not exist`. Use
  `Route::getRoutes()->hasNamedRoute()`.

* **Overriding a parent method's visibility is a fatal at class load, not at
  parse time, so `php -l` passes.** Two of these happened in one session:
  `getSubHeading()` is public on Filament's Login, and the middleware's
  `handle()` carries a variadic the subclass had dropped. Both took every page
  of both panels down.

* **A Livewire component may have only one root element.** Three sibling
  elements is a `MultipleRootElementsDetectedException`, not a warning.

* **The sign-in form and the registration form share one stylesheet.** The colour
  tokens live in `theme.css`, so the registration page loads it alongside
  `app.css`. When it loaded only `app.css` it had no tokens and fell back to
  utility classes, and the two pages became visibly different products. The
  shared classes are `.imnehnet-auth-*`; the sign-in form gets the same result by
  overriding Filament's own selectors.

* **Cache plain values, not objects.** A serialized object whose class has been
  renamed comes back as `__PHP_Incomplete_Class` and fails a type hint, taking the
  page down. This happened once on the Monitoring page and healed itself only
  because the entry had 10 seconds left. `ComputePlaneInventory` caches an array
  of strings for exactly this reason.

* **Rate limits must be checked before validation and counted on every
  attempt.** Counting only successful attempts let a script submit addresses that
  fail the uniqueness rule for ever and never reach the limit.

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
tests        213 passed (804 assertions), 1 pre-existing warning
HEAD         see git log, working tree clean
queue worker supervised by scripts/queue-supervisor.sh, started by @reboot cron
Incus lab-env   lab-test 10.20.0.31
                lab-tofu-test 10.20.0.202  (protected, untouched)
                lab-web-smoke 10.20.0.217  (disposable smoke environment, student uid 1001)
tofu         workspace env-lab-web-smoke active; default holds lab-tofu-test
```

The one warning is `TerminalSlotGrantsTest`, "writing a map to an unwritable
location says so instead of failing". It is pre-existing and unrelated.

Accounts: one administrator and one student (`abdi@gmail.com`, self-registered).
Registration is live at `/login/regist` and creates active students, rate limited
to five attempts per address pair per hour, counting attempts that fail
validation. Each student may hold `STUDENT_ENVIRONMENT_LIMIT` environments, three
by default.

There are **two panels**, one per role:

| Panel | Path | Contains |
|---|---|---|
| admin | `/admin` | everything |
| student | `/student` | dashboard, environments, terminal — nothing else |

The administrative resources are not registered on the student panel at all, so
they cannot be reached there whatever a permission changes to. The lists are
written out by hand rather than discovered, which is the point: adding an
administrative resource to a directory must not hand it to every student.

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