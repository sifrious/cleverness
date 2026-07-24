<?php

declare(strict_types=1);

use Clever\Clever\Probes\ProbeResult;
use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Tests\TestCase;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a real throwaway git repository from a list of commits, each
 * ['author' => name, 'files' => [path => contents]]. Caller must delete
 * the returned directory.
 *
 * @param  list<array{author: string, files: array<string, string>}>  $commits
 */
function makeGitRepo(array $commits): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-git-'.uniqid();

    File::makeDirectory($directory, 0755, true);

    $git = function (array $args) use ($directory): void {
        Process::path($directory)->run(['git', ...$args])->throw();
    };

    $git(['init', '--initial-branch=main']);

    foreach ($commits as $commit) {
        foreach ($commit['files'] as $file => $contents) {
            $path = $directory.'/'.$file;
            File::ensureDirectoryExists(dirname($path));
            file_put_contents($path, $contents);
        }

        $git(['add', '-A']);
        $git([
            '-c', 'user.name='.$commit['author'],
            '-c', 'user.email='.$commit['author'].'@example.com',
            '-c', 'commit.gpgsign=false',
            '-c', 'core.autocrlf=false',
            'commit', '-m', 'change', '--no-verify',
        ]);
    }

    return $directory;
}

/**
 * A minimal ok ProbeResult for report and command tests.
 *
 * @param  array<string, mixed>  $metrics
 */
function fakeProbeResult(string $key, array $metrics = ['n' => 1], ?string $headline = null): ProbeResult
{
    return new ProbeResult(
        key: $key,
        name: 'The '.$key.' probe',
        prints: 'What '.$key.' prints.',
        status: ProbeStatus::Ok,
        skipReason: null,
        metrics: $metrics,
        headline: $headline ?? $key.' headline — 42',
        handVerify: "git log --no-merges -- '*.php' # verify ".$key,
        pairsWith: 'A1 — the cloc ownership bar',
        caveats: ['One caveat.'],
        notes: [],
        warnings: [],
        durationMs: 5,
    );
}

/**
 * Fake git invocations by wildcard pattern against the joined argv.
 *
 * Laravel's Process::fake() string patterns match the shell-quoted command
 * line, which never matches array commands — so we match on the raw argv
 * joined with spaces instead. Unmatched commands fail loudly rather than
 * running a real process.
 *
 * @param  array<string, FakeProcessResult>  $map
 */
function fakeGit(array $map): void
{
    Process::fake(function (PendingProcess $process) use ($map): FakeProcessResult {
        $command = $process->command;
        $key = is_array($command) ? implode(' ', $command) : (string) $command;

        foreach ($map as $pattern => $result) {
            if (Str::is($pattern, $key)) {
                return $result;
            }
        }

        return Process::result(errorOutput: 'unfaked command: '.$key, exitCode: 1);
    });
}

/**
 * The standard healthy-repository fake: preflight passes, context resolves,
 * and git log output can be supplied per probe pattern.
 *
 * @param  array<string, FakeProcessResult>  $overrides
 */
function fakeHealthyGit(array $overrides = []): void
{
    $defaults = [
        'git --version' => Process::result('git version 2.44.0'),
        'git rev-parse --is-inside-work-tree' => Process::result('true'),
        'git rev-parse HEAD' => Process::result('abc1234def'),
        'git rev-parse --is-shallow-repository' => Process::result('false'),
        'git rev-parse --abbrev-ref HEAD' => Process::result("main\n"),
        'git rev-parse --short HEAD' => Process::result("1a2b3c4\n"),
        'git status --porcelain' => Process::result(''),
        'git -c core.quotepath=off log --no-merges *' => Process::result(''),
    ];

    fakeGit([...$overrides, ...array_diff_key($defaults, $overrides)]);
}
