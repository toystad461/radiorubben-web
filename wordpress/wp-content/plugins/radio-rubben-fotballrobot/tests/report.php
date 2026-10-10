<?php
function add_filter(...$a){} function add_action(...$a){}
require __DIR__.'/../includes/report.php';require __DIR__.'/../includes/facts.php';
use RadioRubben\Fotballrobot\Report;use RadioRubben\Fotballrobot\Facts;
$l=['roster'=>[3=>'Inn',13=>'Ut']];
$e=['type'=>'sub','side'=>'home','player'=>3,'out'=>13,'seconds'=>2791];
$r=Report::substitutions([$e,$e,['type'=>'goal','player'=>3],array_merge($e,['player'=>99]),array_merge($e,['dismissed'=>true]),array_merge($e,['seconds'=>-1])],$l);
if(count($r)!==1||$r[0]['seconds']!==2791||$r[0]['in']!=='Inn'||array_keys($r[0])!==['side','in','out','seconds','source'])throw new RuntimeException('Whitelist/dedup failed');
$m=Facts::match(file_get_contents(__DIR__.'/fixtures/match.html'),8985491);
if(count($m['referees'])!==3||$m['referees'][0]['name']!=='Hattun Rune Bø')throw new RuntimeException('Referees failed');
echo "OK: substitutions whitelist, deduplication, invalid records, referee parsing\n";
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}
$timeline=Report::timeline($m,$r);
if(substr_count($timeline,'class="poll-event-row ')!==12||!str_contains($timeline,'47′')||strpos($timeline,'90′')>strpos($timeline,'24′')||!str_contains($timeline,'poll-event-icon red'))throw new RuntimeException('Timeline merging/order failed');
echo "OK: combined timeline, minute rounding, newest first, card icon\n";
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
$options=['rr_match_archive_8985501'=>['match'=>['home_id'=>30365],'state'=>['poll_award'=>['type'=>'award','side'=>'home','description'=>'Nr. 2 Testspiller · 4 stemmer · Trukket stemmevinner: Private Person']],
    'results'=>[['number'=>2,'player'=>'Testspiller','total'=>4]],'sponsor'=>['name'=>'Testsponsor']]];
$before=$options;$extra=Report::extras(8985501,['roster'=>[2=>'Testspiller']]);
if(($extra['award']['players']??[])!==['Testspiller']||($extra['sponsor']['name']??'')!=='Testsponsor'||str_contains(json_encode($extra),'Private Person')||$before!==$options)throw new RuntimeException('Award/sponsor privacy or read-only contract failed');
$options['rr_match_archive_8985501']['match']['home_id']=99;
if(isset(Report::extras(8985501,['roster'=>[2=>'Testspiller']])['award']))throw new RuntimeException('Away match must not claim Dagens Bremnesing');
$options['rr_match_archive_8985501']['match']['home_id']=30365;
unset($options['rr_match_archive_8985501']['state']['poll_award']);
if(isset(Report::extras(8985501,['roster'=>[2=>'Testspiller']])['award']))throw new RuntimeException('No award inferred from vote totals');
echo "OK: optional award and sponsor, privacy and no poll mutations\n";
