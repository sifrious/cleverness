<?php

declare(strict_types=1);

use Clever\Clever\Probes\ProbeResult;
use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Report\ReportWriter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-panel-'.uniqid();
    File::makeDirectory($this->dir, 0755, true);

    config()->set('clever.report.path', $this->dir.DIRECTORY_SEPARATOR.'report.json');

    fakeHealthyGit();
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('renders the report headlines, rule plate, and detail rows', function () {
    app(ReportWriter::class)->writeAll([
        fakeProbeResult('c1', ['code_lines' => 7550, 'top' => [['path' => 'app/Big.php', 'commits' => 24]]], 'owned diff: 7550 lines across 89 files'),
    ]);

    $response = $this->get(route('clever.panel'));

    $response->assertOk()
        ->assertSee('owned diff: 7550 lines across 89 files')
        ->assertSee('C1')
        ->assertSee(strtoupper('The c1 probe'))
        ->assertSee('The probe must recompute the chart')
        ->assertSee('no parsers, no AST')
        ->assertSee('Code Lines')
        ->assertSee('app/Big.php')
        ->assertSee('git log --no-merges')
        ->assertSee('A1')
        ->assertSee('One caveat.')
        ->assertSee('main')
        ->assertSee('1a2b3c4')
        ->assertSee('clever:scan');
});

it('renders a skipped probe with its reason and hand-verify command', function () {
    app(ReportWriter::class)->writeAll([
        new ProbeResult(
            key: 'c3',
            name: 'The lonely files',
            prints: 'What c3 prints.',
            status: ProbeStatus::Skipped,
            skipReason: 'not a git repository (git rev-parse failed)',
            metrics: null,
            headline: null,
            handVerify: "git log --no-merges --pretty=format:'@%an'",
            pairsWith: 'A3 — the lonely-file scatter',
            caveats: ['Needs commit history; run it where the project actually lives.'],
            notes: [],
            warnings: [],
        ),
    ]);

    $this->get(route('clever.panel'))
        ->assertOk()
        ->assertSee('SKIPPED')
        ->assertSee('not a git repository')
        ->assertSee('git log --no-merges')
        ->assertSee('Needs commit history');
});

it('renders without crashing when an ok probe entry is missing its headline', function () {
    File::ensureDirectoryExists(dirname((string) config('clever.report.path')));
    file_put_contents((string) config('clever.report.path'), json_encode([
        'schema_version' => 1,
        'generated_at' => '2026-07-23T10:00:00+00:00',
        'probes' => [
            'c1' => ['key' => 'c1', 'name' => 'The owned diff', 'status' => 'ok'],
        ],
    ]));

    $this->get(route('clever.panel'))->assertOk()->assertSee('C1');
});

it('shows the empty state when no report exists', function () {
    $this->get(route('clever.panel'))
        ->assertOk()
        ->assertSee('No report yet')
        ->assertSee('php artisan clever:scan');
});

it('shows a parse warning when the report is corrupt', function () {
    file_put_contents((string) config('clever.report.path'), '{not json');

    $this->get(route('clever.panel'))
        ->assertOk()
        ->assertSee('No report yet')
        ->assertSee('could not be parsed');
});

it('ships zero javascript and makes no external requests', function () {
    app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    $content = $this->get(route('clever.panel'))->getContent();

    expect(str_contains($content, '<script'))->toBeFalse()
        ->and(str_contains($content, 'href="http'))->toBeFalse()
        ->and(str_contains($content, "href='http"))->toBeFalse()
        ->and(str_contains($content, 'src='))->toBeFalse()
        ->and(str_contains($content, '@import'))->toBeFalse()
        ->and(str_contains($content, 'fonts.googleapis'))->toBeFalse();
});

it('returns 404 when disabled at runtime after the route was registered', function () {
    app(ReportWriter::class)->writeAll([fakeProbeResult('c1')]);

    config()->set('clever.enabled', false);

    $this->get('/clever')->assertNotFound();
});
