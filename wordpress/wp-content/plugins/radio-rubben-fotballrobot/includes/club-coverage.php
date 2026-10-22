<?php
namespace RadioRubben\Fotballrobot;

/** Bremnes club feed: only allowlisted sporting facts leave the adapter. */
final class ClubCoverage {
    public const CLUB=827;
    public static function teams(array $data): array {
        if (($data['ClubId']??null)!==self::CLUB || !is_array($data['Teams']??null)) throw new \RuntimeException('Ukjent klubbliste fra Fotballdata.');
        $teams=[];
        foreach ($data['Teams'] as $r) {
            if (!is_array($r) || ($r['ClubId']??null)!==self::CLUB || !is_int($r['TeamId']??null) || $r['TeamId']<1 || !is_string($r['TeamName']??null)) throw new \RuntimeException('Ugyldig lagidentitet.');
            $name=trim($r['TeamName']); $age=null;
            if (preg_match('/^Bremnes\s+([GJ])(\d{1,2})(?=\D|$)/u',$name,$m)) {
                $age=(int)$m[2]; if ($age<13) continue;
            } elseif (!preg_match('/^Bremnes\s+(Menn|Kvinner)(?:\s|$)/u',$name)) continue;
            $team=['id'=>$r['TeamId'],'name'=>$name,'age_class'=>$age];
            if (isset($teams[$team['id']]) && $teams[$team['id']]!==$team) throw new \RuntimeException('Motstridende lag-ID.');
            $teams[$team['id']]=$team;
        }
        if (!$teams) throw new \RuntimeException('Ingen lag fra G13/J13 eller senior funnet.');
        ksort($teams); return $teams;
    }
    public static function matches(array $data,array $teams,int $now): array {
        if (($data['ClubId']??null)!==self::CLUB || !is_array($data['Matches']??null)) throw new \RuntimeException('Ukjent kampliste fra Fotballdata.');
        $out=[];
        foreach ($data['Matches'] as $r) {
            if (!is_array($r)) throw new \RuntimeException('Ugyldig kamprad.');
            if (!isset($teams[$r['HomeTeamId']??0]) && !isset($teams[$r['AwayTeamId']??0])) continue;
            foreach (['Home','Away'] as $side) {
                if (isset($teams[$r[$side.'TeamId']??0]) && ($r[$side.'TeamClubId']??null)!==self::CLUB) throw new \RuntimeException('Lag og klubb stemmer ikke.');
            }
            foreach (['Cancelled','Postponed','Interrupted','WalkOverHome','WalkOverAway','WalkOverBoth','FinalResultApprovedByDistrict','FinalResultApprovedByReferee'] as $key) {
                if (!is_bool($r[$key]??null)) throw new \RuntimeException('Kampen mangler entydig status.');
            }
            $m=Fotballdata::matchRow($r);
            if (!is_string($r['TournamentName']??null) || trim($r['TournamentName'])==='') throw new \RuntimeException('Turneringsnavn mangler.');
            $m['competition']=['id'=>$m['competition_id'],'name'=>trim($r['TournamentName'])];
            $m['cancelled']=$r['Cancelled']; $m['postponed']=$r['Postponed']; $m['interrupted']=$r['Interrupted'];
            $m['walkover']=$r['WalkOverHome']||$r['WalkOverAway']||$r['WalkOverBoth'];
            $m['finished_confirmed']=($r['FinalResultApprovedByDistrict']||$r['FinalResultApprovedByReferee']) && $m['score']!==null && strtotime($m['kickoff'])<=$now && !self::blocked($m);
            if (!$m['finished_confirmed']) $m['score']=null;
            foreach (['home','away'] as $side) if (isset($teams[$m[$side]['id']])) $m[$side]['registered_name']=$teams[$m[$side]['id']]['name'];
            $m['events']=[]; // No invented scorers or substitutions from a result-only feed.
            if (isset($out[$m['id']]) && $out[$m['id']]!==$m) throw new \RuntimeException('Motstridende kampkopier.');
            $out[$m['id']]=$m;
        }
        uasort($out,static fn($a,$b)=>strcmp($a['kickoff'],$b['kickoff']) ?: $a['id']<=>$b['id']);
        return $out;
    }
    public static function blocked(array $m): bool { return $m['cancelled']||$m['postponed']||$m['interrupted']||$m['walkover']; }
    public static function collect(): array {
        $teams=self::teams(Fotballdata::clubTeams());
        return ['teams'=>$teams,'matches'=>self::matches(Fotballdata::clubMatches(),$teams,time()),'fetched_at'=>gmdate(DATE_ATOM),'provider'=>'Fotballdata'];
    }
    /** Result feed and exact NFF match detail must agree before writing. */
    public static function detailFacts(array $match): array {
        Fotballdata::verify($match,self::CLUB);
        $r=wp_safe_remote_get($match['source'],['timeout'=>20,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'text/html']]);
        if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)
            throw new \RuntimeException('Kampdetaljene kunne ikke hentes. Ingen ny artikkel er skrevet.');
        $html=wp_remote_retrieve_body($r);
        if(!is_string($html)||$html===''||strlen($html)>=2500000)throw new \RuntimeException('Kampdetaljene er tomme eller avkortet.');
        $facts=self::normalizeDetails($match,$html,gmdate(DATE_ATOM));
        foreach(['home','away'] as $side) {
            try {
                $history=Fotballdata::history($facts['match'][$side]['id']);
                $facts['forms'][$side]=Facts::form($history['rows'],$facts['match'],$facts['match'][$side]['id']);
                $facts['sources'][]=['url'=>$history['url'],'provider'=>$history['provider'],'fetched_at'=>$history['fetched_at'],
                    'team_id'=>$facts['match'][$side]['id'],'purpose'=>'team_history'];
            } catch(\Throwable $e) {
                $facts['warnings'][]='Historikk for '.$facts['match'][$side]['name'].' kunne ikke bekreftes. Utelat form og rekker for dette laget.';
            }
        }
        $facts['angles']=Facts::angles($facts['match'],$facts['forms']);
        return $facts;
    }
    public static function normalizeDetails(array $feed,string $html,string $at): array {
        $m=Facts::match($html,(int)$feed['id']);
        if(empty($feed['finished_confirmed'])||self::blocked($feed)||$m['score']===null
            ||$m['score']!==$feed['score']||$m['home']['id']!==$feed['home']['id']||$m['away']['id']!==$feed['away']['id']
            ||$m['competition']['id']!==$feed['competition']['id']||strtotime($m['kickoff'])!==strtotime($feed['kickoff']))
            throw new \RuntimeException('Fotballdata og kampdetaljene er ikke enige. Kontroller kampgrunnlaget.');
        $lineups=Lineups::parse($html,$m);
        foreach(['home','away'] as $side)if(isset($feed[$side]['registered_name']))$m[$side]['registered_name']=$feed[$side]['registered_name'];
        $m=array_replace($feed,$m);$score=[0,0];
        foreach($m['events'] as $event)if(in_array($event['type'],['Spillemål','Straffemål','Selvmål'],true)) {
            $side=$event['side']==='home'?0:1;if($event['type']==='Selvmål')$side=1-$side;$score[$side]++;
        }
        $complete=$score===$m['score'];
        $warnings=$lineups['warnings'];
        if(!$complete)$warnings[]='Kamphendelsene dekker ikke hele sluttresultatet. Bare en begrenset resultatnotis kan skrives; ikke utled kampforløp.';
        return ['match'=>$m,'finished_confirmed'=>true,'forms'=>['home'=>null,'away'=>null],'lineups'=>$lineups,
            'angles'=>Facts::angles($m,[]),'warnings'=>$warnings,
            'detail_check'=>['version'=>1,'match_id'=>$m['id'],'checked_at'=>$at,'events_complete'=>$complete],
            'sources'=>[['url'=>$m['source'],'provider'=>'NFF','fetched_at'=>$at],['url'=>'https://www.fotballdata.no/','provider'=>'Fotballdata','fetched_at'=>$at]],'created_at'=>$at];
    }
    public static function sameFeed(array $snapshot,array $feed): bool {
        foreach($feed as $key=>$value) { if($key==='events')continue;if(($snapshot[$key]??null)!==$value)return false; }
        return true;
    }
    public static function nextSunday(int $now): int {
        $local=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Europe/Oslo'));
        $sunday=$local->modify('sunday this week')->setTime(18,0);
        if ($sunday->getTimestamp()<=$now) $sunday=$sunday->modify('+1 week');
        return $sunday->getTimestamp();
    }
    public static function weekForSunday(int $due): array {
        $start=(new \DateTimeImmutable('@'.$due))->setTimezone(new \DateTimeZone('Europe/Oslo'))->modify('next monday')->setTime(0,0);
        return ['start'=>$start->format(DATE_ATOM),'end'=>$start->modify('+1 week')->format(DATE_ATOM),'key'=>$start->format('Y-m-d')];
    }
    public static function weekMatches(array $matches,array $week): array {
        return array_values(array_filter($matches,static fn($m)=>!self::blocked($m) && strtotime($m['kickoff'])>=strtotime($week['start']) && strtotime($m['kickoff'])<strtotime($week['end'])));
    }
}
