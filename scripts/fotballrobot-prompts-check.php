<?php
// Read-only health check; no article generation, source retrieval, mail or data writes.
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\EditorialQuality;
if(Writer::WRITING_PROMPT_VERSION!=='2026-10-03.1')throw new RuntimeException('Unexpected prompt revision');
if(Writer::DEFAULT_FEATURED_MEDIA!==812||EditorialQuality::RULES_VERSION!=='1.0.0')throw new RuntimeException('Editorial safeguards changed');
foreach([Writer::prompt(),Writer::playerPrompt()] as $prompt) {
    if(strpos($prompt,Writer::editorialProfile())!==0||strpos($prompt,EditorialQuality::prompt())===false)throw new RuntimeException('Shared writing instructions missing');
}
require __DIR__.'/fotballrobot-monitor-check.php';
echo "Verified editorial prompts 2026-10-03.1 and unchanged quality rules. No paid AI call or article mutation.\n";
