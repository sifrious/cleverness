<?php

declare(strict_types=1);

use Clever\Clever\Probes\HotspotsProbe;
use Clever\Clever\Probes\LonelyFilesProbe;
use Clever\Clever\Probes\OwnedDiffProbe;
use Clever\Clever\Probes\WeldedCallSitesProbe;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Clever is a tool you run at your desk. In "production" it registers
    | nothing — no commands, no route — whatever you set here. Everywhere
    | else the flag is tri-state:
    |
    |   null  => on in "local" and "testing" only.
    |   true  => opt in for any non-production environment, staging included.
    |   false => off everywhere.
    |
    | Not an env() call on purpose: the package bans env() in its own arch
    | rules. Publish this file to change it.
    |
    */

    'enabled' => null,

    /*
    |--------------------------------------------------------------------------
    | Root
    |--------------------------------------------------------------------------
    |
    | The directory the probes measure. null is base_path(). Point it at
    | another checkout to read a repo this app doesn't live in.
    |
    */

    'root' => null,

    /*
    |--------------------------------------------------------------------------
    | Probes
    |--------------------------------------------------------------------------
    |
    | Every class here resolves from the container and implements
    | Clever\Clever\Probes\Probe. The list is itself an enabling point —
    | remove a probe, reorder them, add your own.
    |
    */

    'probes' => [
        OwnedDiffProbe::class,
        WeldedCallSitesProbe::class,
        LonelyFilesProbe::class,
        HotspotsProbe::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report
    |--------------------------------------------------------------------------
    |
    | One flat JSON file, overwritten atomically every run — the current
    | number is the deliverable, yesterday's is not. null resolves to
    | storage_path('clever/report.json'). clever:scan rewrites the lot; a
    | single-probe command rewrites only its own section.
    |
    */

    'report' => [
        'path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Route
    |--------------------------------------------------------------------------
    |
    | The panel. Registered only when Clever is enabled, and it 404s if
    | reached while disabled — a stale route cache doesn't get you in.
    |
    */

    'route' => [
        'path' => 'clever',
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | The Owned Diff (clever:owned-diff)
    |--------------------------------------------------------------------------
    |
    | The lines you own on top of `laravel new`; everything else is
    | borrowed. Point "paths" at wherever your app keeps owned code. Run the
    | probe against a fresh skeleton too — the difference between the two
    | numbers is the diff you actually own.
    |
    */

    'owned_diff' => [
        'paths' => ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js'],
        'extensions' => ['php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'css', 'json'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Welded Call Sites (clever:welds)
    |--------------------------------------------------------------------------
    |
    | Where `new LiteralClass(` and bare `Class::method(` are welded in with
    | no enabling point. Facades look like bare statics but resolve from the
    | container — borrowed seams, not welds — so they're reported as a
    | split, never quietly subtracted. "facades" adds class basenames to
    | treat as aliases. "max_sites" caps the per-site lists in the report;
    | the totals are never truncated.
    |
    */

    'welds' => [
        'paths' => ['app'],
        'facades' => [],
        'max_sites' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lonely Files (clever:lonely-files)
    |--------------------------------------------------------------------------
    |
    | "min_lines" is the tiny-file filter — one author may just mean new or
    | trivial, so a file under this many code lines is dropped before you
    | conclude anything. "limit" is how many single-author files to list.
    |
    */

    'lonely' => [
        'min_lines' => 30,
        'limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Churn (clever:hotspots)
    |--------------------------------------------------------------------------
    |
    | "since" takes any `git log --since` expression; null is the whole
    | history. "limit" is how many churn-against-size points to keep.
    |
    */

    'churn' => [
        'since' => '24 months ago',
        'limit' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Directories
    |--------------------------------------------------------------------------
    |
    | Directory basenames skipped wherever the probes walk. vendor,
    | node_modules, storage, and .git are always skipped on top of this.
    |
    */

    'exclude' => [],

];
