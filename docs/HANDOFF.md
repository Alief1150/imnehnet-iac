# IMNEHNET Portal — Handoff

Position, what is done, what is not, and the traps already paid for. Read this
before changing anything.

Last updated: 2026-10-04. The tree was clean at this commit.

There is deliberately no commit hash here. A document cannot contain the hash of
the commit that contains it, so every version of this line was one commit stale by
the time it was read. `git log --oneline -1` answers the question for any moment.

---

## 0. Picking this up again

```
ssh iaas                                     # IAAS-MGMT
cd /home/iaas/projects/webportal-iaas
bash scripts/verify.sh                       # the gate. Nothing is done until it is green.
```

**The portal is at `http://100.77.79.18` — Tailscale, not the lab LAN.** Measured on
2026-10-06 from a laptop on a home network: `192.168.100.20` is unreachable, because
that network has no route to `192.168.100.0/24` and the address is sent to the home
gateway, which drops it. Everything resolves over Tailscale instead, including the SSH
alias and the portal. `100.77.79.18` is IAAS-MGMT's tailnet address; both the browser
and `ssh iaas` go through it.

Apache serves port 80 and `artisan serve` serves 8000; both answer over Tailscale.
Use port 80 anyway — `artisan serve` is a single worker and serialises two students
talking to two terminals at once.

`192.168.100.20` is LAB-ENV, not the portal, and is only reachable from inside the lab
or by hopping through IAAS-MGMT. Any note that gives it as the portal's address is
wrong for anyone outside.

To see the course as a student, sign in as `ctf-student@imnehnet.test`. Its password is not
written here: it belongs to a real account, and a password that is committed is a password
that has to be changed rather than edited. The same value appears in
`tests/Feature/Filament/StudentRegistrationTest.php`, where it is a fixture for an account
the factory creates and no account has. It owns a finished lab, so the page opens on the
results — `Finish Lab` clears it back to a fresh run. The administrator is
`imnehnetkelompok5@gmail.com`, whose password is likewise not written down anywhere.

Nothing here is held on a laptop. Every container, database row and process lives on
IAAS-MGMT, LAB-ENV and LAB-ATK, so the machine this was written on can be switched off
at any point without disturbing a running lab.

The fastest way to see the whole course working again is to run it end to end against a
live lab: drive the attacker container with the six commands the hints name, ask the
validator between each, then submit the flag. Provisioning is about two minutes and
solving is about ninety seconds.

## 1. Where the work stands

| Area | State |
|---|---|
| Laravel 13 + Filament 5 repair (§1–§13) | done |
| Dark theme, Lucide icons, scoped Lenis (§14–§16) | done |
| Dashboard, sidebar, environment table, detail page (§17–§22) | done |
| Live infrastructure probes (§23) | done, 11 components |
| Web terminal (§24–§26) | done — Apache gate loaded and verified on port 80 |
| Honest states, badges, error UX, reconciliation (§27–§33) | done |
| E2E smoke test (§34) | **done and passed** — see §8 |
| Student self-registration | done, at `/login/regist` |
| Student panel | done, at `/student` — one panel per role |
| Dashboard redesign | done — Recent Activity removed, two tiles added |
| LAB-ATK guided CTF pair | **done** — both halves provisioned, validated, and reachable |
| Guided CTF course | **done** — 6 objectives, real validator, flag, results page |
| Guided CTF tests | **none** — see §5 item 2 |
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

The course runs end to end. One session, two containers, six objectives, a flag,
a results page. What follows is what is not finished.

### 1. Five containers are running with no portal record

```
lab-test             10.20.0.31    leftover from an earlier debugging session
victim-demo-a        10.20.0.100   from the LaaS demo pair
victim-demo-b        10.20.0.101   from the LaaS demo pair
student-a-victim     10.20.0.102   built by hand, has full CTF content
student-b-victim     10.20.0.103   built by hand, has full CTF content
```

Every view asks the database what exists, so nothing else in the portal would ever
have found them. Decide per container: register it, or delete it. Do not leave them
unowned. `student-a-victim` and `student-b-victim` prove the victim half of
`ctf-lab-pair.yml` works by hand, which is now redundant.

### 2. No tests for the CTF domain

227 tests pass but none of them exercise the guided CTF. Missing:

* validator verdicts per `EvidenceType`, including the negative case
* sequential progression — that objective 3 cannot be proven while 2 is locked
* flag comparison, and that a wrong flag changes nothing
* the IDOR property from the specification: that a student cannot obtain another
  session's attacker. This is the one that matters most and it is the one with no
  coverage. The design prevents it — the session is resolved from the signed-in
  user, no request names a target — and none of that is tested.

### 3. No admin view of lab sessions or validation outcomes

An administrator can read the database but not the portal. §15 wants sessions,
their states, and what the validator concluded. Worth building before the first
student, not after.

### 4. The dual terminal is described, not embedded

The workspace shows both container names and links to the existing terminal page.
The supplied design has them side by side. The link was the cheaper half of the
job: the terminal page already owns opening a session, resolving the shell uid and
rendering the frame, and reproducing that inline would be a second copy of the
question "whose session is this", which is the question that has to have one answer.

### 5. The victim terminal is read-only by construction

The workspace labels the victim's side read-only and the attacker shell is the only
one wired to a PTY. If the design wants a genuine second interactive shell, that is
`TerminalService` pointed at the victim's environment with the victim's own account,
which the attacker container cannot do — it has no way in.

### 6. Follow-ups, low priority

* Ansible playbook for the scenario. The scenario is deployed over Incus with shell
  and stdin, because that is the path proven against real containers.
* `terminal_enabled` is `false` on the attacker as created and turned on by
  `markReady()`. Worth folding into the create path so the state is never briefly
  wrong.

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

### Passing a function's return value to a by-reference parameter

`array_pop(explode('.', $address))` raised on every call. `array_pop` takes its argument
by reference; so do `array_shift`, `sort`, `rsort`, `usort`, `settype`. Handing any of
them the result of a function call is the mistake, and unlike `strtok` — which only
warns — it becomes a thrown `ErrorException` under Laravel's error handler.

It cost more than a line. `octetFromAddress()` in `PairAddressAllocator` exists to report
which octets are already taken, so the exception fired on the collision path and nowhere
else. Provisioning looked healthy for as long as it did because a plane with a hundred
free addresses never reaches the broken case. The first two students to collide would
not have been given a different octet; they would have stopped provisioning.

Give the value a variable first:

```php
$octets = explode('.', $address);
return (int) array_pop($octets);
```

Worth knowing that the log check in `scripts/verify.sh` is what found this. Everything
the student touches passed. The gate reads the error log, and that is the only reason a
bug outside every user-visible path ever surfaces.

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
tests        227 passed (837 assertions), 1 pre-existing warning
HEAD         see git log, working tree clean
queue worker supervised by scripts/queue-supervisor.sh, started by @reboot cron
Incus lab-env   lab-test 10.20.0.31
                lab-tofu-test 10.20.0.202  (protected, untouched)
                lab-web-smoke 10.20.0.217  (disposable smoke environment, student uid 1001)
Incus lab-atk   reachable at https://192.168.100.30:8443, remote name `lab-atk`
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

The web process is Apache on port 80 plus `php artisan serve` on 8000. Both
serve correctly; the Apache vhost has been reloaded and verified, including the
terminal token gate returning 403 on a bogus token. Port 80 is the one to use:
`artisan serve` is a single worker and serialises two students talking to two
terminals at once.

A live guided lab is running against `ctf-student@imnehnet.test`, owned by session `#16`. Its containers are the attacker
`attacker-106` and the victim `victim-web-106` at `10.20.0.106`, permitted as a
pair, with `ctf-http` active on the victim.

## 6. Terminal — what is proven, and what it cost

The administrative console opens a real shell on IAAS-MGMT, LAB-ENV or LAB-ATK, and a student
opens one inside their own container. Both were proven end to end against the running host on
all three nodes: HTTP 401 with no credential, HTTP 200 with the session's own, HTTP 401 with a
wrong one, HTTP 403 once the session is closed. That is the whole chain — service, lease file,
Apache, listener.

### Three faults that made it impossible, in the order they had to be found

**The listener died seconds after it started.** ttyd bound its port and was gone a second later,
and its own log carried nothing but `received signal: SIGTERM`. Symfony's `Process` kills its
child in `__destruct__` (`vendor/symfony/process/Process.php:224`), which runs when the request
ends — correct for a command whose output you meant to collect, wrong for a listener meant to
outlive the request. Both services now use `proc_open` with the descriptors already pointed at a
log file, and there is no wrapper object to fire. `setsid` is prepended so the listener leads its
own session and a signal aimed at it cannot reach the web server or a worker nearby.

**The token gate could not work, and looked like it could.** The vhost gated `/terminal/` on a
`RewriteMap` lookup against a single `port token` file. A `txt:` map is read when the server
starts, so a slot leased afterwards is never in it — and the lookup was keyed by a rewrite
backreference, which does not resolve, so every lookup returned NONE and *every* request was
refused, a correct token included. Two repairs were tried and both were wrong: comparing against
`%2` (a backreference that did not exist, because the URI condition captured nothing) and against
`%1` (keying the lookup by the token, when the map is keyed by port). What settled it was a
bisect — removing only the map condition, with the capture conditions untouched, answered 503
instead of 403 — which places the fault on the lookup and nowhere else. The gate is now a
password file per slot: its existence is the lease, tested per request, and `Require valid-user`
checks the same file. Verified that a slot written after the last reload is reachable without a
reload, and gone the moment its file is deleted.

**Nobody could hold a lecturer account.** `UserRole` had a `Lecturer` case and `users.role` was an
ENUM of `admin, student`, so every insert was refused by the database and
`canOperateInfrastructure()` returned true for a role no account could hold.

### Ownership of the terminal directory — this is the part to get right on a new host

```
/var/lib/imnehnet/terminal/htpasswd   root:www-data  2770   (setgid)
```

Three facts have to hold together, and each was got wrong at least once:

- `iaas` must be in group `www-data` (`usermod -aG www-data iaas`), or the portal cannot write a
  lease — the web requests run as `www-data` and would be fine, while artisan and any queue
  worker could not.
- the directory must be **setgid**, or each file the portal writes inherits group `iaas` and Apache
  loses the ability to read it. `chgrp` by hand does not survive: the file is replaced on every
  session.
- the directory must be group-**writable** (2770, not 2750), because writing the lease is the
  mechanism.

The old `slots.map` directory at the same path had the same three faults, which is why the gate
refused everything even once the rules were correct.

### Two things that are deliberately not how they look

The direct address opens the shell in its own **tab** and offers the credential behind a
disclosure, while the console itself is **embedded** in the page. HTTP Basic cannot be answered
from inside an iframe — there is no attribute for an Authorization header — so the embedded
frame does not use the password at all: it loads `/embed/<port>/` with the slot's ticket in a
cookie (see the next section), and the address names only the slot. Either address carries no
credential; a token in a query string was the earlier design, and it is written to the access
log, to browser history and to the Referer of anything the page loads.

### The shell runner — why an administrative shell is not started by the web

An administrative shell has to be started by the account that owns the host. On IAAS-MGMT that
is the account that can sudo; on the two lab planes it is the only one that can read the key they
are reached with. The web runs as `www-data`, so it cannot start one. Three ways out, and two
are traps:

- **Run php-fpm as the host account.** Then the process rendering untrusted input can already
  sudo, and every web vulnerability on this host is root-adjacent. A portal that prints
  environment names and container output does not get to own the web server.
- **Let the web start it anyway.** It would open, as `www-data`, with no sudo and no key — and be
  reported as an administrative shell. A control that grants less than it claims is worse than
  one that refuses.
- **Ask a process that does have the account.** That is `bin/shell-runner.php`.

So the web sends a request over a unix socket and the runner decides for itself what is allowed.
The request can say "open a shell on lab-env, slot 38003". It cannot name a binary, a command, a
path, a user or a port outside the window, because the runner reads none of those from the
request — its node table is its own. `close` refuses any pid not in the list the runner wrote,
which is stronger than checking `/proc`: that proves a process is ttyd, not that the ttyd is
ours. Every refusal is a test, through `php bin/shell-runner.php --once`.

Run it with `bash scripts/shell-supervisor.sh`, which is supervised the same way the queue worker
is — deliberately not a systemd service, because the rest of this deployment runs without root.
There is an `@reboot` entry. It **must** be started as the host account: the runner refuses to
run as root, and starting the supervisor under sudo produces a runner that rejects itself every
five seconds, which reads exactly like a broken runner.

Two details that are easy to get wrong and produce a page that lies:

- **Liveness is read from `/proc`, not signalled.** From `www-data`, `kill(pid, 0)` on a process
  owned by another user returns EPERM, so a liveness check written as a signal call reports a
  healthy shell as gone. A zombie is not a shell either, and is treated as gone.
- **The runner reaps what it starts.** Listeners are spawned with `proc_open` and nothing waited
  for them, so a closed terminal left an exited child in `/proc` for as long as the runner lived.
  The reap was originally placed before signalling and after `kill -9`, and the branch that
  actually succeeds — SIGHUP on the first try — returned straight past it, so every close still
  left one behind. Every return path goes through it now, and a restart prunes the record of pids
  that are already gone.

Ownership of the socket directory, which is the same three facts as the lease directory above:
`iaas` must be in group `www-data`, the directory must be setgid, and it must be group-writable.

### What to do when the console says no runner is answering

`bash scripts/shell-supervisor.sh` in a login as the portal user, then check
`storage/logs/shell-runner.out` and `storage/logs/shell-runner.log`. The most common cause by far
is that it was started under sudo, which is what the log line "The shell runner must run as iaas"
means.

### The embedded console — how a shell appears inside the page

Opening a session issues two leases, not one: the password file the direct address checks, and a
ticket file `<port>-<token>` in `/var/lib/imnehnet/terminal/embed/` that the vhost tests for on
every request to `/embed/`. The browser carries the ticket in a per-slot cookie
(`imnehnet_slot_<port>`), queued when the session opens and forgotten when it closes, so the
iframe address names only the slot and no secret is written to the access log, to browser
history or to any Referer. Closing deletes the ticket file, and every request after that — page,
asset or websocket — is refused with no reload; the reaper closes the same way, because expiry
goes through `close()`.

Two traps already paid for, recorded so they are not paid again:

- **Laravel encrypts queued cookies.** The gate reads the cookie value as a filename, so an
  encrypted value names a file that does not exist and every frame answers 403 with the ticket
  leased and the cookie set — a mounted iframe that refuses. The ticket cookies are therefore
  excluded from encryption in `bootstrap/app.php`, by exact name, built from the same port
  window the slots live in. The plaintext is acceptable for the same reason the password was:
  this deployment serves plain HTTP, so the cookie is visible on the wire either way.
- **`mkdir` wants octal.** `mkdir($dir, 2770)` is decimal 2770, which PHP applies as garbage
  permission bits — owner left without read, so a directory full of tickets is a directory
  `glob()` cannot see. It is `02770`, and the comment beside it says why.

The vhost also proxies websocket upgrades explicitly, for both paths. `ProxyPass http://`
serves the ttyd page but answers an upgrade handshake with the page again — measured as 200
instead of 101, a terminal that loads and never connects. Upgrade requests go to `ws://`
targets in rewrite rules before the pass-through, and the direct path's `Require` still
applies: an upgrade with no credential answers 401, verified live in both directions.

`sites-enabled/imnehnet-portal.conf` is a symlink to `sites-available` again, so there is one
vhost file rather than a hand copy drifting from the repository. If it is ever a copy again,
that drift is how three versions of one vhost came to exist.
