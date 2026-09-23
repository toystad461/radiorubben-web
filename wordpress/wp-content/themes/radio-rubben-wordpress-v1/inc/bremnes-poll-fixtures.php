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
