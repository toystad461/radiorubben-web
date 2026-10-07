<?php
// Exercise the real current publication boundary and model transport with isolated WordPress doubles.
require __DIR__.'/publication-gate.php';
use RadioRubben\Fotballrobot\ClubAutomation as C;
use RadioRubben\Fotballrobot\PublicationGate as G;
use RadioRubben\Fotballrobot\EditorialQuality as Q;
use RadioRubben\Fotballrobot\Writer as W;
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_url($v){return esc_html($v);}
function wp_date($fmt,$time,$tz=null){return (new DateTimeImmutable('@'.$time))->setTimezone($tz??new DateTimeZone('Europe/Oslo'))->format($fmt);}
function get_posts($args){return [];}
$requests=[];
$draft=['title'=>'Bremnes G13 vant på hjemmebane','lead'=>'Bremnes G13 vant 2–1 mot Viggo den 25.09.2026.',
    'paragraphs'=>['Kampen ble spilt på Testbanen.'],'checks'=>[['claim'=>'Kampresultat','support'=>'match.score']],'inline_sources'=>[]];
$f=$facts;$f['match']['home']['registered_name']='Bremnes G13';$f['match']['venue']='Testbanen';
$queue=[response($draft),response($yes),response($draft)];
$a=W::clubArticle($f);
check(count($requests)===3&&$a['_quality']['publishable'],'Youth uses independent fact review and language review');
$post=C::reviewedPost($a,W::body($a,$f),$a['_quality']);
$posts[10]=(object)(['ID'=>10,'post_type'=>'post','post_status'=>'draft']+$post);
$meta[10]=$post['meta_input']+['_rrfr_club_key'=>'match:123','_rrfr_ai_match'=>123,'_rrfr_fact_snapshot'=>$f];
check(G::current(10,$posts[10]),'Youth approval binds its own API snapshot without the senior fact store');
$meta[10]['_rrfr_fact_snapshot']['match']['score']=[4,1];
check(!G::current(10,$posts[10]),'New raw score invalidates youth approval');
$week=['key'=>'2026-10-12','start'=>'2026-10-12T00:00:00+02:00','end'=>'2026-10-19T00:00:00+02:00'];
$m=$f['match'];$m['kickoff']='2026-10-13T18:00:00+02:00';$m['competition']=['name'=>'G13 2. divisjon'];$m['source']='https://www.fotball.no/fotballdata/kamp/?fiksId=123';
$fetched='2026-10-11T16:00:00Z';$wf=C::weeklyFacts([$m],$week,$fetched);
$requests=[];$queue=[response($yes),function(){
    $last=end($GLOBALS['requests']);$input=json_decode($last['input'],true);return response($input['article']);
}];
$post=C::weeklyArticle([$m],$week,$fetched);
check(count($requests)===2&&$post['meta_input'][G::META]['publishable'],'Weekly introduction receives factual and language review');
check(str_contains($post['post_content'],'Bremnes G13 – Viggo')&&str_contains($post['post_content'],'18:00')&&str_contains($post['post_content'],'G13 2. divisjon'),'Every fixture is rendered directly with team, time and competition');
$posts[11]=(object)(['ID'=>11,'post_type'=>'post','post_status'=>'draft']+$post);
$meta[11]=$post['meta_input']+['_rrfr_club_key'=>'week:2026-10-12','_rrfr_fact_snapshot'=>$wf];
check(G::managed(11)&&G::current(11,$posts[11]),'Weekly posts are managed and their complete rendered body is bound');
wp_update_post(['ID'=>11,'post_content'=>$post['post_content'].'<p>Endret kamp.</p>','post_status'=>'publish']);
check($posts[11]->post_status==='draft','Changing a deterministic fixture blocks publication');
wp_update_post(['ID'=>11,'post_content'=>$post['post_content']]);
$meta[11]['_rrfr_fact_snapshot']['matches'][0]['kickoff']='2026-10-14T18:00:00+02:00';
check(!G::current(11,$posts[11]),'Changed weekly facts invalidate approval');
$meta[11]['_rrfr_fact_snapshot']=$wf;
$meta[11][G::META]['publishable']=false;
wp_update_post(['ID'=>11,'post_status'=>'publish']);
check($posts[11]->post_status==='draft','Failed weekly quality cannot publish');
check(G::guard(['post_type'=>'post','post_status'=>'publish'],['meta_input'=>['_rrfr_club_key'=>'week:fake']])['post_status']==='draft','New weekly post cannot bypass initial draft');
echo "OK: club integration with current quality pipeline and publication guard\n";
