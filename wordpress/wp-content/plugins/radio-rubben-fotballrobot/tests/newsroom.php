<?php
namespace RadioRubben\Fotballrobot {
 class Robot {static function allowed(){return $GLOBALS['allowed'];}}
 class PlayerReview {const META='player';}
 class ReviewDigest {static function status(){return ['mode'=>'digest'];}static function worker($mode){return ['items'=>[]];}}
 class ReviewDesk {static function decide(...$args){$GLOBALS['decisions'][]=$args;if($args[1]!==str_repeat('a',64))throw new \RuntimeException('Stale token');}}
}
namespace {
require __DIR__.'/../includes/newsroom.php';
use RadioRubben\Fotballrobot\Newsroom as N;
$allowed=true;$publish=true;$decisions=[];$audit=[];$routes=[];$count=0;
function check($v,$label){$GLOBALS['count']++;if(!$v)throw new RuntimeException($label);}
function reject($fn,$label){try{$fn();}catch(RuntimeException $e){check(true,$label);return;}check(false,$label);}
function current_user_can($cap,...$args){return $cap==='publish_posts'?$GLOBALS['publish']:true;}
function get_posts($q){return [];}
function add_post_meta(...$args){$GLOBALS['audit'][]=$args;}
function sanitize_text_field($v){return strip_tags($v);}
function get_current_user_id(){return 7;}
function register_rest_route($ns,$path,$args){$GLOBALS['routes']=$args;}
$input=['id'=>123,'token'=>str_repeat('a',64),'operation'=>'approve','actor'=>'Test'];
$allowed=false;reject(fn()=>N::response(),'queue requires authorization');reject(fn()=>N::decide($input),'decision requires authorization');$allowed=true;
$publish=false;reject(fn()=>N::decide($input),'publication capability required');$publish=true;
reject(fn()=>N::decide(array_replace($input,['operation'=>'delete'])),'unsupported operation rejected');
reject(fn()=>N::decide(array_replace($input,['id'=>'123'])),'ambiguous id rejected');
check(!$decisions&&!$audit,'rejected inputs never reach approval service');
reject(fn()=>N::decide(array_replace($input,['token'=>str_repeat('b',64)])),'stale token delegated to existing gate');
check(!$audit,'failed decision never writes success audit');
$r=N::decide($input);check($r['version']==='newsroom-1'&&$r['items']===[],'fresh queue returned after decision');
check(end($decisions)===[123,$input['token'],'approve','',''],'exact decision passes through existing gates');
check(count($audit)===1&&$audit[0][2]['wordpress_user']===7,'authenticated actor recorded');
N::routes();foreach($routes as $route)check($route['permission_callback']===[RadioRubben\Fotballrobot\Robot::class,'allowed'],'GET and POST both protected');
echo "$count newsroom API checks passed\n";
}
