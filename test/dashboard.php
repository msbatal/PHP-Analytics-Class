<?php

    require_once ('SunDB.php');        // Call 'SunDB' class (dependency, see github.com/msbatal/PHP-PDO-Database-Class)
    require_once ('SunAnalytics.php'); // Call 'SunAnalytics' class

    // Dashboard example: every number and list on this page comes from a SunAnalytics report method.
    // Import test.sql into your database first (it fills the tables with about two weeks of sample traffic).
    // Don't forget to change dbname, username, and password below (same as index.php).
    $db = new SunDB(['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'dbname' => 'test', 'username' => 'test', 'password' => '1234', 'charset' => 'utf8mb4']);
    $analytics = new SunAnalytics($db);

    // A dashboard page is usually not tracked itself, so track() is not called here.
    // Call $analytics->track() on your public pages instead (see index.php).

    $periods = [7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days']; // the sample data covers 14 days, so the 7 day view has a previous period to compare with
    $days = (isset($_GET['period']) && is_string($_GET['period']) && isset($periods[(int) $_GET['period']])) ? (int) $_GET['period'] : 7;

    // Reports (period = number of days, or 'today', 'yesterday', or [from, to])
    $summary   = $analytics->summary($days);
    $previous  = $analytics->summary([date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days')), date('Y-m-d', strtotime('-' . $days . ' days'))]);
    $daily     = $analytics->daily($days);
    $pages     = $analytics->pages($days, 8);
    $devices   = $analytics->breakdown('device', $days, 5);
    $browsers  = $analytics->breakdown('browser', $days, 5);
    $sources   = $analytics->sources($days, 10);
    $mediums   = $analytics->breakdown('medium', $days, 10);
    $campaigns = $analytics->breakdown('campaign', $days, 8);
    $referrers = $analytics->breakdown('referrer', $days, 8);
    $landings  = $analytics->breakdown('landing', $days, 8);
    $online    = $analytics->online();

    $mediumNames  = ['organic' => 'Organic Search', 'direct' => 'Direct', 'social' => 'Social', 'cpc' => 'Paid', 'email' => 'Email', 'referral' => 'Referral', 'display' => 'Display', 'campaign' => 'Campaign'];
    $mediumColors = ['organic' => '#1f8f5f', 'direct' => '#203556', 'social' => '#d84156', 'cpc' => '#d98e04', 'email' => '#2f7fc1', 'referral' => '#7a5cc4', 'display' => '#14a3a3', 'campaign' => '#c45c9e'];
    $mediumName   = function ($m) use ($mediumNames) { return isset($mediumNames[$m]) ? $mediumNames[$m] : ucfirst((string) $m); };
    $mediumColor  = function ($m) use ($mediumColors) { return isset($mediumColors[$m]) ? $mediumColors[$m] : '#8a96a8'; };
    $sourceName   = function ($s) { return $s === 'direct' ? 'Direct' : ucfirst((string) $s); };
    $label        = function ($v) { return (string) $v !== '' ? (string) $v : 'Unknown'; };

    function h($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
    function num($n) { return number_format((int) $n); }
    function pct($n) { return number_format((float) $n, 1) . '%'; }

    // Change against the previous period of the same length ($inverse: a lower value is better, e.g. bounce rate)
    function delta($now, $before, $inverse = false) {
        if ((float) $before <= 0) { return '<small class="delta">No data in the previous period</small>'; }
        $change = ((float) $now - (float) $before) / (float) $before * 100;
        if (abs($change) < 0.05) { return '<small class="delta">Same as the previous period</small>'; }
        $good = $inverse ? ($change < 0) : ($change > 0);
        return '<small class="delta ' . ($good ? 'good' : 'bad') . '">' . ($change > 0 ? '&#9650; ' : '&#9660; ') . number_format(abs($change), 1) . '% vs previous period</small>';
    }

    // Line chart (inline SVG, no JavaScript library): $daily is the result of $analytics->daily()
    function lineChart($daily) {
        $dates = array_keys($daily);
        $n = count($dates);
        $w = 760; $hgt = 280; $left = 46; $right = 14; $top = 14; $bottom = 30;
        $max = 1;
        foreach ($daily as $d) { $max = max($max, $d['visits'], $d['pageviews']); }
        $step = 1; $factor = [2, 2.5, 2]; $i = 0;
        while ($step * 4 < $max) { $step *= $factor[$i % 3]; $i++; }
        $peak = (int) (ceil($max / $step) * $step);
        $inner = $w - $left - $right;
        $plot = $hgt - $top - $bottom;
        $xAt = function ($k) use ($n, $left, $inner) { return round($n > 1 ? $left + $inner * $k / ($n - 1) : $left + $inner / 2, 1); };
        $yAt = function ($v) use ($top, $plot, $peak) { return round($top + $plot * (1 - $v / $peak), 1); };
        $base = $top + $plot;
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $hgt . '" role="img" aria-label="Daily visits and page views" xmlns="http://www.w3.org/2000/svg">';
        for ($t = 0; $t <= $peak; $t += $step) {
            $y = $yAt($t);
            $svg .= '<line class="grid" x1="' . $left . '" x2="' . ($w - $right) . '" y1="' . $y . '" y2="' . $y . '"/><text class="axis" x="' . ($left - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . num($t) . '</text>';
        }
        $every = (int) ceil($n / 8);
        foreach ($dates as $k => $date) {
            if ($k % $every === 0) { $svg .= '<text class="axis" x="' . $xAt($k) . '" y="' . ($hgt - 8) . '" text-anchor="middle">' . h(date('M j', strtotime($date))) . '</text>'; }
        }
        $visitPoints = []; $viewPoints = [];
        foreach ($dates as $k => $date) {
            $visitPoints[] = $xAt($k) . ',' . $yAt($daily[$date]['visits']);
            $viewPoints[] = $xAt($k) . ',' . $yAt($daily[$date]['pageviews']);
        }
        $svg .= '<polygon class="area" points="' . $xAt(0) . ',' . $base . ' ' . implode(' ', $visitPoints) . ' ' . $xAt($n - 1) . ',' . $base . '"/>';
        $svg .= '<polyline class="line-views" points="' . implode(' ', $viewPoints) . '"/><polyline class="line-visits" points="' . implode(' ', $visitPoints) . '"/>';
        if ($n <= 31) {
            foreach ($dates as $k => $date) {
                $svg .= '<circle class="dot-views" cx="' . $xAt($k) . '" cy="' . $yAt($daily[$date]['pageviews']) . '" r="3"/><circle class="dot-visits" cx="' . $xAt($k) . '" cy="' . $yAt($daily[$date]['visits']) . '" r="3.4"/>';
            }
        }
        $column = $n > 1 ? $inner / ($n - 1) : $inner;
        foreach ($dates as $k => $date) { // invisible hover areas with a tooltip for every day
            $d = $daily[$date];
            $svg .= '<rect x="' . round($xAt($k) - $column / 2, 1) . '" y="' . $top . '" width="' . round($column, 1) . '" height="' . $plot . '" fill="transparent"><title>' . h(date('M j, Y', strtotime($date)) . ': ' . num($d['visits']) . ' visits, ' . num($d['pageviews']) . ' page views') . '</title></rect>';
        }
        return $svg . '</svg>';
    }

    // Donut chart (inline SVG): $parts is the result of $analytics->breakdown('medium')
    function donutChart($parts, $total, $color) {
        $svg = '<svg class="donut" viewBox="0 0 42 42" role="img" aria-label="Traffic channels" xmlns="http://www.w3.org/2000/svg"><circle class="donut-base" cx="21" cy="21" r="15.9155"/>';
        $offset = 25.0;
        foreach ($parts as $p) {
            $share = $total > 0 ? $p['visits'] / $total * 100 : 0;
            if ($share <= 0) { continue; }
            $svg .= '<circle class="donut-part" cx="21" cy="21" r="15.9155" stroke="' . h($color($p['name'])) . '" stroke-dasharray="' . round($share, 2) . ' ' . round(100 - $share, 2) . '" stroke-dashoffset="' . round($offset, 2) . '"/>';
            $offset -= $share;
        }
        return $svg . '<text class="donut-total" x="21" y="21.5" text-anchor="middle">' . h(num($total)) . '</text><text class="donut-label" x="21" y="26.5" text-anchor="middle">visits</text></svg>';
    }

    // Bar list: $rows is a breakdown()/pages() result, $key the number column, $name a callable that makes the label
    function barList($rows, $key, $name) {
        if (empty($rows)) { return '<p class="muted">No data.</p>'; }
        $top = max(1, (int) $rows[0][$key]);
        $html = '<ul class="bars">';
        foreach ($rows as $row) {
            $html .= '<li><span>' . $name($row) . '</span><strong>' . num($row[$key]) . '</strong><i style="width: ' . (int) round($row[$key] / $top * 100) . '%"></i></li>';
        }
        return $html . '</ul>';
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SunAnalytics Dashboard Example</title>
<style>
    :root { --navy: #203556; --accent: #d84156; --soft: #5c697b; --line: #e3e8ef; --bg: #f5f7fa; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: #1f2a3a; font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    main { max-width: 1180px; margin: 0 auto; padding: 24px 16px 48px; }
    header.top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
    h1 { margin: 0; color: var(--navy); font-size: 1.4rem; }
    h2 { margin: 0; color: var(--navy); font-size: 1.1rem; }
    h3 { margin: 0 0 12px; color: var(--navy); font-size: .95rem; }
    nav.periods { display: flex; flex-wrap: wrap; gap: 8px; }
    nav.periods a { padding: 6px 14px; border: 1px solid var(--line); border-radius: 999px; background: #fff; color: var(--navy); font-size: .85rem; font-weight: 600; text-decoration: none; }
    nav.periods a.active { border-color: var(--navy); background: var(--navy); color: #fff; }
    section.card { margin-bottom: 20px; padding: 20px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
    .card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 16px; }
    .card-head small { margin-left: 8px; color: var(--soft); font-weight: 600; }
    .online { color: var(--soft); font-size: .875rem; }
    .online::before { content: ""; display: inline-block; width: 8px; height: 8px; margin-right: 6px; border-radius: 50%; background: #1f8f5f; }
    .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .tile { padding: 14px 16px; border-radius: 12px; background: var(--bg); }
    .tile span { display: block; color: var(--soft); font-size: .8rem; font-weight: 600; }
    .tile strong { display: block; color: var(--navy); font-size: 1.5rem; line-height: 1.25; }
    .delta { display: block; color: var(--soft); font-size: .72rem; }
    .delta.good { color: #17714b; }
    .delta.bad { color: #c8374b; }
    .legend { display: flex; flex-wrap: wrap; gap: 4px 18px; margin-bottom: 6px; color: var(--soft); font-size: .82rem; font-weight: 600; }
    .legend i { display: inline-block; width: 10px; height: 10px; margin-right: 6px; border-radius: 50%; }
    .chart-wrap { overflow-x: auto; }
    .chart { display: block; width: 100%; min-width: 560px; height: auto; }
    .grid { stroke: var(--line); }
    .axis { fill: var(--soft); font-size: 11px; }
    .area { fill: rgba(32, 53, 86, .1); }
    .line-visits, .line-views { fill: none; stroke-linecap: round; stroke-linejoin: round; }
    .line-visits { stroke: var(--navy); stroke-width: 2.5; }
    .line-views { stroke: var(--accent); stroke-width: 2; }
    .dot-visits { fill: #fff; stroke: var(--navy); stroke-width: 2; }
    .dot-views { fill: #fff; stroke: var(--accent); stroke-width: 2; }
    .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 20px; }
    .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-top: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    th { padding: 8px 10px; border-bottom: 1px solid var(--line); color: var(--soft); font-size: .72rem; letter-spacing: .04em; text-transform: uppercase; text-align: right; }
    td { padding: 8px 10px; border-bottom: 1px solid var(--line); text-align: right; }
    th:first-child, td:first-child { text-align: left; }
    .scroll { max-height: 320px; overflow: auto; }
    .scroll th { position: sticky; top: 0; background: #fff; }
    .bars { margin: 0; padding: 0; list-style: none; }
    .bars li { position: relative; display: flex; justify-content: space-between; gap: 8px; margin-bottom: 8px; padding: 7px 10px; border-radius: 8px; background: var(--bg); overflow: hidden; }
    .bars span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bars strong { color: var(--navy); }
    .bars small { color: var(--soft); }
    .bars i { position: absolute; left: 0; bottom: 0; height: 3px; background: var(--accent); }
    .channels { display: flex; flex-wrap: wrap; align-items: center; gap: 20px 28px; }
    .donut { flex: 0 0 auto; width: 180px; height: 180px; }
    .donut-base, .donut-part { fill: none; stroke-width: 5; }
    .donut-base { stroke: #eef2f8; }
    .donut-total { fill: var(--navy); font-size: 6.5px; font-weight: 800; }
    .donut-label { fill: var(--soft); font-size: 2.8px; }
    .key { flex: 1 1 200px; margin: 0; padding: 0; list-style: none; }
    .key li { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px dashed var(--line); }
    .key li:last-child { border-bottom: 0; }
    .key span { flex: 1 1 auto; }
    .key small { min-width: 3.4rem; color: var(--soft); text-align: right; }
    .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
    .muted { color: var(--soft); }
    .note { margin: 16px 0 0; color: var(--soft); font-size: .8rem; }
</style>
</head>
<body>
<main>
    <header class="top">
        <h1>SunAnalytics Dashboard Example</h1>
        <nav class="periods" aria-label="Period">
            <?php foreach ($periods as $d => $name): ?>
            <a class="<?= $days === $d ? 'active' : '' ?>" href="?period=<?= $d ?>"><?= h($name) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <section class="card" aria-labelledby="visits-title">
        <div class="card-head">
            <h2 id="visits-title">Visits <small><?= h($periods[$days]) ?></small></h2>
            <span class="online">Online now: <strong><?= (int) $online ?></strong></span>
        </div>
        <div class="tiles">
            <div class="tile"><span>Visits</span><strong><?= num($summary['visits']) ?></strong><?= delta($summary['visits'], $previous['visits']) ?></div>
            <div class="tile"><span>Page views</span><strong><?= num($summary['pageviews']) ?></strong><?= delta($summary['pageviews'], $previous['pageviews']) ?></div>
            <div class="tile"><span>Unique visitors</span><strong><?= num($summary['visitors']) ?></strong><?= delta($summary['visitors'], $previous['visitors']) ?></div>
            <div class="tile"><span>Pages per visit</span><strong><?= number_format($summary['pages_per_visit'], 2) ?></strong><?= delta($summary['pages_per_visit'], $previous['pages_per_visit']) ?></div>
            <div class="tile"><span>Bounce rate</span><strong><?= pct($summary['bounce_rate']) ?></strong><?= delta($summary['bounce_rate'], $previous['bounce_rate'], true) ?></div>
        </div>

        <?php if ($summary['visits'] < 1): ?>
        <p class="muted">No visits in this period. Import test.sql first.</p>
        <?php else: ?>
        <div class="legend" aria-hidden="true"><span><i style="background: var(--navy)"></i>Visits</span><span><i style="background: var(--accent)"></i>Page views</span></div>
        <div class="chart-wrap"><?= lineChart($daily) ?></div>

        <div class="grid-3">
            <div>
                <h3>Daily breakdown</h3>
                <div class="scroll">
                    <table>
                        <thead><tr><th>Date</th><th>Visits</th><th>Views</th><th>Unique</th></tr></thead>
                        <tbody>
                            <?php foreach (array_reverse($daily, true) as $date => $d): ?>
                            <tr><td><?= h(date('M j, Y', strtotime($date))) ?></td><td><?= num($d['visits']) ?></td><td><?= num($d['pageviews']) ?></td><td><?= num($d['visitors']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <h3>Most viewed pages</h3>
                <?= barList($pages, 'views', function ($r) { return h($r['path']); }) ?>
            </div>
            <div>
                <h3>Devices</h3>
                <?= barList($devices, 'visits', function ($r) use ($label) { return h(ucfirst($label($r['name']))) . ' <small>' . pct($r['share']) . '</small>'; }) ?>
                <h3 style="margin-top: 16px">Browsers</h3>
                <?= barList($browsers, 'visits', function ($r) use ($label) { return h($label($r['name'])) . ' <small>' . pct($r['share']) . '</small>'; }) ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="sources-title">
        <div class="card-head">
            <h2 id="sources-title">Traffic sources <small><?= h($periods[$days]) ?></small></h2>
        </div>
        <?php if ($summary['visits'] < 1): ?>
        <p class="muted">No visits in this period.</p>
        <?php else: ?>
        <div class="grid-2">
            <div>
                <h3>Channels</h3>
                <div class="channels">
                    <?= donutChart($mediums, $summary['visits'], $mediumColor) ?>
                    <ul class="key">
                        <?php foreach ($mediums as $m): ?>
                        <li><i class="dot" style="background: <?= h($mediumColor($m['name'])) ?>"></i><span><?= h($mediumName($m['name'])) ?></span><strong><?= num($m['visits']) ?></strong><small><?= pct($m['share']) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div>
                <h3>Sources</h3>
                <div class="scroll">
                    <table>
                        <thead><tr><th>Source</th><th>Visits</th><th>Share</th><th>Pages / visit</th><th>Bounce</th></tr></thead>
                        <tbody>
                            <?php foreach ($sources as $s): ?>
                            <tr>
                                <td><i class="dot" style="background: <?= h($mediumColor($s['medium'])) ?>"></i> <?= h($sourceName($s['name'])) ?><?php if ($s['name'] !== 'direct'): ?> <small class="muted"><?= h($mediumName($s['medium'])) ?></small><?php endif; ?></td>
                                <td><?= num($s['visits']) ?></td>
                                <td><?= pct($s['share']) ?></td>
                                <td><?= number_format($s['visits'] > 0 ? $s['pageviews'] / $s['visits'] : 0, 2) ?></td>
                                <td><?= pct($s['bounce_rate']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid-3">
            <div>
                <h3>Campaigns</h3>
                <?= barList($campaigns, 'visits', function ($r) { return h($r['name']); }) ?>
            </div>
            <div>
                <h3>Referring sites</h3>
                <?= barList($referrers, 'visits', function ($r) { return h($r['name']); }) ?>
            </div>
            <div>
                <h3>Landing pages</h3>
                <?= barList($landings, 'visits', function ($r) { return h($r['name']); }) ?>
            </div>
        </div>
        <?php endif; ?>
        <p class="note">Unique visitors are counted per day (the visitor hash rotates every midnight), so a multi-day figure is the sum of daily unique visitors, not distinct people. Bounce rate is the share of visits that viewed a single page. Direct traffic can include in-app browsers and e-mail links that send no referrer.</p>
    </section>
</main>
</body>
</html>
