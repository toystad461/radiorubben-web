<?php
namespace RadioRubben\Fotballrobot;

final class Report {
    public static function active(): bool {
        return !is_admin() && is_singular('post') && (int)get_post_meta(get_queried_object_id(),'_rrfr_ai_match',true)>0;
    }
    /** Only explicit substitution records are allowed out of the dashboard state. */
    public static function substitutions(array $events,array $lineups): array {
        $rows=[];
        foreach($events as $e) {
            if(($e['type']??'')!=='sub'||!empty($e['dismissed'])||!in_array($e['side']??'',['home','away'],true))continue;
            $roster=$lineups[$e['side']==='home'?'roster':'away_roster']??[];
            $in=(int)($e['player']??0);$out=(int)($e['out']??0);$seconds=$e['seconds']??null;
            if(!$in||!$out||$in===$out||!isset($roster[$in],$roster[$out])||!is_numeric($seconds)||$seconds<0||$seconds>10800)continue;
            $key=$e['side'].':'.$out.':'.$in; // same recorded substitution appears once
            if(isset($rows[$key]))continue;
            $rows[$key]=['side'=>$e['side'],'in'=>$roster[$in],'out'=>$roster[$out],'seconds'=>(int)$seconds,'source'=>'Radio Rubben · manuelt registrert bytte'];
        }
        $rows=array_values($rows);usort($rows,fn($a,$b)=>$a['seconds']<=>$b['seconds']);return $rows;
    }
    public static function extras(int $id,array $lineups): array {
        $a=get_option('rr_match_archive_'.$id,[]);
        $state=$a['state']??get_option('rr_poll_test_'.$id.'_vipps_v3_75',[]);
        $match=get_option('rr_poll_match_'.$id,[]);$logos=[];
        foreach(['home','away'] as $side) {
            $url=$match[$side.'_logo']??'';
            if(is_string($url)&&preg_match('~^https://images\.fotball\.no/clublogos/[a-zA-Z0-9._/-]+$~',$url))$logos[$side]=$url;
        }
        $extras=['manual_substitutions'=>self::substitutions($state['events']??[],$lineups),'logos'=>$logos];
        // Read only the explicitly selected sporting award, never voters/prize winners.
        $award=$state['poll_award']??null;$players=[];
        $bremnesHome=in_array((int)($a['match']['home_id']??$match['home_id']??0),[30365,48835],true);
        if($bremnesHome&&is_array($award)&&empty($award['dismissed'])&&($award['type']??'')==='award'&&($award['side']??'')==='home') {
            $rows=$a['results']??[];$top=$rows?(int)$rows[0]['total']:0;
            foreach($rows as $row)if($top>0&&(int)$row['total']===$top&&is_string($row['player']??null)&&($lineups['roster'][(int)$row['number']]??'')===$row['player'])$players[]=$row['player'];
            // Live award: extract only the sporting name and validate it against this lineup.
            if(!$rows&&preg_match('/^Nr\. ([1-9][0-9]*) (.+?) · /u',(string)($award['description']??''),$found)&&($lineups['roster'][(int)$found[1]]??'')===$found[2])$players[]=$found[2];
        }
        if($players)$extras['award']=['players'=>array_values(array_unique($players)),'source'=>'Radio Rubben · Dagens Bremnesing'];
        $sponsor=$a['sponsor']??get_option('rr_bremnes_sponsor_'.$id,[]);
        if(is_array($sponsor)&&is_string($sponsor['name']??null)&&trim($sponsor['name'])!=='')
            $extras['sponsor']=['name'=>trim($sponsor['name']),'source'=>'Radio Rubben · kampsponsor'];
        return $extras;
    }
    public static function content(string $content): string {
        if(!self::active()||!in_the_loop()||!is_main_query()||get_the_ID()!==get_queried_object_id())return $content;
        $f=get_post_meta(get_the_ID(),'_rrfr_fact_snapshot',true);
        if(!is_array($f)||empty($f['match']['id']))return $content;
        $m=$f['match'];$extra=$f['report_extras']??self::extras((int)$m['id'],$f['lineups']??[]);
        $notice='';$story='';$lineups='';$source='';$lead=true;
        foreach(parse_blocks($content) as $block) {
            $html=render_block($block);
            if(strpos($html,'rrfr-editorial-notice')!==false){$notice.=$html;continue;}
            if(preg_match('~<small[^>]*>Kilde(?:r)?:~u',$html)){$source.=$html;continue;}
            if(strpos($html,'<strong>'.esc_html($m['home']['name']).':</strong>')!==false||strpos($html,'<strong>'.esc_html($m['away']['name']).':</strong>')!==false){$lineups.='<div class="rrfr-lineup">'.$html.'</div>';continue;}
            if(trim(strip_tags($html))==='')continue;
            if($lead&&($block['blockName']??'')==='core/paragraph'){$story.='<div class="rrfr-lead">'.$html.'</div>';$lead=false;}else{$story.=$html;}
        }
        $h='<div class="rrfr-report-content">'.$notice.'<section class="rrfr-scorecard" aria-label="Kampresultat"><p class="rrfr-kicker">KAMP SLUTT · '.esc_html($m['competition']['name']).'</p><div class="rrfr-score-row">';
        foreach(['home','away'] as $i=>$side) {
            if($i===1)$h.='<div class="rrfr-score"><strong>'.esc_html(implode('–',$m['score'])).'</strong><small>Pause '.esc_html($m['half_time']?implode('–',$m['half_time']):'ikke oppgitt').'</small></div>';
            $h.='<div class="rrfr-team">';if(!empty($extra['logos'][$side]))$h.='<img src="'.esc_url($extra['logos'][$side]).'" alt="" width="64" height="64">';
            $h.='<strong>'.esc_html($m[$side]['name']).'</strong></div>';
        }
        $h.='</div><p class="rrfr-match-meta">'.esc_html(wp_date('j. F Y · H:i',strtotime($m['kickoff']),new \DateTimeZone('Europe/Oslo')).' · '.$m['venue']).'</p></section><div class="rrfr-story">'.$story.'</div><section class="rrfr-match-facts"><p class="rrfr-kicker">DETTE SKJEDDE I KAMPEN</p><h2>Kampfakta</h2>';
        $h.=self::timeline($m,$extra['manual_substitutions']);
        $refs=$m['referees']??[];
        if(!$refs){try{$latest=Robot::latest((int)$m['id']);if($latest['match']['score']===$m['score'])$refs=$latest['match']['referees']??[];}catch(\Throwable $e){}}
        if($refs){$h.='<div class="rrfr-officials"><h3>Dommere</h3>';foreach($refs as $r)$h.='<p><strong>'.esc_html($r['role']).':</strong> '.esc_html($r['name']).'</p>';$h.='</div>';}
        $h.='</section>';
        if($lineups)$h.='<section class="rrfr-lineups"><h2>Lagoppstillinger</h2><div class="rrfr-lineup-grid">'.$lineups.'</div></section>';
        return $h.'<footer class="rrfr-sources">'.$source.'</footer></div>';
    }
    public static function timeline(array $match,array $subs): string {
        $rows=[];
        foreach($match['events'] as $e) {
            $kind=match($e['type']) {'Spillemål','Straffemål','Selvmål'=>'goal','Advarsel'=>'yellow','Utvisning'=>'red',default=>null};
            if(!$kind||$e['name']===''||!in_array($e['side'],['home','away'],true))continue;
            $rows[]=['side'=>$e['side'],'kind'=>$kind,'title'=>$e['type'],'text'=>$e['name'],'minute'=>(int)$e['minute'],'sort'=>(int)$e['minute']*60];
        }
        foreach($subs as $e)$rows[]=['side'=>$e['side'],'kind'=>'sub','title'=>'Bytte · Radio Rubben','text'=>$e['out'].' ut → '.$e['in'].' inn','minute'=>(int)ceil($e['seconds']/60),'sort'=>$e['seconds']];
        usort($rows,fn($a,$b)=>$b['sort']<=>$a['sort']);
        $h='<div class="rrfr-timeline"><div class="poll-event-head"><strong>'.esc_html($match['home']['name']).'</strong><span>Min.</span><strong>'.esc_html($match['away']['name']).'</strong></div><ol aria-label="Kamphendelser, nyeste først">';
        foreach($rows as $r) {
            $icon=match($r['kind']) {'goal'=>'⚽','sub'=>'⇄',default=>''};
            $h.='<li class="poll-event-row '.esc_attr($r['side']).'"><span class="poll-event-icon '.esc_attr($r['kind']).'" aria-hidden="true">'.$icon.'</span><div class="poll-event-text"><strong>'.esc_html($r['title']).'</strong><span>'.esc_html($r['text']).'</span><small class="rrfr-event-team">'.esc_html($match[$r['side']]['name']).'</small></div><div class="poll-event-minute">'.esc_html($r['minute']).'′</div></li>';
        }
        $h.='</ol>';
        if(!$rows)$h.='<p class="rrfr-muted">Ingen hendelser registrert.</p>';
        return $h.'<p class="rrfr-muted">Mål og kort: fotball.no. Bytter: registrert av Radio Rubben, avrundet opp til kampminuttet fra dashboardklokken. Nyeste hendelse øverst.</p></div>';
    }
    public static function styles(): void {
        if(!self::active())return;
        echo '<style id="rrfr-report-style">'.file_get_contents(__DIR__.'/report.css').'</style>';
    }
}
add_filter('body_class',static function($classes){if(Report::active())$classes[]='rrfr-report';return $classes;});
add_action('wp_head',[Report::class,'styles'],99);
add_filter('the_content',[Report::class,'content'],8);
