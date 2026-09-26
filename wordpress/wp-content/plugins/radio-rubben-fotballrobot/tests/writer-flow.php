<?php
require __DIR__.'/../includes/robot.php';
require __DIR__.'/../includes/writer.php';
function add_filter(...$a){}
function add_action(...$a){}
require __DIR__.'/../includes/report.php';
require __DIR__.'/../includes/learning.php';
use RadioRubben\Fotballrobot\Writer;
const MINUTE_IN_SECONDS=60;
const RRFR_OPENAI_API_KEY='sk-fake-local-test-key';
$options=['rrfr_fact_123'=>1];$trans=[];$posts=[];$meta=[];$queue=[];$calls=0;$user=10;$requests=[];$lessons=[];
$f=['match'=>['id'=>123,'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=123','home'=>['name'=>'A'],'away'=>['name'=>'B']],'fact_hash'=>'hash','finished_confirmed'=>true,'angles'=>[['id'=>'angle']],'sources'=>[]];$meta[1]=['_rrfr_payload'=>$f];
function get_option($k,$d=false){global $options;return $options[$k]??$d;}
function add_option($k,$v,...$args){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function get_post_meta($id,$key,$single=true){global $meta;return $meta[$id][$key]??null;}
function get_posts($q){global $posts,$lessons;return ($q['meta_key']??'')==='_rrfr_learning'?array_values($lessons??[]):array_values($posts);}
function current_user_can(...$a){return true;}
function wp_strip_all_tags($s){return strip_tags($s);}
function strip_shortcodes($s){return $s;}
function get_current_user_id(){global $user;return $user;}
function wp_generate_password($n,...$args){return str_repeat('a',$n);}
function set_transient($k,$v,$ttl){global $trans;$trans[$k]=$v;}
function get_transient($k){global $trans;return $trans[$k]??false;}
function delete_transient($k){global $trans;unset($trans[$k]);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_remote_post($url,$args){global $queue,$calls,$requests;$calls++;if($url!=='https://api.openai.com/v1/responses')throw new RuntimeException('Unexpected destination');$q=json_decode($args['body'],true);$requests[]=$q;if($q['store']!==false)throw new RuntimeException('Store must be false');return array_shift($queue);}
function is_wp_error($r){return false;}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function esc_html($s){return htmlspecialchars($s);}
function esc_url($s){return $s;}
function wp_insert_post($p,$error){global $posts,$inserted;$inserted=$p;if($p['post_status']!=='draft')throw new RuntimeException('Must be draft');$posts[42]=(object)['ID'=>42,'post_status'=>'draft'];return 42;}
function get_edit_post_link($id,$context){return 'https://example.test/wp-admin/post.php?post='.$id.'&action=edit';}
function response($v){return ['status'=>200,'body'=>json_encode(['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($v)]]]]])];}
$n=0;
function ok($v){global $n;if(!$v)throw new RuntimeException('Test failed');$n++;}
function rejects($f){try{$f();}catch(RuntimeException $e){ok(true);return;}ok(false);}
$a=['title'=>'Lag A sikret poeng','lead'=>'Det ble uavgjort.','paragraphs'=>['Begge lag tok ett poeng.'],'checks'=>[['claim'=>'Uavgjort','support'=>'score']]];
rejects(fn()=>Writer::generate(123,'stale','angle'));ok($calls===0);
$lessons[5]=(object)['ID'=>5,'post_title'=>'Tidligere kamp','post_content'=>'Godkjent tekst'];
$meta[5]=['_rrfr_ai_match'=>999,'_rrfr_learning'=>['active'=>true,'article_hash'=>RadioRubben\Fotballrobot\Learning::hash($lessons[5]),'scope'=>'general','saved_at'=>'2026-09-26T12:00:00Z','revision'=>1,'note'=>'Skriv korte, konkrete setninger.','before'=>null,'after'=>['title'=>'Tidligere kamp','paragraphs'=>['Eksempeltekst.']]]];
$queue=[response($a)];$r=Writer::generate(123,'hash','angle');ok(isset($r['review_token'])&&count($posts)===0&&$calls===1);
$input=json_decode($requests[0]['input'],true);ok(count($input['editorial_examples'])===1&&$input['facts']['match']['id']===123);
$user=11;rejects(fn()=>Writer::review($r['review_token']));ok($calls===1);$user=10;
$queue=[response(['approved'=>false,'issues'=>['Feil resultat']])];rejects(fn()=>Writer::review($r['review_token']));ok(count($posts)===0);
$queue=[response(['approved'=>true,'issues'=>[]])];$saved=Writer::review($r['review_token']);ok($saved['existing']===false&&count($posts)===1);
ok(!array_key_exists('editorial_examples',json_decode($requests[2]['input'],true)));
ok($inserted['meta_input']['_rrfr_original_article']['title']===$a['title']&&$inserted['meta_input']['_rrfr_learning_used'][0]['post_id']===5);
$before=$calls;$again=Writer::generate(123,'hash','angle');ok($again['existing']===true&&$calls===$before);
$posts=[];$meta[1]['_rrfr_payload']['finished_confirmed']=false;rejects(fn()=>Writer::generate(123,'hash','angle'));ok($calls===$before);
$meta[1]['_rrfr_payload']=$f;$options['rrfr_ai_lock_123']=time();rejects(fn()=>Writer::generate(123,'hash','angle'));ok($calls===$before);
unset($options['rrfr_ai_lock_123']);$queue=[['status'=>429,'body'=>'private provider error']];rejects(fn()=>Writer::generate(123,'hash','angle'));ok(!isset($options['rrfr_ai_lock_123']));
echo "OK: $n flow controls with mocked provider; no paid calls\n";
