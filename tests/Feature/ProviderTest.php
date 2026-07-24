<?php

declare(strict_types=1);

use Clever\Clever\Clever;
use Clever\Clever\CleverServiceProvider;
use Clever\Clever\Probes\HotspotsProbe;
use Clever\Clever\Probes\LonelyFilesProbe;
use Clever\Clever\Probes\OwnedDiffProbe;
use Clever\Clever\Probes\Probe;
use Clever\Clever\Probes\WeldedCallSitesProbe;
use Illuminate\Support\ServiceProvider;

it('resolves the singleton', function () {
    expect(app(Clever::class))->toBeInstanceOf(Clever::class);
});

it('returns the same instance from the container', function () {
    expect(app(Clever::class))->toBe(app(Clever::class));
});

it('merges the package config', function () {
    expect(config('clever.enabled'))->toBeNull()
        ->and(config('clever.root'))->toBeNull()
        ->and(config('clever.report.path'))->toBeNull()
        ->and(config('clever.route.path'))->toBe('clever')
        ->and(config('clever.route.middleware'))->toBe([])
        ->and(config('clever.owned_diff.paths'))->toBe(['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js'])
        ->and(config('clever.welds.paths'))->toBe(['app'])
        ->and(config('clever.lonely.min_lines'))->toBe(30)
        ->and(config('clever.churn.since'))->toBe('24 months ago');
});

it('registers the four talk probes in order', function () {
    expect(config('clever.probes'))->toBe([
        OwnedDiffProbe::class,
        WeldedCallSitesProbe::class,
        LonelyFilesProbe::class,
        HotspotsProbe::class,
    ]);

    $probes = app(Clever::class)->probes();

    expect(array_map(fn (Probe $probe): string => $probe->key(), $probes))
        ->toBe(['c1', 'c2', 'c3', 'c4']);
});

it('finds a probe by key', function () {
    expect(app(Clever::class)->probe('c2'))->toBeInstanceOf(WeldedCallSitesProbe::class)
        ->and(app(Clever::class)->probe('nope'))->toBeNull();
});

it('rejects configured probe classes that do not implement the contract', function () {
    config()->set('clever.probes', [Clever::class]);

    app(Clever::class)->probes();
})->throws(InvalidArgumentException::class);

it('loads the panel view', function () {
    expect(view()->exists('clever::panel'))->toBeTrue();
});

it('registers the publish mappings under the clever tags', function () {
    $config = ServiceProvider::pathsToPublish(CleverServiceProvider::class, 'clever-config');

    expect(array_values($config))->toBe([config_path('clever.php')])
        ->and(realpath((string) array_key_first($config)))
        ->toBe(realpath(__DIR__.'/../../config/clever.php'));

    $views = ServiceProvider::pathsToPublish(CleverServiceProvider::class, 'clever-views');

    expect(array_values($views))->toBe([resource_path('views/vendor/clever')]);
});
