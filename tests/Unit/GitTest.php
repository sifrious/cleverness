<?php

declare(strict_types=1);

use Clever\Clever\Support\Git;
use Clever\Clever\Support\GitStatus;
use Illuminate\Support\Facades\Process;

it('preflights ok on a healthy repository', function () {
    fakeHealthyGit();

    $git = app(Git::class);

    expect($git->preflight())->toBe(GitStatus::Ok)
        ->and($git->isShallow())->toBeFalse();
});

it('detects a missing git binary', function () {
    fakeGit([
        'git --version' => Process::result(errorOutput: 'not found', exitCode: 127),
    ]);

    expect(app(Git::class)->preflight())->toBe(GitStatus::NotFound)
        ->and(GitStatus::NotFound->skipReason())->toBe('git binary not available');
});

it('detects a missing work tree', function () {
    fakeGit([
        'git --version' => Process::result('git version 2.44.0'),
        'git rev-parse --is-inside-work-tree' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    expect(app(Git::class)->preflight())->toBe(GitStatus::NotARepo)
        ->and(GitStatus::NotARepo->skipReason())->toBe('not a git repository (git rev-parse failed)');
});

it('detects an empty history', function () {
    fakeGit([
        'git --version' => Process::result('git version 2.44.0'),
        'git rev-parse --is-inside-work-tree' => Process::result('true'),
        'git rev-parse HEAD' => Process::result(errorOutput: 'fatal: ambiguous argument', exitCode: 128),
    ]);

    expect(app(Git::class)->preflight())->toBe(GitStatus::NoCommits)
        ->and(GitStatus::NoCommits->skipReason())->toBe('no commit history');
});

it('flags shallow clones while staying ok', function () {
    fakeHealthyGit([
        'git rev-parse --is-shallow-repository' => Process::result('true'),
    ]);

    $git = app(Git::class);

    expect($git->preflight())->toBe(GitStatus::Ok)
        ->and($git->isShallow())->toBeTrue();
});

it('memoizes the preflight result', function () {
    fakeHealthyGit();

    $git = app(Git::class);
    $git->preflight();
    $git->preflight();

    Process::assertRanTimes(fn ($process) => $process->command === ['git', '--version'], 1);
});

it('runs git log with the base arguments prepended', function () {
    fakeGit([
        'git -c core.quotepath=off log --no-merges *' => Process::result("@alice\napp/A.php"),
    ]);

    $output = app(Git::class)->log(['--pretty=format:@%an', '--name-only', '--', '*.php']);

    expect($output)->toBe("@alice\napp/A.php\n");

    Process::assertRan(fn ($process) => $process->command === [
        'git', '-c', 'core.quotepath=off', 'log', '--no-merges',
        '--pretty=format:@%an', '--name-only', '--', '*.php',
    ]);
});

it('returns null when git log fails', function () {
    fakeGit([
        'git -c core.quotepath=off log *' => Process::result(errorOutput: 'fatal', exitCode: 128),
    ]);

    expect(app(Git::class)->log(['--name-only']))->toBeNull();
});

it('resolves the git context', function () {
    fakeHealthyGit([
        'git status --porcelain' => Process::result(" M src/Clever.php\n"),
    ]);

    $context = app(Git::class)->context();

    expect($context->available)->toBeTrue()
        ->and($context->branch)->toBe('main')
        ->and($context->sha)->toBe('1a2b3c4')
        ->and($context->dirty)->toBeTrue()
        ->and($context->toArray())->toBe([
            'available' => true,
            'branch' => 'main',
            'sha' => '1a2b3c4',
            'dirty' => true,
        ]);
});

it('resolves a clean working tree', function () {
    fakeHealthyGit();

    expect(app(Git::class)->context()->dirty)->toBeFalse();
});

it('resolves unavailable when git is broken', function () {
    fakeGit([
        'git --version' => Process::result(errorOutput: 'not found', exitCode: 127),
    ]);

    expect(app(Git::class)->context()->toArray())->toBe([
        'available' => false,
        'branch' => null,
        'sha' => null,
        'dirty' => null,
    ]);
});

it('runs git at the configured root', function () {
    config()->set('clever.root', '/tmp/some-repo');

    fakeHealthyGit();

    app(Git::class)->preflight();

    Process::assertRan(fn ($process) => $process->command === ['git', '--version']
        && $process->path === '/tmp/some-repo');
});
