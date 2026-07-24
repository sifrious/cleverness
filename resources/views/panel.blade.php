<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clever{{ is_array($report) && is_string($report['app_name'] ?? null) ? ' — '.$report['app_name'] : '' }}</title>
<style>
    :root {
        --bg: #f6f5f2; --plate: #fffefb; --line: #d8d4cc; --ink: #1a1a18;
        --soft: #55534d; --muted: #8a877f; --accent: #0f766e; --warn: #b45309;
        --leader: #b8b4aa;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: var(--bg); color: var(--ink);
        font: 14px/1.55 -apple-system, "Segoe UI", system-ui, sans-serif;
        padding: 32px 16px 0;
    }
    .wrap { max-width: 960px; margin: 0 auto; }
    .meta { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
    .meta h1 { font-size: 18px; font-weight: 700; }
    .meta .env { color: var(--muted); font-weight: 400; }
    .mono, pre, .headline, .gitline { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .gitline { font-size: 12px; color: var(--soft); }
    .stale { font-size: 12px; color: var(--muted); margin-bottom: 20px; }
    .plate { background: var(--plate); border: 1px solid var(--line); margin-bottom: 18px; }
    .plate-head {
        display: flex; justify-content: space-between; align-items: center;
        padding: 8px 16px; border-bottom: 1px solid var(--line);
        font-size: 10px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;
    }
    .bar { width: 44px; height: 3px; background: var(--accent); flex: none; }
    .plate-body { padding: 16px; display: grid; gap: 12px; }
    .prints { color: var(--soft); }
    .headline { font-size: 24px; overflow-wrap: anywhere; }
    .row { display: flex; align-items: baseline; gap: 10px; font-size: 12px; }
    .row-label { font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; font-size: 10px; flex: none; }
    .leader { flex: 1; border-bottom: 1px dotted var(--leader); min-width: 24px; }
    .row-value { color: var(--soft); text-align: right; max-width: 60ch; overflow-wrap: anywhere; }
    .row.indent { padding-left: 14px; }
    .row.skip .row-value, .row.skip .row-label { color: var(--warn); }
    .row.note .row-value { color: var(--muted); }
    pre {
        font-size: 11.5px; padding: 10px 12px; background: var(--bg);
        border: 1px solid var(--line); overflow-x: auto; white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .duration { text-align: right; font-size: 11px; color: var(--muted); }
    .empty { text-align: center; padding: 40px 16px; display: grid; gap: 12px; justify-items: center; }
    .empty pre { display: inline-block; }
    .muted { color: var(--muted); font-size: 12px; }
    .footer {
        display: flex; justify-content: space-between; gap: 12px;
        border-top: 1px solid var(--line); margin-top: 28px; padding: 12px 0 24px;
        font-size: 11px; color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    .base { height: 4px; background: var(--accent); margin: 0 -16px; }
</style>
</head>
<body>
<div class="wrap">
    @if ($report === null)
        <div class="plate">
            <div class="plate-head"><span>00 — NO REPORT</span><span class="bar"></span></div>
            <div class="plate-body empty">
                <p>No report yet. Run:</p>
                <pre>php artisan clever:scan</pre>
                <p class="muted">The panel only reads {{ $reportPath }} — it never scans on its own.</p>
                @if ($wasCorrupt)
                    <p class="muted">The existing report could not be parsed and will be replaced by the next scan.</p>
                @endif
            </div>
        </div>
    @else
        <div class="meta">
            <h1>{{ $report['app_name'] ?? 'Clever' }} <span class="env">· {{ $report['environment'] ?? '' }}</span></h1>
            <span class="gitline">
                @if (is_array($report['git'] ?? null) && ($report['git']['available'] ?? false))
                    {{ $report['git']['branch'] }} · {{ $report['git']['sha'] }} · {{ ($report['git']['dirty'] ?? false) ? 'dirty' : 'clean' }}
                @else
                    git unavailable
                @endif
            </span>
        </div>
        <p class="stale">
            generated {{ $report['generated_at'] ?? 'unknown' }}@if ($generatedAgo !== null) — {{ $generatedAgo }}@endif.
            If the repository has changed since, rerun <span class="mono">php artisan clever:scan</span>.
        </p>

        <div class="plate">
            <div class="plate-head"><span>00 — THE RULE</span><span class="bar"></span></div>
            <div class="plate-body">
                <p class="prints">The probe must recompute the chart. Whatever number appears on a slide, the command that produced it runs on your repo the same afternoon.</p>
                <div class="row"><span class="row-label">Rule</span><span class="leader"></span><span class="row-value">PHP-native recomputation of the same shell probes — git, line counts, and pattern matches only; no parsers, no AST</span></div>
                <div class="row"><span class="row-label">Rule</span><span class="leader"></span><span class="row-value">every number hand-verifiable: the shell command that checks it is printed under each plate</span></div>
                <div class="row"><span class="row-label">Rule</span><span class="leader"></span><span class="row-value">numbers are an illustration of this repo — n=1, not a benchmark</span></div>
            </div>
        </div>

        @foreach ($probes as $probe)
            <div class="plate">
                <div class="plate-head">
                    <span>{{ strtoupper($probe['key'] ?? '') }} — {{ strtoupper($probe['name'] ?? '') }}</span>
                    <span class="bar"></span>
                </div>
                <div class="plate-body">
                    <p class="prints">{{ $probe['prints'] ?? '' }}</p>

                    @if (($probe['status'] ?? '') === 'ok')
                        <div class="headline">{{ $probe['headline'] ?? '' }}</div>
                        @foreach ($probe['metric_rows'] as $row)
                            <div class="row{{ $row['indent'] ? ' indent' : '' }}">
                                <span class="row-label">{{ $row['label'] }}</span>
                                <span class="leader"></span>
                                <span class="row-value">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="row skip">
                            <span class="row-label">Status</span>
                            <span class="leader"></span>
                            <span class="row-value">{{ strtoupper($probe['status'] ?? '') }} — {{ $probe['skip_reason'] ?? '' }}</span>
                        </div>
                    @endif

                    @foreach ($probe['warnings'] ?? [] as $warning)
                        <div class="row skip"><span class="row-label">Warning</span><span class="leader"></span><span class="row-value">{{ $warning }}</span></div>
                    @endforeach

                    <div>
                        <div class="row"><span class="row-label">Hand-verify</span><span class="leader"></span></div>
                        <pre>{{ $probe['hand_verify'] ?? '' }}</pre>
                    </div>

                    <div class="row"><span class="row-label">Pairs with</span><span class="leader"></span><span class="row-value">{{ $probe['pairs_with'] ?? '' }}</span></div>

                    @foreach ($probe['caveats'] ?? [] as $caveat)
                        <div class="row"><span class="row-label">Caveat</span><span class="leader"></span><span class="row-value">{{ $caveat }}</span></div>
                    @endforeach

                    @foreach ($probe['notes'] ?? [] as $note)
                        <div class="row note"><span class="row-label">Note</span><span class="leader"></span><span class="row-value">{{ $note }}</span></div>
                    @endforeach

                    <div class="duration">{{ $probe['duration_ms'] ?? 0 }} ms</div>
                </div>
            </div>
        @endforeach
    @endif

    <div class="footer">
        <span>maryperry/clever · schema v1</span>
        <span>{{ is_array($report) ? ($report['package_version'] ?? '') : '' }}</span>
    </div>
</div>
<div class="base"></div>
</body>
</html>
