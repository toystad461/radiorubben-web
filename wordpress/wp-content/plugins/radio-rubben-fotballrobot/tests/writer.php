<?php
require __DIR__.'/../includes/writer.php';
use RadioRubben\Fotballrobot\Writer;
$n=0;
function ok($v){global $n;if(!$v)throw new RuntimeException('Test failed');$n++;}
function rejects($f){try{$f();}catch(RuntimeException $e){ok(true);return;}ok(false);}
$a=['title'=>'Nesse stoppet Viggos seiersrekke','lead'=>'Bremnes hentet inn 0–2 og spilte uavgjort.','paragraphs'=>['Erlend Meling Nesse utlignet i det 89. minutt.'],'checks'=>[['claim'=>'Utligning 89','support'=>'match.events.7389350']]];
ok(Writer::validate($a)===$a);
$b=$a;$b['title']=str_repeat('a',66);rejects(fn()=>Writer::validate($b));
$b=$a;$b['paragraphs']=['<script>alert(1)</script>'];rejects(fn()=>Writer::validate($b));
$b=$a;$b['checks']=[];rejects(fn()=>Writer::validate($b));
$b=$a;$b['paragraphs']=[];rejects(fn()=>Writer::validate($b));
$b=$a;$b['lead']='';rejects(fn()=>Writer::validate($b));
$r=['status'=>'completed','output'=>[['type'=>'reasoning'],['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($a)]]]]];
ok(Writer::extract($r)===$a);
$r['status']='incomplete';rejects(fn()=>Writer::extract($r));
$r=['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'refusal']]]]];rejects(fn()=>Writer::extract($r));
$r=['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'not json']]]]];rejects(fn()=>Writer::extract($r));
ok(Writer::schema()['additionalProperties']===false);
// Secret round-trip uses only fake local credentials.
$options=[];
function get_option($k,$d=false){global $options;return $options[$k]??$d;}
function update_option($k,$v,$a=false){global $options;$options[$k]=$v;}
function wp_salt($s){return 'local-test-salt-not-a-credential';}
function wp_unslash($s){return $s;}
$_POST=['model'=>'gpt-6-astra','api_key'=>'sk-'.str_repeat('fake',12)];Writer::configure();
ok(Writer::key()===$_POST['api_key']);ok(!str_contains(json_encode($options),$_POST['api_key']));
$_POST=['model'=>'gpt-6-astra','api_key'=>''];Writer::configure();ok(Writer::key()==='sk-'.str_repeat('fake',12));
$options['rrfr_openai_secret']['tag']=base64_encode(str_repeat('x',16));ok(Writer::key()==='');
echo "OK: $n writer controls\n";
