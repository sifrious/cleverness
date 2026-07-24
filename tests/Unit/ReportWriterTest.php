<?php

declare(strict_types=1);

use Clever\Clever\Report\ReportWriter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-writer-'.uniqid();
    $this->path = $this->dir.DIRECTORY_SEPARATOR.'report.json';

    File::makeDirectory($this->dir, 0755, true);
    config()->set('clever.report.path', $this->path);

    fakeHealthyGit();
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('writes pretty printed json with stable key order', function () {
    app(ReportWriter::class)->writeAll([fakeProbeResult('c1'), fakeProbeResult('c2')]);

    $raw = (string) file_get_contents($this->path);
    $decoded = json_decode($raw, true);

    expect(array_keys($decoded))->toBe([
        'schema_version', 'generated_at', 'app_name', 'environment', 'git', 'package_version', 'probes',
    ])
        ->and(array_keys($decoded['probes']))->toBe(['c1', 'c2'])
        ->and(array_keys($decoded['probes']['c1']))->toBe([
            'key', 'name', 'prints', 'status', 'skip_reason', 'metrics', 'headline',
            'hand_verify', 'pairs_with', 'caveats', 'notes', 'warnings', 'duration_ms',
        ])
        ->and($raw)->toEndWith("\n")
        ->and(str_contains($raw, '    "schema_version"'))->toBeTrue();
});

it('writes atomically leaving no temp file', function () {
    app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    expect(is_file($this->path))->toBeTrue()
        ->and(is_file($this->path.'.tmp'))->toBeFalse();
});

it('creates the report directory when missing', function () {
    config()->set('clever.report.path', $this->dir.'/nested/deeper/report.json');

    app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    expect(is_file($this->dir.'/nested/deeper/report.json'))->toBeTrue();
});

it('does not escape slashes or unicode', function () {
    app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    $raw = (string) file_get_contents($this->path);

    expect($raw)->toContain("git log --no-merges -- '*.php'")
        ->and($raw)->toContain('A1 — the cloc ownership bar');
});

it('records the git context and environment in the envelope', function () {
    $document = app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    expect($document['git'])->toBe([
        'available' => true,
        'branch' => 'main',
        'sha' => '1a2b3c4',
        'dirty' => false,
    ])
        ->and($document['environment'])->toBe('testing')
        ->and($document['schema_version'])->toBe(1)
        ->and($document['package_version'])->toBeString();
});

it('still writes when git is unavailable', function () {
    fakeGit([
        'git --version' => Process::result(errorOutput: 'not found', exitCode: 127),
    ]);

    $document = app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    expect($document['git'])->toBe([
        'available' => false,
        'branch' => null,
        'sha' => null,
        'dirty' => null,
    ]);
});

it('merges one probe preserving the rest byte for byte', function () {
    $writer = app(ReportWriter::class);

    $writer->writeAll([fakeProbeResult('c1'), fakeProbeResult('c3')]);
    $before = json_decode((string) file_get_contents($this->path), true);

    $writer->mergeOne(fakeProbeResult('c2', ['fresh' => true]));
    $after = json_decode((string) file_get_contents($this->path), true);

    expect($after['probes']['c1'])->toBe($before['probes']['c1'])
        ->and($after['probes']['c3'])->toBe($before['probes']['c3'])
        ->and($after['probes']['c2']['metrics'])->toBe(['fresh' => true])
        ->and(array_keys($after['probes']))->toBe(['c1', 'c2', 'c3'])
        ->and($writer->startedFresh())->toBeFalse();
});

it('merge starts fresh silently when the file is missing', function () {
    $writer = app(ReportWriter::class);

    $document = $writer->mergeOne(fakeProbeResult('c2'));

    expect(array_keys($document['probes']))->toBe(['c2'])
        ->and($writer->startedFresh())->toBeFalse();
});

it('merge starts fresh and flags it when the file is corrupt', function () {
    file_put_contents($this->path, '{not json');

    $writer = app(ReportWriter::class);
    $document = $writer->mergeOne(fakeProbeResult('c2'));

    expect($writer->startedFresh())->toBeTrue()
        ->and(array_keys($document['probes']))->toBe(['c2']);

    $reread = json_decode((string) file_get_contents($this->path), true);

    expect($reread['schema_version'])->toBe(1);
});

it('scan overwrite drops unknown probe keys', function () {
    $writer = app(ReportWriter::class);

    $writer->mergeOne(fakeProbeResult('zz'));
    $document = $writer->writeAll([fakeProbeResult('c1')]);

    expect(array_keys($document['probes']))->toBe(['c1']);
});
