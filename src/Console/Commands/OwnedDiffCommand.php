<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Probes\OwnedDiffProbe;

final class OwnedDiffCommand extends ProbeCommand
{
    /**
     * The command signature.
     */
    protected $signature = 'clever:owned-diff {--json : Print this probe\'s result as JSON}';

    /**
     * The command description.
     */
    protected $description = 'The owned diff — the lines you own on top of the framework.';

    protected function probeClass(): string
    {
        return OwnedDiffProbe::class;
    }
}
