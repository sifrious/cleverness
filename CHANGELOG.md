# Release Notes

## [Unreleased](https://github.com/maryperry/clever/compare/v0.1.0...1.x)

### Added

- The four lunch probes, each an Artisan command that prints one number and the shell one-liner that recomputes it: `clever:owned-diff` (c1), `clever:welds` (c2), `clever:lonely-files` (c3), `clever:hotspots` (c4). `clever:scan` runs all four; every command takes `--json`.
- One flat report at `storage/clever/report.json`, overwritten atomically each run — a single-probe command rewrites only its own section, `clever:scan` rewrites the lot.
- A panel at `/clever`: one server-rendered page, no JavaScript, no outside request. It reads the report; it never scans.
- The dev-only gate. Production registers nothing, whatever the config says; `local` and `testing` are on by default; anywhere else opts in through `clever.enabled`.
- A probe registry in `config('clever.probes')` — the list is an enabling point, and a custom probe is a class implementing `Clever\Clever\Probes\Probe`.

### Removed

- The skeleton's placeholder command, view, translations, and public assets, and with them the `clever-lang` and `clever-assets` publish tags — the panel ships inline and owns no assets.


## [v0.1.0](https://github.com/maryperry/clever/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
