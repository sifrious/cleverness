<?php

declare(strict_types=1);

namespace Clever\Clever\Probes;

enum ProbeStatus: string
{
    case Ok = 'ok';
    case Skipped = 'skipped';
    case Error = 'error';
}
