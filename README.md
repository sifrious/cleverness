<div align="center">
    <h1>Clever</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/maryperry/clever"><img src="https://img.shields.io/packagist/v/maryperry/clever.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/maryperry/clever"><img src="https://img.shields.io/packagist/php-v/maryperry/clever.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud/badge/maryperry/clever?style=flat"><img src="https://badge.laravel.cloud/badge/maryperry/clever?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/maryperry/clever/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/maryperry/clever/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/maryperry/clever"><img src="https://img.shields.io/packagist/dt/maryperry/clever.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Your architecture is the diff you own on top of `laravel new`; cleverness is the part of that diff that charges interest. Clever runs four small measurements over that diff and prints each one as a number — and next to every number, the shell one-liner that recomputes it. The probe must recompute the chart: whatever the number is, the command that produced it runs on your repo the same afternoon.

| Probe | Command | Prints |
|---|---|---|
| c1 — the owned diff | `clever:owned-diff` | the lines you own on top of the framework; everything else is borrowed |
| c2 — the welded call sites | `clever:welds` | the `new Class(` and `Class::method(` calls with no enabling point — split into welded and borrowed |
| c3 — the lonely files | `clever:lonely-files` | your most-churned files with exactly one author; clever code is lonely code |
| c4 — the hotspots | `clever:hotspots` | churn against current size, two axes, never multiplied |

git, cloc, and grep only — no parsers, no AST. Every number is hand-verifiable, which is the whole point of shipping the command beside it. The numbers you see are an illustration of one repo — n=1, not a benchmark.

## Installation

Clever is a tool you run at your desk, so it installs as a dev dependency:

```bash
composer require --dev maryperry/clever
```

Production is a hard no, and not a config you can flip: in the `production` environment the package registers nothing — no commands, no route — whatever the config says. On by default in `local` and `testing`; everywhere else it waits to be asked (see [Configuration](#configuration)). A firm no is good leadership; it makes the yes mean something.

## Usage

Run every probe and write the report:

```bash
php artisan clever:scan
```

Or run one — it rewrites only its own section of the report:

```bash
php artisan clever:owned-diff
php artisan clever:welds
php artisan clever:lonely-files
php artisan clever:hotspots
```

Every command takes `--json` and prints the result to stdout for a machine to read.

Each number shows up in three places: in your terminal, in one flat file at `storage/clever/report.json` — overwritten atomically every run, because the current number is the deliverable and yesterday's is not — and in a panel at `GET /clever` you can leave open in a tab. The panel only reads the file; it never scans on its own, and it ships no JavaScript and makes no outside request.

## Reading the numbers

The words mean what they mean in the talk, so read them literally.

The **owned diff** is the lines you own on top of `laravel new`; everything else is borrowed and serviced by someone else. Run c1 inside a fresh skeleton too — the difference between the two numbers is the diff you actually own.

A call site is **welded** when only a literal class name follows `new`; `new static` and `new $var` carry latent enabling points, and c2 counts neither. Facades look like bare statics but resolve from the container — borrowed seams, not welds — so the probe reports them as a split and never quietly subtracts them from the grep total. Eloquent statics construct locally via `new static`, so those stay counted.

A **lonely** file is a churned file with exactly one author. One author may just mean new or trivial, so files under thirty code lines are filtered before you conclude anything.

A **hotspot** is churn against size, kept as two numbers. Line count stands in for complexity on purpose — Jay et al. 2009 found LOC predicts about ninety percent of what cyclomatic complexity would tell you — and the two axes stay two axes; multiply them into one number and you've hidden which problem you have.

Every probe also records its `notes`: the places where the PHP-native count and the shell one-liner share a blind spot — a comment marker inside a string, a PHP 8 attribute counted as a comment, a rename that resets the churn.

## Configuration

Publish the config to change any of it:

```bash
php artisan vendor:publish --tag="clever-config"
```

The keys that matter, in `config/clever.php`:

- `enabled` — tri-state, `null` by default: `null` means on in `local` and `testing` only; `true` opts in for any non-production environment, staging included; `false` is off everywhere. Production ignores all three.
- `root` — the directory the probes measure; `null` is `base_path()`. Point it at another checkout to read a repo this app doesn't live in.
- `probes` — the registry, resolved from the container in order. The list is itself an enabling point — remove one, reorder them, add your own.
- `report.path` — `null` resolves to `storage_path('clever/report.json')`.
- `route.path` and `route.middleware` — where the panel lives (`/clever`, no middleware) and what wraps it if you want it wrapped.
- `owned_diff.paths` / `owned_diff.extensions`, `welds.paths` / `welds.facades` / `welds.max_sites`, `lonely.min_lines` / `lonely.limit`, `churn.since` / `churn.limit`, `exclude` — the knobs each probe reads.

The panel view publishes too, under `--tag="clever-views"`.

### Custom probes

A probe implements `Clever\Clever\Probes\Probe` — extend `Clever\Clever\Probes\BaseProbe` and you inherit the timing and the skip-when-there's-no-git handling. Add the class to `config('clever.probes')` and it runs with `clever:scan` and lands in the report and the panel like the four that ship.

## What this is not

No score, no grade, no verdict — four numbers stay four numbers, and nothing is aggregated into a rating that pretends to know your codebase better than you do. No parsers, no AST; line heuristics and grep, because a number you can't recompute by hand is a number you have to take on faith. No history; the file is overwritten every run. No production surface, ever.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [maryperry](https://github.com/maryperry)
- [All Contributors](../../contributors)

## License

Clever is open-sourced software licensed under the [MIT license](LICENSE.md).
