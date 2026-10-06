<?php
/** Rendering boundary regression without touching match storage or remote services. */
define('ABSPATH',__DIR__);
function add_action(...$args){} function add_shortcode(...$args){}
$loop=true;$main=true;$protected=false;$id=42;$exists=true;$player='';
function in_the_loop(){return $GLOBALS['loop'];}
function is_main_query(){return $GLOBALS['main'];}
function get_the_ID(){return $GLOBALS['id'];}
function post_password_required(){return $GLOBALS['protected'];}
function shortcode_exists($tag){return $GLOBALS['exists'];}
function do_shortcode($tag){if($tag!=='[rr_spillerkamper]')throw new Exception('Wrong delegate');return $GLOBALS['player'];}
require __DIR__.'/../rr-site-functions/inc/match-day-embed.php';
$n=0;function verify($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
verify(rr_site_match_day_shortcode()==='','Unprepared content must not execute match renderer');
foreach(['loop'=>false,'main'=>false,'protected'=>true,'id'=>99] as $key=>$value){
 $before=$GLOBALS[$key];$GLOBALS[$key]=$value;
 $GLOBALS['rr_site_match_day_view']=['post'=>42,'html'=>'public match','used'=>false];
 verify(rr_site_match_day_shortcode()==='','Render boundary '.$key);$GLOBALS[$key]=$before;
}
$GLOBALS['rr_site_match_day_view']=['post'=>42,'html'=>'public match','used'=>false];
verify(rr_site_match_day_shortcode()==='public match','Prepared view delivered');
verify(rr_site_match_day_shortcode()==='','Duplicate module suppressed');
verify(str_contains(rr_site_player_matches_shortcode(),'Ingen spillerkamper'),'Empty data explained');
$exists=false;verify(str_contains(rr_site_player_matches_shortcode(),'ikke tilgjengelig'),'Missing plugin explained');
$exists=true;$player='<section>Existing widget</section>';
verify(rr_site_player_matches_shortcode()===$player,'Existing plugin owns player data and markup');
echo "PASS: $n embedding boundaries and player delegation assertions.\n";
