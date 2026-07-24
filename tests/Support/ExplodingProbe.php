<?php

declare(strict_types=1);

namespace Clever\Clever\Tests\Support;

use Clever\Clever\Probes\BaseProbe;
use Clever\Clever\Probes\ProbeResult;
use RuntimeException;

final class ExplodingProbe extends BaseProbe
{
    public function key(): string
    {
        return 'c9';
    }

    public function name(): string
    {
        return 'The exploding probe';
    }

    public function prints(): string
    {
        return 'Nothing — it always throws.';
    }

    public function handVerify(): string
    {
        return 'true';
    }

    public function pairsWith(): string
    {
        return 'nothing';
    }

    /**
     * @return list<string>
     */
    public function caveats(): array
    {
        return [];
    }

    protected function execute(): ProbeResult
    {
        throw new RuntimeException('boom');
    }
}
