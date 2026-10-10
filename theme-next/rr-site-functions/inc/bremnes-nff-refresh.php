<?php
if (!defined('ABSPATH')) exit;
function rr_nff_event_snapshot($html,$match) {
    if (!class_exists('DOMDocument')) return new WP_Error('parser','Serveren mangler støtte for import.');
    $doc=new DOMDocument(); $prior=libxml_use_internal_errors(true);
    $ok=$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prior);
    if (!$ok) return new WP_Error('format','Kunne ikke lese hendelsene.');
    $xp=new DOMXPath($doc); $c='rr_poll_class_xpath';
    $text=static function($node){return $node?trim(preg_replace('/\s+/u',' ',$node->textContent)):'';};
    $card=$xp->query('//*['.$c('a_matchCard').']')->item(0);
    if (!$card) return new WP_Error('match','Fant ikke dagens kampkort.');
    $teams=$xp->query('.//*['.$c('teamName').']/a',$card);
    if ($teams->length!==2 || $text($teams->item(0))!==trim($match['home']) || $text($teams->item(1))!==trim($match['away'])) return new WP_Error('match','Kunne ikke bekrefte lagene. Ingen data er endret.');
    $section=$xp->query('//*['.$c('tabulatedContentWrapper').' and @data-tab="kamphendelser"]')->item(0);
    if (!$section) return new WP_Error('format','Fant ikke hendelsesdelen. Tidligere hentede hendelser er beholdt.');
    $events=[]; $seq=0;
    foreach ($xp->query('.//*['.$c('timelineEventLine').']',$section) as $row) {
        $content=$xp->query('.//*['.$c('timelineEventContent').']',$row)->item(0);
        if (!$content) continue;
        $label=$text($xp->query('./div',$content)->item(0));
        $heading=$text($xp->query('.//*['.$c('eventHeading').']',$content)->item(0));
        $minute=preg_replace("/[\\s'\\x{2032}]+/u",'',$text($xp->query('.//*['.$c('timelineMinute').']',$row)->item(0)));
        if (!preg_match('/^([0-9]{1,3})(?:\\+([0-9]{1,2}))?$/D',$minute,$m)) continue;
        $classes=' '.$row->getAttribute('class').' ';
        $side=strpos($classes,' awayTeam ')!==false?'away':(strpos($classes,' homeTeam ')!==false?'home':'');
        if (!$side) continue; // Period markers remain controlled by the speaker.
        $type='info';
        if (preg_match('/^(Spillemål|Straffemål|Selvmål)$/ui',$label)) $type='goal';
        elseif (preg_match('/advarsel|gult/iu',$label)) $type='yellow';
        elseif (preg_match('/utvisning|rødt/iu',$label)) $type='red';
        elseif (preg_match('/bytte/iu',$label)) $type='sub';
        if ($label==='' && $heading==='') continue;
        $id=$row->getAttribute('id');
        if ($id==='') $id=hash('sha256',$side.'|'.$minute.'|'.$label.'|'.$heading);
        $events[$id]=['source_id'=>$id,'side'=>$side,'type'=>$type,'minute'=>$minute,'label'=>sanitize_text_field($label?:'Hendelse'),'description'=>sanitize_text_field($heading),'dismissed'=>false,'sort'=>(int)$m[1]+(int)($m[2]??0),'sequence'=>$seq++];
    }
    $events=array_values($events);
    usort($events,static function($a,$b){return ($a['sort']<=>$b['sort'])?:($a['sequence']<=>$b['sequence']);});
    $yellow_counts=[];
    foreach ($events as &$event) {
        if ($event['type']!=='yellow' || $event['description']==='') continue;
        $identity=$event['side'].'|'.$event['description'];
        $yellow_counts[$identity]=($yellow_counts[$identity]??0)+1;
        if ($yellow_counts[$identity]>=2 || preg_match('/andre|2\\.?\\s*gule/iu',$event['label'])) {
            $event['dismissed']=true;
            $event['label']='Andre gule · utvist';
        }
    }
    unset($event);
    $events=array_reverse($events);
    $score_text=$text($xp->query('.//*['.$c('endResult').']',$card)->item(0));
    $score=null;
    if (preg_match('/^([0-9]{1,2})\\s*[-–]\\s*([0-9]{1,2})$/u',$score_text,$m)) $score=['home'=>(int)$m[1],'away'=>(int)$m[2]];
    // During live matches NFF can publish goals before the match-card result.
    // Use the deduplicated timeline only when the current card has no score.
    if ($score===null && $events) {
        $goals=['home'=>0,'away'=>0]; $has_goal=false;
        foreach ($events as $event) {
            if ($event['type']!=='goal') continue;
            $side=$event['side'];
            if (preg_match('/^Selvmål$/ui',$event['label'])) $side=$side==='home'?'away':'home';
            $goals[$side]++; $has_goal=true;
        }
        if ($has_goal) $score=$goals;
    }
    return ['events'=>$events,'score'=>$score,'fetched'=>time()];
}
function rr_nff_refresh_form($kind,$url,$source='nff',$section='') {
    ?><form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($url,$section)); ?>">
    <?php wp_nonce_field('rr_nff_refresh','rr_nff_nonce'); ?>
    <input type="hidden" name="rr_nff_refresh" value="<?php echo esc_attr($kind); ?>">
    <?php if ($kind==='events'): ?>
    <label for="rr-event-source">Kilde for hendelser og resultat i kamprammen</label>
    <select id="rr-event-source" name="rr_event_source"><option value="manual" <?php selected($source,'manual'); ?>>Registrert av speaker</option><option value="nff" <?php selected($source,'nff'); ?>>Fotball.no</option></select>
    <button type="submit">Oppdater hendelser / lagre kilde</button>
    <p class="muted">Med Fotball.no valgt hentes hendelser automatisk omtrent hvert minutt fra kampstart til kampslutt mens dashboardet er synlig. Knappen kan også brukes til manuell oppdatering. Manuelle bytter vises sammen med hendelsene fra Fotball.no, med draktnummer på spiller ut og inn. Registrer byttene her for å åpne innbyttere for stemmer. Kampklokken styres fortsatt her.</p>
    <?php else: ?>
    <button type="submit">Oppdater spillertropper fra Fotballdata</button>
    <p class="muted">Henter begge lag fra Fotballdata-API-et når oppstillingene er publisert. Etter kampstart beholdes spillernes identitet og startoppstilling.</p>
    <?php endif; ?></form><?php
}
$rr_nff_key='rr_poll_nff_'.$rr_match_id;
$rr_nff=get_option($rr_nff_key,[]);
$rr_nff_source=get_option($rr_nff_key.'_source','nff');
$rr_nff_message='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rr_nff_refresh'])) {
    if (!$rr_admin || !$rr_control) wp_die('Ingen tilgang.', '', ['response'=>403]);
    check_admin_referer('rr_nff_refresh','rr_nff_nonce');
    $kind=is_string($_POST['rr_nff_refresh'])?sanitize_key($_POST['rr_nff_refresh']):'';
    if ($kind==='events' && ($_POST['rr_event_source']??'')==='manual') {
        update_option($rr_nff_key.'_source','manual',false);
        $rr_nff_source='manual'; $rr_nff_message='Kamprammen viser speakerens hendelser og resultat.';
    } elseif (in_array($kind,['events','players'],true)) {
        if ($kind==='players') $fresh=rr_poll_fetch_nff($rr_match_id);
        else {
            $response=wp_safe_remote_get(add_query_arg('underside','kamphendelser',rr_poll_source_url($rr_match_id)),['timeout'=>20,'redirection'=>0,'limit_response_size'=>2000000,'headers'=>['Accept'=>'text/html']]);
            $fresh=is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200
                ? new WP_Error('fetch','Kunne ikke hente fra Fotball.no. Tidligere data er beholdt.')
                : rr_nff_event_snapshot(wp_remote_retrieve_body($response),$rr_match);
        }
        {
            if (is_wp_error($fresh)) $rr_nff_message=$fresh->get_error_message();
            elseif ($kind==='events') {
                update_option($rr_nff_key,$fresh,false); update_option($rr_nff_key.'_source','nff',false);
                $rr_nff=$fresh; $rr_nff_source='nff';
                $rr_nff_message='Hentet '.count($fresh['events']).' hendelser. Kamprammen viser nå Fotball.no. Kampklokken er uendret.';
            } else {
                $clock=get_option($rr_key,$rr_default);
                $current=get_option('rr_poll_match_'.$rr_match_id,$rr_match);
                $updated=[]; $kept=[];
                foreach (['home','away'] as $side) {
                    $prefix=$side==='away'?'away_':'';
                    $r=$prefix.'roster'; $s=$prefix.'starters'; $b=$prefix.'bench';
                    if (empty($fresh[$s]) || count($fresh[$s])>11) { $kept[]=$side==='home'?'Bremnes':'bortelaget'; continue; }
                    $safe=true;
                    if (!empty($clock['opened']) && !empty($current[$s])) {
                        foreach (($current[$r]??[]) as $no=>$name) if (($fresh[$r][$no]??null)!==$name) $safe=false;
                        $old=$current[$s]; $new=$fresh[$s]; sort($old); sort($new); if ($old!==$new) $safe=false;
                    }
                    if (!$safe) { $kept[]=$side==='home'?'Bremnes (låst etter start)':'bortelaget (låst etter start)'; continue; }
                    foreach ([$r,$s,$b] as $field) $current[$field]=$fresh[$field];
                    $updated[]=$side==='home'?'Bremnes':'bortelaget';
                }
                if ($updated) { $current['fetched']=time(); update_option('rr_poll_match_'.$rr_match_id,$current,false); $rr_match=$current; $rr_roster=$current['roster']; }
                $rr_nff_message=($updated?'Oppdatert: '.implode(', ',$updated).'. ':'').($kept?'Beholdt tidligere tropp for '.implode(', ',$kept).'. Ny eller gyldig startoppstilling mangler, eller troppen er låst.':'');
            }
        }
    } else $rr_nff_message='Ugyldig oppdateringsvalg.';
}
