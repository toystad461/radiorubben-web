<?php
require __DIR__.'/../includes/writer.php';
use RadioRubben\Fotballrobot\EditorialQuality as Q;
use RadioRubben\Fotballrobot\Writer;
$count=0;
function check($ok,$why){global $count;$count++;if(!$ok)throw new RuntimeException($why);}
$facts=['finished_confirmed'=>true,'match'=>['home'=>['name'=>'Bremnes'],'away'=>['name'=>'Viggo'],
    'kickoff'=>'2026-09-25T19:00:00+02:00','score'=>[2,1],'events'=>[['name'=>'Ola Olsen']]],
    'report_extras'=>['manual_substitutions'=>[['in'=>'Ola Olsen','out'=>'Per Pedersen']]]];
$good=['title'=>'Bremnes slo Viggo 2–1','lead'=>'Bremnes vant 25. september 2026.',
    'paragraphs'=>['Ola Olsen kom inn for Per Pedersen. Som det framgår av kampdataene, vant Bremnes 2–1 over Viggo.'],
    'checks'=>[['claim'=>'Resultat','support'=>'match.score']]];
$yes=static fn()=>['approved'=>true,'issues'=>[]];
$same=static fn($a)=>$a;
$result=Q::review($good,$facts,$yes,function($a,$f,$instructions) use($good){
    check(str_contains($instructions,'versjon 1.0.0')&&str_contains($instructions,'kom inn for'),'Versioned fixed language prompt reaches reviewer');return $good;
});
check($result['publishable']&&$result['rulesVersion']==='1.0.0','Positive port of Robot fixture');
check(str_contains(Writer::prompt(),Q::prompt()),'Fixed rules included in generation');
$bad=$good;$bad['paragraphs']=[str_replace('kampdataene,','kampdataene',$good['paragraphs'][0])];
check(!Q::review($bad,$facts,$yes,$same)['publishable'],'Missing introductory comma blocked');
check(Q::review($bad,$facts,$yes,fn()=>$good)['publishable'],'Copyedit fixes introductory comma');
$bad=$good;$bad['paragraphs']=[str_replace('kom inn for','erstattet',$good['paragraphs'][0])];
check(!Q::review($bad,$facts,$yes,$same)['publishable'],'Ambiguous substitution blocked');
check(Q::review($bad,$facts,$yes,fn()=>$good)['publishable'],'Explicit substitution direction accepted');
$bad=$good;$bad['paragraphs']=[str_replace('Ola Olsen kom inn for Per Pedersen','Per Pedersen kom inn for Ola Olsen',$good['paragraphs'][0])];
check(!Q::review($bad,$facts,$yes,$same)['publishable'],'Reversed substitution blocked');
foreach(['Bremnes'=>'Brann','25. september 2026'=>'26. september 2026','2–1'=>'3–1','Ola Olsen'=>'Ola Olssen'] as $before=>$after){
    $changed=json_decode(str_replace($before,$after,json_encode($good,JSON_UNESCAPED_UNICODE)),true);
    check(!Q::review($good,$facts,$yes,fn()=>$changed)['publishable'],'Copyedit cannot change '.$before);
}
$reviewCalls=0;
$changed=$good;$changed['paragraphs'][]='Brann vant samme kamp 4–0. Ukjent Spiller scoret.';
$result=Q::review($good,$facts,function($a)use(&$reviewCalls){$reviewCalls++;return $reviewCalls===1?['approved'=>true,'issues'=>[]]:['approved'=>false,'issues'=>['Lag, resultat og navn mangler kildestøtte.']];},fn()=>$changed);
check(!$result['publishable']&&$reviewCalls===2,'Correct facts elsewhere cannot excuse contradictory rewrite; final text reviewed separately');
check(count($result['factReviews'])===2&&str_contains(implode(' ',$result['findings']),'navn'),'Both independent reviews retained');
$result=Q::review($good,$facts,fn()=>['approved'=>false,'issues'=>['Navnet stemmer ikke.']],function(){throw new RuntimeException('must not run');});
check(!$result['publishable']&&$result['languageStatus']==='not_run','Unapproved original facts stop before copyedit');
foreach([fn()=>throw new RuntimeException('secret'),fn()=>[],fn()=>['title'=>'x'],fn()=>['approved'=>true]] as $fail){
    $r=Q::review($good,$facts,$yes,$fail);
    check(!$r['publishable']&&$r['languageStatus']==='failed'&&$r['article']===$good,'Copyedit failure retains blocked original');
    check(!str_contains(json_encode($r),'secret'),'Provider exception not persisted');
}
foreach([['approved'=>'true','issues'=>[]],['approved'=>true],['approved'=>true,'issues'=>['Uavklart']]] as $malformed)
    check(!Q::review($good,$facts,fn()=>$malformed,$same)['publishable'],'Strict factual approval required');
$live=$facts;$live['finished_confirmed']=false;
check(!Q::review($good,$live,$yes,$same)['publishable'],'Unfinished match blocked');
$badScore=$facts;$badScore['match']['score']=[-1,1];
check(!Q::review($good,$badScore,$yes,$same)['publishable'],'Invalid score blocked');
echo "$count editorial checks passed; no transport\n";
