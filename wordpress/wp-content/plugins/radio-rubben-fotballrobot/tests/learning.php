<?php
require __DIR__.'/../includes/robot.php';
function add_action(...$a){}
require __DIR__.'/../includes/learning.php';
use RadioRubben\Fotballrobot\Learning;
$meta=[];$posts=[];$can=true;
function current_user_can(...$a){global $can;return $can;}
function wp_json_encode($v){return json_encode($v,JSON_UNESCAPED_UNICODE);}
function wp_strip_all_tags($s){return strip_tags(preg_replace('~<script.*?</script>~s','',$s));}
function strip_shortcodes($s){return preg_replace('/\[[^]]+\]/','',$s);}
function sanitize_textarea_field($s){return strip_tags($s);}
function wp_slash($v){return $v;}
function get_current_user_id(){return 42;}
function get_post($id){global $posts;return $posts[$id]??null;}
function get_post_meta($id,$key,...$a){global $meta;return $meta[$id][$key]??'';}
function update_post_meta($id,$key,$value){global $meta;$meta[$id][$key]=$value;return 1;}
function get_posts($q){global $posts;return array_values(array_filter($posts,fn($p)=>in_array($p->post_status,$q['post_status'],true)));}
// Mimic the block AST returned by WP; extraction never calls dynamic renderers.
function parse_blocks($content){return json_decode($content,true);}
function block($kind,$html){return ['blockName'=>$kind,'innerHTML'=>$html,'innerBlocks'=>[]];}
$n=0;function ok($v,$label){global $n;if(!$v)throw new RuntimeException($label);$n++;}
function rejects($fn,$label){try{$fn();}catch(RuntimeException $e){ok(true,$label);return;}ok(false,$label);}
$blocks=[block('core/html','<aside class="rrfr-editorial-notice">AI-merknad</aside>'),block('core/paragraph','<p>Innbytter &amp; målscorer.</p>'),block('core/paragraph','<p><strong>Bremnes:</strong> spillerliste</p>'),block('core/paragraph','<p><small>Kilde: fotball.no</small></p>'),block('core/shortcode','[private_state]'),block('core/html','<div>private notater</div>')];
$posts[1]=(object)['ID'=>1,'post_type'=>'post','post_status'=>'draft','post_title'=>'God tittel','post_content'=>json_encode($blocks)];$meta[1]['_rrfr_ai_match']=123;
$hash=Learning::hash($posts[1]);$version=Learning::version([]);
ok(Learning::snapshot($posts[1])===['title'=>'God tittel','paragraphs'=>['Innbytter & målscorer.']],'prose whitelist');
rejects(fn()=>Learning::save(1,$hash,$version,'Fremhev dokumentert innhopp.','general',false),'explicit approval');
rejects(fn()=>Learning::save(1,'stale',$version,'Fremhev dokumentert innhopp.','general',true),'stale article');
$can=false;rejects(fn()=>Learning::save(1,$hash,$version,'Fremhev dokumentert innhopp.','general',true),'unauthorized');$can=true;
rejects(fn()=>Learning::save(1,$hash,$version,'Kort','general',true),'too short');
rejects(fn()=>Learning::save(1,$hash,$version,'Fremhev dokumentert innhopp.','invalid',true),'invalid scope');
$lesson=Learning::save(1,$hash,$version,'Fremhev dokumentert innhopp.','general',true);
ok($lesson['before']===null&&$lesson['active']&&$lesson['revision']===1,'legacy no invented original');
ok(count(Learning::context([]))===1,'approved included');
rejects(fn()=>Learning::save(1,$hash,$version,'Endret regel som ikke skal lagres.','general',true),'stale learning');
$again=Learning::save(1,$hash,Learning::version($lesson),'Fremhev dokumentert innhopp.','general',true);ok($again===$lesson,'idempotent save');
Learning::disable(1,Learning::version($lesson));ok(Learning::context([])===[],'disabled excluded');
$meta[1]['_rrfr_original_article']=['title'=>'Original','paragraphs'=>['Opprinnelig tekst.']];
$lesson=Learning::save(1,$hash,Learning::version(Learning::lesson(1)),'Fremhev dokumentert innhopp.','substitute_goal',true);
ok($lesson['before']['title']==='Original','original preserved');
ok(Learning::context([])===[],'irrelevant excluded');
$f=['match'=>['events'=>[['type'=>'Spillemål','side'=>'home','name'=>'Erlend Meling Nesse','minute'=>89]]],'report_extras'=>['manual_substitutions'=>[['side'=>'home','in'=>'Erlend Meling Nesse','seconds'=>3839]]]];
ok(count(Learning::context($f))===1,'relevant included');
$wrong=$f;$wrong['report_extras']['manual_substitutions'][0]['in']='Johannes Nesse';ok(!Learning::has_substitute_goal($wrong),'same surname insufficient');
$wrong=$f;$wrong['report_extras']['manual_substitutions'][0]['seconds']=5500;ok(!Learning::has_substitute_goal($wrong),'chronology');
$posts[1]->post_title='Ny rettelse';ok(Learning::context($f)===[],'edited article excluded');$posts[1]->post_title='God tittel';
$posts[1]->post_status='trash';ok(Learning::context($f)===[],'trashed excluded');$posts[1]->post_status='draft';
$can=false;ok(Learning::context($f)===[],'read permission');$can=true;
for($id=2;$id<=6;$id++){$posts[$id]=clone $posts[1];$posts[$id]->ID=$id;$meta[$id]=$meta[1];$meta[$id][Learning::KEY]['scope']='general';}
$context=Learning::context($f);ok(count($context)===3&&$context[0]['post_id']===1,'bounded selection with relevance');
ok(!str_contains(json_encode($context),'saved_by')&&!str_contains(json_encode($context),'private notater'),'prompt whitelist');
echo "OK: $n learning controls; no network or paid calls\n";
