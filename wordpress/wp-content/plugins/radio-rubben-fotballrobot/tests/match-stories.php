<?php
namespace RadioRubben\Fotballrobot {
 class Robot {static function allowed(){return $GLOBALS['allowed'];}}
 class EditorialQuality {static function hash($v){return hash('sha256',json_encode($v));}}
 class PublicationGate {static function hash($p){return EditorialQuality::hash($p);}static function facts($id){return $GLOBALS['facts'][$id];}}
 class ClubCoverage {static function blocked($m){return !empty($m['cancelled']);}static function collect(){return ['matches'=>$GLOBALS['feed']];}static function detailFacts($m){return ['match'=>$m,'detail_check'=>['version'=>1,'match_id'=>$m['id']]];}}
 class MatchJobs {static function state($id){return $GLOBALS['jobs'][$id]??[];}}
 class ClubAutomation {
  static function existing($key){return $GLOBALS['existing'][$key]??0;}
  static function create($key,$facts,$build){$GLOBALS['created']++;$a=$build();$id=200;$GLOBALS['posts'][$id]=(object)(['ID'=>$id,'post_type'=>'post','post_status'=>'draft']+$a);$GLOBALS['facts'][$id]=$facts;$GLOBALS['meta'][$id]=['_rrfr_fact_snapshot'=>$facts]+$a['meta_input'];$GLOBALS['existing'][$key]=$id;return $id;}
  static function reviewedPost($a,$body,$q){return ['post_title'=>$a['title'],'post_content'=>$body,'post_excerpt'=>$a['lead'],'meta_input'=>['quality'=>$q]];}
 }
 class Writer {static function briefArticle($f){$GLOBALS['writes']++;if(!empty($GLOBALS['duringWrite']))($GLOBALS['duringWrite'])();return ['title'=>'Testlaget vant','lead'=>'Et kildebelagt resultat.','paragraphs'=>['Kort referat.'],'_quality'=>['publishable'=>false]];}}
 class EditorialNotice {const BLOCK='<p>AI-generert artikkel</p>';}
 class InlineSources {static function paragraphs($a,$f){return [$a['lead'],$a['paragraphs'][0]];}}
}
namespace {
function wp_update_post($v,...$args){foreach($v as $k=>$value)$GLOBALS['posts'][$v['ID']]->$k=$value;return $v['ID'];}
function is_wp_error($v){return false;}
function wp_json_encode($v){return json_encode($v);}
function get_post($id){return isset($GLOBALS['posts'][$id])?clone $GLOBALS['posts'][$id]:null;}
function get_post_meta($id,$key,...$a){return $GLOBALS['meta'][$id][$key]??'';}
function current_user_can($cap,...$a){return $GLOBALS['allowed']&&($cap!=='publish_posts'||$GLOBALS['publish']);}
function add_option($k,$v,...$a){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_url($s){return esc_html($s);}
function get_permalink($id){return 'https://example.test/article/'.$id;}
function wp_date($f,$t,$tz=null){return (new DateTimeImmutable('@'.$t))->setTimezone($tz??new DateTimeZone('Europe/Oslo'))->format($f);}
function check($v,$why){$GLOBALS['count']++;if(!$v)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(RuntimeException $e){check(true,$why);return;}check(false,$why);}
require __DIR__.'/../includes/weekly-coverage.php';
use RadioRubben\Fotballrobot\MatchStories as S;
use RadioRubben\Fotballrobot\WeeklyCoverage as W;
$count=0;$allowed=true;$publish=true;$created=0;$writes=0;$options=[];$existing=[];$jobs=[];$meta=[];
$m=['id'=>123,'home'=>['id'=>1,'name'=>'Bremnes J13-1'],'away'=>['id'=>2,'name'=>'Nord 2'],'competition'=>['id'=>3,'name'=>'J13 2. divisjon'], 'score'=>[5,0],'kickoff'=>'2026-10-12T18:30:00+02:00','venue'=>'ScaleAQ Stadion 9er A','source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=123','finished_confirmed'=>true];
$posts=[100=>(object)['ID'=>100,'post_type'=>'post','post_status'=>'draft','post_content'=>'Ukesartikkel','post_title'=>'Ukesresultater']];
$facts=[100=>['week'=>['end'=>'2026-10-19T00:00:00+02:00'],'kind'=>'results','matches'=>[$m]]];$feed=[123=>$m];
$t=S::token(100);
$allowed=false;rejects(fn()=>S::prepare(100,123,$t),'Unauthorized refused');$allowed=true;
$publish=false;rejects(fn()=>S::prepare(100,123,$t),'Missing publisher capability refused');$publish=true;
rejects(fn()=>S::prepare(100,123,'stale'),'Stale browser refused');
rejects(fn()=>S::prepare(100,456,$t),'Unlisted match refused');
$facts[100]['matches'][0]['score']=[6,0];rejects(fn()=>S::prepare(100,123,$t),'Changed saved facts refused');$facts[100]['matches'][0]=$m;
$posts[100]->post_content.=' edited';rejects(fn()=>S::prepare(100,123,$t),'Changed editorial text refused');$posts[100]->post_content='Ukesartikkel';
$feed[123]['score']=[4,0];rejects(fn()=>S::prepare(100,123,$t),'Changed live result refused');$feed[123]=$m;
$feed[123]['cancelled']=true;rejects(fn()=>S::prepare(100,123,$t),'Cancelled match refused');$feed[123]=$m;
$options['rrfr_ai_lock_123']=123;rejects(fn()=>S::prepare(100,123,$t),'Writer lock respected');check($options===['rrfr_ai_lock_123'=>123],'Other owner lock preserved, own lock released');$options=[];
$jobs[123]=['status'=>'blocked'];rejects(fn()=>S::prepare(100,123,$t),'Uncertain existing job cannot regenerate');$jobs=[];
$duringWrite=static function(){$GLOBALS['facts'][100]['matches'][0]['score']=[8,0];};rejects(fn()=>S::prepare(100,123,$t),'Mid-generation parent change refused');$duringWrite=null;$facts[100]['matches'][0]=$m;
check(!$options&&!$existing,'Failed attempt releases own locks and creates no successful duplicate');
$id=S::prepare(100,123,$t);check($id===200&&$posts[200]->post_status==='draft','Only a draft is produced');
$body=$posts[200]->post_content;check(strpos($body,'rrfr-week-card')<strpos($body,'Et kildebelagt'),'Card precedes prose');
check(str_contains($body,'AI-generert artikkel')&&str_contains($body,'fotball.no'),'Disclosure and source retained');
$before=serialize([$posts,$meta,$facts]);$calls=$writes;check(S::prepare(100,123,$t)===200&&$writes===$calls&&serialize([$posts,$meta,$facts])===$before,'Existing article reused byte-for-byte without AI');
$card=W::card($m,true,true);check(S::links($card)===$card,'No public draft link');
$posts[200]->post_status='publish';$linked=S::links($card);check(str_contains($linked,'https://example.test/article/200')&&substr_count($linked,'<a ')===2,'Published article linked without nesting NFF anchor');
foreach(['draft','private','future','trash'] as $state){$posts[200]->post_status=$state;check(S::links($card)===$card,'Hidden status '.$state.' never linked');}
rejects(fn()=>S::prepare(100,123,$t),'Trash never recreated');$posts[200]->post_status='publish';
$posts[200]->post_password='protected';check(S::links($card)===$card,'Password-protected story hidden');unset($posts[200]->post_password);
$meta[200]['_rrfr_test_only']=true;check(S::links($card)===$card,'Test never linked');unset($meta[200]['_rrfr_test_only']);
$meta[200]['_rrfr_fact_snapshot']['match']['score']=[6,0];check(S::links($card)===$card,'Contradictory score never linked');$meta[200]['_rrfr_fact_snapshot']['match']=$m;
check(S::links(W::card($m))===W::card($m),'Child card never links to itself');
$m['home']['name']='<script>alert(1)</script>';check(!str_contains(W::card($m),'<script>'),'Names escaped');$m['home']['name']='Bremnes J13-1';
$posts[100]->post_content='<p>Behold min personlige ingress.</p>'.W::card($m,true,true);$posts[100]->featured_media=812;
$before=$posts[100]->post_title;$t=S::token(100);S::template(100,$t);
check(str_starts_with($posts[100]->post_content,'<p>Behold min personlige ingress.</p>')&&$posts[100]->post_title===$before&&$posts[100]->featured_media===812,'Template preserves prose, title and image');
$posts[100]->post_status='publish';rejects(fn()=>S::template(100,S::token(100)),'Published template change refused');$posts[100]->post_status='draft';
$posts[100]->post_content='<p>No recognised cards</p>';rejects(fn()=>S::template(100,S::token(100)),'Unrecognised draft left intact');
check($posts[100]->post_content==='<p>No recognised cards</p>','Failed migration leaves original body intact');
$render=getenv('RRFR_RENDER_DIR');if($render){if(!is_dir($render))mkdir($render,0700,true);$css=file_get_contents(__DIR__.'/../includes/weekly-coverage.css');$demo=$m;$demo['away']['name']='Fitjar';$demo['competition']['name']='DEMO · Ikke et faktisk resultat';$demo['logos']=['home'=>'https://images.fotball.no/clublogos/test-home.png','away'=>'https://images.fotball.no/clublogos/test-away.png'];preg_match_all('~src="(data:image/[^"]+)"~',file_get_contents(dirname(__DIR__,5).'/docs/previews/weekly-match-cards.html'),$logos);$demoCard=W::card($demo,true,true);$demoCard=str_replace(array_values($demo['logos']),array_slice($logos[1],0,2),$demoCard);$content='<h1>Ukens resultater</h1><p>Syntetisk visningsprøve – ikke en publisert artikkel.</p>'.S::links($demoCard);$long=$m;$long['home']['name']='Torvastad/Avaldsnes';$long['away']['name']='Bremnes Menn Senior A';$content.=W::card($long);file_put_contents($render.'/weekly.html','<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:16px;background:#0c1421;color:#eef3fa;font:16px/1.6 system-ui}main{max-width:720px;margin:auto}h1{font-size:28px}'.$css.'</style><main>'.$content.'</main></html>');}
echo "OK: $count match story permissions, source drift, duplicate and public visibility checks\n";
}
