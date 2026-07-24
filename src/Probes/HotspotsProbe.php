<?php

declare(strict_types=1);

namespace Clever\Clever\Probes;

use Clever\Clever\Support\CleverConfig;
use Clever\Clever\Support\Git;
use Clever\Clever\Support\GitStatus;
use Clever\Clever\Support\LineClassifier;
use Clever\Clever\Support\NameOnlyLogParser;
use Clever\Clever\Support\SourceFiles;

final class HotspotsProbe extends BaseProbe
{
    private const string LOC_AXIS_DEFENSE = 'LOC on the axis: Jay et al. 2009 — LOC predicts ~90% of cyclomatic-complexity variance.';

    public function __construct(
        private readonly CleverConfig $config,
        private readonly Git $git,
        private readonly NameOnlyLogParser $parser,
        private readonly SourceFiles $files,
        private readonly LineClassifier $classifier,
    ) {}

    public function key(): string
    {
        return 'c4';
    }

    public function name(): string
    {
        return 'The hotspots';
    }

    public function prints(): string
    {
        return 'Churn x current size pairs for your most-touched files. Two axes, deliberately never multiplied — line count stands in for complexity, and that stand-in is defended, not lazy.';
    }

    public function handVerify(): string
    {
        $since = $this->config->churnSince();
        $sinceFlag = $since === null ? '' : sprintf(" --since='%s'", $since);
        $limit = $this->config->churnLimit();

        return <<<CMD
        git log --no-merges{$sinceFlag} --pretty=format: --name-only -- '*.php' \\
          | awk 'NF {ch[\$0]++} END {for (f in ch) print ch[f], f}' \\
          | sort -rn | head -{$limit}
        CMD;
    }

    public function pairsWith(): string
    {
        return 'A4 — the churn x LOC scatter';
    }

    /**
     * @return list<string>
     */
    public function caveats(): array
    {
        return [
            'Two axes, no product: a big quiet file and a small busy file are different problems, and multiplying them into one number would hide which one you have.',
            'Churn counts touches, not size of change; renames reset the count — this deliberately follows nothing.',
            'The chart plots current size against past churn — a file that shrank last week still carries its old commits.',
        ];
    }

    protected function execute(): ProbeResult
    {
        $status = $this->git->preflight();

        if ($status !== GitStatus::Ok) {
            return $this->skipped((string) $status->skipReason());
        }

        $since = $this->config->churnSince();

        $args = ['--pretty=format:@%H', '--name-only', '--', '*.php'];

        if ($since !== null) {
            array_unshift($args, '--since='.$since);
        }

        $output = $this->git->log($args);

        if ($output === null) {
            return $this->error('git log failed after a successful preflight');
        }

        $aggregate = $this->parser->aggregate($output);
        $ownedPaths = $this->config->ownedPaths();
        $limit = $this->config->churnLimit();
        $root = rtrim($this->config->root(), '/\\');

        $touched = [];

        foreach ($aggregate['perFile'] as $path => $entry) {
            if ($this->withinOwnedPaths($path, $ownedPaths)) {
                $touched[$path] = $entry['touches'];
            }
        }

        arsort($touched);

        $points = [];
        $missingOnDisk = 0;

        foreach ($touched as $path => $churn) {
            if (count($points) >= $limit) {
                break;
            }

            $contents = $this->files->read($root.'/'.$path);

            if ($contents === null) {
                $missingOnDisk++;

                continue;
            }

            $points[] = [
                'path' => $path,
                'churn' => $churn,
                'code_lines' => $this->classifier->count($contents, $this->files->extensionOf($path))['code'],
            ];
        }

        $warnings = $this->git->isShallow()
            ? ['shallow clone: history is truncated; churn and authorship under-count']
            : [];

        return $this->ok(
            metrics: [
                'since' => $since,
                'commits_scanned' => $aggregate['commits'],
                'files_touched' => count($aggregate['perFile']),
                'points' => $points,
                'files_missing_on_disk' => $missingOnDisk,
                'limit' => $limit,
                'loc_axis_defense' => self::LOC_AXIS_DEFENSE,
            ],
            headline: sprintf(
                'hotspots: churn x lines for the %d most-churned php files since %s',
                count($points),
                $since ?? 'the beginning of history',
            ),
            notes: [
                'Files outside the configured owned paths are ignored so the scatter matches the owned diff\'s scope; the one-liner lists everything.',
                'Runs git with -c core.quotepath=off so non-ASCII paths arrive unescaped; the one-liner does not pass that flag.',
            ],
            warnings: $warnings,
        );
    }

    /**
     * @param  list<string>  $ownedPaths
     */
    private function withinOwnedPaths(string $path, array $ownedPaths): bool
    {
        foreach ($ownedPaths as $owned) {
            if ($path === $owned || str_starts_with($path, $owned.'/')) {
                return true;
            }
        }

        return false;
    }
}
