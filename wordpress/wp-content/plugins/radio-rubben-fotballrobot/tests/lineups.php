<?php
namespace RadioRubben\Fotballrobot {
    final class MatchJobs { public static function eligible(...$args){return false;} public static function confirmed(...$args){return true;} }
    final class FactStore { public static function save($id,$payload){return $payload;} }
}
namespace {
require __DIR__.'/../includes/facts.php';
require __DIR__.'/../includes/robot.php';
require __DIR__.'/../includes/writer.php';
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\Lineups;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\Robot;
const MINUTE_IN_SECONDS=60;
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function get_option($key,$default=[]){if(str_starts_with($key,'rr_poll_match_'))throw new RuntimeException('Must not read poll cache');return $default;}
function get_transient($key){global $source;return ['html'=>$source,'url'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=8985491','fetched_at'=>'2026-10-03T17:00:00Z'];}
$n=0;
function check($ok,$label){global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function rejected($fn,$label){try{$fn();}catch(RuntimeException $e){check(true,$label);return;}throw new RuntimeException($label);}
$base=file_get_contents(__DIR__.'/fixtures/match.html');
// Minimal fixture uses the already observed NFF wrapper/list/player markup. Synthetic identities.
function player($number,$id,$name){return '<div class="matchPlayerListItem"><div class="a_playerWithEvents"><div class="playerContent"><div class="playerNumber">'.$number.'</div><a class="playerName" href="/fotballdata/person/profil/?fiksId='.$id.'">'.$name.'</a></div></div></div>';}
function squad($side,$start,$bench){return '<div class="'.$side.'TeamWrapper"><h4>Startoppstilling:</h4><div class="a_matchPlayerList">'.$start.'</div><h4>Innbyttere:</h4><div class="a_matchPlayerList">'.$bench.'</div></div>';}
$starters=player(12,1001,'Hilde Hope').player(3,1002,'Hanna Midtbø Økland').player(4,1003,'Tiril Berntsen Kvarven').player(6,1004,'Aurora Meling').player(19,1005,'Martine Meling').player(7,1006,'Mia Tomine Sortland').player(9,1007,'Emmy Kallevåg Rinne').player(10,1008,'Emilie Ånderå').player(11,1009,'Julia Nesse').player(13,1010,'Sanna Alena Oa Våge').player(22,1011,'Kristin Stoknes');
$bench=player(15,1012,'Lilly Mathea Sortland').player(16,1013,'Milena Madsen Helvik').player(21,1014,'Mia Engseth Lie');
$expected='Hope, Økland, Kvarven, A. Meling, M. Meling, M. Sortland, Rinne, Ånderå, Nesse, Våge, Stoknes.';
$fixture=$base.squad('home',$starters,$bench).squad('away',player(1,2001,'Test Motstander'),player(2,2002,'Anne Reserve'));
$m=Facts::match($fixture,8985491);$l=Lineups::parse($fixture,$m);$f=['match'=>$m,'lineups'=>$l];
check($l['starters']===[12,3,4,6,19,7,9,10,11,13,22],'Keep source order, not number sorting');
check($l['bench']===[15,16,21] && count($l['starters'])===11,'Separate bench and starters');
check($l['person_ids'][12]===1001 && $l['team_ids']['home']===30365,'Match, team and player identity');
$html=Lineups::paragraph($f);
check(str_contains($html,$expected),'Surnames and initials across starting and reserve lists');
check(str_contains($html,'(Innbyttere: L. Sortland, Helvik, Lie)'),'Reserve text');
check(substr_count($html,'<p>')===1 && !str_contains($html,'<br') && str_contains($html,'font-size:0.85em;line-height:1.5;'),'One paragraph, readable smaller reserve text');
check(!str_contains($html,'Motstander')&&!str_contains($html,'Test '),'Only Bremnes paragraph');
// Both authorized team IDs and both sides, independent of identical team display names.
foreach([30365,48835] as $team)foreach(['home','away'] as $side){
    $page=$base;$page=str_replace('fiksId=30365','fiksId='.($side==='home'?$team:19047),$page);
    $page=str_replace('fiksId=19046','fiksId='.($side==='away'?$team:19046),$page);
    $page.=squad($side,$starters,$bench).squad($side==='home'?'away':'home',player(1,2001,'Test Motstander'),'');
    $match=Facts::match($page,8985491);$lineups=Lineups::parse($page,$match);
    check(str_contains(Lineups::paragraph(['match'=>$match,'lineups'=>$lineups]),$expected),'Correct authorized ID/side '.$team.' '.$side);
}
$wrong=$f;$wrong['match']['home']['id']=999;check(Lineups::paragraph($wrong)==='','Name alone does not select Bremnes');
$wrong=$m;$wrong['home']['id']=48835;rejected(fn()=>Lineups::parse($fixture,$wrong),'Wrong team must stop');
rejected(fn()=>Lineups::parse('<link rel="canonical" href="https://www.fotball.no/fotballdata/kamp/?fiksId=7">'.$fixture,$m),'Wrong match must stop');
$empty=Lineups::parse($base,$m);$emptyHtml=Lineups::paragraph(['match'=>$m,'lineups'=>$empty]);
check(str_contains($emptyHtml,'Startoppstillingen er ikke tilgjengelig hos NFF.') && str_contains($emptyHtml,'Innbytterliste ikke tilgjengelig hos NFF'),'Explicit fallback for missing lists');
check(count($empty['warnings'])===4,'Missing lists visible in editor warning panel');
$partial=Lineups::parse($base.squad('home',$starters,''),$m);
check(str_contains(Lineups::paragraph(['match'=>$m,'lineups'=>$partial]),$expected) && $partial['status']['home']['bench']==='missing','Missing bench preserves starters');
$reservesOnly=Lineups::parse($base.squad('home','',$bench),$m);
check($reservesOnly['starters']===[]&&$reservesOnly['bench']===[15,16,21],'Never promote reserve list to starters');
foreach([
    $base.squad('home',$starters.player(12,9000,'Extra Duplicate'),$bench),
    $base.squad('home',$starters,player(15,1001,'Hilde Hope')),
    str_replace('Startoppstilling:','Ukjent:',$fixture),
    $base.squad('home',$starters,$bench).squad('home',$starters,$bench),
    $base.squad('home',$starters.player(24,9000,'Extra Twelfth'),$bench),
    str_replace('Hilde Hope','Hilde Hope (strøket)',$fixture),
    str_replace('fiksId=1001','fiksId=ukjent',$fixture)
] as $page){$bad=Lineups::parse($page,$m);check($bad['roster']===[]&&$bad['status']['home']['starters']==='invalid','Ambiguous list cannot publish guessed players');}
$hyphen=Lineups::parse($base.squad('home',player(1,1,'Anna Nord-Sør').player(2,2,'Åse Anne Lik').player(3,3,'Åse Beate Lik'),''),$m);
$h=Lineups::paragraph(['match'=>$m,'lineups'=>$hyphen]);check(str_contains($h,'Nord-Sør, Å. A. Lik, Å. B. Lik'),'Hyphen surnames and colliding unicode initials');
$unsafe=$f;$unsafe['lineups']['match_id']=1;check(!str_contains(Lineups::paragraph($unsafe),'Hope'),'Foreign snapshot is not reused');
$legacy=$f;unset($legacy['lineups']['version']);check(!str_contains(Lineups::paragraph($legacy),'Hope'),'Unproven poll cache is not reused');
$article=['lead'=>'Bremnes spilte kamp.','paragraphs'=>['Kampreferatet.','Kort og andre registrerte hendelser: Gult kort til Test.','Neste kamp er borte.']];
$body=Writer::body($article,$f);
check(strpos($body,'Kampreferatet.')<strpos($body,'<strong>Bremnes:') && strpos($body,'<strong>Bremnes:')<strpos($body,'Kort og andre'),'Lineup after recap before cards');
check(substr_count($body,'<strong>Bremnes:</strong>')===1,'Exactly one lineup');
$article['paragraphs']=['Kampreferatet.'];check(str_contains(Writer::body($article,$f),$expected),'Lineup even without event paragraph');
$source=$fixture;$refreshed=Robot::refresh(8985491,true);
check($refreshed['lineups']['starters']===$l['starters'] && $refreshed['lineups']['bench']===$l['bench'],'Refresh actually uses NFF roster, never poll state');
$source=$base;$refreshed=Robot::refresh(8985491,true);check($refreshed['lineups']['starters']===[] && count($refreshed['warnings'])>=4,'Missing source creates fresh unknown state and warnings');
echo "OK: $n lineup source, identity, fallback and rendering controls\n";
}
