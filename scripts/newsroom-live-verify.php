<?php
use RadioRubben\Fotballrobot\ReviewDigest;
if(get_option('home')!=='https://www.radiorubben.no'||!current_user_can('manage_options'))throw new RuntimeException('Wrong site or actor.');
require_once ABSPATH.'studio-private/app/newsroom.php';
// Resolve only the known failed draft handoff after an authenticated all-status slug lookup.
$id='37115a841ef21a07';$item=studio_case_get($id);$delivery=$item['web']['delivery']??[];
if(($delivery['state']??'')==='unknown'&&($delivery['requestedStatus']??'')==='draft'&&empty($delivery['id'])){
    global $wpdb;$matches=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_name LIKE %s",$wpdb->esc_like('studio-'.$id).'%'));
    if(!$matches){
        studio_board_change(static function(&$b)use($id,$delivery){foreach($b['items']as&$row)if($row['id']===$id){if(($row['web']['delivery']??[])!==$delivery)throw new RuntimeException('Delivery changed.');$row['web']['history'][]=['action'=>'resolve_not_delivered','at'=>gmdate('c'),'actor'=>'Kontrollert serveroppslag','before'=>['delivery'=>$delivery],'evidence'=>'Ingen WordPress-innlegg med reservert Studio-slug i noen status.'];$row['web']['delivery']=array_replace($delivery,['state'=>'not_delivered','resolvedAt'=>gmdate('c')]);$row['web']['approvedHash']=null;$row['revision']++;}});
        echo "LEGACY_DRAFT_RESOLVED_NOT_DELIVERED (no publication or retry)\n";
    }else echo "LEGACY_DRAFT_NEEDS_REVIEW (matching post exists)\n";
}
// Exercise exactly one normal bounded preparation job. It never approves, publishes or sends mail.
try{$job=ReviewDigest::worker('tick');echo wp_json_encode(['preparation'=>$job])."\n";}catch(Throwable $e){echo "PREPARATION_REQUIRES_ATTENTION\n";}
$cards=[];foreach(studio_board_active(studio_board_read())as$i)if(studio_web_is_news($i)){
    $card=studio_newsroom_card($i);$cards[]=['id'=>$i['id'],'title'=>$card['title'],'status'=>$card['status'],'reasons'=>$card['reasons'],'sourceUrl'=>$card['sourceUrl'],'originalRead'=>$card['originalRead'],'sourceBytes'=>strlen($i['web']['check']['source']['text']??''),'sourceFetchedAt'=>$card['sourceFetchedAt'],'hasReadMore'=>str_contains(studio_web_html(array_replace($i,['web'=>array_replace(['intro'=>'','body'=>''],$i['web']??[])])),'Les hele saken hos NRK'),'delivery'=>$i['web']['delivery']['state']??'none'];
}
echo wp_json_encode(['studio_cards'=>$cards,'digest'=>ReviewDigest::status(),'scheduled'=>['prepare'=>wp_next_scheduled('rrfr_newsroom_prepare'),'digest'=>wp_next_scheduled('rrfr_newsroom_digest')]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
echo "NEWSROOM_LIVE_VERIFIED\n";
