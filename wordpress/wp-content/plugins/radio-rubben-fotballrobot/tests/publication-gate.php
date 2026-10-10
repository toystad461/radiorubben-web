<?php
const ABSPATH=__DIR__.'/';
const RRFR_OPENAI_API_KEY='sk-local-fake';
$actions=[];$filters=[];$posts=[];$meta=[];$options=[];$queue=[];$requests=[];$trans=[];$count=0;$canEdit=true;
function add_action($hook,$fn,$priority=10,...$a){$GLOBALS['actions'][$hook][$priority][]=$fn;}
function add_filter($hook,$fn,$priority=10,...$a){$GLOBALS['filters'][$hook][$priority][]=$fn;}
function register_deactivation_hook(...$a){}
function get_post($id){return clone $GLOBALS['posts'][$id];}
function get_post_meta($id,$key,...$a){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function add_option($key,$v,...$a){if(isset($GLOBALS['options'][$key]))return false;$GLOBALS['options'][$key]=$v;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);}
function current_user_can(...$a){return $GLOBALS['canEdit'];}
function get_current_user_id(){return 7;}
function set_transient($k,$v,...$a){$GLOBALS['trans'][$k]=$v;}
function get_transient($k){return $GLOBALS['trans'][$k]??false;}
function delete_transient($k){unset($GLOBALS['trans'][$k]);}
function wp_unslash($v){return is_array($v)?array_map('wp_unslash',$v):stripslashes($v);}
function wp_slash($v){return is_array($v)?array_map('wp_slash',$v):addslashes($v);}
function wp_strip_all_tags($s){return strip_tags($s);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_remote_post($url,$args){$GLOBALS['requests'][]=json_decode($args['body'],true);$r=array_shift($GLOBALS['queue']);if(is_callable($r))return $r();return $r;}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function is_wp_error($r){return false;}
function response($v){return ['status'=>200,'body'=>json_encode(['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($v)]]]]])];}
function check($ok,$why){$GLOBALS['count']++;if(!$ok)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(RuntimeException $e){check(true,$why);return;}check(false,$why);}
require __DIR__.'/../radio-rubben-fotballrobot.php';
use RadioRubben\Fotballrobot\PublicationGate as G;
use RadioRubben\Fotballrobot\EditorialQuality as Q;
use RadioRubben\Fotballrobot\Writer;
function wp_update_post($changes,...$a){
    $id=$changes['ID'];$data=wp_slash($changes+(array)get_post($id));$hooks=$GLOBALS['filters']['wp_insert_post_data'];ksort($hooks);
    foreach($hooks as $callbacks)foreach($callbacks as $fn)$data=$fn($data,wp_slash($changes));
    $GLOBALS['posts'][$id]=(object)wp_unslash($data);return $id;
}
$facts=['match'=>['id'=>123,'home'=>['name'=>'Bremnes'],'away'=>['name'=>'Viggo'],'score'=>[2,1],'kickoff'=>'2026-09-25T19:00:00+02:00','events'=>[]],
    'finished_confirmed'=>true,'fact_hash'=>'snapshot','report_extras'=>['manual_substitutions'=>[],'logos'=>[]]];
$options['rrfr_fact_123']=100;$meta[100]['_rrfr_payload']=$facts;
$posts[1]=(object)['ID'=>1,'post_type'=>'post','post_status'=>'draft','post_title'=>'Bremnes slo Viggo 2–1','post_content'=>'Bremnes vant 2–1 over Viggo den 25.09.2026.','post_excerpt'=>'Bremnes slo Viggo.'];
$meta[1]=['_rrfr_ai_match'=>123,'_rrfr_fact_snapshot'=>$facts];
$article=['title'=>$posts[1]->post_title,'lead'=>$posts[1]->post_excerpt,'paragraphs'=>[$posts[1]->post_content],'checks'=>[['claim'=>'Lagret tekst','support'=>'facts']]];
$yes=['approved'=>true,'issues'=>[]];
$review=Q::review($article,$facts,fn()=>$yes,fn($a)=>$a);
$approved=G::bind($review,(array)$posts[1]);
wp_update_post(['ID'=>1,'post_status'=>'publish']);check($posts[1]->post_status==='draft','Legacy without audit cannot publish');
foreach(['publish','future','private'] as $status){
    $meta[1][G::META]=$approved;wp_update_post(['ID'=>1,'post_status'=>$status]);check($posts[1]->post_status===$status,'Reviewed match permits explicit '.$status);
    wp_update_post(['ID'=>1,'post_status'=>'draft']);
}
foreach(['post_title','post_content','post_excerpt'] as $field){
    $before=$posts[1]->$field;
    wp_update_post(['ID'=>1,$field=>$before.' Endret.','post_status'=>'publish']);
    check($posts[1]->post_status==='draft','Changed '.$field.' invalidates approval even with simultaneous publication');
    wp_update_post(['ID'=>1,$field=>$before]);
}
$originalTitle=$posts[1]->post_title;$posts[1]->post_title="Bremnes' seier";
$meta[1][G::META]=G::bind($review,(array)$posts[1]);wp_update_post(['ID'=>1,'post_status'=>'publish']);
check($posts[1]->post_status==='publish','WordPress slashing does not change the reviewed visible text');
wp_update_post(['ID'=>1,'post_status'=>'draft','post_title'=>$originalTitle]);
foreach(['rulesVersion'=>'0.9.0','publishable'=>false,'languageStatus'=>'failed','findings'=>['Uavklart']] as $field=>$value){
    $meta[1][G::META]=$approved;$meta[1][G::META][$field]=$value;
    wp_update_post(['ID'=>1,'post_status'=>'publish']);check($posts[1]->post_status==='draft','Invalid '.$field.' blocks');
}
$meta[1][G::META]=$approved;$meta[100]['_rrfr_payload']['match']['score']=[3,1];
wp_update_post(['ID'=>1,'post_status'=>'publish']);check($posts[1]->post_status==='draft','Changed source invalidates approval');
$meta[100]['_rrfr_payload']=$facts;
// Planned publication must check again at execution, before core's priority-10 handler.
check(isset($actions['publish_future_post'][9]),'Scheduled guard registered before core');
$posts[1]->post_status='future';$meta[1][G::META]['rulesVersion']='old';
foreach($actions['publish_future_post'][9] as $fn)$fn(1);
check($posts[1]->post_status==='draft','Future post demoted before core can publish');
// Supplied meta_input cannot forge a successful review at the boundary.
$forged=G::guard(['post_type'=>'post','post_status'=>'publish'],['ID'=>1,'meta_input'=>[G::META=>$approved]]);
check($forged['post_status']==='draft','Incoming forged audit ignored');
$new=G::guard(['post_type'=>'post','post_status'=>'publish'],['meta_input'=>['_rrfr_ai_match'=>123,G::META=>$approved]]);
check($new['post_status']==='draft','New managed post must first be stored as draft');
$posts[2]=(object)['ID'=>2,'post_type'=>'post','post_status'=>'draft','post_title'=>'Vanlig innlegg','post_content'=>'Redaktørtekst','post_excerpt'=>''];
wp_update_post(['ID'=>2,'post_status'=>'publish']);check($posts[2]->post_status==='publish','Unrelated posts unchanged');
// Explicit saved-text recheck runs real Writer calls against mocked transport and never edits prose.
$hash=G::hash($posts[1]);$before=clone $posts[1];
$queue=[response($yes),response($article)];$r=Writer::recheck(1,$hash);
check(G::current(1,$posts[1])&&$posts[1]==$before,'Recheck approves saved text without altering or publishing it');
check(count($requests)===2&&str_contains($requests[0]['instructions'],'navn')&&str_contains($requests[1]['instructions'],'Språkvask'),'Independent factual and language requests');
rejects(fn()=>Writer::recheck(1,'old'),'Stale browser cannot recheck');
$canEdit=false;rejects(fn()=>Writer::recheck(1,$hash),'Recheck requires permissions');$canEdit=true;
$suggestion=$article;$suggestion['paragraphs']=['Bremnes vant kampen 2–1 over Viggo den 25.09.2026.'];
$queue=[response($yes),response($suggestion),response($yes)];Writer::recheck(1,$hash);
check(!G::current(1,$posts[1])&&$posts[1]==$before,'Suggested rewrite invalidates old approval without overwriting human edit');
check(isset($meta[1][G::META]['suggestion']),'Suggested copyedit retained for manual correction');
$queue=[response($yes),['status'=>503,'body'=>'secret']];Writer::recheck(1,$hash);
check(!G::current(1,$posts[1])&&$posts[1]==$before,'Copyedit transport error invalidates old approval and preserves text');
$queue=[response($yes),function()use($article){$GLOBALS['posts'][1]->post_content.=' Concurrent edit.';return response($article);}];
rejects(fn()=>Writer::recheck(1,$hash),'Concurrent edit cannot receive stale approval');
check(!isset($options['rrfr_quality_lock_1']),'Recheck lock released on failure');
echo "$count publication controls passed; mocked WordPress and AI, no publication\n";
