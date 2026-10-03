<?php
// Read-only check of two actual match pages using the candidate parser and renderer.
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI required');
if(class_exists('RadioRubben\\Fotballrobot\\Writer',false))throw new RuntimeException('Skip installed robot');
require dirname(__DIR__).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/facts.php';
require dirname(__DIR__).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/writer.php';
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\Lineups;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PublicationGate;
if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
$before=PublicationGate::hash(get_post(1073));$status=get_post_status(1073);
foreach([[8984418,48835,'home'],[8985483,30365,'away']] as [$id,$team,$side]) {
    $url='https://www.fotball.no/fotballdata/kamp/?fiksId='.$id;
    $response=wp_safe_remote_get($url,['timeout'=>25,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'text/html']]);
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)throw new RuntimeException('Official match source unavailable: '.$id);
    $html=wp_remote_retrieve_body($response);
    if(strlen($html)>=2500000)throw new RuntimeException('Truncated source');
    $match=Facts::match($html,$id);$lineups=Lineups::parse($html,$match);$prefix=$side==='home'?'':'away_';
    if($match[$side]['id']!==$team||$lineups['status'][$side]['starters']!=='confirmed'||count($lineups[$prefix.'starters'])!==11)
        throw new RuntimeException('Starting lineup not verified for '.$id.': '.wp_json_encode($lineups['warnings'],JSON_UNESCAPED_UNICODE));
    $paragraph=Lineups::paragraph(['match'=>$match,'lineups'=>$lineups]);
    if(!str_contains($paragraph,'<strong>Bremnes:</strong>')||!str_contains($paragraph,'(Innbyttere: '))throw new RuntimeException('Rendered lineup missing');
    if($id===8984418 && (!str_contains($paragraph,'Hope, Økland, Kvarven, A. Meling, M. Meling, M. Sortland, Rinne, Ånderå, Nesse, Våge, Stoknes.')||!str_contains($paragraph,'(Innbyttere: L. Sortland, Helvik, Lie)')))
        throw new RuntimeException('Women lineup differs from independently verified source; inspect before deploying');
    echo wp_json_encode(['match'=>$id,'team'=>$team,'side'=>$side,'starters'=>count($lineups[$prefix.'starters']),'bench'=>count($lineups[$prefix.'bench']),'paragraph'=>$paragraph],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}
if($before!==PublicationGate::hash(get_post(1073))||$status!==get_post_status(1073))throw new RuntimeException('Published article changed');
$root=dirname(__DIR__).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/';$checks=[];
foreach(['includes/lineups.php','includes/robot.php','includes/writer.php','radio-rubben-fotballrobot.php'] as $file)$checks[]=hash_file('sha256',$root.$file).'  '.$file;
file_put_contents(dirname(__DIR__).'/lineup-preflight-ok.sha256',implode("\n",$checks)."\n");
echo "LIVE LINEUP PREFLIGHT PASSED; existing article unchanged.\n";
