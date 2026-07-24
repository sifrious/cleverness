<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-cmd-'.uniqid();
    $this->reportPath = $this->root.DIRECTORY_SEPARATOR.'report.json';

    File::makeDirectory($this->root.'/app', 0755, true);
    file_put_contents($this->root.'/app/Big.php', "<?php\n".str_repeat("\$a = new Foo();\n", 40));

    config()->set('clever.root', $this->root);
    config()->set('clever.owned_diff.paths', ['app']);
    config()->set('clever.report.path', $this->reportPath);

    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --pretty=format:@%an *' => Process::result("@alice\napp/Big.php\n"),
        'git -c core.quotepath=off log --no-merges --since=* *' => Process::result("@aaa1\napp/Big.php\n"),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
    Date::setTestNow();
});

it('updates only its own report section', function () {
    Date::setTestNow('2026-07-23 10:00:00');
    $this->artisan('clever:scan')->assertSuccessful();

    $before = json_decode((string) file_get_contents($this->reportPath), true);

    Date::setTestNow('2026-07-23 11:00:00');
    $this->artisan('clever:welds')->assertSuccessful();

    $after = json_decode((string) file_get_contents($this->reportPath), true);

    expect($after['probes']['c1'])->toBe($before['probes']['c1'])
        ->and($after['probes']['c3'])->toBe($before['probes']['c3'])
        ->and($after['probes']['c4'])->toBe($before['probes']['c4'])
        ->and($after['generated_at'])->not->toBe($before['generated_at'])
        ->and(array_keys($after['probes']))->toBe(['c1', 'c2', 'c3', 'c4']);
});

it('creates a fresh report when none exists', function () {
    $this->artisan('clever:owned-diff')->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect(array_keys($document['probes']))->toBe(['c1']);
});

it('warns and recovers from a corrupt report', function () {
    file_put_contents($this->reportPath, '{not json');

    $this->artisan('clever:welds')
        ->expectsOutputToContain('could not read existing report; starting fresh')
        ->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect($document['schema_version'])->toBe(1)
        ->and(array_keys($document['probes']))->toBe(['c2']);
});

it('supports --json on every command', function (string $command, ?string $key) {
    Artisan::call($command, ['--json' => true]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray();

    if ($key !== null) {
        expect($decoded['key'])->toBe($key);
    } else {
        expect(array_keys($decoded['probes']))->toBe(['c1', 'c2', 'c3', 'c4']);
    }
})->with([
    'scan' => ['clever:scan', null],
    'owned-diff' => ['clever:owned-diff', 'c1'],
    'welds' => ['clever:welds', 'c2'],
    'lonely-files' => ['clever:lonely-files', 'c3'],
    'hotspots' => ['clever:hotspots', 'c4'],
]);
