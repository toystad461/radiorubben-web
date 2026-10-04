<?php
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Sources as S;
use RadioRubben\PlayerWidget\Service as App;
use RadioRubben\PlayerWidget\View;
$checks=0;
function ok($value,$message) { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
$team=S::team(file_get_contents(__DIR__.'/fixtures/team.html'),35897);
$m=$team['matches'][8989882];$html=file_get_contents(__DIR__.'/fixtures/mygame.html');
$withoutLink=preg_replace('~<a\b.*?</a>~s','',$html);
$page=S::mygame($withoutLink,$m);
ok($page['source']===S::url('stream',8989882) && $page['url']===null,'Verified MyGame page does not require a broadcast');
try {S::mygame(str_replace('Brann 2','Wrong team',$withoutLink),$m);ok(false,'Wrong page accepted');} catch (RuntimeException $e) {ok(true,'Wrong page rejected');}
$posts=[1011,1013];$now=time();
foreach($posts as $id) $options['rrfr_player_'.$id]=['name'=>$id===1011?'Tiril Elisabeth Sellevold-Øystad':'Annen Spiller','fiks_id'=>$id===1011?3942773:3584397,'enabled'=>true,'snapshot'=>['clubs'=>[781=>[]],'stats'=>[]],'note'=>'DO NOT PUBLISH'];
$options[App::SETTINGS]=['revision'=>1,'enabled'=>true,'players'=>[1011=>['fiks_id'=>3942773,'teams'=>[35897]]]];
$m['kickoff']=gmdate(DATE_ATOM,$now+3600);$team['matches']=[8989882=>$m];
$options[App::CACHE]=['teams'=>[35897=>$team+['checked_at'=>$now,'error'=>null]],'matches'=>[8989882=>['match'=>$m,'stream'=>$page,'stream_checked_at'=>$now,'roles'=>[3942773=>'bench'],'lineup_checked_at'=>$now]]];
$before=$options;$beforeCalls=count($calls);$out=View::shortcode();
ok(substr_count($out,'class="rrpw-mini"')===1 && !str_contains($out,'Annen Spiller'),'Only selected profiles');
ok(str_contains($out,'MyGame ↗') && !str_contains($out,'TV 2 ↗'),'MyGame separate from stream');
ok(str_contains($out,'Innbytter') && !str_contains($out,'Spiller nå'),'No inferred live participation');
ok(!str_contains($out,'DO NOT PUBLISH') && $options===$before && count($calls)===$beforeCalls,'Read-only rendering, no private notes');
ok(View::shortcode(['player'=>999])==='','Unknown player filter');
$options[App::CACHE]['matches'][8989882]['stream_checked_at']=$now-App::FRESH-1;
ok(!str_contains(View::shortcode(),'href="https://kampoversikt'),'Expired MyGame link removed');
$options[App::CACHE]['teams'][35897]['matches']=[];
ok(str_contains(View::shortcode(),'Tiril') && !str_contains(View::shortcode(),'Ingen kamp'),'Player remains without a match');
$options[App::CACHE]['teams'][35897]['error']='Fetch failure';
ok(str_contains(View::shortcode(),'Tiril') && !str_contains(View::shortcode(),'uavklart'),'Fetch failure is not an empty schedule');
$options['rrfr_player_1011']['name']='<script>alert(1)</script>';
ok(!str_contains(View::shortcode(),'<script>'),'Name escaped');
$options['rrfr_player_1011']['enabled']=false;ok(View::shortcode()==='','Paused player hidden');
$options['rrfr_player_1011']['enabled']=true;$posts=[];ok(View::shortcode()==='','Deleted player hidden');
echo "$checks compact-widget checks passed\n";
