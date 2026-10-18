<?php
namespace RadioRubben\Fotballrobot;
if(!Robot::allowed())wp_die('Ingen tilgang.');
$all=self::all();$filter=sanitize_key($_GET['status']??'pending');
$labels=['pending'=>'Til vurdering','later'=>'Se senere','rejected'=>'Avvist','approved'=>'Godkjent'];
$notice=get_transient('rrfr_candidates_notice_'.get_current_user_id());delete_transient('rrfr_candidates_notice_'.get_current_user_id());
echo '<div class="wrap"><h1>Spillere ute – kandidater</h1><p>Spillere med dokumentert bakgrunn fra Bømlo og aktivitet i en annen klubb. Du velger hvem roboten skal følge. Hvert artikkelutkast krever egen godkjenning.</p>';
if($notice)echo '<div class="notice notice-info"><p role="status">'.esc_html($notice).'</p></div>';
echo '<p><strong>Søkevindu: '.esc_html((int)wp_date('Y')-20).'–'.esc_html(wp_date('Y')).'.</strong> Kartlegging pågår. Listen er ikke en fullstendig oversikt over alle overganger i perioden. Gamle klubbskifter blir bakgrunn, ikke ferske overgangssaker.</p><p>';
foreach($labels+['all'=>'Alle'] as $key=>$label){$count=count(array_filter($all,static fn($c)=>$key==='all'||$c['status']===$key));echo '<a href="'.esc_url(self::url().'&status='.$key).'">'.esc_html($label.' ('.$count.')').'</a> &nbsp; ';}
echo '</p>';$shown=0;
foreach(array_reverse($all,true) as $s){
    if($filter!=='all'&&$s['status']!==$filter)continue;$shown++;
    echo '<section style="background:#fff;border:1px solid #c3c4c7;padding:20px;margin:16px 0;max-width:960px"><h2>'.esc_html($s['name']).'</h2><p><strong>'.esc_html($s['former_club'].' → '.$s['current_club']).'</strong> · '.esc_html($s['current_level']).'</p><p>'.esc_html($s['history_note']).'</p><p>Dokumenterte Bømlo-sesonger: '.esc_html(implode(', ',$s['former_seasons'])).'. Aktivitet: '.esc_html($s['active_season']).'.</p><p>Kontrollert '.esc_html($s['checked_at']).' · '.esc_html($labels[$s['status']]).'</p><ul>';
    foreach($s['sources'] as $source)echo '<li>'.esc_html(['history'=>'Bømlo-bakgrunn','current'=>'Nåværende klubb','activity'=>'Aktiv spiller'][$source['kind']]).': '.esc_html($source['fact']).' <a href="'.esc_url($source['url']).'" target="_blank" rel="noopener">Åpne kilde</a></li>';
    echo '</ul><p><a href="'.esc_url(PlayerFacts::url($s['fiks_id'])).'" target="_blank" rel="noopener">Spillerprofil på Fotball.no</a></p>';
    if($s['status']==='approved')echo '<p><a href="'.esc_url(admin_url('admin.php?page=rr-fotballrobot-players&player='.$s['player_id'])).'">Åpne spilleroppfølging</a></p>';
    else {
        if(strtotime($s['checked_at'])<time()-30*DAY_IN_SECONDS)echo '<p><strong>Kildene ble kontrollert for over 30 dager siden. Sjekk nåværende klubb før du godkjenner.</strong></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('rrfr_candidate_action');
        foreach(['action'=>'rrfr_candidate_action','fiks_id'=>$s['fiks_id'],'version'=>$s['version']] as $k=>$v)echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
        $actions=$s['status']==='rejected'?['reopen'=>'Vurder på nytt']:['approve'=>'Godkjenn og følg','later'=>'Se senere','reject'=>'Avvis'];
        foreach($actions as $op=>$label)echo '<button class="button '.($op==='approve'?'button-primary':'').'" name="operation" value="'.esc_attr($op).'">'.esc_html($label).'</button> ';
        echo '</form>';
    }
    echo '</section>';
}
if(!$shown)echo '<p>Ingen kandidater i denne visningen.</p>';
echo '</div>';
