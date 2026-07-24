<?php

declare(strict_types=1);

namespace Clever\Clever\Http\Controllers;

use Clever\Clever\Clever;
use Clever\Clever\Report\ReportRepository;
use Clever\Clever\Support\MetricRows;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * The panel: a read-only GET that renders the last report. It reads the
 * file and nothing else — it never scans, never runs git — and it 404s
 * whenever Clever is disabled, belt and braces on top of the provider that
 * never registered this route in the first place.
 */
final class PanelController
{
    public function __construct(
        private readonly MetricRows $rows,
        private readonly ViewFactory $views,
    ) {}

    public function __invoke(Clever $clever, ReportRepository $reports): View
    {
        abort_unless($clever->enabled(), 404);

        $report = $reports->read();

        return $this->views->make('clever::panel', [
            'report' => $report,
            'probes' => $this->probes($report),
            'wasCorrupt' => $reports->wasCorrupt(),
            'reportPath' => $reports->path(),
            'generatedAgo' => $this->generatedAgo($report),
        ]);
    }

    /**
     * Probe entries with display-ready metric rows attached — the Blade
     * view only echoes and loops.
     *
     * @param  array<string, mixed>|null  $report
     * @return list<array<string, mixed>>
     */
    private function probes(?array $report): array
    {
        if ($report === null || ! is_array($report['probes'] ?? null)) {
            return [];
        }

        $probes = [];

        foreach ($report['probes'] as $probe) {
            if (! is_array($probe)) {
                continue;
            }

            $metrics = $probe['metrics'] ?? null;
            $probe['metric_rows'] = is_array($metrics) ? $this->rows->build($metrics) : [];

            $probes[] = $probe;
        }

        return $probes;
    }

    /**
     * @param  array<string, mixed>|null  $report
     */
    private function generatedAgo(?array $report): ?string
    {
        $generatedAt = $report['generated_at'] ?? null;

        if (! is_string($generatedAt)) {
            return null;
        }

        try {
            return Date::parse($generatedAt)->diffForHumans();
        } catch (Throwable) {
            return null;
        }
    }
}
