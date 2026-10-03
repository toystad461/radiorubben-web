<?php
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI required');
if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
use RadioRubben\Fotballrobot\Writer;
if(!class_exists(Writer::class)||Writer::DEFAULT_FEATURED_MEDIA!==812)throw new RuntimeException('Wrong default image');
if(!wp_attachment_is_image(812)||get_post_status(812)!=='inherit')throw new RuntimeException('Expected image unavailable');
$m=wp_get_attachment_metadata(812);
if(($m['width']??0)!==1672||($m['height']??0)!==941)throw new RuntimeException('Image dimensions changed');
if(get_post_status(1073)!=='publish'||get_post_thumbnail_id(1073)!==812)throw new RuntimeException('Article image or status changed; inspect');
WP_CLI::success('Installed default image 812 and published article image verified.');
