<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Probes\WeldedCallSitesProbe;

final class WeldsCommand extends ProbeCommand
{
    /**
     * The command signature.
     */
    protected $signature = 'clever:welds {--json : Print this probe\'s result as JSON}';

    /**
     * The command description.
     */
    protected $description = 'The welded call sites — literal news and bare statics with no enabling point.';

    protected function probeClass(): string
    {
        return WeldedCallSitesProbe::class;
    }
}
