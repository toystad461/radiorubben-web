<?php
// Read-only same-host deployment inventory. Never print configuration values or credentials.
$root=ABSPATH.'studio-private';$public=ABSPATH.'studio-public';
$paths=['app/news-script.php','app/web-publish.php','app/case-workflow.php','public/newsdesk.php'];$hashes=[];
foreach($paths as $p){$file=str_starts_with($p,'public/')?$public.'/'.substr($p,7):$root.'/'.$p;$hashes[$p]=is_file($file)?hash_file('sha256',$file):null;}
$file=$root.'/config/wordpress.php';$c=is_file($file)?require $file:[];
$user=!empty($c['username'])?get_user_by('login',$c['username']):false;
$auth=['configured'=>!empty($c['application_password']),'user_exists'=>(bool)$user,'roles'=>$user?array_values($user->roles):[],'application_names'=>[]];
if($user)foreach(WP_Application_Passwords::get_user_application_passwords($user->ID) as $p)$auth['application_names'][]=$p['name'];
if($user&&!empty($c['application_password'])){
 $r=wp_remote_get('https://www.radiorubben.no/wp-json/wp/v2/users/me',['timeout'=>12,'redirection'=>0,'headers'=>['Authorization'=>'Basic '.base64_encode($c['username'].':'.$c['application_password'])]]);
 $auth['http']=is_wp_error($r)?0:wp_remote_retrieve_response_code($r);
}
echo wp_json_encode(['studio_hashes'=>$hashes,'wordpress'=>$auth,'cli'=>PHP_BINDIR.'/php','cli_available'=>is_executable(PHP_BINDIR.'/php'),'proc_open'=>function_exists('proc_open'),'board_hash'=>is_file($root.'/config/sending-board.json')?hash_file('sha256',$root.'/config/sending-board.json'):null],JSON_UNESCAPED_SLASHES)."\n";
