<?php
namespace RadioRubben\Fotballrobot;

/** Match-local NFF lineups. Poll caches and reserve lists never prove participation. */
final class Lineups {
    const VERSION = '1.0.0';
    public static function parse(string $html,array $match): array {
        // Bind the roster wrappers to the same verified home/away card as the article.
        $actual=Facts::match($html,(int)$match['id']);
        foreach(['home','away'] as $side) if($actual[$side]!==$match[$side])
            throw new \RuntimeException('Lagoppstillingen tilhører ikke kampens bekreftede lag.');
        $x=Facts::dom($html);
        foreach($x->query('//link[@rel="canonical"]') as $canonical) {
            parse_str(parse_url($canonical->getAttribute('href'),PHP_URL_QUERY)?:'', $q);
            if(isset($q['fiksId']) && (string)$q['fiksId']!==(string)$match['id'])
                throw new \RuntimeException('Kamp-ID i kilden avviker fra valgt kamp.');
        }
        $out=['version'=>self::VERSION,'match_id'=>$match['id'],'source'=>$match['source'],
            'team_ids'=>['home'=>$match['home']['id'],'away'=>$match['away']['id']],
            'status'=>[],'warnings'=>[]];
        foreach(['home'=>'','away'=>'away_'] as $side=>$prefix) {
            $roster=[];$roles=['starters'=>[],'bench'=>[]];$people=[];
            $state=['starters'=>'missing','bench'=>'missing'];
            try {
                $wrappers=$x->query('//*['.Facts::cls($side.'TeamWrapper').']');
                if($wrappers->length>1) throw new \RuntimeException('Flere lagblokker i kilden.');
                if($wrappers->length===1) {
                    $wrapper=$wrappers->item(0);$seen=[];
                    foreach($x->query('.//*['.Facts::cls('a_matchPlayerList').']',$wrapper) as $list) {
                        $heading=Facts::text($x->query('preceding-sibling::h4[1]',$list)->item(0));
                        $role=['Startoppstilling:'=>'starters','Innbyttere:'=>'bench'][$heading]??null;
                        if(!$role || isset($seen[$role])) throw new \RuntimeException('Uklar startoppstilling eller innbytterliste.');
                        $seen[$role]=true;
                        foreach($x->query('.//*['.Facts::cls('playerContent').']',$list) as $player) {
                            $links=$x->query('.//a['.Facts::cls('playerName').']',$player);
                            $a=$links->item(0);$name=Facts::text($a);$pid=Facts::id($a);
                            $number=Facts::text($x->query('.//*['.Facts::cls('playerNumber').']',$player)->item(0));
                            if($links->length!==1 || !$pid || $name==='' || !ctype_digit($number) || (int)$number<1 || (int)$number>999
                                || isset($roster[(int)$number]) || isset($people[$pid]) || preg_match('/strøket/iu',Facts::text($player)))
                                throw new \RuntimeException('Ufullstendig, gjentatt eller strøket spiller i oppstillingen.');
                            $no=(int)$number;$roster[$no]=$name;$people[$pid]=$no;$roles[$role][]=$no;
                        }
                        // Empty lists are unknown, never evidence that no reserves were available.
                        $state[$role]=$roles[$role]?'confirmed':'missing';
                        if($role==='starters' && count($roles[$role])>11) throw new \RuntimeException('For mange startspillere.');
                    }
                }
            } catch(\RuntimeException $e) {
                $roster=[];$roles=['starters'=>[],'bench'=>[]];$people=[];
                $state=['starters'=>'invalid','bench'=>'invalid'];
                $out['warnings'][]=$match[$side]['name'].': '.$e->getMessage();
            }
            $out[$prefix.'roster']=$roster;$out[$prefix.'starters']=$roles['starters'];$out[$prefix.'bench']=$roles['bench'];
            $out[$prefix.'person_ids']=array_flip($people);$out['status'][$side]=$state;
            if($state['starters']!=='confirmed') $out['warnings'][]=$match[$side]['name'].': startoppstillingen er ikke tilgjengelig eller entydig hos NFF.';
            if($state['bench']!=='confirmed') $out['warnings'][]=$match[$side]['name'].': innbytterlisten er ikke tilgjengelig eller entydig hos NFF.';
        }
        return $out;
    }
    /** Use initials only for colliding surnames, across starters AND reserves. */
    private static function labels(array $roster,array $ids): array {
        $parts=[];$counts=[];
        foreach($ids as $id) {
            $name=preg_split('/\s+/u',trim($roster[$id]));$last=array_pop($name);
            $parts[$id]=[$last,$name];$key=mb_strtolower($last,'UTF-8');$counts[$key]=($counts[$key]??0)+1;
        }
        $labels=[];
        foreach($parts as $id=>[$last,$given]) {
            $label=$last;
            if($counts[mb_strtolower($last,'UTF-8')]>1) {
                // Expand the forename prefix if first initials collide too.
                $initials=[];
                foreach($given as $word) {
                    $initials[]=mb_substr($word,0,1,'UTF-8').'.';
                    $prefix=implode(' ',$initials);$same=0;
                    foreach($parts as [$otherLast,$otherGiven]) if(mb_strtolower($otherLast,'UTF-8')===mb_strtolower($last,'UTF-8')) {
                        $other=implode(' ',array_map(static fn($w)=>mb_substr($w,0,1,'UTF-8').'.',array_slice($otherGiven,0,count($initials))));
                        if($other===$prefix)$same++;
                    }
                    if($same===1)break;
                }
                $label=($initials?implode(' ',$initials).' ':'').$last;
                // Fully identical initials cannot distinguish names: retain the verified full name.
                if(($same??0)>1)$label=trim(implode(' ',$given).' '.$last);
            }
            $labels[$id]=$label;
        }
        return $labels;
    }
    public static function paragraph(array $facts): string {
        $match=$facts['match']??[];$l=$facts['lineups']??[];$side=null;
        foreach(['home','away'] as $candidate) if(in_array($match[$candidate]['id']??0,[30365,48835],true)) {$side=$candidate;break;}
        if($side===null)return '';
        $prefix=$side==='home'?'':'away_';
        $bound=($l['version']??'')===self::VERSION && ($l['match_id']??null)===($match['id']??null)
            && ($l['team_ids'][$side]??null)===$match[$side]['id'] && ($l['source']??null)===($match['source']??null);
        $roster=$bound?($l[$prefix.'roster']??[]):[];
        $starters=$bound&&($l['status'][$side]['starters']??'')==='confirmed'?($l[$prefix.'starters']??[]):[];
        $bench=$bound&&($l['status'][$side]['bench']??'')==='confirmed'?($l[$prefix.'bench']??[]):[];
        foreach([$starters,$bench] as $list) foreach($list as $id) if(!isset($roster[$id])||!is_string($roster[$id])||trim($roster[$id])==='')
            throw new \RuntimeException('Lagoppstillingen mangler et bekreftet navn. Hent kampgrunnlaget på nytt.');
        $labels=self::labels($roster,array_values(array_unique(array_merge($starters,$bench))));
        $names=static fn($ids)=>implode(', ',array_map(static fn($id)=>$labels[$id],$ids));
        $text=$starters?$names($starters).'.':'Startoppstillingen er ikke tilgjengelig hos NFF.';
        $reserves=$bench?'Innbyttere: '.$names($bench):'Innbytterliste ikke tilgjengelig hos NFF';
        return '<!-- wp:paragraph -->'."\n".'<p><strong>Bremnes:</strong> '.esc_html($text).' <small style="font-size:0.85em;line-height:1.5;">('.esc_html($reserves).')</small></p>'."\n".'<!-- /wp:paragraph -->';
    }
}
