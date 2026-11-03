<?php
// Read-only, bounded source probe for the existing host CLI. Never runs tick(), cron or writers.
namespace RadioRubben\Fotballrobot;
if(!defined('ABSPATH')||!class_exists(Fotballdata::class))throw new \RuntimeException('Fotballrobot must be loaded.');
$receipts=[];$endpoint='';
$observer=static function($response,$context,$transport,$args,$url)use(&$receipts,&$endpoint){
    if($context!=='response'||parse_url($url,PHP_URL_HOST)!=='api.fotballdata.no')return;
    $receipt=['endpoint'=>$endpoint];
    if(is_wp_error($response)) {
        $receipt['category']=preg_match('/cURL error 28|timed out|timeout/i',$response->get_error_message())?'timeout':'network';
    } else {
        $status=(int)wp_remote_retrieve_response_code($response);
        $receipt['http_status']=$status;
        $receipt['category']=match(true){$status===200=>'http_ok',$status===401=>'authentication',$status===403=>'access',$status===429=>'rate_limit',$status>=500=>'service',default=>'http'};
        if($status!==200) {
            // Fixed booleans/enums only: distinguish edge challenges from API errors without exposing bodies.
            $body=(string)wp_remote_retrieve_body($response);
            $decoded=json_decode($body);
            $receipt['response_format']=json_last_error()===JSON_ERROR_NONE?'json':(preg_match('/<!doctype html|<html/i',$body)?'html':'other');
            $receipt['api_error_envelope']=is_object($decoded)&&property_exists($decoded,'ResponseStatus');
            $server=strtolower((string)wp_remote_retrieve_header($response,'server'));
            $receipt['server_family']=str_contains($server,'cloudflare')?'cloudflare':(str_contains($server,'nginx')?'nginx':(str_contains($server,'iis')?'iis':'other'));
            $receipt['edge_challenge']=strtolower((string)wp_remote_retrieve_header($response,'cf-mitigated'))==='challenge';
            $receipt['mentions_access_denied']=(bool)preg_match('/access denied|forbidden|unauthorized/i',$body);
            $receipt['mentions_invalid_credentials']=(bool)preg_match('/invalid (credentials|api key|client)|expired (credentials|api key)|authentication failed/i',$body);
        }
    }
    $receipts[]=$receipt;
};
add_action('http_api_debug',$observer,10,5);
$out=['at'=>gmdate(DATE_ATOM),'runtime_sha256'=>[]];
try {
    $dir=dirname((new \ReflectionClass(Fotballdata::class))->getFileName());
    foreach(['fotballdata.php','club-coverage.php','match-followup.php'] as $file)$out['runtime_sha256'][$file]=hash_file('sha256',$dir.'/'.$file);
    $endpoint='club_teams';$teams=ClubCoverage::teams(Fotballdata::clubTeams());
    $out['eligible_teams']=count($teams);
    if($teams){$endpoint='club_matches';$matches=ClubCoverage::matches(Fotballdata::clubMatches(),$teams,time());$out['matches']=count($matches);}
    else $out['matches_request']='not_needed';
    $out['result']='valid';
} catch(\Throwable $e) {
    // Old production adapter exceptions lack structured diagnostics. Never output their messages.
    $out['result']='error';
} finally {remove_action('http_api_debug',$observer,10);}
$out['requests']=$receipts;
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";
