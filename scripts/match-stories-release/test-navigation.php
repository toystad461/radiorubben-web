<?php
if(!defined('WP_CLI')||!WP_CLI||getenv('RRFR_ISOLATED_WP')!=='1'||get_option('blogname')!=='RRFR isolated integration')throw new RuntimeException('Disposable WordPress only');
require __DIR__.'/navigation.php';
function navAssert($ok,$why){if(!$ok)throw new RuntimeException($why);}
$start='<!-- rr-weekly-fixtures:start -->';$end='<!-- rr-weekly-fixtures:end -->';
$block=$start.'<p>Old fixtures</p>'.$end;
$text='<p>Preserve "quotes" and <strong>editorial content</strong>.</p><a href="#old">Old</a>'.$block.'<p>Keep topscorers</p>';
$id=wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'publish','post_title'=>'Sport navigation test','post_content'=>$text]));
$mid=wp_create_nav_menu('Sport deploy test');set_theme_mod('nav_menu_locations',['primary'=>$mid]);
wp_update_nav_menu_item($mid,0,['menu-item-title'=>'Hjem','menu-item-url'=>home_url('/'),'menu-item-type'=>'custom','menu-item-status'=>'publish']);
$menu=rrSportMenu($mid);$tmp=tempnam(sys_get_temp_dir(),'sport-nav-');unlink($tmp);
$plan=['page_id'=>$id,'page_content_sha256'=>hash('sha256',$text),'legacy_weekly_block_sha256'=>hash('sha256',$block),'replace_block_between'=>[$start,$end],'replacement'=>'[rrfr_weekly_results]','shortcut'=>['old'=>'href="#old">Old','new'=>'href="#rr-weekly-results">Ukesresultater'],'menu'=>['id'=>$mid,'location'=>'primary','title'=>'Sport']];
rrSportNavigation($plan,$tmp,'check');navAssert(!file_exists($tmp),'Check wrote backup');
rrSportNavigation($plan,$tmp,'apply');navAssert(count(rrSportMenu($mid))===2,'Sport menu absent');
navAssert(str_contains(get_post_field('post_content',$id),'Keep topscorers'),'Preserved content lost');
rrSportNavigation($plan,$tmp,'after');
$saved=get_post_field('post_content',$id);wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$saved.'<p>Concurrent edit</p>']));
$blocked=false;try{rrSportNavigation($plan,$tmp,'rollback');}catch(RuntimeException $e){$blocked=true;}
navAssert($blocked,'Concurrent content overwritten');wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$saved]));
rrSportNavigation($plan,$tmp,'rollback');navAssert(get_post_field('post_content',$id)===$text&&rrSportMenu($mid)===$menu,'Navigation rollback incomplete');
rrSportNavigation($plan,$tmp,'rollback');unlink($tmp);
echo "SPORT_NAVIGATION_APPLY_ROLLBACK_AND_CONCURRENCY_OK\n";

require __DIR__.'/job-lock.php';
$lockfile=sys_get_temp_dir().'/sport-lock-'.bin2hex(random_bytes(6));
rrSportJobLock($lockfile,'acquire');navAssert(get_option('rrfr_match_followup_lock')!==false,'Lock missing');
$foreign=$lockfile.'-foreign';$blocked=false;try{rrSportJobLock($foreign,'acquire');}catch(RuntimeException $e){$blocked=true;}
navAssert($blocked,'Second owner acquired lock');rrSportJobLock($lockfile,'release');navAssert(get_option('rrfr_match_followup_lock',null)===null,'Owned lock remains');
rrSportJobLock($lockfile,'acquire');update_option('rrfr_match_followup_lock','foreign-owner');$blocked=false;
try{rrSportJobLock($lockfile,'release');}catch(RuntimeException $e){$blocked=true;}
navAssert($blocked&&get_option('rrfr_match_followup_lock')==='foreign-owner','Foreign lock removed');
delete_option('rrfr_match_followup_lock');unlink($lockfile);
echo "SPORT_FOLLOWUP_OWNER_LOCK_TESTS_OK\n";
