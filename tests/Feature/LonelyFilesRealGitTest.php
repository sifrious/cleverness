<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('computes lonely files from a real repository end to end', function () {
    try {
        Process::run(['git', '--version'])->throw();
    } catch (Throwable) {
        $this->markTestSkipped('git unavailable');
    }

    $big = "<?php\n".str_repeat("\$a = 1;\n", 60);

    $repo = makeGitRepo([
        ['author' => 'alice', 'files' => ['app/Big.php' => $big."// v1\n", 'app/Shared.php' => $big, 'app/Tiny.php' => "<?php\n\$a = 1;\n"]],
        ['author' => 'alice', 'files' => ['app/Big.php' => $big."// v2\n"]],
        ['author' => 'alice', 'files' => ['app/Big.php' => $big."// v3\n"]],
        ['author' => 'bob', 'files' => ['app/Shared.php' => $big."// bob\n"]],
    ]);

    $reportPath = $repo.'/report.json';

    config()->set('clever.root', $repo);
    config()->set('clever.report.path', $reportPath);

    try {
        $this->artisan('clever:lonely-files')->assertSuccessful();

        $document = json_decode((string) file_get_contents($reportPath), true);
        $metrics = $document['probes']['c3']['metrics'];
        $paths = array_column($metrics['top'], 'path');

        expect($document['probes']['c3']['status'])->toBe('ok')
            ->and($paths)->toContain('app/Big.php')
            ->and($paths)->not->toContain('app/Shared.php')
            ->and($paths)->not->toContain('app/Tiny.php');

        $big = collect($metrics['top'])->firstWhere('path', 'app/Big.php');

        expect($big['commits'])->toBe(3)
            ->and($big['author'])->toBe('alice')
            ->and($big['code_lines'])->toBeGreaterThan(50);
    } finally {
        File::deleteDirectory($repo);
    }
});
