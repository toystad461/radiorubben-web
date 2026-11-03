<?php
namespace RadioRubben\Fotballrobot;
// Isolated capability, route and status checks. No production HTTP, posts or options.
const ABSPATH=__DIR__.'/';
$caps=[];$options=[];$routes=[];$hooks=[];$menus=[];
function current_user_can($cap){global $caps;return in_array($cap,$caps,true);}
function get_option($k,$d=false){global $options;return $options[$k]??$d;}
function update_option($k,$v,...$unused){global $options;$options[$k]=$v;return true;}
function add_action($k,$fn,...$unused){global $hooks;$hooks[$k][]=$fn;}
function register_rest_route($ns,$path,$args){global $routes;$routes[$path]=$args;}
function add_submenu_page($parent,$title,$label,$cap,$slug,$fn){global $menus;$menus[$slug]=[$parent,$cap,$fn];}
function sanitize_text_field($v){return strip_tags($v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',$v);}
function absint($v){return abs((int)$v);}
function admin_url($path){return 'https://wp.example/wp-admin/'.$path;}
final class Robot {public static function allowed(): bool {return current_user_can('manage_options')&&current_user_can('edit_posts');}}
require __DIR__.'/../includes/source-collection.php';
function check($condition,$message){if(!$condition)throw new \RuntimeException($message);}
check(!SourceCollection::allowed(),'anonymous rejected');
$caps=['read'];check(!SourceCollection::allowed(),'ordinary read user rejected');
$caps=['rrfr_collect_sources'];check(SourceCollection::allowed(),'scoped collector accepted');check(!Robot::allowed(),'collector cannot use editor actions');
$caps=['manage_options','edit_posts'];check(SourceCollection::allowed(),'administrator accepted');
SourceCollection::menu();check($menus['rrfr-source-collection'][1]==='manage_options','admin-only menu');
SourceCollection::routes();check(isset($routes['/source-collection'],$routes['/players/(?P<id>[0-9]+)/source-history']),'read routes exist');
foreach($routes as $r)check($r['methods']==='GET','status/history never writes');
$options[SourceCollection::OPTION]=['last_success'=>'2026-01-01T00:00:00Z'];
$s=SourceCollection::status();check($s['state']==='unavailable'&&!$s['reachable'],'missing configuration not active');
check($s['last_contact']['last_success']==='2026-01-01T00:00:00Z','last receipt preserved');
$clean=SourceCollection::clean(['secret'=>'synthetic-secret','events'=>array_fill(0,80,['kind'=>'test','message'=>'<script>x</script>'])]);
check(!isset($clean['secret'])&&count($clean['events'])===50,'bounded allowlisted status');
check($clean['events'][0]['message']==='x','status text sanitized');
$source=file_get_contents(__DIR__.'/../includes/source-collection.php');
check(str_contains($source,"check_admin_referer('rrfr_source_collection')"),'control nonce');
check(!str_contains($source,'wp_insert_post(')&&!str_contains($source,'wp_schedule_event('),'no publishing or WP scheduler');
// Scope applies only to collection. Decisions and profile editing retain Robot::allowed.
$candidates=file_get_contents(__DIR__.'/../includes/player-candidates.php');
check(str_contains($candidates,"public static function decide(int \$fiks,int \$version,string \$operation): array {\n        if(!Robot::allowed())"),'candidate approval still admin only');
$players=file_get_contents(__DIR__.'/../includes/players.php');
check(str_contains($players,"public static function action(): void {\n        if(!Robot::allowed())"),'profile editing still admin only');
echo "OK: source collection capability, routes, unavailable status, retained history and bounds\n";
