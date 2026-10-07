<?php
require __DIR__.'/../includes/writer.php';
use RadioRubben\Fotballrobot\EditorialQuality as Q;
$n=0;function check($v,$why){global $n;$n++;if(!$v)throw new RuntimeException($why);}
$facts=['finished_confirmed'=>true,'match'=>['id'=>1,'home'=>['name'=>'Smørås'],'away'=>['name'=>'Bremnes'],'score'=>[1,3],'kickoff'=>'2026-10-01T18:45:00+02:00','events'=>[['name'=>'Pavlo Buriak']]]];
$a=['title'=>'Bremnes vant borte','lead'=>'Smørås og Bremnes spilte 1–3 den 01.10.2026.','paragraphs'=>['Pavlo Buriak scoret.'],'checks'=>[['claim'=>'Resultat','support'=>'match.score']]];
$yes=['approved'=>true,'issues'=>[]];$calls=0;
$fact=function()use(&$calls,$yes){$calls++;return $yes;};$language=function($a)use(&$calls){$calls++;return $a;};
$r=Q::begin($a,$facts);$r=Q::advance($r,$facts,$fact,$language);
check($calls===1&&$r['phase']==='language'&&!$r['publishable'],'First request only checks facts');
$r=Q::advance($r,$facts,$fact,$language);
check($calls===2&&$r['phase']==='done'&&$r['publishable'],'Unchanged copyedit completes after second request');
check(Q::advance($r,$facts,$fact,$language)===$r&&$calls===2,'Done state never makes a new paid call');
$rewrite=function($a)use(&$calls){$calls++;$a['title']='Bremnes slo Smørås';return $a;};
$r=Q::advance(Q::begin($a,$facts),$facts,$fact,$rewrite);$r=Q::advance($r,$facts,$fact,$rewrite);
check($r['phase']==='recheck'&&!$r['publishable'],'Rewrite always needs new fact review');
$r=Q::advance($r,$facts,fn()=>['approved'=>false,'issues'=>['Feil vinkel']],$rewrite);
check(!$r['publishable']&&$r['phase']==='done'&&count($r['factReviews'])===2,'Rejected rewrite is blocked');
$r=Q::advance(Q::begin($a,$facts),$facts,$fact,$language);
$changed=$facts;$changed['match']['score']=[0,3];$before=$calls;
$r=Q::advance($r,$changed,$fact,$language);
check(!$r['publishable']&&$calls===$before,'Changed facts stop before a paid call');
$r=Q::advance(Q::begin($a,$facts),$facts,$fact,$language);
$r=Q::advance($r,$facts,$fact,fn()=>throw new RuntimeException('secret provider body'));
check(!$r['publishable']&&$r['languageStatus']==='failed'&&!str_contains(json_encode($r),'secret provider'),'Transport failure is safely retained');
$facts['finished_confirmed']=false;$before=$calls;
$r=Q::advance(Q::begin($a,$facts),$facts,$fact,$language);
check(!$r['publishable']&&$calls===$before,'Unconfirmed match does not reach model');
echo "OK: $n staged quality controls\n";
