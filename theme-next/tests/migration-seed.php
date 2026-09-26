<?php
// Explicitly disposable local copy only. Writes synthetic users/matches, never production.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || getenv('RR_DISPOSABLE_TEST_DB')!=='1' || wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1') throw new RuntimeException('Disposable localhost only');
if (file_exists('/tmp/rr-migration-fixtures.json')) throw new RuntimeException('Fixtures already exist; clean up before reseeding');
$fixture=['users'=>[],'original'=>[],'match'=>99999991];
$week=rrwq_week()['id'];
foreach (['rr_poll_selected_match','rr_quiz_weekly_enabled','rrwq_pack_'.$week] as $key) $fixture['original'][$key]=['exists'=>get_option($key,null)!==null,'value'=>get_option($key)];
foreach (['admin'=>'administrator','member'=>'subscriber','delete'=>'subscriber'] as $label=>$role) {
    $login='rr-stage-'.$label.'-'.wp_generate_password(6,false);$pass=wp_generate_password(32,false);
    $id=wp_insert_user(['user_login'=>$login,'user_pass'=>$pass,'user_email'=>$login.'@example.invalid','display_name'=>'Staging '.$label,'role'=>$role]);
    if(is_wp_error($id))throw new RuntimeException('Cannot create synthetic test account');
    // Test-only exception on disposable accounts; never touches existing users.
    update_user_meta($id,'_require_vipps_confirm','no');
    $fixture['users'][$label]=['id'=>$id,'login'=>$login,'password'=>$pass];
}
$id=$fixture['match'];
$match=['id'=>$id,'home'=>'Bremnes TEST','away'=>'Staging United','home_id'=>30365,'team'=>'Menn A','home_logo'=>'','away_logo'=>'','kickoff'=>gmdate('c',time()+3600),'date_label'=>'STAGING TEST','venue'=>'Isolert testbane','competition'=>'Kun test','roster'=>[1=>'Test Keeper',2=>'Test Starter',12=>'Test Reserve'],'starters'=>[1,2],'bench'=>[12],'away_roster'=>[3=>'Test Opponent',13=>'Test Away Reserve'],'away_starters'=>[3],'away_bench'=>[13],'fetched'=>time()];
update_option('rr_poll_match_'.$id,$match,false);update_option('rr_poll_selected_match',$id,false);
update_option('rr_poll_nff_'.$id.'_source','manual',false);
update_option('rr_quiz_weekly_enabled',1);
$questions=[];for($i=0;$i<20;$i++)$questions[]=['q'=>'Staging test '.($i+1),'a'=>['A','B','C','D'],'correct'=>0];
update_option('rrwq_pack_'.$week,['title'=>'STAGING quiz','revision'=>1,'questions'=>$questions],false);
$post=wp_insert_post(['post_type'=>'rr_match','post_title'=>'Staging RRLive','post_name'=>'rr-staging-match','post_status'=>'publish']);$fixture['post']=$post;
foreach(['rr_home_team'=>'Bremnes TEST','rr_away_team'=>'Staging United','rr_status'=>'scheduled','rr_score_home'=>0,'rr_score_away'=>0] as $k=>$v)update_post_meta($post,$k,$v);
$fixture['rrlive_url']=get_permalink($post);
$fixture['answers']=array_column(rrwq_pack($week)['questions'],'correct');
flush_rewrite_rules(false);
file_put_contents('/tmp/rr-migration-fixtures.json',wp_json_encode($fixture));chmod('/tmp/rr-migration-fixtures.json',0600);
echo "Synthetic local fixtures created.\n";
