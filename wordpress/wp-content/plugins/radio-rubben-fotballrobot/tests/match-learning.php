<?php
require __DIR__.'/../includes/facts.php';
use RadioRubben\Fotballrobot\Facts;
$count=0;
function check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException($label);}
function goal($id,$side,$minute,$name,$type='Spillemål'){return compact('id','side','minute','name','type');}
function angle($m,$id){foreach(Facts::scoringAngles($m) as $a)if($a['id']===$id)return $a;return null;}
$m=['id'=>101,'home'=>['id'=>10,'name'=>'Hjemmelaget'],'away'=>['id'=>20,'name'=>'Bortelaget'],
    'score'=>[3,4],'duration_minutes'=>70,'events'=>[
        goal('a','away','15','Spiller A'),goal('b','home','31','Spiller B'),
        goal('c','away','58','Personinfo ikke tilgjengelig'),goal('d','away','60','Spiller A'),
        goal('e','away','61','Spiller A'),goal('f','home','67','Spiller B'),goal('g','home','74','Spiller B')]];
$hat=angle($m,'hattricks');
check(count($hat['scorers'])===2,'both teams have a hattrick');
check($hat['scorers'][0]['event_ids']===['a','d','e'],'hidden scorer not attributed to named scorer');
check($hat['scorers'][1]['minutes']===['31','67','74'],'overtime raw minute preserved');
check(angle($m,'winning_goal')['winning_event_id']==='c','last goal is not automatically decisive');
check(in_array('hattricks',array_column(Facts::angles($m,[]),'id')),'new angles reach actual pipeline');
$n=$m;$n['events'][]=$n['events'][0];
check(count(angle($n,'hattricks')['event_ids'])===6,'duplicate event ID counted once');
$n['events'][7]['name']='Conflicting name';
check(Facts::scoringAngles($n)===[],'conflicting duplicate suppresses evidence');
$n=$m;array_pop($n['events']);
check(Facts::scoringAngles($n)===[],'incomplete score suppresses narrative');
$n=$m;foreach($n['events'] as &$e)$e['name']='Personinfo ikke tilgjengelig';unset($e);
check(angle($n,'hattricks')===null,'hidden placeholders never form one scorer');
$n=$m;foreach($n['events'] as &$e)if($e['name']==='Spiller B')$e['name']='Spiller A';unset($e);
check(count(angle($n,'hattricks')['scorers'])===2,'identical names on opponents stay separate');
$n=$m;$n['events'][4]['type']='Straffemål';
check(count(angle($n,'hattricks')['scorers'])===2,'penalties count');
$n=$m;$n['events'][4]['type']='Selvmål';$n['events'][4]['side']='home';
check(count(angle($n,'hattricks')['scorers'])===1,'own goal not credited to named player');
$n=$m;$n['events'][2]['minute']='31';
check(angle($n,'winning_goal')===null && angle($n,'hattricks')!==null,'same minute permits totals, not sequence');
$n=$m;$n['events'][6]['minute']='70+4';
check(angle($n,'hattricks')['scorers'][1]['minutes'][2]==='70+4','added-time notation preserved');
$n=$m;$n['events'][]=goal('h','home','75','Spiller B');$n['score']=[4,4];
check(count(angle($n,'hattricks')['scorers'][1]['event_ids'])===4,'four goals retain exact count');
check(angle($n,'winning_goal')===null,'draw has no winning goal');
// Synthetic 80-minute match: three equalizers followed by a new permanent lead.
$t=$m;$t['duration_minutes']=80;$t['score']=[4,3];$t['events']=[
    goal('1','home','17','A'),goal('2','away','23','B'),goal('3','home','41','C'),
    goal('4','away','43','B'),goal('5','home','55','D'),goal('6','away','60','B'),goal('7','home','70','E')];
$winner=angle($t,'winning_goal');
check($winner['winning_event_id']==='7' && $winner['minute']==='70','final permanent lead identified');
check($winner['equalizer_event_ids'][1]===['2','4','6'],'three equalizers retain their evidence');
$t['events'][6]['type']='Selvmål';$t['events'][6]['side']='away';
check(angle($t,'winning_goal')===null,'own goal does not crown scorer as winner');
$z=$m;$z['score']=[0,0];$z['events']=[goal('red','home','57','Spiller C','Rødt kort')];
check(Facts::scoringAngles($z)===[],'red card in goalless game cannot invent scoring narrative');
check(array_column(Facts::angles($z,[]),'id')===['result'],'goalless match keeps result angle');
echo "OK: $count match-learning checks\n";
