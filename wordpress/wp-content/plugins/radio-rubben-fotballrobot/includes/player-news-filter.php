<?php
namespace RadioRubben\Fotballrobot;

/** Deterministic editorial selection. No network, state writes, model calls or mail. */
final class PlayerNewsFilter {
    const VERSION = '2026-10-04.1';
    const MAX_MATCH_AGE = 172800; // 48 hours; discovery time never makes an old match new.
    const MAX_SOURCE_AGE = 21600;
    const REASONS = [
        'routine'=>'Vanlig troppsregistrering, bytte eller gult kort – kun historikk.',
        'statistics'=>'Statistikk eller klubbregister er endret – ingen bekreftet ny kamphendelse.',
        'correction'=>'Eksisterende hendelse er rettet eller omdøpt – kun historikk.',
        'identity'=>'Kamp eller spiller kan ikke knyttes entydig til kilden.',
        'date'=>'Kampdato mangler eller er ugyldig.',
        'old'=>'Kampen er eldre enn 48 timer – ingen fersk nyhet.',
        'future'=>'Kampen har ikke startet.',
        'context'=>'Venter på bekreftet kampslutt, lag, turnering og resultat.',
        'stale'=>'Kampgrunnlaget må oppdateres før skriving.',
        'superseded'=>'Opplysningen finnes ikke uendret i siste kampgrunnlag.',
    ];
    private static function timestamp($value): ?int {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$value))return null;
        try{$d=new \DateTimeImmutable($value);$errors=\DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))return null;return $d->getTimestamp();}catch(\Throwable $e){return null;}
    }
    private static function type(string $type): string {
        $type=trim(preg_replace('/\s+/u',' ',$type));
        if(str_starts_with($type,'Inn:'))return 'Innbytte';
        if(str_starts_with($type,'Ut:'))return 'Utbytte';
        return $type;
    }
    private static function same(array $a,array $b): bool {
        foreach(['id','minute','name'] as $key)if((string)($a[$key]??'')!==(string)($b[$key]??''))return false;
        return self::type((string)($a['type']??''))===self::type((string)($b['type']??''));
    }
    private static function context(array $m,int $id,int $now): ?string {
        $start=self::timestamp($m['kickoff']??null);
        if($start===null)return 'date';
        if($start>$now)return 'future';
        if($start<$now-self::MAX_MATCH_AGE)return 'old';
        $n=$m['news_context']??[];
        if(($n['id']??0)!==$id || ($n['kickoff']??null)!==($m['kickoff']??null) || ($n['source']??'')!==($m['source']??''))return 'context';
        if(($n['finished']??false)!==true || empty($n['home']['id']) || empty($n['away']['id']) || $n['home']['id']===$n['away']['id'] || empty($n['home']['name']) || empty($n['away']['name']) || empty($n['competition']['id']) || empty($n['competition']['name']))return 'context';
        $score=$n['score']??null;
        if(!is_array($score)||array_keys($score)!==[0,1]||!is_int($score[0])||!is_int($score[1])||min($score)<0)return 'context';
        $checked=self::timestamp($n['checked_at']??null);
        if($checked===null||$checked>$now+300||$checked<$now-self::MAX_SOURCE_AGE)return 'stale';
        return null;
    }
    public static function select(array $player,array $events,?int $now=null): array {
        $now??=time();$groups=[];$counts=[];
        $reject=static function(string $reason)use(&$counts){$counts[$reason]=($counts[$reason]??0)+1;};
        foreach($events as $e){
            $after=$e['after']??null;$before=$e['before']??null;
            if(in_array($e['kind']??'', ['statistics','club'],true)||!is_array($after)||empty($after['type'])){$reject('statistics');continue;}
            if($before!==null){$reject('correction');continue;}
            if(!preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/D',(string)($e['key']??''),$ids)||($e['fiks_id']??0)!==($player['fiks_id']??null)||($e['fiks_id']??0)<1){$reject('identity');continue;}
            $mid=(int)$ids[1];$eid=$ids[2];$m=$player['snapshot']['matches'][$mid]??[];
            $url='https://www.fotball.no/fotballdata/kamp/?fiksId='.$mid;
            if((isset($e['match_id'])&&(int)$e['match_id']!==$mid)||($e['source']??'')!==$url||($m['source']??'')!==$url||(int)($m['id']??0)!==$mid||(string)($after['id']??'')!==$eid){$reject('identity');continue;}
            if(($why=self::context($m,$mid,$now))!==null){$reject($why);continue;}
            $seen=self::timestamp($e['detected_at']??null);
            if($seen===null||$seen>$now+300){$reject('date');continue;}
            $latest=$m['events'][$eid]??null;
            if(!is_array($latest)||!self::same($after,$latest)||empty($latest['name'])||!preg_match('/^\d{1,3}(?:\+\d{1,2})?$/D',(string)($latest['minute']??''))){$reject('superseded');continue;}
            $type=self::type((string)$latest['type']);
            $significant=in_array($type,['Spillemål','Straffemål','Selvmål','Utvisning'],true);
            if(!$significant&&!in_array($type,['Innbytte','Utbytte','Advarsel'],true)){$reject('routine');continue;}
            if(!isset($groups[$mid]))$groups[$mid]=['match'=>$m['news_context'],'events'=>[],'significant'=>false,'at'=>$e['detected_at']];
            $groups[$mid]['events'][$eid]=$e;
            $groups[$mid]['significant']=$groups[$mid]['significant']||$significant;
            if($seen<strtotime($groups[$mid]['at']))$groups[$mid]['at']=$e['detected_at'];
        }
        $proposals=[];
        foreach($groups as $mid=>$g){
            if(!$g['significant']){foreach($g['events'] as $e)$reject('routine');continue;}
            $events=array_values($g['events']);
            usort($events,static fn($a,$b)=>strnatcmp((string)$a['after']['minute'],(string)$b['after']['minute']));
            $proposals[$mid]=['at'=>$g['at'],'facts'=>['type'=>'events','name'=>$player['name']??'','fiks_id'=>$player['fiks_id'],'match'=>$g['match'],'events'=>$events,'source'=>$g['match']['source'],'fetched_at'=>$g['match']['checked_at'],'news_filter_version'=>self::VERSION]];
        }
        ksort($counts);
        return ['version'=>self::VERSION,'proposals'=>$proposals,'excluded_counts'=>$counts,'reasons'=>array_intersect_key(self::REASONS,$counts)];
    }
}
