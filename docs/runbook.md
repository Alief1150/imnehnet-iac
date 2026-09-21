# Runbook

## Read-only health snapshot

Run these before making infrastructure assumptions:

```bash
hostname
lscpu
free -h
lsblk
grep -E '(vmx|svm)' /proc/cpuinfo | head
warp-cli status
tailscale status
tailscale ip -4
ip addr
ip route
```

## Repository sync

```bash
cd ~/imnehnet-iac
git pull --rebase --autostash
```

After a documented change:

```bash
./scripts/safe-sync.sh "docs: update verified host state"
```

## Tailscale remote access

Use MagicDNS where available:

```bash
ssh imnehnet@imnehnet
```

For VNC, use a VNC client over the Tailscale path. Browser access requires a noVNC/WebSocket gateway and is not provided directly by x11vnc.

## Change management

Before live changes:

1. Capture current state.
2. Generate a plan/diff where possible.
3. Explain impact and rollback.
4. Obtain explicit approval for destructive or connectivity-affecting actions.
5. Apply one change at a time.
6. Verify health.
7. Update `docs/status.md` and, if needed, `docs/decisions.md`.
8. Commit and push repository changes.

## Telegram daily welcome

Hermes uses the `imnehnet-welcome` plugin to prepend a greeting to the first Telegram
response for each authorized user in each daily period beginning at 05:00 WIB.

Each greeting includes up to five latest IMNEHNET Git commits. Commits are shared
updates for every authorized user; private session content is never included. The
plugin does not present commit history as verified runtime state and does not invoke
F5-TTS automatically.

Validate:

```bash
hermes plugins list --plain --no-bundled
hermes plugins doctor imnehnet-welcome
systemctl --user is-active hermes-gateway.service
```

Rate-limit state is stored locally with mode `0600` at:

```text
~/.hermes/state/imnehnet-welcome.json
```

Rollback without deleting rate-limit state:

```bash
hermes plugins disable imnehnet-welcome
hermes gateway restart
```

The tracked plugin source is in `hermes/plugins/imnehnet-welcome/`.

A durable cron job named `IMNEHNET daily welcome 05:00 WIB` sends the daily
briefing at `0 5 * * *` to registered Telegram DM targets. The
generic `deliver=all` target is not used because no home-channel mapping resolved
during the live delivery test. Verify it with:

```bash
hermes cron list
```

At `55 4 * * *`, the no-agent job `Sync IMNEHNET Telegram DM targets` runs
`sync-welcome-dm-targets.py`. It reads only the `gateway_routing` table, selects
Telegram routes whose chat type is `dm` and whose chat ID equals the user ID,
deduplicates them, and updates the 05:00 job. It preserves existing targets if no
DM route is available. Groups and topics are not broadcast targets.

## Hermes Superpowers plugin

The default Hermes profile has `obra/superpowers` version `6.4.1`, based on
immutable upstream commit `5bf4e78011075bcfc0dc295f0724994cd123ee71`. It adds
software-engineering workflows such as brainstorming, implementation planning,
TDD, systematic debugging, code review, worktrees, and verification before
completion. It does not grant infrastructure access by itself; all IMNEHNET
approval gates in `AGENTS.md` still apply.

Validate the installed runtime:

```bash
hermes plugins list --plain --no-bundled
hermes plugins show superpowers
hermes plugins doctor superpowers
hermes plugins capabilities superpowers
```

The installed checkout is under `~/.hermes/plugins/superpowers/`. It contains two
local hardening changes: secret-presence diagnostics report only `SET`/`UNSET`,
and optional visual-companion telemetry is disabled. Do not run
`hermes plugins update superpowers` until the new upstream commit has been audited;
an update can overwrite both changes.

Rollback without deleting the installed checkout:

```bash
hermes plugins disable superpowers
```

Removal, if explicitly requested after rollback verification:

```bash
hermes plugins remove superpowers
```

## Hermes Mantis security-review skills

The active default Hermes profile contains 19 core skills from `google/mantis`,
audited against upstream commit
`6f523a39ccb535b4f5411310f31e872fab954e07`. They support threat modelling,
source review, finding deduplication and validation, reproduction planning,
patch review, and reporting.

Only the static skill packages are installed. `mantis-configure`,
`mantis-launch`, and the repository's Python ADK/reference harness are excluded.
Do not run generated reproducers, patches, Docker/QEMU workloads, cloud setup, or
other Mantis-produced code directly on the infrastructure host. Use an isolated
disposable environment, inspect the exact command and diff first, and retain the
approval requirements in `AGENTS.md`.

Validate discovery and re-scan an individual skill:

```bash
hermes skills list --enabled-only
hermes skills audit mantis-threat-model --deep
```

For runtime acceptance, a fresh Hermes process must be able to invoke
`skill_view` for `mantis-threat-model`. The expected marker used during the
installation test was `MANTIS_RUNTIME_OK`.

Do not run `hermes skills update` for these skills without auditing the proposed
upstream commit and comparing it with the currently pinned commit. The two
skills that were absent from the `skills.sh` index at installation time,
`mantis-pipeline-adapter` and `mantis-structural-index`, were installed directly
from the same audited checkout.

Rollback/removal is destructive to the local skill copies and therefore must be
explicitly requested. Remove one package with:

```bash
hermes skills uninstall mantis-threat-model
```

After any removal, start a fresh Hermes process and verify that the removed
skill is no longer discoverable before deleting any retained audit checkout.

## Hermes draw.io skill

The active default Hermes profile contains `Agents365-ai/drawio-skill` version
`3.4.0`, audited against release commit
`7aa92f73819766eb914fffac66762cf2adb5d828`. Use it when the requested artifact
must remain editable in draw.io/diagrams.net, or when converting code, IaC,
schemas, or API definitions into an editable architecture model. Continue to
prefer simpler diagram-as-code or SVG workflows when editability in draw.io is
not required.

The package has an accepted `CAUTION` scanner verdict. It can launch explicit
local binaries, read source or sanitized live-state JSON, write diagram output,
and optionally fetch allowlisted icons. Do not register its MCP server, install
dependencies, invoke live Terraform/Docker/Kubernetes capture, or contact icon
CDNs unless the operator explicitly requests that separate capability. Never
embed credentials, state secrets, Secret payloads, or environment values in
diagram nodes or provenance.

Validate discovery and the Python-only core:

```bash
hermes skills audit drawio-skill --deep
python3 ~/.hermes/skills/creative/drawio-skill/scripts/diagramctl.py doctor
```

Current expected capability state is Python/XML/Diagram IR available, while
native export and Graphviz auto-layout are unavailable until `drawio` and `dot`
are separately reviewed and installed. Do not treat a diagram inferred from
configuration as verified deployed state.

Upstream updates require a new source review, test run, scanner review, and
recursive hash comparison. Do not update solely because a new version appears.

Rollback/removal, only when explicitly requested:

```bash
hermes skills uninstall drawio-skill
```

Verify removal with a fresh Hermes process before deleting any user-created
`.drawio`, PNG, SVG, PDF, HTML, or Diagram IR artifacts.

## Hermes delegation fleet setup skill

The active default Hermes profile contains only the `delegate-setup` utility
from `amElnagdy/delegate-skills` version `0.5.0`, audited against commit
`6826b363085dcc80875372315fe7d208c4bf733f`. It discovers compatible coding-agent
CLIs and can author a lane map after explicit approval; it never dispatches work
itself.

The repository's 17 `*-delegate` relay skills are intentionally not installed.
At verification time none of their target CLIs were present, and several relay
write modes use broad permissions or lack an OS-enforced sandbox. Continue using
Hermes `delegate_task` and the existing reviewed coding-agent skills unless a
specific CLI relay is separately requested and audited.

Read-only validation:

```bash
hermes skills audit delegate-setup --deep
node ~/.hermes/skills/autonomous-ai-agents/delegate-setup/scripts/discover.mjs
node ~/.hermes/skills/autonomous-ai-agents/delegate-setup/scripts/config.mjs \
  load --cwd ~/imnehnet-iac
```

Do not use `discover.mjs --usage` without telling the operator first; it reads
session counts and mtimes, although it does not open conversation content. Do
not write a global or project lane map until the complete JSON and scope have
been shown and explicitly approved. Authentication for future implementer CLIs
must be completed outside chat and credentials must never be copied into a
brief, config, repository, or relay output.

Rollback/removal, only when explicitly requested:

```bash
hermes skills uninstall delegate-setup
```

## Hermes avoid-ai-writing skill

The active default Hermes profile contains `conorbronsdon/avoid-ai-writing`
version `3.35.0`, pinned to release commit
`fc979c6489ec0ac81a77236782ba496da81b24cb`. The standalone package lives at
`~/.hermes/skills/creative/avoid-ai-writing/` and contains its rules, detector,
preservation validator, quote normalizer, style checker, examples, and reference
catalog. It does not require an API, MCP server, or globally installed npm
package.

Use it for one of three explicit modes:

- `detect`: report AI-associated writing patterns without modifying text;
- `rewrite`: rewrite prose while preserving supported facts and the source's
  level of certainty;
- `edit`: make targeted changes to a named prose file and verify preservation.

Treat detector output as a writing-quality signal, never as proof of authorship
or as the sole basis for academic, hiring, or disciplinary decisions. Do not run
edit mode on source code, configuration, generated data, OpenTofu state, or
credential-bearing files. Quotations, code blocks, tables, URLs, paths,
identifiers, frontmatter, and attributed text remain protected unless the
operator explicitly requests a narrow change.

Read-only validation:

```bash
hermes skills list
node ~/.hermes/skills/creative/avoid-ai-writing/detector/validate.js \
  /tmp/original.md /tmp/rewritten.md
```

For a detector smoke test, import `detector/patterns.js` from a reviewed local
Node harness and call `analyzeText()`. Do not pipe untrusted content into a shell
or interpreter. `hermes skills audit avoid-ai-writing --deep` currently refuses
manual local installs as non-hub-managed, so retain the pinned checkout, run the
upstream tests, perform static review, and compare recursive file hashes during
every update.

The installed skill overlaps with the built-in `humanizer`. Prefer
`avoid-ai-writing` for detect-only audits, source-fidelity-sensitive rewrites,
and file edits that need deterministic preservation checks. Never combine
conflicting rewrite instructions or allow either skill to invent facts,
experiences, opinions, measurements, or citations.

Update procedure:

1. Fetch the proposed release into a temporary checkout and record its immutable
   commit.
2. Review `SKILL.md`, references, scripts, detector code, symlinks, executable
   bits, network access, process execution, environment access, and write paths.
3. Run the complete upstream test suite without installing dependencies.
4. Compare the standalone package against the installed directory and show the
   diff before replacing any files.
5. After approval, replace the package, compare recursive hashes, run detector
   and preservation smoke tests, and verify loading in a fresh Hermes session.

Rollback/removal, only when explicitly requested:

```bash
rm -rf ~/.hermes/skills/creative/avoid-ai-writing
```

Restart or open a fresh Hermes session and verify that `avoid-ai-writing` no
longer appears in `hermes skills list`. Removing the skill must not delete prose
files previously reviewed or edited with it.
