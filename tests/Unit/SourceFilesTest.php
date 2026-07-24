<?php

declare(strict_types=1);

use Clever\Clever\Support\SourceFiles;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clever-src-'.uniqid();

    File::makeDirectory($this->root.'/app/Sub', 0755, true);
    File::makeDirectory($this->root.'/app/vendor', 0755, true);
    File::makeDirectory($this->root.'/app/generated', 0755, true);

    file_put_contents($this->root.'/app/One.php', "<?php\n");
    file_put_contents($this->root.'/app/Sub/Two.php', "<?php\n");
    file_put_contents($this->root.'/app/view.blade.php', "<div></div>\n");
    file_put_contents($this->root.'/app/notes.txt', "text\n");
    file_put_contents($this->root.'/app/vendor/Skipped.php', "<?php\n");
    file_put_contents($this->root.'/app/generated/Skipped.php', "<?php\n");

    config()->set('clever.root', $this->root);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('walks directories, filters extensions, and sorts by relative path', function () {
    $result = app(SourceFiles::class)->files(['app'], ['php']);

    $relative = array_map(app(SourceFiles::class)->relative(...), $result['files']);

    expect($relative)->toBe([
        'app/One.php',
        'app/Sub/Two.php',
        'app/generated/Skipped.php',
        'app/view.blade.php',
    ])->and($result['missing'])->toBe([]);
});

it('always excludes vendor and honors configured exclusions', function () {
    config()->set('clever.exclude', ['generated']);

    $relative = array_map(
        app(SourceFiles::class)->relative(...),
        app(SourceFiles::class)->files(['app'], ['php'])['files'],
    );

    expect($relative)->toBe(['app/One.php', 'app/Sub/Two.php', 'app/view.blade.php']);
});

it('reports configured paths that do not exist', function () {
    $result = app(SourceFiles::class)->files(['app', 'missing-dir'], ['php']);

    expect($result['missing'])->toBe(['missing-dir'])
        ->and($result['files'])->not->toBe([]);
});

it('de-duplicates files reachable through overlapping configured paths', function () {
    $relative = array_map(
        app(SourceFiles::class)->relative(...),
        app(SourceFiles::class)->files(['app', 'app/Sub'], ['php'])['files'],
    );

    expect($relative)->toBe([
        'app/One.php',
        'app/Sub/Two.php',
        'app/generated/Skipped.php',
        'app/view.blade.php',
    ]);
});

it('accepts a configured path that is a single file', function () {
    $result = app(SourceFiles::class)->files(['app/One.php'], ['php']);

    expect(array_map(app(SourceFiles::class)->relative(...), $result['files']))
        ->toBe(['app/One.php']);
});

it('classifies blade extensions distinctly', function () {
    $files = app(SourceFiles::class);

    expect($files->extensionOf('app/view.blade.php'))->toBe('blade.php')
        ->and($files->extensionOf('app/One.php'))->toBe('php')
        ->and($files->extensionOf('app/app.ts'))->toBe('ts')
        ->and($files->extensionOf('Makefile'))->toBe('');
});

it('reads text files and refuses binary ones', function () {
    file_put_contents($this->root.'/app/binary.php', "<?php\0\1\2");

    $files = app(SourceFiles::class);

    expect($files->read($this->root.'/app/One.php'))->toBe("<?php\n")
        ->and($files->read($this->root.'/app/binary.php'))->toBeNull()
        ->and($files->read($this->root.'/app/absent.php'))->toBeNull();
});

it('skips symlinked directories', function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('symlink creation needs elevated privileges on Windows');
    }

    File::makeDirectory($this->root.'/real');
    file_put_contents($this->root.'/real/Linked.php', "<?php\n");
    symlink($this->root.'/real', $this->root.'/app/link');

    $relative = array_map(
        app(SourceFiles::class)->relative(...),
        app(SourceFiles::class)->files(['app'], ['php'])['files'],
    );

    expect($relative)->not->toContain('app/link/Linked.php');
});
