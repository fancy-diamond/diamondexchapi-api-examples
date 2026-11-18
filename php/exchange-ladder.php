<?php
// Print an exchange back/lay ladder for match odds, and the bookmaker rates
// converted to decimal odds.
//   php exchange-ladder.php [ev_...]   (default: the first event in play)
require __DIR__ . '/lib.php';

const DEPTH = 3;

function level(array $r, string $side, int $i): string
{
    $l = $r[$side][$i] ?? null;
    return $l ? n($l['price']) . ' (' . round($l['size']) . ')' : '';
}

function header_line(array $m): void
{
    $flag = !empty($m['stale']) ? "{$m['status']}, stale" : $m['status'];
    echo "\n" . strtoupper($m['name']) . "  [$flag]" . (tradeable($m) ? '' : '  no bets now') . "\n";
}

function ladder(array $m): void
{
    header_line($m);
    // Back prices read right to left (best next to the middle), lay left to right,
    // as on an exchange screen. Each level: decimal odds (amount available).
    printf("%-22s%" . (DEPTH * 14) . "s  |  Lay\n", '', 'Back');
    foreach ($m['runners'] as $r) {
        $back = '';
        for ($i = DEPTH - 1; $i >= 0; $i--) {
            $back .= sprintf('%14s', level($r, 'back', $i));
        }
        $lay = '';
        for ($i = 0; $i < DEPTH; $i++) {
            $lay .= sprintf('%-14s', level($r, 'lay', $i));
        }
        echo rtrim(sprintf("%-22s%s  |  %s", substr($r['name'], 0, 21), $back, $lay)) . "\n";
    }
    foreach ($m['runners'] as $r) {
        if (isset($r['back'][0])) {
            $p = (float)$r['back'][0]['price'];
            printf("A back stake of 100 on %s at %s returns %.2f (profit %.2f).\n", $r['name'], n($p), 100 * $p, 100 * ($p - 1));
            break;
        }
    }
}

function bookmaker(array $m): void
{
    header_line($m);
    // Bookmaker prices are rates: profit per 100 staked. decimal = 1 + rate / 100.
    printf("%-22s%10s%10s  |%10s%10s\n", 'Runner', 'back rate', 'decimal', 'lay rate', 'decimal');
    foreach ($m['runners'] as $r) {
        $b = $r['back'][0] ?? null;
        $l = $r['lay'][0] ?? null;
        printf("%-22s%10s%10s  |%10s%10s\n", substr($r['name'], 0, 21),
            $b ? n($b['price']) : '-', $b ? sprintf('%.2f', rate_to_decimal((float)$b['price'])) : '-',
            $l ? n($l['price']) : '-', $l ? sprintf('%.2f', rate_to_decimal((float)$l['price'])) : '-');
    }
    foreach ($m['runners'] as $r) {
        if (isset($r['back'][0])) {
            $rate = (float)$r['back'][0]['price'];
            printf("Rate %s = decimal %.2f: 100 on %s wins %s.\n", n($rate), rate_to_decimal($rate), $r['name'], n($rate));
            break;
        }
    }
}

run(function () use ($argv) {
    $id = $argv[1] ?? (inplay_event_ids(1)[0] ?? null);
    if (!$id) {
        echo "Nothing in play right now; pass an event id: php exchange-ladder.php ev_...\n";
        return;
    }
    $ev = get_json('/v1/events/' . $id);
    echo "{$ev['name']}  ({$ev['sport']}, " . ($ev['competition']['name'] ?? '') . ")\n";
    $mo = array_filter($ev['markets'], function ($m) { return $m['type'] === 'match_odds'; });
    $bm = array_filter($ev['markets'], function ($m) { return $m['type'] === 'bookmaker'; });
    if (!$mo && !$bm) {
        echo "This event has no match odds or bookmaker market right now.\n";
    }
    array_map('ladder', $mo);
    array_map('bookmaker', $bm);
});
