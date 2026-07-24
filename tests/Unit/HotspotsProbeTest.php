<?php

declare(strict_types=1);

use Clever\Clever\Probes\HotspotsProbe;
use Clever\Clever\Probes\ProbeStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-c4-'.uniqid();

    File::makeDirectory($this->root.'/app', 0755, true);

    file_put_contents($this->root.'/app/Busy.php', "<?php\n".str_repeat("\$a = 1;\n", 9));
    file_put_contents($this->root.'/app/Quiet.php', "<?php\n\$a = 1;\n");

    config()->set('clever.root', $this->root);
    config()->set('clever.owned_diff.paths', ['app']);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('pairs churn with current code lines, scoped to owned paths', function () {
    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --since=* *' => Process::result(implode("\n", [
            '@aaa1', 'app/Busy.php', 'tests/Excluded.php', '',
            '@aaa2', 'app/Busy.php', '',
            '@aaa3', 'app/Quiet.php', 'app/Gone.php', '',
        ])),
    ]);

    $result = app(HotspotsProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->metrics['points'])->toBe([
            ['path' => 'app/Busy.php', 'churn' => 2, 'code_lines' => 10],
            ['path' => 'app/Quiet.php', 'churn' => 1, 'code_lines' => 2],
        ])
        ->and($result->metrics['commits_scanned'])->toBe(3)
        ->and($result->metrics['files_touched'])->toBe(4)
        ->and($result->metrics['files_missing_on_disk'])->toBe(1)
        ->and($result->metrics['since'])->toBe('24 months ago')
        ->and($result->headline)->toBe('hotspots: churn x lines for the 2 most-churned php files since 24 months ago');

    Process::assertRan(fn ($process) => $process->command === [
        'git', '-c', 'core.quotepath=off', 'log', '--no-merges',
        '--since=24 months ago', '--pretty=format:@%H', '--name-only', '--', '*.php',
    ]);
});

it('omits the since flag for full history', function () {
    config()->set('clever.churn.since', null);

    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --pretty=format:@%H *' => Process::result("@aaa1\napp/Busy.php\n"),
    ]);

    $result = app(HotspotsProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->headline)->toBe('hotspots: churn x lines for the 1 most-churned php files since the beginning of history');

    Process::assertRan(fn ($process) => $process->command === [
        'git', '-c', 'core.quotepath=off', 'log', '--no-merges',
        '--pretty=format:@%H', '--name-only', '--', '*.php',
    ]);
});

it('stays ok with an empty window', function () {
    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --since=* *' => Process::result(''),
    ]);

    $result = app(HotspotsProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->metrics['points'])->toBe([])
        ->and($result->metrics['commits_scanned'])->toBe(0);
});

it('skips when git is unavailable', function () {
    fakeGit([
        'git --version' => Process::result(errorOutput: 'not found', exitCode: 127),
    ]);

    $result = app(HotspotsProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Skipped)
        ->and($result->skipReason)->toBe('git binary not available');
});
