<?php

declare(strict_types=1);

namespace Clever\Clever\Probes;

use Clever\Clever\Support\CleverConfig;
use Clever\Clever\Support\LineClassifier;
use Clever\Clever\Support\SourceFiles;

final class OwnedDiffProbe extends BaseProbe
{
    private const string CLASSIFIER_NOTE = 'Line classification is a heuristic, not a parser: comment markers inside strings and heredocs are misread, and PHP 8 attributes count as comments because cloc counts them that way. Expect the cloc one-liner and this probe to agree within a few lines per file, not to the line.';

    public function __construct(
        private readonly CleverConfig $config,
        private readonly SourceFiles $files,
        private readonly LineClassifier $classifier,
    ) {}

    public function key(): string
    {
        return 'c1';
    }

    public function name(): string
    {
        return 'The owned diff';
    }

    public function prints(): string
    {
        return 'One number: the lines you own on top of the framework. Your architecture is the diff you own — this prints the diff.';
    }

    public function handVerify(): string
    {
        $paths = implode(' ', $this->config->ownedPaths());

        return <<<CMD
        cloc --json {$paths} \\
          | php -r '\$s=json_decode(stream_get_contents(STDIN))->SUM;
              printf("owned diff: %d lines across %d files\\n",\$s->code,\$s->nFiles);'
        CMD;
    }

    public function pairsWith(): string
    {
        return 'A1 — the cloc ownership bar';
    }

    /**
     * @return list<string>
     */
    public function caveats(): array
    {
        return [
            'Run the same command inside a fresh laravel new; the difference between the two numbers is the diff you own.',
            'Adjust the directory list to wherever your app actually keeps owned code.',
        ];
    }

    protected function execute(): ProbeResult
    {
        $paths = $this->config->ownedPaths();
        $found = $this->files->files($paths, $this->config->ownedExtensions());

        if ($found['files'] === [] && count($found['missing']) === count($paths)) {
            return $this->skipped('none of the configured paths exist');
        }

        $totals = ['code' => 0, 'comment' => 0, 'blank' => 0];
        $fileCount = 0;
        $unreadable = 0;
        $byPath = [];
        $byExtension = [];

        foreach ($found['files'] as $absolute) {
            $contents = $this->files->read($absolute);

            if ($contents === null) {
                $unreadable++;

                continue;
            }

            $relative = $this->files->relative($absolute);
            $extension = $this->files->extensionOf($absolute);
            $counts = $this->classifier->count($contents, $extension);

            $fileCount++;
            $totals['code'] += $counts['code'];
            $totals['comment'] += $counts['comment'];
            $totals['blank'] += $counts['blank'];

            $configured = $this->configuredPathFor($relative, $paths);
            $byPath[$configured] ??= ['code_lines' => 0, 'files' => 0];
            $byPath[$configured]['code_lines'] += $counts['code'];
            $byPath[$configured]['files']++;

            $byExtension[$extension] ??= ['code_lines' => 0, 'files' => 0];
            $byExtension[$extension]['code_lines'] += $counts['code'];
            $byExtension[$extension]['files']++;
        }

        uasort($byExtension, static fn (array $a, array $b): int => $b['code_lines'] <=> $a['code_lines']);

        $warnings = array_map(
            static fn (string $path): string => sprintf('configured path does not exist: %s', $path),
            $found['missing'],
        );

        if ($unreadable > 0) {
            $warnings[] = sprintf('%d unreadable or binary files skipped', $unreadable);
        }

        return $this->ok(
            metrics: [
                'code_lines' => $totals['code'],
                'comment_lines' => $totals['comment'],
                'blank_lines' => $totals['blank'],
                'files' => $fileCount,
                'by_path' => $byPath,
                'by_extension' => $byExtension,
                'paths' => $paths,
            ],
            headline: sprintf('owned diff: %d lines across %d files', $totals['code'], $fileCount),
            notes: [self::CLASSIFIER_NOTE],
            warnings: $warnings,
        );
    }

    /**
     * @param  list<string>  $paths
     */
    private function configuredPathFor(string $relative, array $paths): string
    {
        foreach ($paths as $path) {
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                return $path;
            }
        }

        return dirname($relative);
    }
}
