<?php

declare(strict_types=1);

use Clever\Clever\Probes\LonelyFilesProbe;
use Clever\Clever\Probes\ProbeStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-c3-'.uniqid();

    File::makeDirectory($this->root.'/app', 0755, true);

    file_put_contents($this->root.'/app/Big.php', "<?php\n".str_repeat("\$a = 1;\n", 60));
    file_put_contents($this->root.'/app/Shared.php', "<?php\n".str_repeat("\$a = 1;\n", 60));
    file_put_contents($this->root.'/app/Tiny.php', "<?php\n\$a = 1;\n");

    config()->set('clever.root', $this->root);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('lists lonely files, filtering tiny and missing ones', function () {
    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --pretty=format:@%an *' => Process::result(implode("\n", [
            '@alice', 'app/Big.php', 'app/Shared.php', '',
            '@alice', 'app/Big.php', 'app/Tiny.php', '',
            '@alice', 'app/Big.php', 'app/Gone.php', '',
            '@bob', 'app/Shared.php', '',
        ])),
    ]);

    $result = app(LonelyFilesProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->headline)->toBe('lonely files: 3 of 4 churned php files have exactly one author')
        ->and($result->metrics['top'])->toBe([
            ['path' => 'app/Big.php', 'commits' => 3, 'author' => 'alice', 'code_lines' => 61],
        ])
        ->and($result->metrics['files_with_history'])->toBe(4)
        ->and($result->metrics['lonely_total'])->toBe(3)
        ->and($result->metrics['filtered_missing'])->toBe(1)
        ->and($result->metrics['filtered_tiny'])->toBe(1);
});

it('skips outside a git repository with the canonical reason', function () {
    fakeGit([
        'git --version' => Process::result('git version 2.44.0'),
        'git rev-parse --is-inside-work-tree' => Process::result(errorOutput: 'fatal', exitCode: 128),
    ]);

    $result = app(LonelyFilesProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Skipped)
        ->and($result->skipReason)->toBe('not a git repository (git rev-parse failed)')
        ->and($result->handVerify)->toContain('git log --no-merges');
});

it('warns on shallow clones', function () {
    fakeHealthyGit([
        'git rev-parse --is-shallow-repository' => Process::result('true'),
        'git -c core.quotepath=off log --no-merges --pretty=format:@%an *' => Process::result("@alice\napp/Big.php\n"),
    ]);

    $result = app(LonelyFilesProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->warnings)->toBe(['shallow clone: history is truncated; churn and authorship under-count']);
});
