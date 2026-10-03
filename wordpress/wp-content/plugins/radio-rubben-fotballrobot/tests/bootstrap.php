<?php
// Load the actual combined plugin and run menu callbacks in WordPress priority order.
const ABSPATH=__DIR__.'/';
function get_option($k,$d=false){return $d;}

$hooks=[];$menus=[];$styles=[];$deactivation=null;
function add_action($name,$fn,$priority=10,...$unused){global $hooks;$hooks[$name][$priority][]=$fn;}
function add_filter(...$unused){}
function register_deactivation_hook($file,$fn){global $deactivation;$deactivation=$fn;}
function add_menu_page($title,$label,$cap,$slug,...$unused){global $menus;$menus[$slug]=['parent'=>null,'cap'=>$cap];}
function add_submenu_page($parent,$title,$label,$cap,$slug,...$unused){global $menus;if(!isset($menus[$parent]))throw new RuntimeException('Parent not registered before submenu');$menus[$slug]=['parent'=>$parent,'cap'=>$cap];}
function wp_enqueue_style($handle,$url,$deps,$version){global $styles;$styles[]=[$handle,$version];}
function plugins_url($name,$file){return $name;}
require __DIR__.'/../radio-rubben-fotballrobot.php';
ksort($hooks['admin_menu']);foreach($hooks['admin_menu'] as $callbacks)foreach($callbacks as $fn)$fn();
foreach(['rr-fotballrobot','rr-fotballrobot-players','rrfr-learning','rrfr-player-review'] as $slug)if(($menus[$slug]['cap']??null)!=='manage_options')throw new RuntimeException('Missing protected menu: '.$slug);
foreach(['toplevel_page_rr-fotballrobot','fotballrobot_page_rr-fotballrobot-players','fotballrobot_page_rrfr-learning','fotballrobot_page_rrfr-player-review'] as $page){$styles=[];foreach($hooks['admin_enqueue_scripts'][10] as $fn)$fn($page);if($styles!==[['rrfr-admin','0.9.5']])throw new RuntimeException('Missing or duplicate styles: '.$page);}
foreach(['admin_post_rrfr_action','admin_post_rrfr_player_action','admin_post_rrfr_learning','rrfr_players_tick'] as $hook)if(empty($hooks[$hook]))throw new RuntimeException('Missing handler: '.$hook);
if($deactivation!==[RadioRubben\Fotballrobot\Players::class,'stop'])throw new RuntimeException('Cron cleanup missing');
echo "OK: combined plugin bootstrap, menu ordering, access capabilities, styles and handlers\n";
