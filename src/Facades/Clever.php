<?php

declare(strict_types=1);

namespace Clever\Clever\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled()
 * @method static list<\Clever\Clever\Probes\Probe> probes()
 * @method static \Clever\Clever\Probes\Probe|null probe(string $key)
 * @method static list<\Clever\Clever\Probes\ProbeResult> scan()
 *
 * @see \Clever\Clever\Clever
 */
class Clever extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Clever\Clever\Clever::class;
    }
}
