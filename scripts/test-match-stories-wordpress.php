<?php
// Run only through WP-CLI in the disposable CI database. No production, AI, mail or source calls.
use RadioRubben\Fotballrobot\{MatchStories as S,WeeklyCoverage as W,PublicationGate as G,EditorialQuality as Q,ReviewDesk as D,EditorialNotice as N};
if(!defined('WP_CLI')||!WP_CLI||getenv('RRFR_ISOLATED_WP')!=='1'||get_option('blogname')!=='RRFR isolated integration')throw new RuntimeException('Disposable WordPress only');
add_filter('pre_http_request',static fn()=>new WP_Error('offline_test','Network is disabled in the integration test'),1);
add_filter('pre_wp_mail',static fn()=>false);
wp_set_current_user(1);
$count=0;
function verify($v,$message){global $count;$count++;if(!$v)throw new RuntimeException($message);}
function refused($fn,$message){try{$fn();}catch(Throwable $e){verify(true,$message);return;}verify(false,$message);}
function approveQuality($id){$p=get_post($id);update_post_meta($id,G::META,G::bind(['rulesVersion'=>Q::RULES_VERSION,'factsHash'=>Q::hash(Q::packet(G::facts($id))),'publishable'=>true,'languageStatus'=>'completed','findings'=>[]],(array)$p));}
function renderTest($name,$html){$dir=getenv('RRFR_RENDER_DIR');if(!$dir)throw new RuntimeException('Missing output directory');wp_mkdir_p($dir);ob_start();require WP_PLUGIN_DIR.'/radio-rubben-fotballrobot/includes/review-desk-style.php';W::stylesheet();$styles=ob_get_clean();$admin=file_get_contents(WP_PLUGIN_DIR.'/radio-rubben-fotballrobot/admin.css');file_put_contents($dir.'/'.$name.'.html','<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#0c1421;color:#eef3fa;padding:16px;font-family:system-ui}main{max-width:780px;margin:auto}'.$admin.'</style>'.$styles.'<main class="rrfr rrfr-desk">'.$html.'</main></html>');}
$m=['id'=>987654321,'home'=>['id'=>1,'name'=>'Bremnes testlag'],'away'=>['id'=>2,'name'=>'Eksempellag'],'competition'=>['id'=>3,'name'=>'Syntetisk testkamp'],'score'=>[2,1],'kickoff'=>'2026-10-12T18:30:00+02:00','venue'=>'Testbane','source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=987654321','finished_confirmed'=>true];
$wf=['week'=>W::week('2026-10-12'),'kind'=>'results','matches'=>[$m],'fetched_at'=>'2026-10-19T08:00:00+02:00','sources'=>[['url'=>$m['source'],'provider'=>'NFF']]];
$legacy=N::BLOCK.'<p>Behold min redigerte ingress.</p><style>.rrfr-week-card{color:red}</style>'.W::card($m,true,true);
$parent=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Ukesresultater – syntetisk prøve','post_content'=>$legacy,'meta_input'=>['_rrfr_club_key'=>'results:2026-10-12','_rrfr_fact_snapshot'=>$wf,'_rrfr_editor_decision'=>['status'=>'pending','version'=>2]]],true);
verify(!is_wp_error($parent),'Parent created');approveQuality($parent);verify(G::current($parent,get_post($parent)),'Real post/fact binding');
$token=S::token($parent);$beforeDecision=get_post_meta($parent,'_rrfr_editor_decision',true);
S::template($parent,$token);verify(get_post_status($parent)==='draft'&&str_contains(get_post_field('post_content',$parent),'Behold min redigerte ingress.'),'Draft migration preserves prose/status');
verify(!G::current($parent,get_post($parent)),'Migration invalidates original quality');verify(get_post_meta($parent,'_rrfr_editor_decision',true)===$beforeDecision,'Decision unchanged');
verify(!str_contains(get_post_field('post_content',$parent),'<style>'),'Legacy CSS removed');
wp_update_post(['ID'=>$parent,'post_status'=>'publish']);verify(get_post_status($parent)==='draft','Unreviewed publication stopped in real WordPress');
$cf=['match'=>$m,'finished_confirmed'=>true,'detail_check'=>['version'=>1,'match_id'=>$m['id']],'sources'=>$wf['sources']];
$child=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Bremnes testlag vant 2–1','post_content'=>N::BLOCK.W::card($m).'<p>Dette er en syntetisk kontrolltekst, ikke et ekte referat.</p>','meta_input'=>['_rrfr_club_key'=>'match:'.$m['id'],'_rrfr_ai_match'=>$m['id'],'_rrfr_fact_snapshot'=>$cf,'_rrfr_editor_decision'=>['status'=>'pending','version'=>2]]],true);
verify(!is_wp_error($child),'Child created');$card=W::card($m,true,true);verify(S::links($card)===$card,'Draft never linked');
approveQuality($child);wp_update_post(['ID'=>$child,'post_status'=>'publish']);verify(get_post_status($child)==='draft','CLI quality approval alone never publishes');
wp_set_current_user(0);refused(fn()=>S::template($parent,S::token($parent)),'Anonymous cannot migrate');wp_set_current_user(1);
$model=D::model($child);D::decide($child,D::token($child,$model),'approve');verify(get_post_status($child)==='publish','Explicit existing review action publishes synthetic child');
verify(str_contains(S::links($card),get_permalink($child)),'Public link resolves through real WordPress');
$changed=$cf;$changed['match']['score']=[9,0];update_post_meta($child,'_rrfr_fact_snapshot',$changed);verify(S::links($card)===$card,'Changed child source removes public link');update_post_meta($child,'_rrfr_fact_snapshot',$cf);
wp_update_post(['ID'=>$child,'post_status'=>'draft']);verify(S::links($card)===$card,'Withdrawal removes public link');
verify(!str_contains(S::archive(),get_permalink($parent)),'Draft week hidden from Sport');
approveQuality($parent);$model=D::model($parent);D::decide($parent,D::token($parent,$model),'approve');verify(get_post_status($parent)==='publish','Synthetic weekly explicitly approved');
verify(str_contains(S::archive(),get_permalink($parent)),'Published week visible in archive');refused(fn()=>S::template($parent,S::token($parent)),'Published migration refused');
$sport=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Sport','post_name'=>'sport','post_content'=>'[rrfr_weekly_results]']);
$menu=wp_create_nav_menu('Hovedmeny test');wp_update_nav_menu_item($menu,0,['menu-item-title'=>'Sport','menu-item-object'=>'page','menu-item-object-id'=>$sport,'menu-item-type'=>'post_type','menu-item-status'=>'publish']);
$nav=wp_nav_menu(['menu'=>$menu,'echo'=>false]);verify(str_contains($nav,get_permalink($sport))&&str_contains($nav,'Sport'),'Real WP menu resolves Sport page');
$archive=apply_filters('the_content',get_post_field('post_content',$sport));verify(str_contains($archive,'id="rr-weekly-results"')&&!str_contains($archive,'[rrfr_weekly_results]'),'Shortcode resolves in real WordPress content pipeline');
$routes=rest_get_server()->get_routes();verify(isset($routes['/rr-fotballrobot/v1/weekly-template'],$routes['/rr-fotballrobot/v1/weekly-match']),'Routes registered');
wp_set_current_user(0);$response=rest_do_request(new WP_REST_Request('POST','/rr-fotballrobot/v1/weekly-template'));verify($response->get_status()>=400,'Anonymous REST denied');wp_set_current_user(1);
ob_start();D::article($parent,D::model($parent));$html=ob_get_clean();verify(str_contains($html,'rrfr-match-layout')&&!str_contains(strip_tags($html),'.rrfr-week-card{color:red}'),'Actual KSES preview has cards and no CSS text leak');
renderTest('wp-review',$html);renderTest('wp-sport',$nav.$archive);
echo 'OK: '.$count.' real WordPress integration checks; WordPress '.get_bloginfo('version').'; synthetic isolated database only'.PHP_EOL;
