<?php

declare(strict_types=1);

namespace Clever\Clever\Console\Commands;

use Clever\Clever\Probes\LonelyFilesProbe;

final class LonelyFilesCommand extends ProbeCommand
{
    /**
     * The command signature.
     */
    protected $signature = 'clever:lonely-files {--json : Print this probe\'s result as JSON}';

    /**
     * The command description.
     */
    protected $description = 'The lonely files — your most-churned files with exactly one author.';

    protected function probeClass(): string
    {
        return LonelyFilesProbe::class;
    }
}
