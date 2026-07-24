<?php

declare(strict_types=1);

use Clever\Clever\Clever;
use Illuminate\Support\Facades\Artisan;

it('is enabled by default in the testing environment', function () {
    expect(app(Clever::class)->enabled())->toBeTrue();
});

it('can be disabled at runtime with config', function () {
    config()->set('clever.enabled', false);

    expect(app(Clever::class)->enabled())->toBeFalse();
});

it('registers the clever commands in the testing environment by default', function () {
    $names = array_keys(Artisan::all());

    expect($names)->toContain('clever:scan')
        ->and($names)->toContain('clever:owned-diff')
        ->and($names)->toContain('clever:welds')
        ->and($names)->toContain('clever:lonely-files')
        ->and($names)->toContain('clever:hotspots');
});
