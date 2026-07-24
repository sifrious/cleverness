<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Clever;
use Clever\Clever\Console\Concerns\RendersProbeResults;
use Clever\Clever\Probes\ProbeResult;
use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Report\ReportRepository;
use Illuminate\Console\Command;

final class ScanCommand extends Command
{
    use RendersProbeResults;

    /**
     * The command signature.
     */
    protected $signature = 'clever:scan {--json : Print the full report document as JSON}';

    /**
     * The command description.
     */
    protected $description = 'Run every registered probe and write the report file.';

    public function handle(Clever $clever, ReportRepository $reports): int
    {
        $results = $clever->scan();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $reports->read(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $this->exitCode($results);
        }

        foreach ($results as $result) {
            $this->renderResult($result);
        }

        $this->components->info(sprintf('Report written to %s', $reports->path()));

        return $this->exitCode($results);
    }

    /**
     * Probe errors fail the command; skips do not.
     *
     * @param  list<ProbeResult>  $results
     */
    private function exitCode(array $results): int
    {
        foreach ($results as $result) {
            if ($result->status === ProbeStatus::Error) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
