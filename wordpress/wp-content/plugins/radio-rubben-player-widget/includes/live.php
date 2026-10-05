<?php
namespace RadioRubben\PlayerWidget;

/** Match-driven polling. Rendering and the public endpoint never fetch upstream data. */
final class Live {
    private static function cls(string $c): string { return "contains(concat(' ',normalize-space(@class),' '),' $c ')"; }
    private static function person($node): int {
        parse_str(parse_url($node->getAttribute('href'),PHP_URL_QUERY)?:'', $q);
        return (int)($q['fiksId']??0);
    }
    public static function status(string $html,array $match,array $players,int $now): array {
        $roles=Sources::lineup($html,$match,$players); // Exact team IDs and kickoff, never names.
        $x=Sources::dom($html); $phase='unknown'; $events=[]; $evidence=false; $cancelled=false; $finished=false;
        foreach($x->query('//*[@data-tab="kamphendelser"]//*['.self::cls('timelineEventLine').']') as $row) {
            $content=$x->query('.//*['.self::cls('timelineEventContent').']',$row)->item(0);
            if(!$content) continue;
            $text=Sources::text($content);
            if(str_contains($text,'Kampen er slutt')) $finished=true;
            if(preg_match('/Kampen er (?:avlyst|avbrutt|utsatt)/u',$text)) $cancelled=true;
            $minute=Sources::text($x->query('.//*['.self::cls('timelineMinute').']',$row)->item(0));
            if(!preg_match('/^(\d{1,3})(?:\+(\d{1,2}))?[\x{0027}\x{2032}]?$/u',$minute,$m)) continue;
            $order=(int)$m[1]*100+(int)($m[2]??0);
            if($order>0) $evidence=true;
            foreach($x->query('./div',$content) as $part) {
                $label=Sources::text($part);
                $kind=str_starts_with($label,'Inn:')?'in':(str_starts_with($label,'Ut:')?'out':null);
                if(!$kind) continue;
                foreach($x->query('.//a[contains(@href,"/person/profil/")]',$part) as $a) $events[]=['player'=>self::person($a),'kind'=>$kind,'order'=>$order];
            }
            if(preg_match('/(?:Utvisning|Andre gule kort)/u',$text)) foreach($x->query('.//a[contains(@href,"/person/profil/")]',$content) as $a) $events[]=['player'=>self::person($a),'kind'=>'red','order'=>$order];
        }
        $card=$x->query('(//*['.self::cls('a_matchCard').'])[1]')->item(0);
        if($card && preg_match('/\b(?:Avlyst|Utsatt|Avbrutt)\b/u',Sources::text($card))) $cancelled=true;
        $start=strtotime($match['kickoff']);
        if($cancelled) $phase='cancelled';
        elseif($finished) $phase='finished';
        elseif($now<$start) $phase='scheduled';
        elseif($now<$start+10800 && $evidence) $phase='live';
        usort($events,static fn($a,$b)=>$a['order']<=>$b['order']);
        if($phase==='live') {
            foreach($roles as &$role) if($role==='starter') $role='playing'; unset($role);
            foreach($events as $event) if(array_key_exists($event['player'],$roles) && $roles[$event['player']]!==null) {
                $roles[$event['player']]=['in'=>'playing','out'=>'off','red'=>'red'][$event['kind']];
            }
        }
        if(in_array($phase,['finished','cancelled'],true)) $roles=array_fill_keys($players,null);
        return ['phase'=>$phase,'roles'=>$roles,'score'=>self::score($x,$match,$phase)];
    }
    /** Only the current match card, never historical head-to-head cards or goal counts. */
    private static function score(\DOMXPath $x,array $match,string $phase): ?array {
        if(in_array($phase,['scheduled','cancelled'],true)) return null;
        $urls=$x->query('//meta[@property="og:url"]/@content');
        if($urls->length!==1) return null;
        parse_str(parse_url($urls->item(0)->nodeValue,PHP_URL_QUERY)?:'', $query);
        if((string)($query['fiksId']??'')!==(string)$match['id']) return null;
        foreach(['endResult'=>'current','halfTime'=>'halftime'] as $class=>$kind) {
            $nodes=$x->query('(//*['.self::cls('a_matchCard').'])[1]//*['.self::cls('result').']/*['.self::cls($class).']');
            if(!$nodes->length) continue;
            if($nodes->length!==1) return null;
            $text=Sources::text($nodes->item(0));
            $pattern=$kind==='halftime'?'/^\((\d{1,2})\s*[-–]\s*(\d{1,2})\)$/u':'/^(\d{1,2})\s*[-–]\s*(\d{1,2})$/u';
            if(!preg_match($pattern,$text,$m)) return null;
            return ['home'=>(int)$m[1],'away'=>(int)$m[2],'kind'=>$kind==='current' && $phase==='finished'?'final':$kind];
        }
        return null;
    }
    /** Public allowlist: selected fixtures only. No private profile/editorial data. */
    public static function publicMatches(): array {
        $s=Service::settings(); if(!$s['enabled']) return [];
        $now=time(); $cache=Service::cache(); $out=[];
        foreach(Service::candidates($s,Service::profiles(),$cache,$now) as $id=>$c) {
            $m=$c['match']; $start=strtotime($m['kickoff']);
            if($start>$now+4500 || $start<$now-14400) continue;
            $r=$cache['matches'][$id]??[];
            if(($r['match']??null)!==$m) continue;
            $checked=(int)($r['lineup_checked_at']??0);
            $ttl=in_array($r['phase']??'', ['finished','cancelled'],true)?900:150;
            $fresh=$checked>$now-$ttl;
            $out[]=['id'=>(int)$id,'home'=>$m['home'],'away'=>$m['away'],'kickoff'=>$m['kickoff'],
                'phase'=>$fresh?($r['phase']??'unknown'):'unknown','score'=>$fresh?($r['score']??null):null,
                'checked_at'=>$checked,'expires'=>$checked+$ttl];
        }
        return $out;
    }
    public static function interval(array $m,array $record,int $now): int {
        $start=strtotime($m['kickoff']);
        if(in_array($record['phase']??'', ['finished','cancelled'],true)) return $now-($record['finished_at']??$now)<1800?300:3600;
        if($start<=$now+4500 && $start>=$now-10800) return 60;
        return $start<$now?900:21600;
    }
    public static function tick(): void {
        $settings=Service::settings(); if(!$settings['enabled']) return;
        $token=wp_generate_uuid4(); $now=time();
        // A crashed read-only fetch cannot leave the widget permanently locked.
        $old=get_option('rrpw_refresh_lock',[]);
        if($old && ($old['at']??$now)<$now-180) delete_option('rrpw_refresh_lock');
        if(!add_option('rrpw_refresh_lock',['token'=>$token,'at'=>$now],'',false)) return;
        try {
            $cache=Service::cache(); $profiles=Service::profiles(); $teamIds=[];
            foreach(Service::selected($settings,$profiles) as $p) foreach($p['selected_teams'] as $id) $teamIds[$id]=$id;
            // Recheck nearby schedules every ten minutes, all others hourly. Oldest due first.
            $due=[];
            foreach($teamIds as $id) {
                $interval=3600;
                foreach($cache['teams'][$id]['matches']??[] as $m) if(abs(strtotime($m['kickoff'])-$now)<7200) $interval=600;
                $last=$cache['teams'][$id]['attempted_at']??0;
                if($last<=$now-$interval) $due[$id]=$last+$interval;
            }
            asort($due); $began=microtime(true);
            foreach(array_slice(array_keys($due),0,2) as $id) {
                try { $cache['teams'][$id]=Sources::team(Sources::fetch('team',$id),$id)+['checked_at'=>time(),'error'=>null]; }
                catch(\Throwable $e) { $cache['teams'][$id]['error']=$e->getMessage(); }
                $cache['teams'][$id]['attempted_at']=time();
            }
            $all=Service::candidates($settings,$profiles,$cache,$now);
            uasort($all,static fn($a,$b)=>strtotime($a['match']['kickoff'])<=>strtotime($b['match']['kickoff']));
            $queue=[]; $seen=[];
            foreach($all as $id=>$c) {
                $record=$cache['matches'][$id]??[]; $start=strtotime($c['match']['kickoff']);
                $near=$start<=$now+4500 && $start>=$now-21600;
                $nearest=false;
                if(!in_array($record['phase']??'', ['finished','cancelled'],true)) foreach($c['players'] as $pid=>$p) if(!isset($seen[$pid])) {$seen[$pid]=true;$nearest=true;}
                if(!$near && !$nearest) continue;
                if(($record['match']??null)!==$c['match']) $record=[];
                $interval=self::interval($c['match'],$record,$now);
                if(($record['attempted_at']??0)+$interval<=$now) $queue[$id]=['candidate'=>$c,'priority'=>$near?0:1,'due'=>($record['attempted_at']??0)+$interval];
            }
            uasort($queue,static fn($a,$b)=>($a['priority']<=>$b['priority'])?:($a['due']<=>$b['due']));
            $requests=0;
            foreach($queue as $id=>$job) {
                if(microtime(true)-$began>40 || $requests>=12) break;
                $c=$job['candidate']; $r=$cache['matches'][$id]??[];
                if(($r['match']??null)!==$c['match']) $r=[];
                $r+=['match'=>$c['match'],'stream'=>null,'roles'=>[],'stream_checked_at'=>0,'lineup_checked_at'=>0];
                $r['attempted_at']=time(); $r['errors']=[];
                try {
                    $requests++; $html=Sources::fetch('match',$id); $state=self::status($html,$c['match'],array_keys($c['players']),time());
                    $r=array_replace($r,$state); $r['lineup_checked_at']=time();
                    if(in_array($state['phase'],['finished','cancelled'],true)) $r['finished_at']=$r['finished_at']??time();
                    // The owning robot records exact player-ID events; no new HTTP request per player.
                    if(class_exists('RadioRubben\\Fotballrobot\\Players') && method_exists('RadioRubben\\Fotballrobot\\Players','observeMatch')) \RadioRubben\Fotballrobot\Players::observeMatch($id,$html,array_keys($c['players']),$state['phase']==='finished');
                } catch(\Throwable $e) { $r['errors'][]=$e->getMessage(); $r['roles']=[]; $r['lineup_checked_at']=0; }
                $cache['matches'][$id]=$r;
            }
            // Streams change slowly. They never compete with minute-by-minute NFF status.
            foreach($all as $id=>$c) {
                if(microtime(true)-$began>40) break;
                $r=$cache['matches'][$id]??null;
                if(!$r || ($r['match']??null)!==$c['match'] || in_array($r['phase']??'', ['finished','cancelled'],true) || ($r['stream_attempted_at']??0)>$now-1800) continue;
                $r['stream_attempted_at']=time();
                try { $r['stream']=Sources::mygame(Sources::fetch('stream',$id),$c['match']);$r['stream_checked_at']=time(); }
                catch(\Throwable $e) { $r['errors'][]='MyGame: '.$e->getMessage(); $r['stream']=null; $r['stream_checked_at']=0; }
                $cache['matches'][$id]=$r; break;
            }
            $cache['teams']=array_intersect_key($cache['teams'],$teamIds);
            $cache['matches']=array_intersect_key($cache['matches'],$all);
            $cache['last_run']=time(); $cache['due_matches']=count($queue); $cache['match_requests']=$requests;
            if(Service::settings()['revision']===$settings['revision']) update_option(Service::CACHE,$cache,false);
        } finally { if((get_option('rrpw_refresh_lock',[])['token']??'')===$token) delete_option('rrpw_refresh_lock'); }
    }
    public static function routes(): void {
        register_rest_route('rr-player-widget/v1','/widget',['methods'=>'GET','permission_callback'=>'__return_true','args'=>['player'=>['default'=>0,'sanitize_callback'=>'absint']],'callback'=>static function($r) {
            $response=new \WP_REST_Response(['html'=>Compact::render((int)$r['player']),'matches'=>self::publicMatches(),'updated_at'=>Service::cache()['last_run']??0]);
            $response->header('Cache-Control','no-store, max-age=0'); return $response;
        }]);
    }
}
