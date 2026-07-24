<?php

declare(strict_types=1);

use Clever\Clever\Report\ReportRepository;
use Clever\Clever\Report\ReportWriter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-report-'.uniqid();
    $this->path = $this->dir.DIRECTORY_SEPARATOR.'report.json';

    File::makeDirectory($this->dir, 0755, true);
    config()->set('clever.report.path', $this->path);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('returns null for a missing file without flagging corruption', function () {
    $repository = app(ReportRepository::class);

    expect($repository->read())->toBeNull()
        ->and($repository->wasCorrupt())->toBeFalse()
        ->and($repository->exists())->toBeFalse();
});

it('returns null and flags corruption for invalid json', function () {
    file_put_contents($this->path, '{not json');

    $repository = app(ReportRepository::class);

    expect($repository->read())->toBeNull()
        ->and($repository->wasCorrupt())->toBeTrue();
});

it('returns null and flags corruption for a wrong schema version', function () {
    file_put_contents($this->path, '{"schema_version": 99, "probes": {}}');

    $repository = app(ReportRepository::class);

    expect($repository->read())->toBeNull()
        ->and($repository->wasCorrupt())->toBeTrue();
});

it('round-trips a document written by the writer', function () {
    fakeHealthyGit();

    $written = app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    expect(app(ReportRepository::class)->read())->toBe($written);
});

it('defaults the path to storage', function () {
    config()->set('clever.report.path', null);

    expect(app(ReportRepository::class)->path())->toBe(storage_path('clever/report.json'));
});
