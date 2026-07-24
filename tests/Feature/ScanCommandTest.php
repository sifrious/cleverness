<?php

declare(strict_types=1);

use Clever\Clever\Tests\Support\ExplodingProbe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-scan-'.uniqid();
    $this->reportPath = $this->root.DIRECTORY_SEPARATOR.'report.json';

    File::makeDirectory($this->root.'/app', 0755, true);
    file_put_contents($this->root.'/app/Big.php', "<?php\n".str_repeat("\$a = new Foo();\n", 40));

    config()->set('clever.root', $this->root);
    config()->set('clever.owned_diff.paths', ['app']);
    config()->set('clever.report.path', $this->reportPath);

    fakeHealthyGit([
        'git -c core.quotepath=off log --no-merges --pretty=format:@%an *' => Process::result("@alice\napp/Big.php\n\n@alice\napp/Big.php\n"),
        'git -c core.quotepath=off log --no-merges --since=* *' => Process::result("@aaa1\napp/Big.php\n"),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('writes a report with every registered probe', function () {
    $this->artisan('clever:scan')->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect($document['schema_version'])->toBe(1)
        ->and(array_keys($document['probes']))->toBe(['c1', 'c2', 'c3', 'c4'])
        ->and(array_column($document['probes'], 'status'))->toBe(['ok', 'ok', 'ok', 'ok']);

    foreach ($document['probes'] as $probe) {
        expect($probe['duration_ms'])->toBeInt()->toBeGreaterThanOrEqual(0);
    }
});

it('prints every written headline byte for byte — the coherence pin', function () {
    Artisan::call('clever:scan');
    $output = Artisan::output();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    foreach ($document['probes'] as $probe) {
        expect($probe['headline'])->toBeString()
            ->and($output)->toContain($probe['headline']);
    }
});

it('prints the hand-verify commands', function () {
    Artisan::call('clever:scan');
    $output = Artisan::output();

    expect($output)->toContain('Hand-verify:')
        ->and($output)->toContain('cloc --json app')
        ->and($output)->toContain("grep -rE --include='*.php'")
        ->and($output)->toContain('git log --no-merges');
});

it('emits the report on stdout with --json and still writes the file', function () {
    Artisan::call('clever:scan', ['--json' => true]);

    $decoded = json_decode(Artisan::output(), true);
    $file = json_decode((string) file_get_contents($this->reportPath), true);

    expect($decoded)->toBe($file)
        ->and(array_keys($decoded['probes']))->toBe(['c1', 'c2', 'c3', 'c4']);
});

it('overwrites the previous report atomically dropping unknown keys', function () {
    file_put_contents($this->reportPath, json_encode([
        'schema_version' => 1,
        'generated_at' => '2020-01-01T00:00:00+00:00',
        'probes' => ['zz' => ['key' => 'zz']],
    ]));

    $this->artisan('clever:scan')->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect(array_keys($document['probes']))->toBe(['c1', 'c2', 'c3', 'c4'])
        ->and($document['generated_at'])->not->toBe('2020-01-01T00:00:00+00:00')
        ->and(is_file($this->reportPath.'.tmp'))->toBeFalse();
});

it('records the git context in the envelope', function () {
    $this->artisan('clever:scan')->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect($document['git'])->toBe([
        'available' => true,
        'branch' => 'main',
        'sha' => '1a2b3c4',
        'dirty' => false,
    ]);
});

it('skips history probes without git and still exits zero', function () {
    fakeGit([
        'git --version' => Process::result(errorOutput: 'not found', exitCode: 127),
    ]);

    $this->artisan('clever:scan')->assertSuccessful();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect($document['git']['available'])->toBeFalse()
        ->and($document['probes']['c1']['status'])->toBe('ok')
        ->and($document['probes']['c2']['status'])->toBe('ok')
        ->and($document['probes']['c3']['status'])->toBe('skipped')
        ->and($document['probes']['c3']['skip_reason'])->toBe('git binary not available')
        ->and($document['probes']['c4']['status'])->toBe('skipped')
        ->and($document['probes']['c3']['hand_verify'])->toContain('git log')
        ->and($document['probes']['c3']['metrics'])->toBeNull()
        ->and($document['probes']['c3']['headline'])->toBeNull();
});

it('fails when a probe errors', function () {
    config()->set('clever.probes', [ExplodingProbe::class]);

    $this->artisan('clever:scan')->assertFailed();

    $document = json_decode((string) file_get_contents($this->reportPath), true);

    expect($document['probes']['c9']['status'])->toBe('error')
        ->and($document['probes']['c9']['skip_reason'])->toBe('boom');
});
