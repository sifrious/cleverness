<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Console\Concerns\RendersProbeResults;
use Clever\Clever\Probes\Probe;
use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Report\ReportRepository;
use Clever\Clever\Report\ReportWriter;
use Illuminate\Console\Command;

/**
 * Base for the single-probe commands: run one probe, merge only its
 * section into the report, render.
 */
abstract class ProbeCommand extends Command
{
    use RendersProbeResults;

    /**
     * @return class-string<Probe>
     */
    abstract protected function probeClass(): string;

    public function handle(ReportWriter $writer, ReportRepository $reports): int
    {
        $probe = $this->laravel->make($this->probeClass());
        $result = $probe->run();

        $writer->mergeOne($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $result->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $result->status === ProbeStatus::Error ? self::FAILURE : self::SUCCESS;
        }

        if ($writer->startedFresh()) {
            $this->components->warn('could not read existing report; starting fresh');
        }

        $this->renderResult($result);

        $this->components->info(sprintf('Report updated at %s', $reports->path()));

        return $result->status === ProbeStatus::Error ? self::FAILURE : self::SUCCESS;
    }
}
