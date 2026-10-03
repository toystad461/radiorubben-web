<?php
namespace RadioRubben\Fotballrobot;
if(!Robot::allowed()) wp_die('Ingen tilgang.');
$id=absint($_GET['player']??0); $selected=null;
try {if($id) $selected=self::state($id);} catch(\Throwable $e) {$id=0;}
$notice=get_transient('rrfr_players_notice_'.get_current_user_id()); delete_transient('rrfr_players_notice_'.get_current_user_id());
$group=sanitize_key($_GET['group']??''); $status=sanitize_key($_GET['status']??'new');
$base=admin_url('admin.php?page=rr-fotballrobot-players'); $post=admin_url('admin-post.php');
$s=$selected??['name'=>'','fiks_id'=>'','club_note'=>'','note'=>'','group'=>'','enabled'=>true,'watch'=>array_keys(self::KINDS)];
echo '<div class="wrap rrfr"><header><p class="rrfr-eyebrow">RADIO RUBBEN · FOTBALLROBOTEN</p><h1>Spillere jeg følger</h1><p>Lokale spillere. Nye kamper. Historier å følge opp.</p></header>';
if($notice) echo '<p class="rrfr-notice" role="status">'.esc_html($notice).'</p>';
echo '<div class="rrfr-grid"><main><section class="rrfr-card"><h2>'.($id?'Rediger spiller':'Legg til spiller').'</h2><form method="post" action="'.esc_url($post).'">';
self::fields('save',$id);
foreach(['name'=>'Navn','fiks'=>'Fotball.no-lenke eller FIKS-ID','club_note'=>'Nåværende klubb (manuelt notat)'] as $key=>$label) echo '<p><label>'.esc_html($label).'<br><input class="regular-text" name="'.esc_attr($key).'" value="'.esc_attr($s[$key==='fiks'?'fiks_id':$key]).'" '.($key!=='club_note'?'required':'').' '.($key==='fiks'&&$id?'readonly':'').'></label></p>';
echo '<p><label>Lokal tilknytning / notat<br><textarea name="note" rows="3" class="large-text">'.esc_textarea($s['note']).'</textarea></label></p><p><label>Gruppe <select name="group"><option value="">Alle spillere</option><option value="bomlo-away" '.selected($s['group'],'bomlo-away',false).'>Bømlo-spillere ute</option></select></label></p><fieldset><legend>Hendelser som skal følges</legend>';
foreach(self::KINDS as $key=>$label) echo '<p><label><input type="checkbox" name="watch[]" value="'.esc_attr($key).'" '.checked(in_array($key,$s['watch'],true),true,false).'> '.esc_html($label).'</label></p>';
echo '</fieldset><p><label><input type="checkbox" name="enabled" value="1" '.checked($s['enabled'],true,false).'> Automatisk oppfølging</label></p><button class="rrfr-primary">Lagre spiller</button> <a href="'.esc_url($base).'">Ny spiller</a></form></section></main><aside><section class="rrfr-card"><h2>Slik følger roboten med</h2><p>Første innhenting lagrer et utgangspunkt. Deretter vises endringer nedenfor. Utkast lagres for redigering i WordPress.</p><p>Én aktiv spiller kontrolleres hver time når WordPress kjører planlagte oppgaver. Seks kampdetaljer hentes per kontroll, i rotasjon. Oppstillinger kan derfor komme senere enn kamp- og sesongdata.</p><p>Kamp- og sesongdata hentes fra offentlige profiler og kampsider på Fotball.no. Kontrollerte avis- og klubbnyheter samles i kildeinnboksen. Manglende data behandles som ukjent. Klubbnotatet ditt holdes atskilt fra NFFs klubbtilknytning.</p></section></aside></div>';
PlayerMonitor::panel();
echo '<section class="rrfr-card"><h2>Spilleroversikt</h2><p><a href="'.esc_url($base).'">Alle spillere</a> · <a href="'.esc_url($base.'&group=bomlo-away').'">Bømlo-spillere ute</a></p>';
$states=[];
foreach(self::ids() as $pid) {
    $p=self::state((int)$pid); if($group==='bomlo-away'&&$p['group']!==$group) continue; $states[$pid]=$p;
    $clubs=$p['snapshot']['clubs']??null;
    echo '<article class="rrfr-player"><h3><a href="'.esc_url($base.'&player='.$pid).'">'.esc_html($p['name']).'</a></h3><p>'.esc_html($clubs?implode(', ',array_column($clubs,'name')):'Klubb ikke bekreftet av NFF').($p['enabled']?'':' · Pauset').'</p><p>'.esc_html($p['note']).'</p><p><a href="'.esc_url(PlayerFacts::url($p['fiks_id'])).'" target="_blank" rel="noopener">Spillerprofil på Fotball.no</a></p><p>Sist kontrollert: '.esc_html($p['last_checked']??'Ikke hentet ennå').'</p>';
    if($p['error']) echo '<p role="status" class="rrfr-notice">'.esc_html($p['error']).'</p>';
    foreach($p['snapshot']['warnings']??[] as $w) echo '<p class="rrfr-notice">'.esc_html($w).'</p>';
    if(!empty($p['snapshot']['detail_backlog'])) echo '<p>Kampdetaljer kontrolleres videre ved neste innhenting.</p>';
    echo '<form method="post" action="'.esc_url($post).'">'; self::fields('refresh',(int)$pid); echo '<button>Hent spillerdata nå</button></form>';
    if($p['snapshot']) {echo '<details><summary>Sesongstatistikk</summary><div class="rrfr-table"><table><thead><tr><th>Sesong</th><th>Lag</th><th>Kamper</th><th>Mål</th><th>Gule</th><th>Røde</th></tr></thead><tbody>'; foreach($p['snapshot']['stats'] as $r) {echo '<tr>';foreach(['year','team','appearances','goals','yellow','red'] as $k) echo '<td>'.esc_html($r[$k]??'Ukjent').'</td>';echo '</tr>';}echo '</tbody></table></div></details>';}
    echo '</article>';
}
if(!$states) echo '<p>Ingen spillere i denne oversikten ennå.</p>';
echo '</section><section class="rrfr-card"><h2>Spillerhendelser</h2><p><a href="'.esc_url($base.'&group='.$group).'">Nye treff</a> · <a href="'.esc_url($base.'&status=all&group='.$group).'">Alle hendelser</a></p>';
$events=[]; foreach($states as $pid=>$p) foreach($p['events'] as $e) if($status==='all'||$e['status']==='new') $events[]=$e+['player_id'=>$pid];
usort($events,static fn($a,$b)=>strcmp($b['detected_at'],$a['detected_at']));
foreach($events as $e) {
    echo '<article class="rrfr-player"><h3>'.esc_html($e['player_name'].' · '.self::KINDS[$e['kind']]).'</h3><p>'.esc_html($e['detected_at'].' · '.(['new'=>'Nytt treff','ignored'=>'Ignorert','draft'=>'Utkast laget'][$e['status']]??$e['status'])).'</p><details><summary>Se detaljer</summary><p>'.esc_html($e['context']??'').'</p><h4>Før</h4><p>'.nl2br(esc_html(self::describe($e['before']))).'</p><h4>Etter</h4><p>'.nl2br(esc_html(self::describe($e['after']))).'</p><p><a href="'.esc_url($e['source']).'">Kilde på Fotball.no</a></p></details><div class="rrfr-row">';
    foreach(['draft'=>'Lag artikkelutkast','ignore'=>'Ignorer'] as $op=>$label) {echo '<form method="post" action="'.esc_url($post).'">';self::fields($op,(int)$e['player_id'],$e['id']);echo '<button>'.esc_html($label).'</button></form>';}
    echo '</div></article>';
}
if(!$events) echo '<p>Ingen nye spillerhendelser. Første innhenting gir et sammenligningsgrunnlag.</p>';
echo '</section></div>';
