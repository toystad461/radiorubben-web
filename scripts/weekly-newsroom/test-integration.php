<?php
/** Exact WP image contract -> full Studio newsroom -> real weekly gates, with WP/model doubles. */
$studio=realpath($argv[1]??'')?:throw new RuntimeException('Studio checkout required');
$root=dirname(__DIR__,2);$dir=sys_get_temp_dir().'/rr-combined-'.bin2hex(random_bytes(6));
mkdir($dir,0700);mkdir($dir.'/data',0700);
try {
 // Source hash was independently compared with Web main's production image plugin.
 copy($studio.'/app/news-images-contract.php',$dir.'/contract.php');
 copy($studio.'/app/data/01-BILDEREGISTER.json',$dir.'/data/01-BILDEREGISTER.json');
 require $dir.'/contract.php';
 require $studio.'/app/newsroom.php';
 if(count(rrni_assets())!==21||!function_exists('studio_newsroom_card'))throw new RuntimeException('Image/Studio bootstrap failed');
 require $root.'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/tests/weekly-publication.php';
 require_once $root.'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/newsroom.php';
 // Actual queue serializer retains image identity and cannot change existing decision data.
 if(!function_exists('wp_get_attachment_image_url')){function wp_get_attachment_image_url($id,$size){return 'https://example.test/synthetic.png';}}
 if(!function_exists('wp_get_attachment_caption')){function wp_get_attachment_caption($id){return 'Syntetisk testbilde';}}
 if(!function_exists('get_permalink')){function get_permalink($id){return 'https://example.test/?p='.$id;}}
 $meta[900]['_thumbnail_id']=42;
 $before=serialize([$posts,$meta]);
 $card=RadioRubben\Fotballrobot\Newsroom::card(900);
 check($card['image']['id']===42,'Newsroom retains existing featured image');
 check(str_contains($card['body'],'0–0'),'Newsroom contains result from deterministic card');
 check($before===serialize([$posts,$meta]),'Reading newsroom does not alter article/image/approval data');
 echo "OK: combined WP image contract, Studio newsroom, weekly cards and unchanged read state\n";
} finally {unlink($dir.'/contract.php');unlink($dir.'/data/01-BILDEREGISTER.json');rmdir($dir.'/data');rmdir($dir);}
