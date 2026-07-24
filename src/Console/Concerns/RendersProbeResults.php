<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Concerns;

use Clever\Clever\Probes\ProbeResult;
use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Support\MetricRows;

/**
 * Shared terminal rendering: headline first (byte-identical to the report),
 * metric rows, warnings, caveats, then the hand-verify one-liner raw and
 * copy-pasteable. Never prints a score, grade, verdict, or rating.
 */
trait RendersProbeResults
{
    protected function renderResult(ProbeResult $result): void
    {
        match ($result->status) {
            ProbeStatus::Ok => $this->components->info((string) $result->headline),
            ProbeStatus::Skipped => $this->components->warn(sprintf('%s — skipped: %s', $result->name, (string) $result->skipReason)),
            ProbeStatus::Error => $this->components->error(sprintf('%s — error: %s', $result->name, (string) $result->skipReason)),
        };

        if ($result->metrics !== null) {
            foreach ((new MetricRows)->build($result->metrics) as $row) {
                $this->components->twoColumnDetail(($row['indent'] ? '  ' : '').$row['label'], $row['value']);
            }
        }

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($result->caveats !== []) {
            $this->components->bulletList($result->caveats);
        }

        $this->line('  Hand-verify:');

        foreach (explode("\n", $result->handVerify) as $verifyLine) {
            $this->line('  '.$verifyLine);
        }

        $this->newLine();
    }
}
