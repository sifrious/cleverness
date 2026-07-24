<?php

declare(strict_types=1);

use Clever\Clever\Probes\ProbeStatus;
use Clever\Clever\Probes\WeldedCallSitesProbe;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-c2-'.uniqid();

    File::makeDirectory($this->root.'/app', 0755, true);

    file_put_contents($this->root.'/app/Invoice.php', <<<'PHP'
    <?php

    use Illuminate\Support\Facades\Cache;

    $tax = new TaxCalculator($order);
    Cache::put("inv", 1);
    Carbon::now();
    PHP);

    file_put_contents($this->root.'/app/Order.php', "<?php\n\$o = Order::findOrFail(\$id);\n");

    config()->set('clever.root', $this->root);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('counts welds and splits facades from candidates without altering the grep totals', function () {
    $result = app(WeldedCallSitesProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Ok)
        ->and($result->headline)->toBe('welded new: 1 / bare static calls: 3')
        ->and($result->metrics)->toMatchArray([
            'welded_new' => 1,
            'bare_static_total' => 3,
            'bare_static_facades' => 1,
            'bare_static_candidates' => 2,
            'files_scanned' => 2,
            'sites_truncated' => false,
        ])
        ->and($result->metrics['welded_new_sites'])->toBe([
            ['file' => 'app/Invoice.php', 'line' => 5, 'match' => 'new TaxCalculator('],
        ])
        ->and(array_column($result->metrics['bare_static_sites'], 'kind'))->toBe(['facade', 'candidate', 'candidate'])
        ->and($result->handVerify)->toContain("grep -rE --include='*.php'");
});

it('caps site lists at max_sites without truncating totals', function () {
    config()->set('clever.welds.max_sites', 1);

    $result = app(WeldedCallSitesProbe::class)->run();

    expect($result->metrics['bare_static_total'])->toBe(3)
        ->and($result->metrics['bare_static_sites'])->toHaveCount(1)
        ->and($result->metrics['sites_truncated'])->toBeTrue();
});

it('skips when no configured paths exist', function () {
    config()->set('clever.welds.paths', ['missing']);

    $result = app(WeldedCallSitesProbe::class)->run();

    expect($result->status)->toBe(ProbeStatus::Skipped)
        ->and($result->skipReason)->toBe('none of the configured paths exist');
});
