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

