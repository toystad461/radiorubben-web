<?php
// Run via WP-CLI with --skip-plugins --skip-themes. Only digests leave the process.
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI required');
global $wpdb;
if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
$queries=[
 'posts'=>"SELECT * FROM {$wpdb->posts} WHERE post_type IN ('post','attachment') ORDER BY ID",
 'postmeta'=>"SELECT m.* FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type IN ('post','attachment') ORDER BY m.meta_id",
 'terms'=>"SELECT r.* FROM {$wpdb->term_relationships} r JOIN {$wpdb->posts} p ON p.ID=r.object_id WHERE p.post_type IN ('post','attachment') ORDER BY r.object_id,r.term_taxonomy_id",
 'editorial_options'=>"SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'rrfr_%' AND option_name NOT LIKE '%lock%' ORDER BY option_name",
];
$state=[];foreach($queries as$key=>$sql){$rows=$wpdb->get_results($sql,ARRAY_A);if($wpdb->last_error)throw new RuntimeException('State read failed');$state[$key]=hash('sha256',serialize($rows));if($key==='editorial_options'){foreach($rows as $row)$state['option_hashes'][$row['option_name']]=hash('sha256',$row['option_value']);}}
$board=ABSPATH.'studio-private/config/sending-board.json';$state['studio_board']=is_file($board)?hash_file('sha256',$board):null;
echo json_encode($state,JSON_UNESCAPED_SLASHES)."\n";
