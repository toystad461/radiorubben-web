<?php
// Read only. Never print credentials, raw responses, posts, or personal metadata.
try {
    if (!defined('ABSPATH') || !class_exists('RadioRubben\\Fotballrobot\\Fotballdata')) throw new RuntimeException();
    $feed = \RadioRubben\Fotballrobot\Fotballdata::clubMatches();
    $rows = [];
    foreach ($feed['Matches'] ?? [] as $row) {
        if (!in_array($row['MatchId'] ?? null, [8985501, 8985509], true)) continue;
        $raw = $row['MatchStartDate'] ?? null;
        if (!is_string($raw) || !preg_match('~^/Date\(([0-9]{12,13})([+-][0-9]{4})?\)/$~D', $raw, $m)) throw new RuntimeException();
        $rows[] = ['id'=>$row['MatchId'], 'raw_date'=>$raw,
            'epoch_as_utc'=>gmdate('Y-m-d H:i:s', intdiv((int)$m[1],1000)),
            'adapter_date'=>\RadioRubben\Fotballrobot\Fotballdata::date($raw)];
    }
    if (count($rows) !== 2) throw new RuntimeException();
    echo json_encode(['mode'=>'read-only','kickoff_dates'=>$rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Kickoff diagnostic failed; response and error details withheld.\n");
    exit(1);
}
