<?php
/** Run only against the isolated local clone; retains data for browser inspection. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || getenv('RR_DISPOSABLE_TEST_DB') !== '1' || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') { throw new RuntimeException('Isolated localhost only'); }
$file='/tmp/rr-football-pilot.json';
if (file_exists($file)) { throw new RuntimeException('Pilot fixtures already exist'); }
$id=99999992;
if (get_option('rr_poll_match_'.$id,false)!==false) { throw new RuntimeException('Reserved match already exists'); }
$match=['id'=>$id,'home'=>'Bremnes TEST','away'=>'Staging United','home_id'=>30365,'team'=>'Menn A','home_logo'=>'','away_logo'=>'','kickoff'=>gmdate('c',time()+3600),'date_label'=>'LOKAL TEST','venue'=>'Isolert testbane','competition'=>'Kun test','roster'=>[1=>'Test Keeper',2=>'Test Starter',12=>'Test Reserve'],'starters'=>[1,2],'bench'=>[12],'away_roster'=>[],'away_starters'=>[],'away_bench'=>[],'fetched'=>time()];
update_option('rr_poll_match_'.$id,$match,false);
update_option('rr_poll_nff_'.$id.'_source','manual',false);
$f=['match'=>$id,'users'=>[],'selected_before'=>get_option('rr_poll_selected_match')];
update_option('rr_poll_selected_match',$id,false);
foreach (['admin'=>'administrator','member'=>'subscriber'] as $label=>$role) {
 $login='rr-pilot-'.$label.'-'.wp_generate_password(8,false);$pass=wp_generate_password(32,false);
 $user=wp_insert_user(['user_login'=>$login,'user_pass'=>$pass,'user_email'=>$login.'@example.invalid','display_name'=>'Pilot '.$label,'role'=>$role]);
 if(is_wp_error($user))throw new RuntimeException('Fixture user failed');
 update_user_meta($user,'_require_vipps_confirm','no');
 $f['users'][$label]=['id'=>$user,'login'=>$login,'password'=>$pass];
}
$pattern=file_get_contents(get_template_directory().'/patterns/combined-football-live.php');
$content=substr($pattern,strpos($pattern,'<!-- wp:heading'));
$content='<!-- wp:paragraph --><p>LOKAL PILOT · testkamp. Eksterne tjenester og radiosending er sperret i denne isolerte kopien.</p><!-- /wp:paragraph -->'.$content;
$page=wp_insert_post(['post_type'=>'page','post_title'=>'Fotball på Radio Rubben','post_name'=>'fotball-pilot','post_status'=>'publish','post_content'=>$content],true);
if(is_wp_error($page))throw new RuntimeException('Page failed');
update_post_meta($page,'_wp_page_template','templates/football.php');
$f['page']=$page;$f['url']=get_permalink($page);
file_put_contents($file,wp_json_encode($f));chmod($file,0600);
echo "Local pilot and synthetic accounts created.\n";
