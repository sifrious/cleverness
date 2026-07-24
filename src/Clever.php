<?php

declare(strict_types=1);

namespace Clever\Clever;

use Clever\Clever\Probes\Probe;
use Clever\Clever\Probes\ProbeResult;
use Clever\Clever\Report\ReportWriter;
use Clever\Clever\Support\CleverConfig;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

final class Clever
{
    public function __construct(
        private readonly Application $app,
        private readonly CleverConfig $config,
        private readonly ReportWriter $writer,
    ) {}

    /**
     * Whether Clever may register anything at all. Production registers
     * nothing — config cannot override that. Local and testing default to
     * on; any other environment has to be asked, through clever.enabled.
     */
    public function enabled(): bool
    {
        if ($this->app->environment('production')) {
            return false;
        }

        if ($this->app->environment('local', 'testing')) {
            return $this->config->enabled() !== false;
        }

        return $this->config->enabled() === true;
    }

    /**
     * The registered probes, container-resolved from config('clever.probes')
     * in order — the package's own enabling point.
     *
     * @return list<Probe>
     */
    public function probes(): array
    {
        $probes = [];

        foreach ($this->config->probeClasses() as $class) {
            $probe = $this->app->make($class);

            if (! $probe instanceof Probe) {
                throw new InvalidArgumentException(sprintf(
                    'Configured clever probe [%s] must implement %s.',
                    $class,
                    Probe::class,
                ));
            }

            $probes[] = $probe;
        }

        return $probes;
    }

    public function probe(string $key): ?Probe
    {
        foreach ($this->probes() as $probe) {
            if ($probe->key() === $key) {
                return $probe;
            }
        }

        return null;
    }

    /**
     * Run every registered probe and overwrite the report file.
     *
     * @return list<ProbeResult>
     */
    public function scan(): array
    {
        $results = [];

        foreach ($this->probes() as $probe) {
            $results[] = $probe->run();
        }

        $this->writer->writeAll($results);

        return $results;
    }
}
