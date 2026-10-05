<?php
// Restore the existing same-site Studio connection. Secrets never leave private server storage.
if(get_option('home')!=='https://www.radiorubben.no'||!current_user_can('manage_options'))throw new RuntimeException('Wrong site or actor.');
$file=ABSPATH.'studio-private/config/wordpress.php';$c=require $file;
$user=get_user_by('login',$c['username']??'');if(!$user||!user_can($user,'manage_options'))throw new RuntimeException('Existing Studio account must be reviewed.');
$test=static function($config){$r=wp_remote_get('https://www.radiorubben.no/wp-json/rr-fotballrobot/v1/newsroom',['timeout'=>15,'redirection'=>0,'headers'=>['Authorization'=>'Basic '.base64_encode($config['username'].':'.$config['application_password'])]]);return !is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200&&json_decode(wp_remote_retrieve_body($r),true)['version']==='newsroom-1';};
if($test($c)){echo "NEWSROOM_CONNECTION_OK (existing credential)\n";return;}
$backup=dirname(__DIR__).'/private-connection-backup';if(!mkdir($backup,0700))throw new RuntimeException('Connection backup already exists or unavailable.');
if(!copy($file,$backup.'/wordpress.php')||!chmod($backup.'/wordpress.php',0600))throw new RuntimeException('Cannot back up connection.');
$result=WP_Application_Passwords::create_new_application_password($user->ID,['name'=>'Radio Rubben Studio – nyhetsdesk','app_id'=>'34468626-b145-4d5c-bc2e-845d9a2fc3b1']);
if(is_wp_error($result))throw new RuntimeException('Could not restore Studio application access.');
[$password,$record]=$result;
file_put_contents($backup.'/created-key.json',wp_json_encode(['user'=>$user->ID,'uuid'=>$record['uuid']]));chmod($backup.'/created-key.json',0600);
try{
    $new=$c;$new['application_password']=$password;
    if(!$test($new))throw new RuntimeException('Restored Studio authentication failed.');
    $temporary=tempnam(dirname($file),'.newsroom-config-');
    if(file_put_contents($temporary,"<?php\nreturn ".var_export($new,true).";\n")===false||!chmod($temporary,0600)||!rename($temporary,$file))throw new RuntimeException('Could not save private Studio connection.');
    if(!$test(require $file))throw new RuntimeException('Saved connection failed verification.');
}catch(Throwable $e){copy($backup.'/wordpress.php',$file);chmod($file,0600);WP_Application_Passwords::delete_application_password($user->ID,$record['uuid']);throw new RuntimeException('Studio connection was not changed.');}
unset($password,$result,$new,$c);
echo "NEWSROOM_CONNECTION_OK (dedicated Studio key; existing application keys preserved)\n";
