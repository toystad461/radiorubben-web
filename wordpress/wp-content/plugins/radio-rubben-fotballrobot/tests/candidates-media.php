<?php
// Real candidate/Players persistence and media validation; no network or paid writing.
require __DIR__.'/players.php';
require __DIR__.'/../includes/player-monitor.php';
require __DIR__.'/../includes/player-candidates.php';
require __DIR__.'/../includes/player-review.php';
use RadioRubben\Fotballrobot\PlayerCandidates as C;
use RadioRubben\Fotballrobot\PlayerMonitor as M;
use RadioRubben\Fotballrobot\Players as P;
use RadioRubben\Fotballrobot\MediaSources as S;
use RadioRubben\Fotballrobot\InlineSources as I;
use RadioRubben\Fotballrobot\PlayerReview as R;
function add_action(...$args){}function add_filter(...$args){}
function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function get_current_user_id(){return 7;}function admin_url($path){return 'https://example.org/wp-admin/'.$path;}
function wp_next_scheduled($hook){return time()+3600;}function register_rest_route($ns,$path,$routes){$GLOBALS['routes'][$path]=$routes;}
function get_post_meta($id,$key,...$args){global $posts;return $posts[$id]->meta_input[$key]??'';}
function esc_attr($v){return esc_html($v);}function wp_nonce_field($action){echo '<input name="_wpnonce" value="nonce">';}function sanitize_key($v){return $v;}
$profile='https://www.fotball.no/fotballdata/person/profil/?fiksId=3115348';
$input=['fiks_id'=>3115348,'name'=>'Kontrollert testspiller','former_club'=>'Bremnes','former_seasons'=>[2020,2021],
    'current_club'=>'Bjarg','current_level'=>'3. divisjon kvinner','active_season'=>2026,'history_note'=>'Spilte tidligere for Bremnes.',
    'checked_at'=>gmdate(DATE_ATOM),'sources'=>array_map(fn($kind)=>['kind'=>$kind,'url'=>$profile,'fact'=>'Dokumentert testfaktum.','public_read'=>true],['history','current','activity'])];
$before=count(P::ids());$r=C::ingest($input);check($r['created']&&count(P::ids())===$before,'Discovery cannot start following');
check(!C::ingest($input)['created']&&count(C::all())===1,'FIKS identity deduplication');
C::decide(3115348,1,'later');check(C::ingest($input)['candidate']['status']==='later','Rediscovery preserves later');
rejects(fn()=>C::decide(3115348,1,'approve'),'Stale decision rejected');
C::decide(3115348,2,'reject');check(C::ingest($input)['candidate']['status']==='rejected','Rediscovery preserves rejection');
rejects(fn()=>C::decide(3115348,3,'approve'),'Cannot silently approve rejection');
C::decide(3115348,3,'reopen');$r=C::decide(3115348,4,'approve');$pid=$r['player_id'];
check(count(P::ids())===$before+1&&P::state($pid)['group']==='bomlo-away'&&P::state($pid)['enabled'],'Explicit approval follows one player');
check(P::state($pid)['snapshot']===null&&P::state($pid)['events']===[],'Historical discovery creates no transfer event');
rejects(fn()=>C::decide(3115348,4,'approve'),'Replay cannot duplicate profile');
$duplicate=$input;$duplicate['fiks_id']=3942773;check(C::ingest($duplicate)['already_followed']&&count(C::all())===1,'Existing followed profiles remain intact');
$bad=$input;$bad['fiks_id']=42;$bad['current_club']='Moster IL';rejects(fn()=>C::ingest($bad),'Local to local excluded');
$bad['current_club']='Bjarg';$bad['former_club']='Stord';rejects(fn()=>C::ingest($bad),'Non-Bomlo history excluded');
$bad=$input;$bad['fiks_id']=42;$bad['sources']=array_slice($bad['sources'],0,2);rejects(fn()=>C::ingest($bad),'Missing activity proof');
$bad=$input;$bad['fiks_id']=42;$bad['checked_at']='2020-01-01T00:00:00Z';rejects(fn()=>C::ingest($bad),'Stale research rejected');
$permissions=[];rejects(fn()=>C::ingest($input),'Anonymous candidate write denied');rejects(fn()=>C::decide(3115348,5,'later'),'Anonymous decision denied');$permissions=['manage_options','edit_posts'];
C::routes();check(count($routes['/player-candidates'])===2,'GET and ingest only; approval is not an automation route');
$_GET['status']='all';ob_start();C::page();$html=ob_get_clean();check(str_contains($html,'Godkjent')&&str_contains($html,'ikke en fullstendig'),'Honest historical coverage and status visible');
$news=['fiks_id'=>3115348,'public_read'=>true,'url'=>'https://medium.example.org/sport/intervju','title'=>'Et kontrollert intervju',
    'identity_note'=>'Eksakt spilleridentitet bekreftet.','published_at'=>gmdate('Y-m-d'),'checked_at'=>gmdate(DATE_ATOM),'event_date'=>gmdate('Y-m-d'),
    'facts'=>['Spilleren ble intervjuet etter kampen.','Intervjuet handler om kampen.'],'format'=>'feature',
    'video'=>['url'=>'https://medium.example.org/sport/intervju','publisher'=>'Lokalmediet','content_verified'=>true],
    'supporting_sources'=>[
        ['kind'=>'match','url'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=42','title'=>'Kontrollert kamp','public_read'=>true,'checked_at'=>gmdate(DATE_ATOM),'facts'=>['Kampen er ferdig.','Resultatet var 1–0.']],
        ['kind'=>'background','url'=>$profile,'title'=>'Spillerens bakgrunn','public_read'=>true,'checked_at'=>gmdate(DATE_ATOM),'facts'=>['Spilleren hadde kamper for Bremnes.','Spilleren er nå registrert i Bjarg.']]]];
$bad=$news;$bad['video']['content_verified']=false;rejects(fn()=>M::ingest($pid,$bad),'Unseen video cannot enter article queue');
$bad=$news;$bad['supporting_sources']=array_slice($news['supporting_sources'],0,1);rejects(fn()=>M::ingest($pid,$bad),'Feature requires background');
$bad=$news;$bad['video']['url']='https://video.example.org/guessed';rejects(fn()=>M::ingest($pid,$bad),'No guessed video link');
$bad=$news;$bad['supporting_sources'][0]['public_read']=false;rejects(fn()=>M::ingest($pid,$bad),'Snippets not accepted as sources');
$bad=$news;$bad['facts']=array_fill(0,6,str_repeat('faktum ',21));rejects(fn()=>M::ingest($pid,$bad),'Per-source word budget');
$received=M::ingest($pid,$news);check($received['created']&&count($received['source']['supporting_sources'])===2,'Verified feature queued');
check(!M::ingest($pid,$news)['created'],'Source replay idempotent');
$facts=M::candidates()[0]['facts'];check(count(I::urls($facts))===3,'All three verified sources available for attribution');
$a=['title'=>'Kontrollert intervju','lead'=>'Spilleren ble intervjuet etter kampen.','paragraphs'=>['Resultatet var 1–0.','Spilleren hadde kamper for Bremnes.'],'checks'=>[],
    'inline_sources'=>[['paragraph'=>0,'text'=>'Resultatet var 1–0','source_url'=>M::url($news['supporting_sources'][0]['url'])],['paragraph'=>1,'text'=>'hadde kamper for Bremnes','source_url'=>M::url($profile)]]];
$body=R::body($a,$facts);check(str_contains($body,'Se hele videointervjuet hos Lokalmediet')&&substr_count($body,'<a href=')===6,'Inline evidence, original video and complete source list');
check(!str_contains($body,'iframe')&&!str_contains($body,'<video'),'No republished third-party video');
$saved=$a;$saved['paragraphs']=[strip_tags($body)];$saved['inline_sources']=I::fromHtml($body);I::validate($saved,$facts);check(true,'Saved video article can be rechecked');
$wrong=$a;$wrong['inline_sources'][0]['source_url']='https://unrelated.example.org/story';rejects(fn()=>I::validate($wrong,$facts),'Unverified inline URL rejected');
echo "$count candidate and media checks passed\n";
