<?php

declare(strict_types=1);

use Clever\Clever\Probes\OwnedDiffProbe;
use Clever\Clever\Probes\ProbeStatus;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-c1-'.uniqid();

    File::makeDirectory($this->root.'/app', 0755, true);
    File::makeDirectory($this->root.'/routes', 0755, true);

    // 3 code lines, 1 comment, 1 blank.
    file_put_contents($this->root.'/app/One.php', "<?php\n\n// comment\nfinal class One\n{\n}\n");
    // 2 code lines.
    file_put_contents($this->root.'/routes/web.php', "<?php\nreturn [];\n");

    config()->set('clever.root', $this->root);
    config()->set('clever.owned_diff.paths', ['app', 'routes']);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('counts code lines per configured path and prints the talk headline', function () {
    $result = app(OwnedDiffProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->headline)->toBe('owned diff: 6 lines across 2 files')
        ->and($result->metrics)->toMatchArray([
            'code_lines' => 6,
            'comment_lines' => 1,
            'blank_lines' => 1,
            'files' => 2,
        ])
        ->and($result->metrics['by_path'])->toBe([
            'app' => ['code_lines' => 4, 'files' => 1],
            'routes' => ['code_lines' => 2, 'files' => 1],
        ])
        ->and($result->handVerify)->toContain('cloc --json app routes')
        ->and($result->warnings)->toBe([])
        ->and($result->durationMs)->toBeGreaterThanOrEqual(0);
});

it('warns about missing configured paths', function () {
    config()->set('clever.owned_diff.paths', ['app', 'routes', 'missing-dir']);

    $result = app(OwnedDiffProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->warnings)->toBe(['configured path does not exist: missing-dir']);
});

it('skips when none of the configured paths exist', function () {
    config()->set('clever.owned_diff.paths', ['nope', 'also-nope']);

    $result = app(OwnedDiffProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Skipped)
        ->and($result->skipReason)->toBe('none of the configured paths exist')
        ->and($result->metrics)->toBeNull()
        ->and($result->headline)->toBeNull()
        ->and($result->handVerify)->not->toBe('');
});
