<?php
namespace RadioRubben\Fotballrobot;

/** Public NFF HTML adapter. Unknown values stay null; never infer a club from a team. */
final class PlayerFacts {
    public static function id(string $input): int {
        $input=trim($input);
        if (!ctype_digit($input)) {
            $u=parse_url($input);
            if (!$u || !in_array(strtolower($u['host']??''),['fotball.no','www.fotball.no'],true) || !in_array($u['scheme']??'',['http','https'],true) || rtrim($u['path']??'','/')!=='/fotballdata/person/profil' || isset($u['user']) || isset($u['port'])) throw new \RuntimeException('Bruk en spillerprofil fra Fotball.no eller en numerisk FIKS-ID.');
            parse_str($u['query']??'',$q); $input=$q['fiksId']??'';
        }
        if (!is_string($input) || !ctype_digit($input) || (int)$input<1 || strlen($input)>9) throw new \RuntimeException('Ugyldig FIKS-ID.');
        return (int)$input;
    }
    public static function url(int $id): string { return 'https://www.fotball.no/fotballdata/person/profil/?fiksId='.$id; }
    public static function profile(string $html,int $id): array {
        $x=Facts::dom($html);
        $name=Facts::text($x->query('//h1['.Facts::cls('personName').']')->item(0));
        $table=$x->query('//table[thead/tr/th[@title="Sesong"]]')->item(0);
        if (!$name || !$table) throw new \RuntimeException('Offentlig spillerprofil eller sesongtabell mangler. Tidligere data beholdes.');
        $clubs=[];
        foreach($x->query('//*['.Facts::cls('a_roleCard').'][normalize-space(*['.Facts::cls('roleName').'])="Spiller"]//a[contains(@href,"/klubb/hjem/")]') as $a) if($cid=Facts::id($a)) $clubs[$cid]=['id'=>$cid,'name'=>Facts::text($a)];
        ksort($clubs); $stats=[];
        foreach($x->query('.//tr[td]',$table) as $tr) {
            $td=$x->query('./td',$tr); $links=$x->query('.//a[@data-stat-type]',$tr); $a=$links->item(0);
            if($td->length!==8 || !$a) throw new \RuntimeException('Ukjent format i sesongstatistikken.');
            // Older zero-appearance seasons can contain only a yellow/red-card link.
            // Every statistical link must identify the same player, team and season.
            foreach($links as $link) {
                if((int)$link->getAttribute('data-fiksid')!==$id || !in_array($link->getAttribute('data-stat-type'),['any','goal','yellowcards','redcards'],true)
                    || $link->getAttribute('data-team-id')!==$a->getAttribute('data-team-id')
                    || $link->getAttribute('data-season-id')!==$a->getAttribute('data-season-id')
                    || strtolower($link->getAttribute('data-is-national-stats'))==='true') throw new \RuntimeException('Motstridende spiller-, lag- eller sesong-ID i statistikken.');
            }
            $year=Facts::text($td->item(0)); $team=(int)$a->getAttribute('data-team-id'); $season=(int)$a->getAttribute('data-season-id');
            if(!preg_match('/^20[0-9]{2}$/',$year)||!$team||!$season) throw new \RuntimeException('Sesong eller lag-ID mangler.');
            $r=['year'=>(int)$year,'team_id'=>$team,'season_id'=>$season,'team'=>Facts::text($td->item(1))];
            foreach(['appearances'=>3,'goals'=>4,'yellow'=>6,'red'=>7] as $key=>$col) { $v=Facts::text($td->item($col)); $r[$key]=ctype_digit($v)?(int)$v:null; }
            $key=$year.':'.$team;
            if(isset($stats[$key]) && $stats[$key]!==$r) throw new \RuntimeException('Motstridende sesongrader.');
            $stats[$key]=$r;
        }
        ksort($stats);
        return ['name'=>$name,'clubs'=>$clubs?:null,'stats'=>$stats];
    }
    public static function matches(string $html): array {
        $x=Facts::dom($html); $table=$x->query('//table[@data-component-name="A_PersonStatTable"]')->item(0);
        if(!$table) throw new \RuntimeException('Spillerens kampliste mangler eller har endret format.');
        $ids=[]; foreach($x->query('.//a[contains(@href,"/fotballdata/kamp/")]',$table) as $a) if($id=Facts::id($a)) $ids[$id]=$ids[$id]??['id'=>$id,'label'=>Facts::text($a)];
        return $ids;
    }
    public static function participation(string $html,int $match,int $player): ?array {
        $x=Facts::dom($html); $nodes=[];
        foreach($x->query('//a['.Facts::cls('playerName').']') as $a) if(Facts::id($a)===$player) $nodes[]=$a;
        if(count($nodes)!==1) return null;
        $a=$nodes[0]; $list=$x->query('ancestor::*['.Facts::cls('a_matchPlayerList').'][1]',$a)->item(0);
        $heading=$list?Facts::text($x->query('preceding-sibling::h4[1]',$list)->item(0)):'';
        $role=str_contains($heading,'Startoppstilling')?'starter':(str_contains($heading,'Innbyttere')?'bench':null);
        $container=$x->query('ancestor::*['.Facts::cls('a_playerWithEvents').'][1]',$a)->item(0);
        if(!$container) return null;
        if(str_contains(Facts::text($container),'strøket')) $role='withdrawn';
        $title=Facts::text($x->query('//title')->item(0));
        if(!preg_match('/^(.*?) - (\d{2}\.\d{2}\.\d{4}) (\d{2}:\d{2}) - /u',$title,$parts) || !($kickoff=Facts::date($parts[2].' '.$parts[3]))) throw new \RuntimeException('Kampdato mangler.');
        $events=[];
        // Exact card/timeline IDs only. NFF may omit the outgoing sub icon from the player card.
        $eventIds=[];
        foreach($x->query('.//a[starts-with(@href,"#")]',$container) as $link) {
            $eid=substr($link->getAttribute('href'),1);
            if(ctype_digit($eid)) $eventIds[$eid]=$eid;
        }
        foreach($x->query('//*[@data-tab="kamphendelser"]//a[contains(@href,"/person/profil/")]') as $link) if(Facts::id($link)===$player) {
            $eventRow=$x->query('ancestor::*['.Facts::cls('timelineEventLine').'][1]',$link)->item(0);
            $eid=$eventRow?$eventRow->getAttribute('id'):'';
            if(ctype_digit($eid)) $eventIds[$eid]=$eid;
        }
        foreach($eventIds as $eid) {
            $row=$x->query('//*[@data-tab="kamphendelser"]//*[@id="'.$eid.'"]')->item(0);
            if(!$row) continue;
            $content=$x->query('.//*['.Facts::cls('timelineEventContent').']',$row)->item(0);
            if(!$content) continue;
            $minute=trim(str_replace(["'",'′'],'',Facts::text($x->query('.//*['.Facts::cls('timelineMinute').']',$row)->item(0))));
            $type=Facts::text($x->query('./div',$content)->item(0));
            foreach($x->query('./div',$content) as $part) foreach($x->query('.//a[contains(@href,"/person/profil/")]',$part) as $person) if(Facts::id($person)===$player) {
                $text=Facts::text($part);
                if(str_starts_with($text,'Inn:')) $type='Innbytte';
                elseif(str_starts_with($text,'Ut:')) $type='Utbytte';
            }
            $events[$eid]=['id'=>$eid,'minute'=>$minute,'type'=>$type,'name'=>Facts::text($a)];
        }
        ksort($events);
        return ['id'=>$match,'label'=>$parts[1],'kickoff'=>$kickoff,'role'=>$role,'events'=>$events,'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$match];
    }
    public static function diff(?array $old,array $new): array {
        if($old===null) return [];
        $out=[];
        $add=static function($kind,$key,$before,$after) use (&$out) { $out[]=['kind'=>$kind,'key'=>(string)$key,'before'=>$before,'after'=>$after]; };
        if($old['clubs']!==null && $new['clubs']!==null && $old['clubs']!==$new['clubs']) $add('club','club',$old['clubs'],$new['clubs']);
        foreach($new['stats'] as $key=>$row) {
            $prev=$old['stats'][$key]??null;
            if($prev!==$row) $add('statistics',$key,$prev,$row);
            foreach(['goals'=>'goals','yellow'=>'cards','red'=>'cards'] as $field=>$kind) if($prev!==null && $prev[$field]!==null && $row[$field]!==null && $prev[$field]!==$row[$field]) $add($kind,$key.':'.$field,$prev[$field],$row[$field]);
        }
        foreach($new['matches'] as $id=>$row) {
            $prev=$old['matches'][$id]??null;
            if($prev===null) $add('match',$id,null,$row);
            if(($row['role']??null)!==null && ($row['role']!==($prev['role']??null))) $add('lineup',$id,$prev['role']??null,$row['role']);
            foreach($row['events']??[] as $eid=>$event) {
                $before=$prev['events'][$eid]??null;
                if($before===$event) continue;
                $kind=in_array($event['type'],['Spillemål','Straffemål','Selvmål'],true)?'goals':(in_array($event['type'],['Advarsel','Utvisning'],true)?'cards':(in_array($event['type'],['Innbytte','Utbytte'],true)?'lineup':null));
                if($kind) $add($kind,$id.':'.$eid,$before,$event);
            }
        }
        return $out;
    }
}
