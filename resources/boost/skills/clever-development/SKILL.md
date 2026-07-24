---
name: clever-development
description: >
  Configure and apply the Clever package in Laravel applications — run the
  complexity probes, read the report file, open the dev panel, and tune the
  dev-only gating.
license: MIT
metadata:
  author: maryperry
---

# Clever

Use this skill when a Laravel application needs to adopt `maryperry/clever` — a dev-only tool that runs four small measurements over the app's owned diff and prints each as a number, beside the shell one-liner that recomputes it. The numbers land in the terminal, in one JSON file, and in a web panel.

## Primary Goal

- apply the `maryperry/clever` public API in the smallest correct way

## Workflow

### 1. Install as a dev dependency

```bash
composer require --dev maryperry/clever
```

The provider auto-discovers. Production registers nothing, whatever the config says; commands and the panel exist in `local` and `testing` by default.

### 2. Run the probes

```bash
php artisan clever:scan            # all four, overwrite the report
php artisan clever:owned-diff      # c1 — the lines you own on top of the framework
php artisan clever:welds           # c2 — welded call sites, with the borrowed (facade) split
php artisan clever:lonely-files    # c3 — most-churned files with one author
php artisan clever:hotspots        # c4 — churn against current size, two axes
```

- `--json` on any command prints the result to stdout for a machine.
- A single-probe command rewrites only its own section of the report.
- A probe errors and the command exits non-zero; a probe skips (no git, no commits) and the command exits zero with a `skipped` status and a canonical `skip_reason`.

### 3. Read the outputs

- The report: `storage/clever/report.json` (`clever.report.path`), schema_version 1, overwritten atomically per run. Each probe entry carries `status`, `metrics`, `headline`, `hand_verify` (the shell one-liner), `caveats`, `notes`, and `warnings`.
- The panel: `GET /clever` (route `clever.panel`). It reads the report and never scans; it shows an empty state until `clever:scan` runs.

### 4. Configure when needed

```bash
php artisan vendor:publish --tag="clever-config"   # config/clever.php
php artisan vendor:publish --tag="clever-views"    # panel Blade view
```

The keys: `enabled` (tri-state — `null` is on in local/testing, `true` opts in outside production, `false` is off; production ignores all three), `root` (measure another checkout), `owned_diff.paths`/`extensions`, `welds.paths`/`facades`/`max_sites`, `lonely.min_lines`/`limit`, `churn.since`/`limit`, `report.path`, `route.path`/`middleware`, `exclude`.

### 5. Programmatic access (optional)

The `Clever` facade (`Clever\Clever\Facades\Clever`) exposes `enabled()`, `probes()`, `probe(string $key)`, and `scan()`. A custom probe implements `Clever\Clever\Probes\Probe` — or extends `Clever\Clever\Probes\BaseProbe` — and joins `config('clever.probes')`.

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill

## Examples

- "How much do we own on top of Laravel?" → `php artisan clever:owned-diff`; read the `owned diff: N lines across M files` headline and the `by_path` breakdown, then hand-verify with the printed cloc one-liner.
- "Where is the coupling welded in?" → `php artisan clever:welds --json`; read `bare_static_sites` where `kind` is `candidate` — the facade calls are borrowed, not welds.
- Turn it on for staging: publish the config and set `'enabled' => true`. Production still stays off.
- Prove the gate in the app's own tests: in `production`, `Artisan::all()` holds no `clever:*` command and `Route::has('clever.panel')` is false.

## Anti-patterns

- do not document package internals here; keep the skill on adoption in Laravel apps
- do not fold the probes into a score, grade, or verdict — four numbers stay four numbers, by design
- do not schedule the probes or fire them from the panel; a run is a deliberate command
- do not scrape the panel HTML — read `report.json` or the `--json` output instead
