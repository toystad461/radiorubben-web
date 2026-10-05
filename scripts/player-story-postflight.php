<?php
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PlayerCorrections;
if(PlayerReview::DEFAULT_FEATURED_MEDIA!==813||!wp_attachment_is_image(813)||Writer::WRITING_PROMPT_VERSION!=='2026-10-05.1'||!class_exists(PlayerCorrections::class))throw new RuntimeException('Unexpected runtime.');
if(get_post(1091)->post_status!=='publish'||get_post_thumbnail_id(1091)!==813)throw new RuntimeException('Requested article/image changed.');
echo "PLAYER_STORY_RUNTIME_OK\n";
