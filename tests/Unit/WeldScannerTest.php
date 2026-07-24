<?php

declare(strict_types=1);

use Clever\Clever\Support\WeldScanner;

it('matches welded new like the grep pattern', function (string $line, int $expected) {
    expect((new WeldScanner)->scan($line)['welded_new'])->toHaveCount($expected);
})->with([
    'literal class' => ['$a = new Foo($b);', 1],
    'namespaced class' => ['$a = new Foo\Bar($b);', 1],
    'leading backslash misses (grep parity)' => ['$a = new \App\Foo($b);', 0],
    'new static carries a latent enabling point' => ['$a = new static();', 0],
    'new self' => ['$a = new self();', 0],
    'variable class' => ['$a = new $class();', 0],
    'no parenthesis' => ['$a = new Foo;', 0],
    'comment counts (grep parity)' => ['// new Foo(', 1],
    'no space means no match' => ['renewFoo(', 0],
    'substring inside renew still matches (grep parity — no word boundary)' => ['renew Foo(', 1],
]);

it('matches bare statics like the grep pipeline', function (string $line, int $expected) {
    expect((new WeldScanner)->scan($line)['bare_static'])->toHaveCount($expected);
})->with([
    'class static call' => ['Foo::bar($a);', 1],
    'self excluded' => ['self::bar($a);', 0],
    'static excluded' => ['static::create($a);', 0],
    'parent excluded' => ['parent::boot();', 0],
    'class constant is not a call' => ['$c = Foo::class;', 0],
    'object variable static' => ['$obj::method($a);', 0],
    'facade still counts toward the grep total' => ['DB::table("audits");', 1],
    'capitalized Self is counted (grep -vE is case-sensitive)' => ['Self::make();', 1],
    'capitalized Static is counted' => ['Static::boot();', 1],
    'capitalized Parent is counted' => ['Parent::handle();', 1],
]);

it('classifies the worked example from the talk design', function () {
    $contents = <<<'PHP'
    <?php

    use App\Models\Order;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Log as Logger;

    $order = Order::findOrFail($id);
    $tax = new TaxCalculator($order);
    $r = new \App\Pdf\Renderer();
    $c = new static();
    $i = new $rendererClass();
    Cache::put("inv", 1);
    Logger::info('built');
    DB::table('audits')->insert([]);
    $when = Carbon::now();
    return self::wrap($tax);
    PHP;

    $scan = (new WeldScanner)->scan($contents);

    expect($scan['welded_new'])->toHaveCount(1)
        ->and($scan['welded_new'][0]['match'])->toBe('new TaxCalculator(')
        ->and($scan['bare_static'])->toHaveCount(5);

    $kinds = array_column($scan['bare_static'], 'kind', 'class');

    expect($kinds)->toBe([
        'Order' => 'candidate',
        'Cache' => 'facade',
        'Logger' => 'facade',
        'DB' => 'facade',
        'Carbon' => 'candidate',
    ]);
});

it('detects group-use facade imports', function () {
    $contents = <<<'PHP'
    <?php

    use Illuminate\Support\Facades\{Cache, Log as Logger};

    Cache::put("x", 1);
    Logger::info("y");
    PHP;

    $kinds = array_column((new WeldScanner)->scan($contents)['bare_static'], 'kind', 'class');

    expect($kinds)->toBe(['Cache' => 'facade', 'Logger' => 'facade']);
});

it('detects host app facades by the Facades namespace heuristic', function () {
    $contents = <<<'PHP'
    <?php

    use App\Facades\Metrics;
    use App\Services\Parser;

    Metrics::increment("x");
    Parser::make("y");
    PHP;

    $kinds = array_column((new WeldScanner)->scan($contents)['bare_static'], 'kind', 'class');

    expect($kinds)->toBe(['Metrics' => 'facade', 'Parser' => 'candidate']);
});

it('honors configured extra facade aliases', function () {
    $scan = (new WeldScanner)->scan('Metrics::increment("x");', ['Metrics']);

    expect($scan['bare_static'][0]['kind'])->toBe('facade');
});

it('records line numbers', function () {
    $scan = (new WeldScanner)->scan("<?php\n\n\$a = new Foo();\nDB::table('x');\n");

    expect($scan['welded_new'][0]['line'])->toBe(3)
        ->and($scan['bare_static'][0]['line'])->toBe(4);
});
