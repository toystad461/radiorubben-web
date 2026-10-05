<?php
use RadioRubben\Fotballrobot\PlayerNewsFilter;
use RadioRubben\Fotballrobot\PlayerMonitor;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\Players;
use RadioRubben\Fotballrobot\Writer;

// Read-only verification. Never refresh, tick, write a draft or send a notification here.
function rrfr_news_fingerprint(): string {
    global $wpdb;
    $options=$wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'rrfr\\_player\\_%' ORDER BY option_name",ARRAY_A);
    $ids=$wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_rrfr_player_review','_rrfr_review_key') ORDER BY post_id");
    $posts=[];
    foreach($ids as $id){$post=get_post((int)$id,ARRAY_A);$meta=get_post_meta((int)$id);ksort($meta);$posts[]=['post'=>$post,'meta'=>$meta];}
    return hash('sha256',serialize([$options,$posts]));
}
try {
    $mode=$args[0]??'';
    if($mode==='fingerprint'){echo rrfr_news_fingerprint()."\n";return;}
    if($mode!=='check')throw new RuntimeException('Unknown check mode');
    if(PlayerNewsFilter::VERSION!=='2026-10-04.1'||Writer::WRITING_PROMPT_VERSION!=='2026-10-04.3')throw new RuntimeException('Expected filter or prompt missing');
    if((new ReflectionMethod(PlayerReview::class,'decide'))->getNumberOfParameters()!==6)throw new RuntimeException('Explicit editor facts were lost');
    $before=rrfr_news_fingerprint();
    $status=PlayerMonitor::status();
    if($status['version']!=='0.9.9'||$status['publication']!=='manual_approval_required'||!in_array('player_news_filter',$status['features'],true))throw new RuntimeException('Monitor or approval contract changed');
    $example=PlayerReview::state(1105);$samplePlayer=null;
    foreach(Players::ids() as $id){$p=Players::state((int)$id);if($p['fiks_id']===3584397)$samplePlayer=$p;}
    if(!$samplePlayer||empty($example['facts']['events']))throw new RuntimeException('Original example cannot be verified');
    $selected=PlayerNewsFilter::select($samplePlayer,$example['facts']['events']);
    if($selected['proposals']!==[])throw new RuntimeException('Original routine example still qualifies');
    if(!hash_equals($before,rrfr_news_fingerprint()))throw new RuntimeException('Read-only checks changed existing state');
    $counts=[];$eligible=0;
    foreach($status['players'] as $p){$eligible+=count($p['news_filter']['candidates']);foreach($p['news_filter']['excluded_counts'] as $reason=>$count)$counts[$reason]=($counts[$reason]??0)+$count;}
    echo wp_json_encode(['version'=>$status['version'],'players'=>count($status['players']),'eligible_matches'=>$eligible,'excluded_counts'=>$counts,'sample_1105_proposals'=>0,'existing_state'=>'unchanged','publication'=>$status['publication']],JSON_UNESCAPED_UNICODE)."\n";
    echo "PLAYER_NEWS_FILTER_RELEASE_OK\n";
} catch(Throwable $e){WP_CLI::error($e->getMessage());}
