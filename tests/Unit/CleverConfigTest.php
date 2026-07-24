<?php

declare(strict_types=1);

use Clever\Clever\Support\CleverConfig;

it('narrows the tri-state enabled flag', function () {
    $config = app(CleverConfig::class);

    expect($config->enabled())->toBeNull();

    config()->set('clever.enabled', true);
    expect($config->enabled())->toBeTrue();

    config()->set('clever.enabled', 'yes');
    expect($config->enabled())->toBeNull();
});

it('falls back to base path when root is unset or invalid', function () {
    $config = app(CleverConfig::class);

    expect($config->root())->toBe(base_path());

    config()->set('clever.root', '/tmp/some-repo');
    expect($config->root())->toBe('/tmp/some-repo');

    config()->set('clever.root', 42);
    expect($config->root())->toBe(base_path());
});

it('falls back to the storage report path', function () {
    $config = app(CleverConfig::class);

    expect($config->reportPath())->toBe(storage_path('clever/report.json'));

    config()->set('clever.report.path', '/tmp/report.json');
    expect($config->reportPath())->toBe('/tmp/report.json');
});

it('filters non-strings out of string lists and falls back on wrong types', function () {
    $config = app(CleverConfig::class);

    config()->set('clever.owned_diff.paths', ['app', 42, 'routes']);
    expect($config->ownedPaths())->toBe(['app', 'routes']);

    config()->set('clever.owned_diff.paths', 'app');
    expect($config->ownedPaths())->toBe(['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js']);
});

it('rejects non-positive integers', function () {
    $config = app(CleverConfig::class);

    expect($config->lonelyMinLines())->toBe(30);

    config()->set('clever.lonely.min_lines', 5);
    expect($config->lonelyMinLines())->toBe(5);

    config()->set('clever.lonely.min_lines', -1);
    expect($config->lonelyMinLines())->toBe(30);

    config()->set('clever.lonely.min_lines', '10');
    expect($config->lonelyMinLines())->toBe(30);
});

it('treats an empty churn window as full history', function () {
    $config = app(CleverConfig::class);

    expect($config->churnSince())->toBe('24 months ago');

    config()->set('clever.churn.since', '');
    expect($config->churnSince())->toBeNull();

    config()->set('clever.churn.since', null);
    expect($config->churnSince())->toBeNull();
});
