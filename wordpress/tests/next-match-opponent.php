<?php
// Exercise the real data provider and rendered template without WordPress or network access.
define('ABSPATH', __DIR__);
function add_filter(...$args) {}
function add_action(...$args) {}
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function home_url($path) { return 'https://example.test'.$path; }
function sanitize_text_field($text) { return trim(strip_tags((string)$text)); }
function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_url($text) { return esc_html($text); }
function wp_json_encode($value) { return json_encode($value); }
function wp_date($format, $timestamp, $timezone) {
    return (new DateTimeImmutable('@'.$timestamp))->setTimezone($timezone)->format($format);
}
function get_header() {}
function get_footer() {}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$source=dirname(__DIR__).'/wp-content/themes/radio-rubben-wordpress-v1/inc';
$temp=sys_get_temp_dir().'/rr-next-match-'.bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    foreach (['bremnes-direkte-test.php','bremnes-poll-rules.php','bremnes-next-match.php','bremnes-history-2026.php'] as $file) {
        check(copy($source.'/'.$file, $temp.'/'.$file), 'Could not copy '.$file);
    }
    // Fixed inputs, relative kickoff: these tests remain valid after the 2026 season.
    file_put_contents($temp.'/bremnes-season-2026.php', '<?php return $GLOBALS["test_fixtures"];');
    require $temp.'/bremnes-direkte-test.php';
    $kickoff=(new DateTimeImmutable('tomorrow 18:45',new DateTimeZone('Europe/Oslo')))->format(DATE_ATOM);
    $cases=0;
    foreach (['herrer'=>['Smørås','herrelag','29.05.2026','Bremnes tapte 1–2'], 'kvinner'=>['Åsane 2','damelag','07.06.2026','Bremnes vant 3–2']] as $team=>$expected) {
        [$opponent,$label,$historyDate,$historyScore]=$expected;
        foreach ([true,false] as $home) {
            foreach ([false,true] as $imported) {
                $GLOBALS['test_fixtures']=[9999999=>[
                    'team'=>$team,'home'=>$home?'yes':'no','opponent'=>$opponent,
                    'kickoff'=>$kickoff,'venue'=>'Testbane','logo'=>'',
                ]];
                $GLOBALS['test_options']=[];
                if ($imported) {
                    $GLOBALS['test_options']['rr_poll_match_9999999']=[
                        'home'=>$home?'Bremnes':$opponent,'away'=>$home?$opponent:'Bremnes',
                        'home_id'=>$home?($team==='kvinner'?48835:30365):0,
                        'kickoff'=>$kickoff,'venue'=>'Testbane','competition'=>'Testserie',
                        // A stale derived field must not override the verified fixture side.
                        'opponent'=>'Bremnes',
                    ];
                }
                $case=$team.' '.($home?'home':'away').' '.($imported?'imported':'snapshot');
                $match=rr_poll_next_match_data();
                check($match['opponent']===$opponent, $case.': wrong opponent');
                ob_start();
                include $temp.'/bremnes-next-match.php';
                $html=ob_get_clean();
                $title=($home?'Bremnes':$opponent).' møter '.($home?$opponent:'Bremnes');
                check(str_contains($html,'<h2>'.esc_html($title).'</h2>'), $case.': changed heading');
                check(str_contains($html,'Bremnes sitt '.$label.' møter '.$opponent.' '), $case.': wrong intro');
                check(!str_contains($html,'Bremnes sitt '.$label.' møter Bremnes'), $case.': self opponent');
                check(str_contains($html,'Seneste møte var '.$historyDate), $case.': missing history');
                check(str_contains($html,$historyScore), $case.': wrong historical score');
                check(!str_contains($html,'Vi har foreløpig ikke et tidligere møte'), $case.': false missing history');
                echo 'PASS '.$case.PHP_EOL;
                $cases++;
            }
        }
    }
    echo $cases.' next-match cases passed.'.PHP_EOL;
} finally {
    foreach (glob($temp.'/*') as $file) unlink($file);
    rmdir($temp);
}
