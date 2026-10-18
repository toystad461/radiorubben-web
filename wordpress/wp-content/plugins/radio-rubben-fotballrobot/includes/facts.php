<?php
namespace RadioRubben\Fotballrobot;

final class Facts {
    public static function text($node): string { return $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent)) : ''; }
    public static function cls(string $name): string { return "contains(concat(' ',normalize-space(@class),' '),' $name ')"; }
    public static function dom(string $html): \DOMXPath {
        if (!class_exists('DOMDocument')) throw new \RuntimeException('Serveren mangler DOM-støtte.');
        $doc = new \DOMDocument(); $old = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($old);
        if (!$ok) throw new \RuntimeException('Kilden kunne ikke leses.');
        return new \DOMXPath($doc);
    }
    public static function id($node): int {
        if (!$node) return 0;
        parse_str(parse_url($node->getAttribute('href'), PHP_URL_QUERY) ?: '', $q);
        return ctype_digit((string)($q['fiksId'] ?? '')) ? (int)$q['fiksId'] : 0;
    }
    public static function score(string $text): ?array {
        return preg_match('/^\(?([0-9]{1,2})\s*[-–]\s*([0-9]{1,2})\)?$/u', trim($text), $m) ? [(int)$m[1],(int)$m[2]] : null;
    }
    public static function date(string $text): ?string {
        $d = \DateTimeImmutable::createFromFormat('!d.m.Y H:i', $text, new \DateTimeZone('Europe/Oslo'));
        return $d && $d->format('d.m.Y H:i') === $text ? $d->format(DATE_ATOM) : null;
    }
    public static function match(string $html, int $id): array {
        $x = self::dom($html); $card = $x->query('//*['.self::cls('a_matchCard').']')->item(0);
        if (!$card) throw new \RuntimeException('Fant ikke kampkortet hos NFF.');
        $teams = $x->query('.//*['.self::cls('teamName').']/a', $card);
        if ($teams->length !== 2) throw new \RuntimeException('Kunne ikke bekrefte begge lag-ID-ene.');
        $home = ['id'=>self::id($teams->item(0)), 'name'=>self::text($teams->item(0))];
        $away = ['id'=>self::id($teams->item(1)), 'name'=>self::text($teams->item(1))];
        if (!$home['id'] || !$away['id'] || $home['id']===$away['id']) throw new \RuntimeException('Ugyldige lag.');
        $title = self::text($x->query('//title')->item(0));
        if (!preg_match('/(\d{2}\.\d{2}\.\d{4}) (\d{2}:\d{2})/', $title, $m) || !($kickoff=self::date($m[1].' '.$m[2]))) throw new \RuntimeException('Ukjent kampdato.');
        $competition = $x->query('//a[contains(@href,"/fotballdata/turnering/hjem/")]')->item(0);
        if (!self::id($competition)) throw new \RuntimeException('Turneringen kunne ikke bekreftes.');
        $score=self::score(self::text($x->query('.//*['.self::cls('endResult').']',$card)->item(0)));
        $half=self::score(self::text($x->query('.//*['.self::cls('halfTime').']',$card)->item(0)));
        $events=[];
        foreach ($x->query('//*[@data-tab="kamphendelser"]//*['.self::cls('timelineEventLine').']') as $row) {
            $side=strpos(' '.$row->getAttribute('class').' ',' homeTeam ')!==false?'home':(strpos(' '.$row->getAttribute('class').' ',' awayTeam ')!==false?'away':null);
            $minute=trim(str_replace(["'",'′'],'',self::text($x->query('.//*['.self::cls('timelineMinute').']',$row)->item(0))));
            $content=$x->query('.//*['.self::cls('timelineEventContent').']',$row)->item(0);
            if (!$side || !$content || !preg_match('/^(\d{1,3})(?:\+(\d{1,2}))?$/',$minute,$mm)) continue;
            $label=self::text($x->query('./div',$content)->item(0));
            $name=self::text($x->query('.//*['.self::cls('eventHeading').']',$content)->item(0));
            $key=$row->getAttribute('id') ?: hash('sha256',$side.$minute.$label.$name);
            $events[$key]=['id'=>$key,'side'=>$side,'minute'=>$minute,'order'=>(int)$mm[1]*100+(int)($mm[2]??0),'name'=>$name,'type'=>$label];
        }
        $events=array_values($events); usort($events,fn($a,$b)=>$a['order']<=>$b['order']);
        $referees=[]; foreach($x->query('//*[@data-tab="dommere"]//tbody/tr') as $row) {$td=$x->query('./td',$row);if($td->length>=2 && self::text($td->item(1))!=='')$referees[]=['role'=>self::text($td->item(0)),'name'=>self::text($td->item(1))];}
        return ['referees'=>$referees,'id'=>$id,'home'=>$home,'away'=>$away,'kickoff'=>$kickoff,'competition'=>['id'=>self::id($competition),'name'=>self::text($competition)],'venue'=>self::text($x->query('.//*['.self::cls('footerElement').']',$card)->item(0)),'score'=>$score,'half_time'=>$half,'events'=>$events,'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$id];
    }
    public static function history(string $html, int $team): array {
        $x=self::dom($html); $tables=$x->query('//*['.self::cls('a_matches').']//table');
        if (!$tables->length) throw new \RuntimeException('NFFs resultatliste mangler eller har endret format.');
        $rows=[];
        foreach ($tables as $table) foreach ($x->query('.//tbody/tr',$table) as $row) {
            $td=$x->query('./td',$row); if ($td->length<8) throw new \RuntimeException('Ukjent kolonneformat i resultatlisten.');
            $link=$x->query('.//a[contains(@href,"/fotballdata/kamp/")]',$td->item(0))->item(0);
            $id=self::id($link); if (!$id) continue;
            $home=$x->query('.//a[contains(@href,"/fotballdata/lag/hjem/")]',$td->item(3))->item(0);
            $away=$x->query('.//a[contains(@href,"/fotballdata/lag/hjem/")]',$td->item(5))->item(0);
            $comp=$x->query('.//a[contains(@href,"/fotballdata/turnering/hjem/")]',$td->item(7))->item(0);
            $date=self::date(self::text($td->item(0)).' '.self::text($td->item(2)));
            if (!in_array($team,[self::id($home),self::id($away)],true)) continue;
            if (!$date || !self::id($comp)) throw new \RuntimeException('Historikken har en kamp med ukjent dato eller turnering.');
            $status=self::text($td->item(4)); $score=self::score($status);
            if (preg_match('/upcoming|cancel|postpon|avlys|utsatt|avbrutt/i',$row->getAttribute('class').' '.$status)) $score=null;
            $r=['id'=>$id,'kickoff'=>$date,'home'=>['id'=>self::id($home),'name'=>self::text($home)],'away'=>['id'=>self::id($away),'name'=>self::text($away)],'score'=>$score,'competition_id'=>self::id($comp),'venue'=>self::text($td->item(6)),'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$id];
            if (isset($rows[$id]) && $rows[$id]!==$r) throw new \RuntimeException('Motstridende resultater for samme kamp.');
            $rows[$id]=$r;
        }
        if (!$rows) throw new \RuntimeException('Ingen kamper kunne knyttes sikkert til laget.');
        return array_values($rows);
    }
    public static function form(array $rows, array $match, int $team): array {
        $before=[]; $next=[]; $seen=[]; $unknown=[];
        foreach ($rows as $r) {
            if (!in_array($team,[$r['home']['id'],$r['away']['id']],true) || $r['competition_id']!==$match['competition']['id'] || substr($r['kickoff'],0,4)!==substr($match['kickoff'],0,4) || $r['id']===$match['id']) continue;
            if(isset($seen[$r['id']])) { if($seen[$r['id']]!==$r) throw new \RuntimeException('Motstridende historikk.'); continue; }
            $seen[$r['id']]=$r;
            if (strtotime($r['kickoff'])>=strtotime($match['kickoff'])) { if ($r['score']===null) $next[]=$r; continue; }
            if ($r['score']===null) { $unknown[]=$r; continue; }
            $gf=$r['score'][$r['home']['id']===$team?0:1]; $ga=$r['score'][$r['home']['id']===$team?1:0];
            $r['result']=$gf>$ga?'V':($gf===$ga?'U':'T'); $r['gf']=$gf; $r['ga']=$ga; $before[]=$r;
        }
        usort($before,fn($a,$b)=>strcmp($b['kickoff'],$a['kickoff'])); usort($next,fn($a,$b)=>strcmp($a['kickoff'],$b['kickoff']));
        // Do not bridge an unresolved match: only use the uninterrupted known suffix.
        $cutoff=$unknown?max(array_map(fn($r)=>strtotime($r['kickoff']),$unknown)):0;
        $suffix=array_values(array_filter($before,fn($r)=>strtotime($r['kickoff'])>$cutoff));
        $five=array_slice($suffix,0,5); $wins=0; $unbeaten=0; $winExact=false; $unbeatenExact=false;
        foreach($suffix as $r) { if($r['result']!=='V') {$winExact=true;break;} $wins++; }
        foreach($suffix as $r) { if($r['result']==='T') {$unbeatenExact=true;break;} $unbeaten++; }
        return ['team_id'=>$team,'last_five'=>$five,'known_count'=>count($before),'unresolved_count'=>count($unknown),'wins'=>$wins,'wins_exact'=>$winExact,'unbeaten'=>$unbeaten,'unbeaten_exact'=>$unbeatenExact,'win_match_ids'=>array_column(array_slice($suffix,0,$wins),'id'),'points_last_five'=>array_sum(array_map(fn($r)=>$r['result']==='V'?3:($r['result']==='U'?1:0),$five)),'next'=>$next[0]??null];
    }
    public static function angles(array $m, array $forms): array {
        if ($m['score']===null) return [];
        $a=[]; $score=[0,0]; $goals=[]; $maxDeficit=[0,0];
        foreach($m['events'] as $e) if(in_array($e['type'],['Spillemål','Straffemål','Selvmål'],true)) {
            $side=$e['side']==='home'?0:1; if($e['type']==='Selvmål') $side=1-$side;
            $score[$side]++; $e['scoring_side']=$side; $e['score']=$score; $goals[]=$e;
            $maxDeficit[0]=max($maxDeficit[0],$score[1]-$score[0]); $maxDeficit[1]=max($maxDeficit[1],$score[0]-$score[1]);
        }
        // Incomplete event feeds must not produce narrative about decisive goals.
        if($score===$m['score'] && $goals) {
            $last=end($goals); $name=trim($last['name']);
            if($score[0]===$score[1] && (int)$last['minute']>=80 && $last['type']!=='Selvmål') $a[]=['id'=>'late_equalizer','title'=>$name.' sikret poeng','reason'=>'Utligning i det '.$last['minute'].'. minutt; hendelsene summerer til sluttresultatet.','event_ids'=>[$last['id']]];
            foreach(['home','away'] as $i=>$side) if($maxDeficit[$i]>=2 && $score[$i]>=$score[1-$i]) $a[]=['id'=>'comeback_'.$side,'title'=>$m[$side]['name'].' hentet inn ledelsen','reason'=>'Lå '.$maxDeficit[$i].' mål under og fikk poeng.','event_ids'=>array_column($goals,'id')];
        }
        $streakSides=['home','away'];
        usort($streakSides,fn($a,$b)=>(($forms[$b]['wins']??0)<=>($forms[$a]['wins']??0)));
        foreach($streakSides as $side) {
            $i=$side==='home'?0:1; $f=$forms[$side]??null;
            if($f && $f['wins']>=3 && $m['score'][$i]<=$m['score'][1-$i]) $a[]=['id'=>'streak_'.$side,'title'=>'Seiersrekken til '.$m[$side]['name'].' tok slutt','reason'=>'Vant '.($f['wins_exact']?'':'minst ').$f['wins'].' kamper på rad før denne kampen.','match_ids'=>$f['win_match_ids']];
        }
        $a[]=['id'=>'result','title'=>$m['home']['name'].' og '.$m['away']['name'].' spilte '.$m['score'][0].'–'.$m['score'][1],'reason'=>'Bekreftet kampresultat.'];
        return array_slice($a,0,3);
    }
}
