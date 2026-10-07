<?php
require __DIR__.'/../includes/player-news-filter.php';
use RadioRubben\Fotballrobot\PlayerNewsFilter as F;
$count=0;function check($ok,$why){global $count;$count++;if(!$ok)throw new RuntimeException($why);}
$now=strtotime('2026-10-04T20:00:00Z');$at=gmdate(DATE_ATOM,$now-100);$start=gmdate(DATE_ATOM,$now-7200);
$url='https://www.fotball.no/fotballdata/kamp/?fiksId=123';
$goal=['id'=>'7','name'=>'Test Spiller','minute'=>'46','type'=>'Spillemål'];
$event=['key'=>'123:7','kind'=>'goals','fiks_id'=>77,'player_name'=>'Test Spiller','before'=>null,'after'=>$goal,'source'=>$url,'detected_at'=>$at];
$match=['id'=>123,'source'=>$url,'kickoff'=>$start,'events'=>[7=>$goal],'news_context'=>['id'=>123,'source'=>$url,'kickoff'=>$start,'home'=>['id'=>1,'name'=>'Hjemme'],'away'=>['id'=>2,'name'=>'Borte'],'competition'=>['id'=>3,'name'=>'Testserie'],'score'=>[1,0],'finished'=>true,'checked_at'=>$at]];
$player=['name'=>'Test Spiller','fiks_id'=>77,'snapshot'=>['matches'=>[123=>$match]]];
function selectEvents($p,$events){return F::select($p,$events,$GLOBALS['now']);}
$result=selectEvents($player,[$event]);check(count($result['proposals'])===1,'Fresh verified goal qualifies');
check($result['proposals'][123]['facts']['match']['competition']['name']==='Testserie','Writer receives competition and complete match packet');
foreach(['Utvisning','Straffemål','Selvmål'] as $type){$e=$event;$p=$player;$e['after']['type']=$type;$p['snapshot']['matches'][123]['events'][7]=$e['after'];check(count(selectEvents($p,[$e])['proposals'])===1,'Significant event passes: '.$type);}
foreach(['Innbytte','Utbytte','Advarsel'] as $type){$e=$event;$p=$player;$e['after']['type']=$type;$p['snapshot']['matches'][123]['events'][7]=$e['after'];$r=selectEvents($p,[$e]);check($r['proposals']===[]&&$r['excluded_counts']['routine']===1,'Routine event alone is quiet: '.$type);}
$sub=$event;$sub['key']='123:8';$sub['after']=['id'=>'8','name'=>'Test Spiller','minute'=>'20','type'=>'Innbytte'];$sub['detected_at']=gmdate(DATE_ATOM,$now-200);
$p=$player;$p['snapshot']['matches'][123]['events'][8]=$sub['after'];$r=selectEvents($p,[$event,$sub,$event]);check(count($r['proposals'])===1&&count($r['proposals'][123]['facts']['events'])===2,'One match packet across discovery times, no duplicate events');
check($r['proposals'][123]['facts']['events'][0]['after']['type']==='Innbytte','Relevant substitution is context when player scores');
$e=$event;$e['before']=$goal;$e['before']['minute']='45';check(selectEvents($player,[$e])['proposals']===[],'Correction is not new goal');
$renamed=$sub;$renamed['before']=$sub['after'];$renamed['before']['type']='Inn: Test Spiller';check(selectEvents($p,[$renamed])['excluded_counts']['correction']===1,'Parser label normalization is history only');
$e=$event;$e['after']=2;$e['before']=1;$e['key']='2026:1:goals';check(selectEvents($player,[$e])['proposals']===[],'Aggregate goal increase never fabricates match goal');
foreach(['statistics','club'] as $kind){$e=$event;$e['kind']=$kind;check(selectEvents($player,[$e])['proposals']===[],'Non-match change quiet: '.$kind);}
foreach(['fiks_id'=>88,'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=999','match_id'=>999,'key'=>'bad'] as $field=>$value){$e=$event;$e[$field]=$value;check(selectEvents($player,[$e])['proposals']===[],'Conflicting event identity rejected: '.$field);}
foreach(['kickoff'=>null,'news_context'=>[],'events'=>[]] as $field=>$value){$p=$player;$p['snapshot']['matches'][123][$field]=$value;check(selectEvents($p,[$event])['proposals']===[],'Missing match evidence rejected: '.$field);}
foreach(['2026-02-30T15:00:00Z','2026-10-04',gmdate(DATE_ATOM,$now+3600),gmdate(DATE_ATOM,$now-172801)] as $date){$p=$player;$p['snapshot']['matches'][123]['kickoff']=$date;check(selectEvents($p,[$event])['proposals']===[],'Invalid, future or historical match rejected');}
foreach(['finished'=>false,'score'=>null,'competition'=>null,'checked_at'=>gmdate(DATE_ATOM,$now-21601),'home'=>['id'=>2,'name'=>'Borte']] as $field=>$value){$p=$player;$p['snapshot']['matches'][123]['news_context'][$field]=$value;check(selectEvents($p,[$event])['proposals']===[],'Incomplete or stale context rejected: '.$field);}
$p=$player;$p['snapshot']['matches'][123]['events'][7]['type']='Advarsel';check(selectEvents($p,[$event])['proposals']===[],'Removed or reclassified goal cannot trigger writing');
$e=$event;$e['detected_at']=gmdate(DATE_ATOM,$now+3600);check(selectEvents($player,[$e])['proposals']===[],'Future detection rejected');
$p=$player;$p['snapshot']['matches'][123]['news_context']['kickoff']=gmdate(DATE_ATOM,$now-7100);check(selectEvents($p,[$event])['proposals']===[],'Moved match cannot reuse old completion evidence');
$p=$player;$p['snapshot']['matches'][124]=$match;$p['snapshot']['matches'][124]['id']=124;$p['snapshot']['matches'][124]['source']=str_replace('123','124',$url);$p['snapshot']['matches'][124]['news_context']['id']=124;$p['snapshot']['matches'][124]['news_context']['source']=$p['snapshot']['matches'][124]['source'];$e=$event;$e['key']='124:7';$e['source']=$p['snapshot']['matches'][124]['source'];check(count(selectEvents($p,[$event,$e])['proposals'])===2,'Separate match IDs never merged by timestamp');
$before=serialize($player);selectEvents($player,[$event]);check(serialize($player)===$before,'Selection never edits historical state');
echo "$count player news filter checks passed\n";
