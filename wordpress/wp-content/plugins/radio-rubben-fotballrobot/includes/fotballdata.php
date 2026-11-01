<?php
namespace RadioRubben\Fotballrobot;

/** Only fixed classifications leave the transport boundary; never retain a previous exception. */
final class FotballdataError extends \RuntimeException {
    public function __construct(public readonly string $category, public readonly int $httpStatus=0, public readonly int $retryAfter=0) {
        parent::__construct('Fotballdata: '.$category.($httpStatus?' (HTTP '.$httpStatus.')':'').'. Tidligere grunnlag beholdes.');
    }
}

/** Server-only Fotballdata adapter. Never persist raw responses or credential URLs. */
final class Fotballdata {
    public static function clubTeams(): array { return self::request('clubs/827/teams'); }
    public static function clubMatches(): array { return self::request('clubs/827/matches'); }
    public static function enabled(): bool {
        return defined('RRFR_FOTBALLDATA_ENABLED') && RRFR_FOTBALLDATA_ENABLED === true;
    }
    private static function setting(string $key): string {
        $value=defined($key)?constant($key):getenv($key);
        // Reuse the site's existing private NFF settings; never copy credentials to code or logs.
        if($value===false||$value==='') {
            $options=['RRFR_FOTBALLDATA_CID'=>'rr_fd_cid','RRFR_FOTBALLDATA_CWD'=>'rr_fd_cwd'];
            if(isset($options[$key])) $value=get_option($options[$key],'');
        }
        return is_scalar($value)?trim((string)$value):'';
    }
    private static function request(string $path,array $query=[]): array {
        $cid=self::setting('RRFR_FOTBALLDATA_CID'); $cwd=self::setting('RRFR_FOTBALLDATA_CWD');
        if(!preg_match('/^[1-9][0-9]*$/D',$cid) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$cwd))
            throw new FotballdataError('configuration');
        $url='https://api.fotballdata.no/v1/'.$path.'?'.http_build_query($query+['cid'=>$cid,'cwd'=>$cwd,'format'=>'json']);
        // Exceptions may contain the request URL. Never propagate transport messages.
        try {
            $r=wp_safe_remote_get($url,['timeout'=>20,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'application/json']]);
        } catch(\Throwable $e) { throw new FotballdataError('network'); }
        if(is_wp_error($r)) {
            $timeout=preg_match('/cURL error 28|timed out|timeout/i',$r->get_error_message());
            throw new FotballdataError($timeout?'timeout':'network');
        }
        $status=(int)wp_remote_retrieve_response_code($r);
        if($status!==200) {
            $category=match(true) {
                $status===401=>'authentication', $status===403=>'access', $status===429=>'rate_limit',
                $status>=500=>'service', default=>'http',
            };
            $retry=0;
            if(in_array($status,[429,503],true)) {
                $header=wp_remote_retrieve_header($r,'retry-after');
                if(is_scalar($header)) {
                    $header=trim((string)$header);
                    $retry=ctype_digit($header)?(int)$header:max(0,(int)strtotime($header)-time());
                    $retry=min(86400,$retry);
                }
            }
            throw new FotballdataError($category,$status,$retry);
        }
        $body=wp_remote_retrieve_body($r);
        if(!is_string($body) || $body==='' || strlen($body)>=2500000)
            throw new FotballdataError('response_body');
        $object=json_decode($body,false,64);
        if(json_last_error()!==JSON_ERROR_NONE)throw new FotballdataError('invalid_json');
        if(!is_object($object)||property_exists($object,'ResponseStatus'))throw new FotballdataError('schema');
        // An empty JSON list is valid; missing/null/object collections are not.
        $collection=str_ends_with($path,'/teams')?'Teams':(str_ends_with($path,'/matches')?'Matches':null);
        if($collection!==null&&!is_array($object->{$collection}??null))throw new FotballdataError('schema');
        $data=json_decode($body,true,64);
        if(!is_array($data) || json_last_error()!==JSON_ERROR_NONE || isset($data['ResponseStatus']))
            throw new FotballdataError('schema');
        return $data;
    }
    private static function integer($v,int $min=0): int {
        if(!is_int($v) || $v<$min || $v>999999999) throw new FotballdataError('schema');
        return $v;
    }
    private static function text($v): string {
        if(!is_string($v) || trim($v)==='') throw new FotballdataError('schema');
        return trim($v);
    }
    public static function date($v): string {
        if(!is_string($v) || !preg_match('~^/Date\(([0-9]{12,13})([+-][0-9]{4})?\)/$~D',$v,$m))
            throw new FotballdataError('schema');
        $date=new \DateTimeImmutable('@'.intdiv((int)$m[1],1000));
        $zone=new \DateTimeZone('Europe/Oslo');
        // Fotballdata serializes Norwegian wall time with the unspecified -0000 marker.
        // Verified against the same XML MatchStartDate and NFF (see kickoff audit).
        if(($m[2]??'')==='-0000') {
            $wall=$date->format('Y-m-d H:i:s');
            $date=new \DateTimeImmutable($wall,$zone);
            if($date->format('Y-m-d H:i:s')!==$wall)
                throw new FotballdataError('schema');
        }
        return $date->setTimezone($zone)->format(DATE_ATOM);
    }
    public static function matchRow(array $r): array {
        $id=self::integer($r['MatchId']??null,1);
        $home=['id'=>self::integer($r['HomeTeamId']??null,1),'name'=>self::text($r['HomeTeamName']??null)];
        $away=['id'=>self::integer($r['AwayTeamId']??null,1),'name'=>self::text($r['AwayTeamName']??null)];
        if($home['id']===$away['id']) throw new FotballdataError('schema');
        $score=null;
        if(isset($r['HomeTeamGoals'],$r['AwayTeamGoals']))
            $score=[self::integer($r['HomeTeamGoals']),self::integer($r['AwayTeamGoals'])];
        $kickoff=self::date($r['MatchStartDate']??null);
        foreach(['Cancelled','Postponed','Interrupted'] as $field) {
            if(isset($r[$field]) && !is_bool($r[$field])) throw new FotballdataError('schema');
            if(($r[$field]??false)===true) $score=null;
        }
        // A scheduled match's default 0-0 is never a played result.
        if(strtotime($kickoff)>time()) $score=null;
        return ['id'=>$id,'home'=>$home,'away'=>$away,'kickoff'=>$kickoff,'score'=>$score,
            'competition_id'=>self::integer($r['TournamentId']??null,1),
            'venue'=>self::text($r['StadiumName']??null),
            'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$id];
    }
    public static function historyRows(array $data,int $team): array {
        if(($data['TeamId']??null)!==$team || !isset($data['Matches']) || !is_array($data['Matches']))
            throw new FotballdataError('schema');
        $rows=[];
        foreach($data['Matches'] as $raw) {
            if(!is_array($raw)) throw new FotballdataError('schema');
            $r=self::matchRow($raw);
            if(!in_array($team,[$r['home']['id'],$r['away']['id']],true)) throw new FotballdataError('schema');
            // Only district-approved history contributes to streaks; unresolved scores stay unknown.
            if(($raw['FinalResultApprovedByDistrict']??false)!==true) $r['score']=null;
            if(isset($rows[$r['id']]) && $rows[$r['id']]!==$r) throw new FotballdataError('schema');
            $rows[$r['id']]=$r;
        }
        return array_values($rows);
    }
    public static function history(int $team): array {
        self::integer($team,1);
        return ['rows'=>self::historyRows(self::request('teams/'.$team.'/matches'),$team),
            'url'=>'https://www.fotballdata.no/','provider'=>'Fotballdata','fetched_at'=>gmdate(DATE_ATOM)];
    }
    public static function assertMatch(array $api,array $match): void {
        $r=self::matchRow($api);
        if($r['id']!==$match['id'] || $r['home']['id']!==$match['home']['id'] || $r['away']['id']!==$match['away']['id']
            || $r['competition_id']!==$match['competition']['id'] || $r['score']!==$match['score']
            || strtotime($r['kickoff'])!==strtotime($match['kickoff']))
            throw new \RuntimeException('Fotballdata og Fotball.no er ikke enige om kampgrunnlaget. Kontroller før nytt utkast.');
    }
    public static function verify(array $match,int $club): array {
        self::integer($club,1); self::integer($match['id'],1);
        self::assertMatch(self::request('matches/'.$match['id'],['clubid'=>$club]),$match);
        return ['url'=>'https://www.fotballdata.no/','provider'=>'Fotballdata','fetched_at'=>gmdate(DATE_ATOM)];
    }
}
