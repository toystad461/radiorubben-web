<?php
if (!defined('ABSPATH')) exit;
$list_view = isset($_GET['kamper']) && is_string($_GET['kamper']) && in_array($_GET['kamper'],['spilte','alle'],true) ? $_GET['kamper'] : 'kommende';
$list_base = remove_query_arg(['kamp','vis','side','rr_edit','lagret','kamper','rr_match_demo']);
?>
<style>
.rr-fixture-tabs{display:flex;gap:4px;padding:5px;border-radius:28px;background:#272b36;margin:20px 0}
.rr-fixture-tabs a{flex:1;min-width:0;padding:12px 4px;text-align:center;color:#e5e7ed;text-decoration:none;border-radius:24px;font-size:14px;font-weight:600}
.rr-fixture-tabs a[aria-current="page"]{background:#bc293e;color:#fff}
.rr-fixtures{display:grid;gap:12px;margin:20px 0}
.rr-fixtures a{display:block;padding:16px;border:1px solid #444956;border-radius:12px;background:#1b1e27;color:#fff;text-decoration:none}
.rr-fixtures strong,.rr-fixtures small{display:block}
.rr-fixtures small{margin-top:7px;color:#bcc1cc}
</style>
<nav class="rr-fixture-tabs" aria-label="Filtrer kamper">
<?php foreach(['kommende'=>'Kommende','spilte'=>'Spilte','alle'=>'Alle kamper'] as $value=>$label): ?>
<a href="<?php echo esc_url(add_query_arg('kamper',$value,$list_base)); ?>" <?php if($list_view===$value && !isset($_GET['kamp'])) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
<?php endforeach; ?></nav>
<?php if (!isset($_GET['kamp'])):
$rows=[];
$history = require get_template_directory() . '/inc/bremnes-history-2026.php';
foreach($fixtures + $history as $id=>$item) {
    if($item['team']!==$team) continue;
    $saved = !empty($item['historical']) ? $item['result'] : get_option('rr_manual_match_trial_v1_'.$team.'_'.$id,[]);
    $done=($saved['status']??'')==='Slutt';
    if($list_view==='spilte' && !$done) continue;
    if($list_view==='kommende' && $done) continue;
    $rows[$id]=['fixture'=>$item,'state'=>$saved];
}
uasort($rows,function($a,$b) use($list_view) {
    if ($list_view !== 'spilte') {
        $live_statuses = ['1. omgang','Pause','2. omgang'];
        $a_live = in_array($a['state']['status'] ?? '', $live_statuses, true);
        $b_live = in_array($b['state']['status'] ?? '', $live_statuses, true);
        if ($a_live !== $b_live) return $a_live ? -1 : 1;
    }
    $order=strcmp($a['fixture']['kickoff'],$b['fixture']['kickoff']);
    return $list_view==='spilte' ? -$order : $order;
});
?>
<p class="muted">Alle kampkort åpner fotball.no. Resultater her oppdateres manuelt av Radio Rubben. Oversikten henter siste lagrede resultat hvert 30. sekund.</p>
<div class="rr-fixtures">
<?php foreach($rows as $id=>$row): $item=$row['fixture']; $saved=$row['state']; ?>
<a href="<?php echo esc_url('https://www.fotball.no/fotballdata/kamp/?fiksId='.$id); ?>" target="_blank" rel="noopener noreferrer">
<strong><?php echo esc_html($item['home']==='yes' ? 'Bremnes – '.$item['opponent'] : $item['opponent'].' – Bremnes'); ?></strong>
<small><?php echo esc_html(wp_date('d.m.Y H:i',strtotime($item['kickoff']),new DateTimeZone('Europe/Oslo')).' · '.$item['venue']); ?></small>
<?php if(!empty($item['competition'])): ?><small><?php echo esc_html($item['competition']); ?></small><?php endif; ?>
<small><?php
$status=$saved['status']??'Ikke startet';
if($status==='Ikke startet' && strtotime($item['kickoff'])<time()) $status='Avventer oppdatering';
if(in_array($status,['1. omgang','Pause','2. omgang'],true)) echo '<b style="color:#ff8294">PÅGÅR · </b>';
echo esc_html($status);
if(!empty($saved['minute'])) echo ' · '.esc_html($saved['minute']).' min';
if(isset($saved['us'],$saved['them']) && $saved['us']!=='' && $saved['them']!=='') {
 echo ' · '.esc_html($item['home']==='yes' ? $saved['us'].'–'.$saved['them'] : $saved['them'].'–'.$saved['us']);
}
?></small>
<?php if(!empty($saved['updated'])): ?><small>Sist oppdatert <?php echo esc_html(wp_date('d.m H:i', $saved['updated'],new DateTimeZone('Europe/Oslo'))); ?></small><?php endif; ?>
</a>
<?php if(current_user_can('edit_others_posts') && empty($item['historical'])): ?>
<a style="padding:8px 16px;font-size:13px" href="<?php echo esc_url(add_query_arg(['kamp'=>$id,'rr_edit'=>1],$list_base)); ?>">Oppdater kampresultat</a>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php if(!$rows): ?><p class="muted"><?php echo $list_view==='spilte' ? 'Ingen ferdigspilte kamper er registrert i denne oversikten ennå.' : 'Ingen kamper i dette utvalget.'; ?></p><?php endif; ?>
<p class="muted rr-refresh-status" role="status"></p>
<script>
(function(){
let busy=false;
setInterval(async function(){
if(busy || document.hidden) return;
busy=true;
try {
const response=await fetch(location.href,{cache:'no-store',credentials:'same-origin'});
if(!response.ok) throw new Error('network');
const doc=new DOMParser().parseFromString(await response.text(),'text/html');
const next=doc.querySelector('.rr-fixtures');
const current=document.querySelector('.rr-fixtures');
if(!next || !current) throw new Error('markup');
current.replaceWith(next);
document.querySelector('.rr-refresh-status').textContent='';
} catch(e) {
document.querySelector('.rr-refresh-status').textContent='Kunne ikke hente oppdatering. Viser sist hentede resultater.';
} finally {busy=false;}
},30000);
})();
</script>
<?php endif; ?>
