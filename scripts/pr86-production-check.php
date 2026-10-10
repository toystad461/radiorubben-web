<?php
// Operational readback only. No article, source, vote or queue is written here.
use RadioRubben\Fotballrobot\PublicationGate;
use RadioRubben\Fotballrobot\MatchFollowup;
use RadioRubben\Fotballrobot\MatchJobs;
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP CLI required');
$mode=$args[0]??'';$file=$args[1]??'';
if(!in_array($mode,['before','after','scheduler'],true)||!str_contains($file,'/.radiorubben-deploy/pr86/'))throw new RuntimeException('Invalid check invocation');
function rr86Assert($v,$why){if(!$v)throw new RuntimeException($why);}
function rr86Protected():array {
    global $wpdb;
    $post=get_post(1303,ARRAY_A);rr86Assert($post&&$post['post_type']==='post','Existing article missing');
    $options=[];
    foreach(['rr_poll_test_8985501_vipps_v3_75','rr_match_archive_8985501','rr_bremnes_sponsor_8985501']as$key)$options[$key]=hash('sha256',serialize(get_option($key)));
    return ['post'=>hash('sha256',serialize($post)),'post_status'=>$post['post_status'],'options'=>$options];
}
rr86Assert(get_option('home')==='https://www.radiorubben.no','Unexpected site');
$current=rr86Protected();
if($mode==='before'){
    rr86Assert(!file_exists($file),'Snapshot must be new');
    rr86Assert(file_put_contents($file,json_encode($current,JSON_THROW_ON_ERROR))!==false&&chmod($file,0600),'Private snapshot failed');
    echo "PR86_PROTECTED_STATE_CAPTURED; post_id=1303; status=".$current['post_status']."\n";return;
}
$before=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
rr86Assert($before===$current,'Protected article or vote state changed; investigate without restoring editorial data');
global $wpdb;
$ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_rrfr_ai_match' WHERE p.post_type='post' AND (p.post_name=%s OR m.meta_value=%s) ORDER BY p.ID",'rubben-kamp-8985501','8985501'));
rr86Assert(array_map('intval',$ids)===[1303],'Unexpected duplicate or missing article');
require_once ABSPATH.'wp-admin/includes/plugin.php';
$data=get_plugin_data(WP_PLUGIN_DIR.'/radio-rubben-fotballrobot/radio-rubben-fotballrobot.php',false,false);
rr86Assert($data['Version']==='0.10.6','Wrong active runtime version');
rr86Assert(class_exists(MatchFollowup::class)&&PublicationGate::AI_POLICY_VERSION==='1.0.0','Runtime/policy missing');
rr86Assert(!empty(get_option(MatchJobs::CONFIG,[])['enabled']),'Automatic match reports disabled');
rr86Assert(has_action(MatchFollowup::HOOK)!==false&&wp_next_scheduled(MatchFollowup::HOOK),'Followup schedule missing');
foreach(['rrfr_match_job','rrfr_club_tick','rrfr_club_match','rrfr_club_weekly']as$hook)rr86Assert(has_action($hook)!==false,'Missing callback: '.$hook);
$guard=PublicationGate::guard(['post_type'=>'post','post_status'=>'publish'],['meta_input'=>['_rrfr_ai_match'=>8985501]]);
rr86Assert($guard['post_status']==='draft','Manual publication gate missing');
ob_start();MatchFollowup::panel();$panel=ob_get_clean();rr86Assert(str_contains($panel,'Kampoppfølging'),'Review progress render failed');
$out=['version'=>$data['Version'],'post_id'=>1303,'post_status'=>$current['post_status'],'existing_article_and_vote_unchanged'=>true,'duplicates'=>0,'manual_publication_gate'=>true,'progress_panel'=>true];
if($mode==='scheduler'){
    $runner=get_option('rrfr_match_followup_runner',[]);
    rr86Assert(($runner['transport']??'')==='host-cli'&&($runner['at']??0)>time()-600,'Independent runner heartbeat missing');
    $out['runner']=$runner;
}
echo json_encode($out,JSON_UNESCAPED_SLASHES)."\nPR86_".strtoupper($mode)."_VERIFIED\n";
