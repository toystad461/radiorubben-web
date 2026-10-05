<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || getenv('RR_DISPOSABLE_TEST_DB')!=='1' || wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1') throw new RuntimeException('Disposable localhost only');
$f=json_decode(file_get_contents('/tmp/rr-migration-fixtures.json'),true);
require_once ABSPATH.'wp-admin/includes/user.php';
foreach($f['users'] as $user)if(get_userdata($user['id']))wp_delete_user($user['id']);
wp_delete_post($f['post'],true);
global $wpdb;
$keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",'%'.$wpdb->esc_like((string)$f['match']).'%'));
foreach($keys as $key)if(str_starts_with($key,'rr_'))delete_option($key);
foreach($f['original'] as $k=>$item){if($item['exists'])update_option($k,$item['value']);else delete_option($k);}
unlink('/tmp/rr-migration-fixtures.json');
echo "Synthetic fixtures removed and original local options restored.\n";
