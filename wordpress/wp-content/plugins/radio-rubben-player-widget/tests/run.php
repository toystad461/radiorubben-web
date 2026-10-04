<?php
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Sources as S;
use RadioRubben\PlayerWidget\Service as App;
use RadioRubben\PlayerWidget\View;
$checks = 0;
function check($ok,$name) { global $checks; $checks++; if (!$ok) throw new RuntimeException($name); }
function rejects($fn,$name) { try { $fn(); } catch (Throwable $e) { check(true,$name); return; } check(false,$name); }
$teamHtml = file_get_contents(__DIR__.'/fixtures/team.html');
$streamHtml = file_get_contents(__DIR__.'/fixtures/mygame.html');
$lineupHtml = file_get_contents(__DIR__.'/fixtures/lineup.html');
$team = S::team($teamHtml,35897); $m=$team['matches'][8989882];
check($team['club_id']===781,'Club identity');
check(count($team['matches'])===2,'Cancelled and finished matches excluded');
check($m['home']['id']===209123 && $m['away']['id']===35897,'Home and away IDs preserved');
check($m['kickoff']==='2026-10-04T14:00:00+02:00','Oslo timezone');
rejects(fn()=>S::team($teamHtml,30365),'Wrong team rejected');
rejects(fn()=>S::team('<html>Service unavailable</html>',35897),'HTTP 200 error page rejected');
rejects(fn()=>S::team(str_replace('04.10.2026','32.10.2026',$teamHtml),35897),'Invalid date');
foreach (['-1','0','1.5','https://evil.test',[], '9999999999'] as $id) rejects(fn()=>S::id($id),'Invalid ID rejected');
$stream=S::stream($streamHtml,$m);
check($stream['url']==='https://play.tv2.no/gpid/c849b4db-02ae-451f-a507-dd01135f8359','Real observed broadcast link');
check(str_ends_with($stream['logos']['home'],'3302.png') && str_ends_with($stream['logos']['away'],'781.png'),'Logos matched to team labels');
foreach ([
    str_replace('fiks-no8989882','fiks-no8989883',$streamHtml),
    str_replace('Sogndal Fotballklubb','Wrong team',$streamHtml),
    str_replace('2026-10-04T12:00:00.000Z','2026-10-04T11:00:00.000Z',$streamHtml),
    str_replace('EventScheduled','EventCancelled',$streamHtml),
    str_replace('https://play.tv2.no/gpid/','https://play.tv2.no.evil.test/gpid/',$streamHtml),
    str_replace('https://play.tv2.no/gpid/','https://user:pass@play.tv2.no/gpid/',$streamHtml),
    str_replace('https://play.tv2.no/gpid/','javascript:',$streamHtml),
    preg_replace('~<a\b.*?</a>~s','',$streamHtml),
    '<h1>Kamp ikke funnet</h1>',
] as $bad) rejects(fn()=>S::stream($bad,$m),'No speculative stream links');
$extra='<a href="https://play.tv2.no/gpid/11111111-1111-1111-1111-111111111111?utm_content=fiks-no8989882">Other</a>';
rejects(fn()=>S::stream($streamHtml.$extra,$m),'Conflicting stream destinations rejected');
check(S::lineup($lineupHtml,$m,[3942773,111,999])===[3942773=>'starter',111=>'bench',999=>null],'Exact person IDs and role');
check(S::lineup(str_replace('fiksId=3942773','fiksId=999',$lineupHtml),$m,[3942773])===[3942773=>null],'Names never prove participation');
check(S::lineup(str_replace('Testspiller','Testspiller strøket',$lineupHtml),$m,[3942773])===[3942773=>null],'Withdrawn player is unknown');
rejects(fn()=>S::lineup(str_replace('14:00','15:00',$lineupHtml),$m,[3942773]),'Moved kickoff invalidates lineup');
rejects(fn()=>S::lineup(str_replace('fiksId=35897','fiksId=1',$lineupHtml),$m,[3942773]),'Wrong team lineup rejected');
$posts=[1011,1013];
$source=['name'=>'Tiril Test','fiks_id'=>3942773,'enabled'=>true,'note'=>'PRIVATE NOTE','group'=>'bomlo-away','snapshot'=>['clubs'=>[781=>['id'=>781]],'stats'=>[['year'=>(int)wp_date('Y'),'team_id'=>108426,'team'=>'Brann']]]];
$options['rrfr_player_1011']=$source;
$options['rrfr_player_1013']=array_replace($source,['name'=>'Second Test','fiks_id'=>3584397,'group'=>'']);
$input=['revision'=>0,'enabled'=>1,'players'=>[1011=>['show'=>1,'teams'=>'35897, 108426,35897'],1013=>['show'=>1,'teams'=>'35897']]];
rejects(fn()=>App::save($input),'Anonymous configuration denied');
$admin=true; App::save($input);
check(App::settings()['players'][1011]['teams']===[35897,108426],'Explicit extra team and deduplication');
check(isset(App::settings()['players'][1013]),'Group label does not silently omit a selected player');
check($scheduled,'Refresh job scheduled');
check(!isset(App::profiles()[1011]['note']),'Private notes never enter widget profiles');
rejects(fn()=>App::save($input),'Stale admin form rejected');
$bad=$input; $bad['revision']=1; $bad['players'][1011]['teams']='https://evil.test';
rejects(fn()=>App::save($bad),'URL not accepted as team ID');
$now=time();$m['kickoff']=gmdate(DATE_ATOM,$now+3600);$team['matches']=[8989882=>$m];
$cache=['teams'=>[35897=>$team+['checked_at'=>$now,'error'=>null]],'matches'=>[]];
$profiles=App::profiles();$settings=App::settings();
$candidates=App::candidates($settings,$profiles,$cache,$now);
check(count($candidates)===1 && count($candidates[8989882]['players'])===2,'One match for multiple followed players');
$wrong=$cache;$wrong['teams'][35897]['club_id']=711;
check(App::candidates($settings,$profiles,$wrong,$now)===[],'Old club/team suppressed');
$stale=$cache;$stale['teams'][35897]['checked_at']=$now-App::TEAM_FRESH-1;
check(App::candidates($settings,$profiles,$stale,$now)===[],'Stale schedules hidden');
$failed=$cache;$failed['teams'][35897]['error']='HTTP 503';
check(App::candidates($settings,$profiles,$failed,$now)===[],'Failed schedule cannot keep actionable old matches');
$paused=$profiles;$paused[1011]['enabled']=false;$paused[1013]['enabled']=false;
check(App::candidates($settings,$paused,$cache,$now)===[],'Paused players disappear immediately');
$oldId=$profiles;$oldId[1011]['fiks_id']=999;unset($oldId[1013]);
check(App::candidates($settings,$oldId,$cache,$now)===[],'Reused local profile ID cannot change public identity');
$cache['matches'][8989882]=['match'=>$m,'stream'=>$stream,'stream_checked_at'=>$now,'lineup_checked_at'=>$now,'roles'=>[3942773=>'starter']];
$options[App::CACHE]=$cache;
$cards=App::cards();check(count($cards)===1 && $cards[0]['players'][3942773]['role']==='starter','Fresh lineup rendered');
$before=$options;$beforeCalls=count($calls);$markup=View::shortcode();
check($before===$options && count($calls)===$beforeCalls,'Rendering never fetches or changes data');
check(str_contains($markup,'Se på TV 2 Play'),'Verified CTA');
check(!str_contains($markup,'PRIVATE NOTE'),'Private data absent');
check(count(App::cards(3584397))===1 && App::cards(999)===[],'Individual player filter');
$posts=[1011];check(count(App::cards()[0]['players'])===1,'Deleted player post excluded even if old option survives');$posts=[1011,1013];
$options[App::CACHE]['matches'][8989882]['stream_checked_at']=$now-App::FRESH-1;
$options[App::CACHE]['matches'][8989882]['lineup_checked_at']=$now-App::FRESH-1;
$markup=View::shortcode();
check(!str_contains($markup,'href="https://play.tv2.no/gpid/'),'Expired CTA removed');
check(str_contains($markup,'Sending ikke bekreftet') && str_contains($markup,'Tropp ikke bekreftet'),'Honest unknown states');
$options[App::CACHE]=$cache;$options[App::CACHE]['teams'][35897]['matches'][8989882]['kickoff']=gmdate(DATE_ATOM,$now+7200);
check(App::cards()[0]['stream']===null && App::cards()[0]['players'][3942773]['role']===null,'Rescheduling invalidates previous confirmations');
$options[App::CACHE]=$cache;
$options['rrfr_player_1011']['name']='<script>alert(1)</script>';
check(!str_contains(View::shortcode(),'<script>alert'),'Player name escaped');
$options['rrfr_player_1011']=$source;
$options[App::CACHE]['teams'][35897]['matches'][8989882]['home']['name']='<img src=x onerror=alert(1)>';
check(!str_contains(View::shortcode(),'<img src=x'),'Team name escaped');
// Full refresh with fixed transports. No network is used by the test.
$localDate=wp_date('d.m.Y',$now+3600);$localTime=wp_date('H:i',$now+3600);
$iso=gmdate('Y-m-d\TH:i:00.000\Z',$now+3600);
$teamNow=str_replace(['04.10.2026','14:00'],[$localDate,$localTime],$teamHtml);
$lineupNow=str_replace(['04.10.2026','14:00'],[$localDate,$localTime],$lineupHtml);
$streamNow=str_replace('2026-10-04T12:00:00.000Z',$iso,$streamHtml);
$remote[S::url('team',35897)]=['status'=>200,'body'=>$teamNow];
$remote[S::url('match',8989882)]=['status'=>200,'body'=>$lineupNow];
$remote[S::url('stream',8989882)]=['status'=>200,'body'=>$streamNow];
$options[App::SETTINGS]['players']=[1011=>['fiks_id'=>3942773,'teams'=>[35897]]];
$options[App::CACHE]=['teams'=>[],'matches'=>[]];$calls=[];
App::tick();$result=App::cache();
check(!empty($result['matches'][8989882]['stream']['url']),'Refresh finds verified stream');
check(!isset($options['rrpw_refresh_lock']),'Refresh unlocks');
check(count($calls)<=5,'Bounded HTTP request budget');
foreach ($calls as $call) check($call['args']['redirection']===0 && $call['args']['timeout']===5,'Redirects off and timeout bounded');
$options['rrpw_refresh_lock']=['token'=>'other','at'=>$now];$n=count($calls);App::tick();
check(count($calls)===$n,'Concurrent refresh skipped');unset($options['rrpw_refresh_lock']);
$options[App::CACHE]['matches'][8989882]['attempted_at']=0;
$remote[S::url('stream',8989882)]=['status'=>503,'body'=>''];$remote[S::url('match',8989882)]=['status'=>503,'body'=>''];
App::tick();check(App::cards()[0]['stream']===null && App::cards()[0]['players'][3942773]['role']===null,'Source failures remove previous confirmation');
App::save(['revision'=>1,'players'=>[]]);
check(App::cards()===[] && !$scheduled,'Disable clears public output and job');
echo "$checks player-widget checks passed\n";
