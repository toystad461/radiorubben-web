<?php
// Thomas requested one baseline article for comments. Use the native test review path.
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\PlayerMonitor;
use RadioRubben\Fotballrobot\Players;
use RadioRubben\Fotballrobot\Robot;
use RadioRubben\Fotballrobot\PublicationGate;
use RadioRubben\Fotballrobot\Writer;
if(!Robot::allowed()||get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site or user capability');
$player=Players::state(1012);
if($player['fiks_id']!==3909887)throw new RuntimeException('Player identity mismatch');
$key='example:player:3909887:match:9007464:2026-10-03';
$profile='https://www.fotball.no/fotballdata/person/profil/?fiksId=3909887';
$match='https://www.fotball.no/fotballdata/kamp/?fiksId=9007464';
// Facts independently read on the concrete NFF match page and linked player profile, 3 October 2026.
$facts=[
 'type'=>'example_match_article','player_id'=>1012,'fiks_id'=>3909887,'name'=>'Lasse Nathaniel Høgmo Breivik',
 'source'=>$match,'fetched_at'=>'2026-10-03',
 'player'=>['name'=>'Lasse Nathaniel Høgmo Breivik','fiks_id'=>3909887,'team'=>'Åsane','role'=>'starter','goal_minute'=>23,'yellow_card_minute'=>59],
 'background'=>['previous_team'=>'Bremnes','evidence'=>'NFFs spillerprofil viser Bremnes-kamper i 2025 og tidligere sesonger.','source'=>$profile],
 'finished_confirmed'=>true,
 'match'=>['id'=>9007464,'home'=>['name'=>'Åsane'],'away'=>['name'=>'Vålerenga'],'score'=>[6,4],'home_score'=>6,'away_score'=>4,'halftime'=>'2–2','status'=>'Kampen er slutt','kickoff'=>'2026-09-26T13:00:00+02:00','venue'=>'Åsane Arena','competition'=>'Nasjonal G17 - 1. div. Elite',
   'goals'=>[
    ['minute'=>1,'name'=>'Sondre Eberg Fimreite','team'=>'Åsane','score'=>'1–0'],
    ['minute'=>2,'name'=>'Raynor Shrestha','team'=>'Vålerenga','score'=>'1–1'],
    ['minute'=>23,'name'=>'Lasse Nathaniel Høgmo Breivik','team'=>'Åsane','score'=>'2–1'],
    ['minute'=>43,'name'=>'Josef Elabdellaoui','team'=>'Vålerenga','score'=>'2–2'],
    ['minute'=>53,'name'=>'Victor Gard Kalsaas','team'=>'Åsane','score'=>'3–2'],
    ['minute'=>62,'name'=>'Malvin Landsvik Frantzen','team'=>'Åsane','score'=>'4–2'],
    ['minute'=>64,'name'=>'Immanuel Dameson Silalahi','team'=>'Vålerenga','score'=>'4–3'],
    ['minute'=>72,'name'=>'Jancarlos Ortiz Ramirez','team'=>'Vålerenga','score'=>'4–4'],
    ['minute'=>78,'name'=>'Victor Gard Kalsaas','team'=>'Åsane','score'=>'5–4'],
    ['minute'=>82,'name'=>'Victor Gard Kalsaas','team'=>'Åsane','score'=>'6–4']
   ],
   'substitution'=>['minute'=>45,'team'=>'Åsane','in'=>'Victor Gard Kalsaas','out'=>'Patrick Svanevik Hølleland']
 ],
 // The body renderer also uses event sources for the visible source list.
 'events'=>[['source'=>$profile,'kind'=>'background','after'=>'Tidligere Bremnes-spiller']]
];
// Match quality packets keep match facts, including the explicit player focus and background.
$facts['match']['player_focus']=$facts['player'];
$facts['match']['player_background']=$facts['background'];
$facts['sources']=['match'=>$match,'player_profile'=>$profile];
$id=PlayerReview::create($key,$facts,true);
$s=PlayerReview::state($id);$post=get_post($id);
if(!$s['test']||$post->post_status!=='draft')throw new RuntimeException('Example must remain a protected test draft');
if($s['status']!=='pending')throw new RuntimeException('Example not ready: '.($s['error']??$s['status']));
// One-time correction of this example's packet structure, not of its prose or editorial history.
// The concrete match was independently verified finished above. No approval is manufactured.
if($id===1075&&is_string($s['facts']['match']['home']??null)){
    if($s['version']!==3||!hash_equals('07f434c146c8cd562316e5c07b5e888674c85c713be060d74a34f19782bbe454',PublicationGate::hash($post)))throw new RuntimeException('Example changed; preserve current work and inspect before correction');
    $lock='rrfr_review_lock_'.$id;
    if(!add_option($lock,time(),'','no'))throw new RuntimeException('Review in progress');
    try{
        $latest=PlayerReview::state($id);
        if($latest!==$s||!hash_equals(PublicationGate::hash($post),PublicationGate::hash(get_post($id))))throw new RuntimeException('Concurrent example edit');
        $s['facts']=$facts;
        update_post_meta($id,PlayerReview::META,$s);
        if(PlayerReview::state($id)!==$s)throw new RuntimeException('Fact packet was not saved');
        $checked=Writer::recheck($id,PublicationGate::hash($post));
        echo wp_json_encode($checked,JSON_UNESCAPED_UNICODE)."\n";
    }finally{delete_option($lock);}
}
// Preserve the first text for comparison. This is not an approved learning example.
add_post_meta($id,'_rrfr_example_baseline',['created_at'=>gmdate(DATE_ATOM),'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'purpose'=>'Eksempel til Thomas sine kommentarer; ikke godkjent skrivestandard.'],true);
echo wp_json_encode(['id'=>$id,'status'=>$post->post_status,'test'=>$s['test'],'review_status'=>$s['status'],'quality_current'=>PublicationGate::current($id,$post),'mail'=>$s['mail'],'review_url'=>PlayerReview::url($id),'title'=>$post->post_title],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
