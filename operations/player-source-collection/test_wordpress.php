<?php
// Native WordPress REST/persistence/UI, disposable CI only. No external HTTP or mail.
use RadioRubben\Fotballrobot\{Players,PlayerCandidates,SourceCollection};
if(!defined('WP_CLI')||!WP_CLI||getenv('RRFR_ISOLATED_WP')!=='1'||get_option('blogname')!=='RRFR isolated integration')throw new RuntimeException('Disposable WordPress only');
add_filter('pre_http_request',static fn()=>new WP_Error('offline_test','External HTTP disabled'),1);
add_filter('pre_wp_mail',static fn()=>false);
$count=0;
$check=static function($value,$message)use(&$count){$count++;if(!$value)throw new RuntimeException($message);};
$request=static function(string $method,string $path,?array $body=null){
    $r=new WP_REST_Request($method,'/rr-fotballrobot/v1'.$path);
    if($body!==null){$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($body));}
    return rest_do_request($r);
};
wp_set_current_user(1);
$pid=Players::save(['name'=>'Syntetisk Kildespiller','fiks'=>'98765001','note'=>'Kun CI','club_note'=>'Testklubb','group'=>'bomlo-away','enabled'=>true,'watch'=>array_keys(Players::KINDS)]);
$uid=wp_insert_user(['user_login'=>'source_collection_ci','user_pass'=>wp_generate_password(),'user_email'=>'collector@example.invalid','role'=>'subscriber']);
$check(!is_wp_error($uid),'Create isolated subscriber');
$user=get_user_by('id',$uid);$user->add_cap(SourceCollection::CAP);
wp_set_current_user($uid);
$check(!current_user_can('manage_options')&&!current_user_can('edit_posts')&&!current_user_can('publish_posts'),'Collector has no editor/admin permissions');
$check($request('GET','/player-monitor')->get_status()===200,'Scoped monitor GET');
$check($request('GET','/players/'.$pid)->get_status()===200,'Scoped exact profile GET');
$check($request('GET','/players/'.$pid.'/source-history')->get_status()===200,'Scoped history GET');
$check($request('GET','/source-collection')->get_status()===200,'Collection handshake');
$source=['fiks_id'=>98765001,'url'=>'https://example.org/synthetic-source-only','title'=>'Syntetisk kildeprøve','identity_note'=>'Kun isolerte testdata.','public_read'=>true,'published_at'=>gmdate('Y-m-d'),'checked_at'=>gmdate(DATE_ATOM),'event_date'=>null,'facts'=>['Dette er en syntetisk testopplysning.']];
$result=$request('POST','/players/'.$pid.'/sources',$source);
$check($result->get_status()===200&&$result->get_data()['created']===true,'Existing source ingestion works with scoped user');
$rows=$request('GET','/players/'.$pid.'/sources')->get_data();
$check(count($rows)===1&&$rows[0]['facts']===$source['facts'],'Real source readback');
$changed=$source;$changed['facts']=['Skal ikke overskrive lagret kilde.'];
$result=$request('POST','/players/'.$pid.'/sources',$changed)->get_data();
$check($result['created']===false&&$result['source']['facts']===$source['facts'],'Duplicate preserves first source facts');
$bad=$source;$bad['fiks_id']=98765002;$bad['url']='https://example.org/invalid-identity-only';
$check($request('POST','/players/'.$pid.'/sources',$bad)->get_status()>=400,'Wrong identity denied');
$n=count(Players::ids());$year=(int)gmdate('Y');
$candidate=['fiks_id'=>98765002,'name'=>'Syntetisk Kandidat','former_club'=>'Bremnes','former_seasons'=>[$year-2],'current_club'=>'Eksempelklubb utenfor','current_level'=>'Syntetisk senior','active_season'=>$year,'history_note'=>'Kun testdata.','checked_at'=>gmdate(DATE_ATOM),
    'sources'=>array_map(static fn($kind)=>['kind'=>$kind,'url'=>'https://example.org/synthetic-'.$kind,'fact'=>'Syntetisk dokumentasjon.','public_read'=>true],['history','current','activity'])];
$result=$request('POST','/player-candidates',$candidate)->get_data();
$check($result['candidate']['status']==='pending'&&count(Players::ids())===$n,'Candidate stays pending, no profile creation');
$denied=false;try{PlayerCandidates::decide(98765002,1,'approve');}catch(Throwable $e){$denied=true;}
$check($denied,'Collector cannot approve candidate');
$r=new WP_REST_Request('POST','/wp/v2/posts');$r->set_param('title','Forbidden collector post');
$check(rest_do_request($r)->get_status()>=400,'Collector cannot write posts');
wp_set_current_user(0);
$check($request('GET','/source-collection')->get_status()>=400,'Anonymous status denied');
$check($request('POST','/players/'.$pid.'/sources',$source)->get_status()>=400,'Anonymous ingest denied');
wp_set_current_user(1);
ob_start();SourceCollection::page();$html=ob_get_clean();
$check(str_contains($html,'Spilleroppfølging – kildeinnsamling')&&str_contains($html,'Utilgjengelig'),'Native admin page shows unavailable, never fabricated VPS activity');
$check(str_contains($html,'Start én innsamlingsrunde')&&str_contains($html,'rrfr-player-candidates')&&str_contains($html,'Europe/Oslo'),'Native controls, existing inbox and Norwegian timezone visible');
$dir=getenv('RRFR_RENDER_DIR');if($dir){wp_mkdir_p($dir);file_put_contents($dir.'/wp-source-collection.html','<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$html.'</html>');}
echo 'OK: '.$count.' native WordPress source collection checks; disposable synthetic data only'.PHP_EOL;
