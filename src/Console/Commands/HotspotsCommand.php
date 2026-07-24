<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Probes\HotspotsProbe;

final class HotspotsCommand extends ProbeCommand
{
    /**
     * The command signature.
     */
    protected $signature = 'clever:hotspots {--json : Print this probe\'s result as JSON}';

    /**
     * The command description.
     */
    protected $description = 'The hotspots — churn x current size pairs for your most-touched files.';

    protected function probeClass(): string
    {
        return HotspotsProbe::class;
    }
}
