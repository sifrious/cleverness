<?php

declare(strict_types=1);

use Clever\Clever\Support\LineClassifier;

it('classifies single php lines like the cloc heuristic', function (string $contents, array $expected) {
    expect(new LineClassifier)->count($contents, 'php')->toBe($expected);
})->with([
    'empty string' => ['', ['code' => 0, 'comment' => 0, 'blank' => 0]],
    'whitespace only' => ["   \n", ['code' => 0, 'comment' => 0, 'blank' => 1]],
    'line comment' => ["// x\n", ['code' => 0, 'comment' => 1, 'blank' => 0]],
    'hash comment' => ["# x\n", ['code' => 0, 'comment' => 1, 'blank' => 0]],
    'attribute counts as comment (cloc-compat)' => ["#[Attribute]\n", ['code' => 0, 'comment' => 1, 'blank' => 0]],
    'one-line block comment' => ["/* x */\n", ['code' => 0, 'comment' => 1, 'blank' => 0]],
    'docblock star line' => [" * text\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'open tag is code' => ["<?php\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'trailing comment still code' => ["\$a = 1; // t\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'comment marker in string is misread as code line' => ["\$s = 'http://x';\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'closing brace' => ["}\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'code after block comment' => ["/* x */ \$a = 1;\n", ['code' => 1, 'comment' => 0, 'blank' => 0]],
    'no trailing newline is not blank' => ['$a = 1;', ['code' => 1, 'comment' => 0, 'blank' => 0]],
]);

it('tracks multi-line block comments and counts inner blanks as blank', function () {
    $contents = <<<'PHP'
    <?php

    /*
     * heading

     */
    $a = 1;
    PHP;

    expect(new LineClassifier)->count($contents, 'php')
        ->toBe(['code' => 2, 'comment' => 3, 'blank' => 2]);
});

it('counts a known php fixture', function () {
    $contents = <<<'PHP'
    <?php

    declare(strict_types=1);

    // A comment.
    # Another comment.
    final class Example
    {
        /* short */
        public function go(): int
        {
            return 1; // done
        }
    }
    PHP;

    expect(new LineClassifier)->count($contents, 'php')
        ->toBe(['code' => 9, 'comment' => 3, 'blank' => 2]);
});

it('understands blade comments only in the blade profile', function () {
    $line = "{{-- hidden --}}\n";

    expect(new LineClassifier)->count($line, 'blade.php')->toBe(['code' => 0, 'comment' => 1, 'blank' => 0])
        ->and(new LineClassifier)->count($line, 'php')->toBe(['code' => 1, 'comment' => 0, 'blank' => 0]);
});

it('treats every non-blank json line as code', function () {
    expect(new LineClassifier)->count("{\n  // not a comment in json\n}\n", 'json')
        ->toBe(['code' => 3, 'comment' => 0, 'blank' => 0]);
});

it('produces identical counts for crlf and lf line endings', function () {
    $lf = "<?php\n// c\n\n\$a = 1;\n";
    $crlf = str_replace("\n", "\r\n", $lf);

    $classifier = new LineClassifier;

    expect($classifier->count($crlf, 'php'))->toBe($classifier->count($lf, 'php'));
});
