<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Clever\Clever')
    ->toUseStrictTypes();

arch('probe classes implement the probe contract')
    ->expect('Clever\Clever\Probes')
    ->classes()
    ->toImplement('Clever\Clever\Probes\Probe')
    ->ignoring('Clever\Clever\Probes\ProbeResult');

arch('probe results are readonly')
    ->expect('Clever\Clever\Probes\ProbeResult')
    ->toBeReadonly();

arch('the package never shells out directly — git only via the Process facade')
    ->expect(['proc_open', 'popen', 'exec', 'shell_exec', 'system', 'passthru'])
    ->each->not->toBeUsed();
