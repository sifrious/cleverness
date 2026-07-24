<?php

declare(strict_types=1);

use Clever\Clever\Support\NameOnlyLogParser;

it('aggregates commit counts and touches per file', function () {
    $output = implode("\n", [
        '@alice',
        'app/A.php',
        'app/B.php',
        '',
        '@alice',
        'app/A.php',
        '',
        '@bob',
        'app/B.php',
    ]);

    $result = (new NameOnlyLogParser)->aggregate($output);

    expect($result['commits'])->toBe(3)
        ->and($result['perFile']['app/A.php']['touches'])->toBe(2)
        ->and($result['perFile']['app/A.php']['authors'])->toBe(['alice' => true])
        ->and($result['perFile']['app/B.php']['touches'])->toBe(2)
        ->and($result['perFile']['app/B.php']['authors'])->toBe(['alice' => true, 'bob' => true]);
});

it('handles empty output', function () {
    expect((new NameOnlyLogParser)->aggregate(''))
        ->toBe(['commits' => 0, 'perFile' => []]);
});

it('handles commits that touch no matching files', function () {
    $result = (new NameOnlyLogParser)->aggregate("@alice\n@bob\napp/A.php\n");

    expect($result['commits'])->toBe(2)
        ->and($result['perFile']['app/A.php']['authors'])->toBe(['bob' => true]);
});

it('parses crlf line endings identically', function () {
    $lf = "@alice\napp/A.php\n\n@bob\napp/A.php\n";
    $crlf = str_replace("\n", "\r\n", $lf);

    $parser = new NameOnlyLogParser;

    expect($parser->aggregate($crlf))->toBe($parser->aggregate($lf));
});

it('treats a renamed file as a new path', function () {
    $result = (new NameOnlyLogParser)->aggregate("@alice\napp/Old.php\n\n@alice\napp/New.php\n");

    expect($result['perFile'])->toHaveKeys(['app/Old.php', 'app/New.php'])
        ->and($result['perFile']['app/Old.php']['touches'])->toBe(1)
        ->and($result['perFile']['app/New.php']['touches'])->toBe(1);
});
