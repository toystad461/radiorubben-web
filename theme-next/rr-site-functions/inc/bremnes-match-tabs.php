<?php
if (!defined('ABSPATH')) exit;
$view = isset($_GET['vis']) && is_string($_GET['vis']) && in_array($_GET['vis'], ['spillere','tabell'], true) ? $_GET['vis'] : 'hendelser';
$side = isset($_GET['side']) && $_GET['side'] === 'borte' ? 'borte' : 'hjemme';
$tab_base = remove_query_arg(['vis','side','rr_edit','lagret']);
?>
<style>
.rr-match-trial .match-tabs{display:flex;gap:0;border-bottom:1px solid #626977;margin:24px 0}
.rr-match-trial .match-tabs a{flex:1;text-align:center;padding:15px 5px;color:#c5cad3;text-decoration:none;border-bottom:3px solid transparent;font-weight:700;font-size:16px}
.rr-match-trial .match-tabs a[aria-current="page"]{color:#fff;border-bottom-color:#ed3d54}
.rr-match-trial .side-tabs{display:flex;gap:8px;margin:16px 0}
.rr-match-trial .side-tabs a{flex:1;text-align:center;padding:12px 8px;border:1px solid #626977;border-radius:8px;color:#fff;text-decoration:none;font-size:14px}
.rr-match-trial .side-tabs a[aria-current="page"]{background:#3b2028;border-color:#ed3d54}
.rr-match-trial .roster{list-style:none;padding:0}
.rr-match-trial .roster li{padding:12px;border-bottom:1px solid #414653;white-space:pre-wrap;overflow-wrap:anywhere}
.rr-match-trial .table-scroll{overflow-x:auto}
.rr-match-trial table{width:100%;border-collapse:collapse;font-size:14px}
.rr-match-trial th,.rr-match-trial td{padding:12px 8px;text-align:right;border-bottom:1px solid #414653;white-space:nowrap}
.rr-match-trial th:nth-child(2),.rr-match-trial td:nth-child(2){text-align:left;white-space:normal;min-width:110px}
</style>
<nav class="match-tabs" aria-label="Kampinformasjon">
<?php foreach (['hendelser'=>'Hendelser','spillere'=>'Spillere','tabell'=>'Tabell'] as $value=>$label): ?>
<a href="<?php echo esc_url(add_query_arg('vis',$value,$tab_base)); ?>" <?php if($view===$value) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
<?php endforeach; ?></nav>
<?php if ($view==='spillere'): ?>
<nav class="side-tabs" aria-label="Velg kamptropp">
<?php foreach (['hjemme'=>'Hjemmelag','borte'=>'Bortelag'] as $value=>$label): ?>
<a href="<?php echo esc_url(add_query_arg(['vis'=>'spillere','side'=>$value],$tab_base)); ?>" <?php if($side===$value) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
<?php endforeach; ?></nav>
<h2><?php echo esc_html($side==='hjemme' ? $left : $right); ?></h2>
<?php $roster=trim($state[$side==='hjemme' ? 'players_home':'players_away'] ?? ''); ?>
<?php if ($roster===''): ?><p class="muted">Kamptroppen er ikke lagt inn ennå.</p>
<?php else: ?><ul class="roster"><?php foreach(array_filter(array_map('trim',explode("\n",$roster))) as $player): ?><li><?php echo esc_html($player); ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php elseif ($view==='tabell'): ?>
<h2><?php echo $team==='herrer' ? '5. divisjon menn avdeling 03' : '3. divisjon kvinner Vestland'; ?></h2>
<?php $table=trim($state['standings'] ?? ''); ?>
<?php if ($table===''): ?><p class="muted">Tabellen er ikke lagt inn ennå.</p>
<?php else: ?>
<div class="table-scroll" role="region" aria-label="Serietabell" tabindex="0"><table>
<caption>Manuelt oppdatert serietabell</caption>
<thead><tr><?php foreach(['Pl.','Lag','K','Mål','P'] as $label): ?><th scope="col"><?php echo esc_html($label); ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach(explode("\n",$table) as $line): $cells=array_map('trim',explode('|',$line)); if(count($cells)!==5) continue; ?><tr><?php foreach($cells as $cell): ?><td><?php echo esc_html($cell); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<p class="muted"><a href="<?php echo esc_url('https://www.fotball.no/fotballdata/lag/hjem/?fiksId='.($team==='herrer' ? '30365':'48835')); ?>" target="_blank" rel="noopener noreferrer">Se oppdatert tabell på fotball.no ↗</a></p>
<?php endif; ?>
