<?php
if (!defined('ABSPATH')) exit;
function rr_poll_pick_home_fixture($html,$team_id,$today) {
    if (!class_exists('DOMDocument')) return new WP_Error('parser','Serveren mangler støtte for kampimport.');
    $d=new DOMDocument(); $prior=libxml_use_internal_errors(true);
    $ok=$d->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prior);
    if (!$ok) return new WP_Error('format','Kunne ikke lese terminlisten.');
    $x=new DOMXPath($d); $candidates=[];
    $rows=$x->query('//table[.//th[normalize-space(.)="Hjemmelag"]]/tbody/tr');
    foreach($rows as $row) {
        $cells=$x->query('./td',$row);
        if ($cells->length<6) continue;
        $home=$x->query('./a',$cells->item(3))->item(0);
        $link=$x->query('./a',$cells->item(0))->item(0);
        if (!$home || !$link) continue;
        parse_str(wp_parse_url($home->getAttribute('href'),PHP_URL_QUERY)??'',$h);
        if ((int)($h['fiksId']??0)!==$team_id) continue;
        $label=mb_strtolower($row->textContent);
        if (strpos($label,'avlyst')!==false || strpos($label,'utsatt')!==false) continue;
        $date=DateTimeImmutable::createFromFormat('!d.m.Y',trim($link->textContent),new DateTimeZone('Europe/Oslo'));
        $errors=DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) continue;
        if ($date->format('Y-m-d')<$today) continue;
        $href=$link->getAttribute('href');
        $id=rr_poll_parse_match_id('https://www.fotball.no'.$href);
        if (!$id) continue;
        $time=trim($cells->item(2)->textContent);
        if (!preg_match('/^\d{2}:\d{2}$/',$time)) $time='23:59';
        $candidates[$id]=$date->format('Y-m-d').' '.$time;
    }
    if (!$candidates) return new WP_Error('empty','Fant ingen hjemmekamp i dag eller fremover for valgt lag. Du kan legge inn kampens FIKS-ID manuelt.');
    asort($candidates,SORT_STRING);
    return (int)array_key_first($candidates);
}
function rr_poll_find_next_home($team_id) {
    if (!in_array($team_id,[30365,48835],true)) return new WP_Error('team','Velg Herrer A eller Damer A.');
    $url='https://www.fotball.no/fotballdata/lag/hjem/?fiksId='.$team_id;
    $response=wp_safe_remote_get($url,['timeout'=>20,'redirection'=>0,'limit_response_size'=>3000000,'headers'=>['Accept'=>'text/html']]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) return new WP_Error('fetch','Kunne ikke hente terminlisten fra Fotball.no. Eksisterende kamp er beholdt.');
    return rr_poll_pick_home_fixture(wp_remote_retrieve_body($response),$team_id,wp_date('Y-m-d',null,new DateTimeZone('Europe/Oslo')));
}

// Advance the shared selection on the first match-page visit after 06:00 Oslo.
function rr_poll_rollover_due($match,$state,$archive,$now) {
    if (empty($state['finished']) || !is_array($archive) || empty($archive['state']['finished'])) return false;
    if ((int)($archive['match']['id']??0)!==(int)($match['id']??0)) return false;
    try {
        $kickoff=new DateTimeImmutable($match['kickoff'],new DateTimeZone('Europe/Oslo'));
        $due=$kickoff->setTimezone(new DateTimeZone('Europe/Oslo'))->modify('+1 day')->setTime(6,0)->getTimestamp();
    } catch (Exception $e) { return false; }
    return $now >= $due;
}
function rr_poll_rollover_selection() {
    $now=time();
    $old=(int)get_option('rr_poll_selected_match',0);
    $match=get_option('rr_poll_match_'.$old,[]);
    $state=get_option('rr_poll_test_'.$old.'_vipps_v3_75',[]);
    $archive=get_option('rr_match_archive_'.$old,false);
    if (!$old || !rr_poll_rollover_due($match,$state,$archive,$now)) return;
    $attempt=get_option('rr_poll_rollover_attempt',[]);
    if ((int)($attempt['match']??0)===$old && $now-(int)($attempt['at']??0)<900) return;
    $lock='rr_poll_rollover_lock';
    $held=(int)get_option($lock,0);
    if ($held && $now-$held>180) delete_option($lock);
    if (!add_option($lock,$now,'',false)) return;
    try {
        update_option('rr_poll_rollover_attempt',['match'=>$old,'at'=>$now,'status'=>'checking'],false);
        $candidates=[];
        foreach ([30365,48835] as $team) {
            $id=rr_poll_find_next_home($team);
            if (is_wp_error($id)) {
                if ($id->get_error_code()==='empty') continue;
                throw new RuntimeException($id->get_error_message());
            }
            if ($id===$old) continue;
            $next=rr_poll_fetch_nff($id);
            if (is_wp_error($next)) throw new RuntimeException($next->get_error_message());
            $kickoff=strtotime($next['kickoff']??'');
            if ((int)($next['id']??0)!==$id || (int)($next['home_id']??0)!==$team || !$kickoff || $kickoff<$now) continue;
            $clock=get_option('rr_poll_test_'.$id.'_vipps_v3_75',[]);
            if (!empty($clock['opened']) || get_option('rr_match_archive_'.$id,false)) continue;
            $candidates[]=$next;
        }
        if (!$candidates) throw new RuntimeException('Ingen ny fremtidig hjemmekamp funnet. Gjeldende kamp beholdes.');
        usort($candidates,static function($a,$b){return strtotime($a['kickoff'])<=>strtotime($b['kickoff']);});
        $next=$candidates[0]; $id=(int)$next['id'];
        // Re-read after network requests; never replace an active or manually changed selection.
        $current=get_option('rr_poll_test_'.$old.'_vipps_v3_75',[]);
        $target=get_option('rr_poll_test_'.$id.'_vipps_v3_75',[]);
        if ((int)get_option('rr_poll_selected_match',0)!==$old || empty($current['finished']) || !empty($target['opened'])) return;
        // Preserve an existing import and any preparations for the next match.
        if (get_option('rr_poll_match_'.$id,false)===false && !add_option('rr_poll_match_'.$id,$next,'',false)) return;
        global $wpdb;
        $changed=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND option_value=%s",(string)$id,'rr_poll_selected_match',(string)$old));
        if ($changed===1) {
            wp_cache_delete('rr_poll_selected_match','options');
            wp_cache_delete('alloptions','options');
            update_option('rr_poll_rollover_attempt',['match'=>$old,'next'=>$id,'at'=>$now,'status'=>'selected'],false);
        }
    } catch (Exception $e) {
        update_option('rr_poll_rollover_attempt',['match'=>$old,'at'=>$now,'status'=>'retry','message'=>$e->getMessage()],false);
    } finally { delete_option($lock); }
}
