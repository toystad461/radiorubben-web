<?php
require __DIR__.'/../includes/facts.php';
use RadioRubben\Fotballrobot\Facts;
$count=0;
function check($ok,$message) { global $count; $count++; if(!$ok) throw new RuntimeException($message); }
$m=Facts::match(file_get_contents(__DIR__.'/fixtures/match.html'),8985491);
$b=Facts::history(file_get_contents(__DIR__.'/fixtures/bremnes.html'),30365);
$v=Facts::history(file_get_contents(__DIR__.'/fixtures/viggo.html'),19046);
$bf=Facts::form($b,$m,30365); $vf=Facts::form($v,$m,19046);
check($m['home']['id']===30365 && $m['away']['id']===19046,'Lag-ID');
check($m['score']===[2,2] && $m['half_time']===[0,2],'Bruk første kampkort, ikke tidligere møter');
check($vf['wins']===5 && $vf['wins_exact'],'Viggo fem strake, med bekreftet brudd');
check($bf['wins']===4 && $bf['wins_exact'],'Bremnes fire strake før kampen');
check(count($vf['last_five'])===5 && $vf['points_last_five']===15,'Fem siste');
check(!in_array(8985491,array_column($vf['last_five'],'id'),true),'Ekskluder aktuell kamp');
$angles=Facts::angles($m,['home'=>$bf,'away'=>$vf]);
check(in_array('late_equalizer',array_column($angles,'id')),'Sen utligning');
check(in_array('comeback_home',array_column($angles,'id')),'Opphenting');
check(in_array('streak_away',array_column($angles,'id')),'Viggos lengre seiersrekke blant tre vinkler');
// Both teams lost their win streak; include opponent in available choices.
$duplicate=Facts::form(array_merge($v,[$v[0]]),$m,19046);
check($duplicate===$vf,'Duplikater');
$conflict=$v[0]; $conflict['score']=[0,0];
// Use a league match to exercise the conflict, not a filtered winter fixture.
foreach($v as $row) if($row['competition_id']===$m['competition']['id'] && $row['id']!==$m['id']) {$conflict=$row;$conflict['score']=[99,98];break;}
try { Facts::form(array_merge($v,[$conflict]),$m,19046); check(false,'Konflikt skulle stoppe'); } catch(RuntimeException $e) {check(true,'Konflikt stoppet');}
$latest=$vf['last_five'][0]; $unknown=$latest; $unknown['score']=null;
$missing=array_map(fn($r)=>$r['id']===$unknown['id']?$unknown:$r,$v);
$mf=Facts::form($missing,$m,19046);
check($mf['wins']===0 && count($mf['last_five'])===0 && $mf['unresolved_count']>=1,'Ikke hopp over ukjent kamp');
$draw=$latest; $draw['score']=[1,1];
$df=Facts::form(array_map(fn($r)=>$r['id']===$draw['id']?$draw:$r,$v),$m,19046);
check($df['wins']===0 && $df['unbeaten']>=5,'Uavgjort bryter seier, ikke ubeseiret');
$partial=Facts::form($vf['last_five'],$m,19046);
check($partial['wins']===5 && !$partial['wins_exact'],'Avgrenset historikk gir minst');
$short=$m; array_pop($short['events']);
$short['events']=array_values(array_filter($m['events'],fn($e)=>$e['minute']!=='89'));
check(!in_array('late_equalizer',array_column(Facts::angles($short,[]),'id')),'Ufullstendig hendelsesliste gir ingen avgjørende mål-vinkel');
$own=['id'=>1,'home'=>['id'=>1,'name'=>'A'],'away'=>['id'=>2,'name'=>'B'],'score'=>[1,1],'events'=>[['id'=>'a','side'=>'home','minute'=>'10','type'=>'Spillemål','name'=>'A'],['id'=>'b','side'=>'home','minute'=>'89','type'=>'Selvmål','name'=>'B']]];
check(!in_array('late_equalizer',array_column(Facts::angles($own,[]),'id')),'Selvmål kåres ikke til helt');
check(Facts::date('31.02.2026 19:00')===null,'Ugyldig dato');
try {Facts::history('<html><h1>Feil</h1></html>',19046);check(false,'Feilside');} catch(RuntimeException $e){check(true,'Feilside stoppet');}
echo "OK: $count kontroller\n";
echo json_encode(['home_form'=>$bf['wins'],'away_form'=>$vf['wins'],'angles'=>$angles],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
