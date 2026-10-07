<?php
if (!defined('ABSPATH')) exit;
function rr_poll_elapsed($state,$now=null) {
    $now=$now??time();
    return max(0,(int)$state['elapsed']+(!empty($state['running'])?max(0,$now-(int)$state['started']):0));
}
function rr_poll_is_closed($state,$now=null) {
    return !empty($state['closed']) || ((int)$state['period']===2 && rr_poll_elapsed($state,$now)>=5100);
}
function rr_poll_lineup_ready($match) {
    return !empty($match['starters']) && count($match['starters'])<=11;
}
function rr_poll_allowed_players($match,$state) {
    if (empty($state['opened']) || !rr_poll_lineup_ready($match)) return [];
    $allowed=[];

    // Voting eligibility is cumulative for the whole match:
    // starters are eligible from kickoff, substitutes are added when they enter,
    // and no player is removed again because they leave the pitch.
    foreach (($state['eligible_players']??[]) as $no=>$stored_name) {
        $no=(int)$no;
        $name=is_string($stored_name) && trim($stored_name)!=='' ? trim($stored_name) : ($match['roster'][$no]??'');
        if ($name!=='') $allowed[$no]=$name;
    }
    foreach ($match['starters'] as $no) {
        $no=(int)$no;
        if(isset($match['roster'][$no])) $allowed[$no]=$match['roster'][$no];
    }
    foreach (($state['entered']??[]) as $no=>$minute) {
        $no=(int)$no;
        if (isset($match['roster'][$no])) $allowed[$no]=$match['roster'][$no];
    }

    ksort($allowed);
    return $allowed;
}

// Immutable, match-scoped archive. Called by the existing finish workflow.
function rr_poll_archive_save($match,$state,$nff,$source,$score,$results) {
    if (empty($state['finished']) || empty($match['id'])) return false;
    $id=(int)$match['id']; $key='rr_match_archive_'.$id;
    if (get_option($key,false)!==false) return true;
    $snapshot=['version'=>1,'saved_at'=>time(),'match'=>$match,'state'=>$state,
        'nff'=>$nff,'source'=>$source,'score'=>$score,'results'=>$results,
        'sponsor'=>get_option('rr_bremnes_sponsor_'.$id,['name'=>'','logo'=>0])];
    return add_option($key,$snapshot,'',false) || get_option($key,false)!==false;
}
function rr_poll_archive_details($archive) {
    $m=$archive['match']; $rows=$archive['results']??[];
    echo '<section class="card"><h2>Dagens Bremnesing</h2>';
    if (!empty($archive['state']['poll_award']) && $rows) {
        $top=(int)$rows[0]['total'];
        foreach ($rows as $row) if ((int)$row['total']===$top)
            echo '<p><strong>'.esc_html('Nr. '.$row['number'].' '.$row['player']).'</strong> · '.(int)$row['total'].' stemmer</p>';
        if (count(array_filter($rows,static fn($r)=>(int)$r['total']===$top))>1) echo '<p>Delt førsteplass.</p>';
    } else echo '<p>Ingen kåring registrert.</p>';
    echo '<p class="muted">'.array_sum(array_column($rows,'total')).' stemmer totalt. Avstemningen er avsluttet.</p>';
    if (current_user_can('manage_options') && !empty($archive['state']['poll_award']['description']))
        echo '<details><summary>Kun administrator: arkivert trekning</summary><p>'.esc_html($archive['state']['poll_award']['description']).'</p></details>';
    echo '</section><section class="card"><h2>Lagoppstillinger</h2>';
    foreach (['home','away'] as $side) {
        $prefix=$side==='away'?'away_':''; $roster=$m[$prefix.'roster']??[];
        echo '<details><summary>'.esc_html($m[$side]).'</summary>';
        foreach (['starters'=>'Startspillere','bench'=>'Innbyttere'] as $field=>$label) {
            echo '<h3>'.esc_html($label).'</h3><ul>';
            foreach (($m[$prefix.$field]??[]) as $no) echo '<li>'.esc_html('Nr. '.$no.' '.($roster[$no]??'Ukjent spiller')).'</li>';
            echo '</ul>';
        }
        echo '</details>';
    }
    echo '</section>';
    $s=$archive['sponsor']??[];
    if (!empty($s['name'])) {
        echo '<section class="card"><h2>Kampsponsor</h2><p>'.esc_html($s['name']).'</p>';
        if (!empty($s['logo'])) echo wp_get_attachment_image((int)$s['logo'],'medium',false,['alt'=>$s['name'],'style'=>'max-width:100%;height:auto;background:white;padding:12px;border-radius:10px']);
        echo '</section>';
    }
    echo '<p class="muted">Kampen er arkivert. Resultat og hendelser er bevart slik de var ved arkivering.</p>';
}
function rr_poll_archive_list() {
    global $wpdb;
    $page=isset($_GET['rr_archive_page']) && is_scalar($_GET['rr_archive_page']) ? max(1,min(10000,absint($_GET['rr_archive_page']))) : 1;
    $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 21 OFFSET %d",$wpdb->esc_like('rr_match_archive_').'%',($page-1)*20));
    echo '<details class="card" id="rr-past-matches" open><summary><strong>Tidligere kamper</strong></summary><ul>';
    foreach (array_slice($keys,0,20) as $key) {
        $a=get_option($key,[]); if (empty($a['match']['id'])) continue;
        $m=$a['match']; $s=$a['score'];
        echo '<li><a href="'.esc_url(add_query_arg('rr_match',(int)$m['id'],home_url('/dagenskamp/'))).'">'.esc_html($m['home'].' – '.$m['away'].' · '.$s['home'].'–'.$s['away'].' · '.$m['date_label']).'</a></li>';
    }
    if (!$keys) echo '<li>Ingen kamper er arkivert ennå.</li>';
    echo '</ul>';
    if ($page>1) echo '<p><a href="'.esc_url(add_query_arg('rr_archive_page',$page-1,home_url('/dagenskamp/'))).'#rr-past-matches">Nyere kamper</a></p>';
    if (count($keys)>20) echo '<p><a href="'.esc_url(add_query_arg('rr_archive_page',$page+1,home_url('/dagenskamp/'))).'#rr-past-matches">Eldre kamper</a></p>';
    echo '</details>';
}
