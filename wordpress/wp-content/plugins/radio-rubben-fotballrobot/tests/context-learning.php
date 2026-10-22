<?php
require __DIR__.'/../includes/facts.php';
use RadioRubben\Fotballrobot\Facts;
$count=0;
function check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException($label);}
$m=['id'=>100,'home'=>['id'=>10,'name'=>'Testlaget G13'],'away'=>['id'=>20,'name'=>'Motstander G13'],
    'competition'=>['id'=>7],'kickoff'=>'2026-10-22T18:00:00+02:00','score'=>[3,2],'events'=>[]];
function row($id,$date,$home=10,$away=20,$score=[2,1],$comp=7){return ['id'=>$id,'kickoff'=>$date.'T18:00:00+02:00','home'=>['id'=>$home,'name'=>'A'],'away'=>['id'=>$away,'name'=>'B'],'score'=>$score,'competition_id'=>$comp,'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$id];}
$rows=[row(1,'2026-10-01'),row(2,'2026-10-08',20,10,[3,0]),row(3,'2026-10-15'),row(4,'2026-10-19',10,20,[0,1],8),row(5,'2026-10-20',30,20),row(100,'2026-10-22',10,20,[3,2]),row(6,'2026-10-24',10,20,null)];
$f=Facts::form($rows,$m,10);
check($f['wins']===1 && $f['venues']['home']['wins']===2,'away loss does not break home-only history');
check($f['venues']['home']['win_match_ids']===[3,1],'exclude other tournament, team, current and future');
check(array_column($f['venues']['home']['win_evidence'],'id')===[3,1],'evidence retains exact source rows');
$a=Facts::angles($m,['home'=>$f]);
$v=array_values(array_filter($a,fn($x)=>$x['id']==='venue_wins_home'));
check(count($v)===1 && $v[0]['match_ids']===[3,1,100],'current win counted exactly once');
check(str_contains($v[0]['reason'],'ikke bekreftet komplett'),'no claim of complete history');
$unknown=Facts::form(array_merge($rows,[row(9,'2026-10-16',10,20,null)]),$m,10);
check($unknown['venues']['home']['wins']===0,'unresolved home match blocks streak');
check(Facts::form(array_merge($rows,[$rows[0]]),$m,10)===$f,'identical duplicate ignored');
try{Facts::form(array_merge($rows,[row(1,'2026-10-01',10,20,[0,1])]),$m,10);check(false,'conflict accepted');}catch(RuntimeException $e){check(true,'conflict rejected');}
check(Facts::form([row(1,'2025-10-01')],$m,10)['wins']===0,'other season excluded');
check(Facts::form([row(3,'2026-10-15',10,20,[1,1])],$m,10)['venues']['home']['wins']===0,'draw breaks wins');
foreach(['<dl><dt>Spilletid</dt><dd>70 minutter</dd></dl>','<p>Spilletid: 70 min</p>','<p>Spilletid: 2 x 35 minutter</p>'] as $html)check(Facts::duration(Facts::dom($html))===70,'explicit duration parsed');
check(Facts::duration(Facts::dom('<p>G13 – 70 spillere</p>'))===null,'never infer duration from age');
check(Facts::duration(Facts::dom('<p>Spilletid: 70 min</p><p>Spilletid: 90 min</p>'))===null,'conflicting duration unknown');
$d=$m;$d['duration_minutes']=70;$d['score']=[1,1];$d['events']=[['id'=>'a','side'=>'away','minute'=>'10','type'=>'Spillemål','name'=>'Test Spiller'],['id'=>'b','side'=>'home','minute'=>'65','type'=>'Spillemål','name'=>'Målscorer skjult']];
$a=Facts::angles($d,[]);check($a[0]['id']==='late_equalizer' && $a[0]['title']==='Testlaget G13 sikret poeng','70 minute match and unnamed scorer use team');
$d['duration_minutes']=90;check(!in_array('late_equalizer',array_column(Facts::angles($d,[]),'id')),'65 is not late in 90 minute match');
$d['duration_minutes']=null;check(!in_array('late_equalizer',array_column(Facts::angles($d,[]),'id')),'unknown duration gives no late claim');
$d['duration_minutes']=70;array_pop($d['events']);check(!in_array('late_equalizer',array_column(Facts::angles($d,[]),'id')),'incomplete event tally cannot give decisive narrative');
$html=file_get_contents(__DIR__.'/fixtures/match.html');
$html=preg_replace('/(<[^>]+class="eventHeading"[^>]*>).*?(<\/[^>]+>)/s','$1Målscorer skjult$2',$html);
foreach(Facts::match($html,8985491)['events'] as $e)check(!str_contains($e['name'],'skjult'),'parser removes hidden placeholders');
echo "OK: $count context-learning checks\n";
