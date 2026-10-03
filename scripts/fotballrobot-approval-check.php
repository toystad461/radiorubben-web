<?php
// Read-only rendering of stored drafts. No decisions, quality calls, emails or cron invocations.
use RadioRubben\Fotballrobot\ReviewDesk;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\PublicationGate;
require __DIR__.'/fotballrobot-inline-check.php';
$users=get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);
if(!$users)throw new RuntimeException('Administrator unavailable for in-memory page check');
wp_set_current_user((int)$users[0]);
$count=0;
foreach(array_slice(ReviewDesk::items(),0,20) as $p) {
    $m=ReviewDesk::model((int)$p->ID);
    ob_start();ReviewDesk::article((int)$p->ID,$m);$html=ob_get_clean();
    if(!str_contains($html,'Lagret artikkeltekst')||!str_contains($html,'Historikk og flere valg'))throw new RuntimeException('Reading controls missing');
    if($m['pending']) {
        if(!str_contains($html,'name="token"')||!str_contains($html,'name="_wpnonce"'))throw new RuntimeException('Protected action missing');
        if(!$m['can_approve']&&!str_contains($html,'value="approve" disabled'))throw new RuntimeException('Unready draft exposes approval');
        if($m['can_approve']&&!PublicationGate::current((int)$p->ID,$p))throw new RuntimeException('Unreviewed approval offered');
    }
    $count++;
}
if(!has_action('admin_post_rrfr_review_desk',[ReviewDesk::class,'action']))throw new RuntimeException('Approval handler missing');
echo "Verified shared reading desk on $count existing drafts; no article changed or approved.\n";
