<?php
// Read only. Never print credentials, raw responses, posts, or personal metadata.
try {
    if (!defined('ABSPATH') || !class_exists('RadioRubben\\Fotballrobot\\Fotballdata')) throw new RuntimeException();
    $feed = \RadioRubben\Fotballrobot\Fotballdata::clubMatches();
    $rows = [];
    foreach ($feed['Matches'] ?? [] as $row) {
        if (!in_array($row['MatchId'] ?? null, [8985501, 8985509], true)) continue;
        $raw = $row['MatchStartDate'] ?? null;
        if (!is_string($raw) || !preg_match('~^/Date\(([0-9]{12,13})([+-][0-9]{4})?\)/$~D', $raw, $m)) throw new RuntimeException();
        $rows[] = ['id'=>$row['MatchId'], 'raw_date'=>$raw,
            'epoch_as_utc'=>gmdate('Y-m-d H:i:s', intdiv((int)$m[1],1000)),
            'adapter_date'=>\RadioRubben\Fotballrobot\Fotballdata::date($raw)];
    }
    if (count($rows) !== 2) throw new RuntimeException();
    // Independently inspect XML serialization, restricted to the same two public dates.
    $setting = static function($key,$option) {
        $v=defined($key)?constant($key):getenv($key);
        return ($v===false||$v==='')?get_option($option,''):$v;
    };
    $cid=$setting('RRFR_FOTBALLDATA_CID','rr_fd_cid');
    $cwd=$setting('RRFR_FOTBALLDATA_CWD','rr_fd_cwd');
    if(!preg_match('/^[1-9][0-9]*$/D',(string)$cid)||!preg_match('/^[a-f0-9-]{36}$/Di',(string)$cwd))throw new RuntimeException();
    $url='https://api.fotballdata.no/v1/clubs/827/matches?'.http_build_query(['cid'=>$cid,'cwd'=>$cwd,'format'=>'xml']);
    $response=wp_safe_remote_get($url,['timeout'=>20,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'application/xml']]);
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)throw new RuntimeException();
    $body=wp_remote_retrieve_body($response);
    if(!is_string($body)||strlen($body)>=2500000||stripos($body,'<!DOCTYPE')!==false)throw new RuntimeException();
    $doc=new DOMDocument();
    if(!@$doc->loadXML($body,LIBXML_NONET))throw new RuntimeException();
    $xpath=new DOMXPath($doc);$xmlDates=[];
    foreach([8985501,8985509] as $id){
        $dates=$xpath->query('//*[local-name()="MatchId" and text()="'.$id.'"]/../*[local-name()="MatchStartDate"]');
        if($dates->length!==1)throw new RuntimeException();
        $date=trim($dates->item(0)->textContent);
        if(!preg_match('/^[0-9T:.+Z-]{19,40}$/D',$date))throw new RuntimeException();
        $xmlDates[]=['id'=>$id,'xml_date'=>$date];
    }
    echo json_encode(['mode'=>'read-only','kickoff_dates'=>$rows,'xml_dates'=>$xmlDates], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Kickoff diagnostic failed; response and error details withheld.\n");
    exit(1);
}
