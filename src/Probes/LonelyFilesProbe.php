<?php

declare(strict_types=1);

namespace Clever\Clever\Probes;

use Clever\Clever\Support\CleverConfig;
use Clever\Clever\Support\Git;
use Clever\Clever\Support\GitStatus;
use Clever\Clever\Support\LineClassifier;
use Clever\Clever\Support\NameOnlyLogParser;
use Clever\Clever\Support\SourceFiles;

final class LonelyFilesProbe extends BaseProbe
{
    public function __construct(
        private readonly CleverConfig $config,
        private readonly Git $git,
        private readonly NameOnlyLogParser $parser,
        private readonly SourceFiles $files,
        private readonly LineClassifier $classifier,
    ) {}

    public function key(): string
    {
        return 'c3';
    }

    public function name(): string
    {
        return 'The lonely files';
    }

    public function prints(): string
    {
        return 'Your ten most-churned files with exactly one author, as "<commits> <path>". Clever code is lonely code — this prints the loneliness.';
    }

    public function handVerify(): string
    {
        return <<<'CMD'
        git log --no-merges --pretty=format:'@%an' --name-only -- '*.php' \
          | awk '/^@/ {au=substr($0,2); next}
                 NF {ch[$0]++; if (!seen[$0","au]++) auth[$0]++}
                 END {for (f in ch) if (auth[f]==1) print ch[f], f}' \
          | sort -rn | head
        CMD;
    }

    public function pairsWith(): string
    {
        return 'A3 — the lonely-file scatter';
    }

    /**
     * @return list<string>
     */
    public function caveats(): array
    {
        return [
            'One author may just mean new or trivial — filter tiny files before concluding anything.',
            'Needs commit history; run it where the project actually lives.',
        ];
    }

    protected function execute(): ProbeResult
    {
        $status = $this->git->preflight();

        if ($status !== GitStatus::Ok) {
            return $this->skipped((string) $status->skipReason());
        }

        $output = $this->git->log(['--pretty=format:@%an', '--name-only', '--', '*.php']);

        if ($output === null) {
            return $this->error('git log failed after a successful preflight');
        }

        $aggregate = $this->parser->aggregate($output);
        $minLines = $this->config->lonelyMinLines();
        $limit = $this->config->lonelyLimit();
        $root = rtrim($this->config->root(), '/\\');

        $lonely = [];
        $lonelyTotal = 0;
        $filteredMissing = 0;
        $filteredTiny = 0;

        foreach ($aggregate['perFile'] as $path => $entry) {
            if (count($entry['authors']) !== 1) {
                continue;
            }

            $lonelyTotal++;
            $absolute = $root.'/'.$path;
            $contents = $this->files->read($absolute);

            if ($contents === null) {
                $filteredMissing++;

                continue;
            }

            $codeLines = $this->classifier->count($contents, $this->files->extensionOf($path))['code'];

            if ($codeLines < $minLines) {
                $filteredTiny++;

                continue;
            }

            $lonely[] = [
                'path' => $path,
                'commits' => $entry['touches'],
                'author' => (string) array_key_first($entry['authors']),
                'code_lines' => $codeLines,
            ];
        }

        usort($lonely, static fn (array $a, array $b): int => [$b['commits'], $b['code_lines'], $a['path']] <=> [$a['commits'], $a['code_lines'], $b['path']]);

        $warnings = $this->git->isShallow()
            ? ['shallow clone: history is truncated; churn and authorship under-count']
            : [];

        return $this->ok(
            metrics: [
                'top' => array_slice($lonely, 0, $limit),
                'files_with_history' => count($aggregate['perFile']),
                'lonely_total' => $lonelyTotal,
                'filtered_missing' => $filteredMissing,
                'filtered_tiny' => $filteredTiny,
                'min_lines' => $minLines,
                'limit' => $limit,
            ],
            headline: sprintf(
                'lonely files: %d of %d churned php files have exactly one author',
                $lonelyTotal,
                count($aggregate['perFile']),
            ),
            notes: [
                'The shell command prints the unfiltered list; this probe additionally drops files missing from disk and files under the configured minimum code lines, per the first caveat.',
                'Authors compare by exact %an spelling — two spellings of one person count as two authors, exactly like the one-liner.',
                'Runs git with -c core.quotepath=off so non-ASCII paths arrive unescaped; the one-liner does not pass that flag.',
            ],
            warnings: $warnings,
        );
    }
}
