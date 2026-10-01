<?php
if (!defined('ABSPATH')) exit;

$rr_next = function_exists('rr_poll_next_match_data') ? rr_poll_next_match_data() : [];
$rr_share = function_exists('rr_poll_next_match_share_data') ? rr_poll_next_match_share_data() : [];

get_header();
?>
<style>
.rr-next-page{width:min(100% - 36px,1000px);margin:36px auto 110px;color:#f5f6fa}
.rr-next-page *{box-sizing:border-box}
.rr-next-kicker{margin:0 0 8px;color:#f5cb45;font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;text-align:center}
.rr-next-page h1{margin:0 0 28px;font-size:clamp(30px,6vw,48px);line-height:1.1;text-align:center}
.rr-next-card{overflow:hidden;border:1px solid #586980;border-top:3px solid #f5cb45;border-radius:20px;background:linear-gradient(145deg,#202b3e,#121925)}
.rr-next-teams{display:grid;grid-template-columns:minmax(0,1fr) minmax(175px,230px) minmax(0,1fr);align-items:center;gap:18px;padding:24px 24px 18px;text-align:center}
.rr-next-team{display:flex;flex-direction:column;align-items:center;gap:10px;min-width:0}
.rr-next-team img{width:92px;height:92px;object-fit:contain;padding:7px;border-radius:14px;background:#fff}
.rr-next-team strong{font-size:clamp(19px,4vw,28px);line-height:1.2;overflow-wrap:anywhere}
.rr-next-countdown-box{margin:0;padding:14px 10px;border-radius:14px;background:#0d1522;text-align:center}
.rr-next-countdown-label{margin:0 0 7px;color:#b9c6d8;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.rr-next-countdown{font-size:clamp(23px,3vw,32px);font-weight:900;font-variant-numeric:tabular-nums;line-height:1.1}
.rr-next-details{display:flex;flex-wrap:wrap;justify-content:center;gap:8px 18px;margin:0;padding:14px 20px 20px;border-top:1px solid #46546c;list-style:none;color:#d7dfeb;font-size:14px;text-align:center}
.rr-next-section{margin-top:18px;padding:24px;border:1px solid #46546c;border-radius:16px;background:#171d29}
.rr-next-section h2{margin:0 0 12px;font-size:22px}
.rr-next-section p{line-height:1.65}
.rr-next-history-meta{color:#aab7cc;font-size:12px}
.rr-next-history-list{margin:14px 0 0;padding:0;list-style:none}
.rr-next-history-list li{padding:10px 0;border-top:1px solid #344054;color:#d7dfeb;font-size:14px}
.rr-share-preview{margin:14px 0;padding:16px;border-radius:12px;background:#0d1522;white-space:pre-line;color:#dfe6ef;font-size:14px;line-height:1.55}
.rr-share-actions{display:flex;flex-wrap:wrap;gap:10px}
.rr-share-actions button{min-height:48px;padding:11px 17px;border:1px solid #586980;border-radius:10px;background:#202b3e;color:#fff;font:inherit;font-weight:800;cursor:pointer}
.rr-share-actions .rr-facebook{border-color:#f5cb45;background:#f5cb45;color:#151b24}
.rr-share-status{min-height:22px;margin:10px 0 0;color:#9de8bf;font-size:13px}
.rr-next-article{border-left:4px solid #f5cb45}.rr-next-article p{margin:0 0 12px}.rr-next-article p:last-child{margin-bottom:0}.rr-next-article a{color:#aaceff}.rr-next-empty{padding:30px;border:1px solid #46546c;border-radius:16px;background:#171d29;text-align:center}
.rr-next-source{margin-top:18px;color:#8f9caf;font-size:11px;text-align:center}
@media(max-width:540px){
 .rr-next-page{width:calc(100% - 28px);margin-top:24px}
 .rr-next-teams{gap:10px;padding:24px 12px 18px}
 .rr-next-team img{width:62px;height:62px}
 .rr-next-teams{grid-template-columns:minmax(0,1fr) minmax(105px,140px) minmax(0,1fr);gap:6px;padding:16px 8px}
 .rr-next-team strong{font-size:15px}
 .rr-next-countdown{font-size:19px}
 .rr-next-countdown-box{padding:10px 4px}
 .rr-next-countdown-label{font-size:9px}
 .rr-next-section{padding:19px 16px}
 .rr-share-actions{display:grid;grid-template-columns:1fr}
}
</style>
<main class="rr-next-page" data-match-id="<?php echo (int)($rr_next['id']??0); ?>">
<?php if (!$rr_next): ?>
  <section class="rr-next-empty">
    <p class="rr-next-kicker">Neste kamp</p>
    <h1>Ingen kamp er valgt</h1>
    <p>Vi har ingen bekreftede kommende kamper i oversikten. Sjekk terminlisten hos Fotball.no.</p>
  </section>
<?php else:
    $rr_tz=new DateTimeZone('Europe/Oslo');
    $rr_ts=strtotime((string)$rr_next['kickoff']);
    $rr_date=wp_date('l d.m.Y',$rr_ts,$rr_tz);
    $rr_time=wp_date('H:i',$rr_ts,$rr_tz);
    $rr_remaining=max(0,$rr_ts-time());
    $rr_initial_countdown=$rr_remaining ? (intdiv($rr_remaining,86400)?intdiv($rr_remaining,86400).' d ':'').sprintf('%02d:%02d:%02d',intdiv($rr_remaining%86400,3600),intdiv($rr_remaining%3600,60),$rr_remaining%60) : 'Kampstart nå';
    $rr_venue=sanitize_text_field($rr_next['venue']??'');
    $rr_comp=sanitize_text_field($rr_next['competition']??'');
    $rr_page_url=home_url('/nestekamp/');
    $rr_share_text=(string)($rr_share['text']??('Jeg skal på kamp – bli med!'."\n".$rr_next['home'].' – '.$rr_next['away']."\n".'Kampstart: '.$rr_date.' kl. '.$rr_time.($rr_venue!==''?"\n".'Bane: '.$rr_venue:'')."\n".$rr_page_url));

    $rr_history_file=__DIR__.'/bremnes-history-2026.php';
    $rr_history=is_readable($rr_history_file)?require $rr_history_file:[];
    $rr_team_key=($rr_next['fixture_team']??'herrer')==='kvinner'?'kvinner':'herrer';
    $rr_opponent=trim((string)($rr_next['opponent']??''));
    $rr_meetings=[];
    foreach ((array)$rr_history as $rr_history_id=>$rr_row) {
        if (!is_array($rr_row) || empty($rr_row['historical']) || ($rr_row['team']??'')!==$rr_team_key) continue;
        if (strcasecmp(trim((string)($rr_row['opponent']??'')),$rr_opponent)!==0) continue;
        $rr_row_ts=strtotime((string)($rr_row['kickoff']??''));
        if (!$rr_row_ts || $rr_row_ts >= $rr_ts || (int)$rr_history_id===(int)$rr_next['id']) continue;
        $rr_row['_ts']=$rr_row_ts;
        $rr_meetings[]=$rr_row;
    }
    usort($rr_meetings,static function($a,$b){return ($b['_ts']??0)<=>($a['_ts']??0);});
    $rr_wins=$rr_draws=$rr_losses=0;
    foreach ($rr_meetings as $rr_m) {
        $rr_us=(int)($rr_m['result']['us']??0); $rr_them=(int)($rr_m['result']['them']??0);
        if ($rr_us>$rr_them) $rr_wins++; elseif ($rr_us<$rr_them) $rr_losses++; else $rr_draws++;
    }
?>
  <p class="rr-next-kicker">Neste kamp</p>
  <h1><?php echo esc_html($rr_next['home'].' – '.$rr_next['away']); ?></h1>

  <section class="rr-next-card" aria-label="Neste kamp">
    <div class="rr-next-teams">
      <div class="rr-next-team">
        <?php if (!empty($rr_next['home_logo'])): ?><img src="<?php echo esc_url($rr_next['home_logo']); ?>" alt="<?php echo esc_attr($rr_next['home'].' sin logo'); ?>"><?php endif; ?>
        <strong><?php echo esc_html($rr_next['home']); ?></strong>
      </div>
      <div class="rr-next-countdown-box">
        <p class="rr-next-countdown-label">Kampstart om</p>
        <div class="rr-next-countdown" data-kickoff="<?php echo esc_attr($rr_next['kickoff']); ?>"><?php echo esc_html($rr_initial_countdown); ?></div>
      </div>
      <div class="rr-next-team">
        <?php if (!empty($rr_next['away_logo'])): ?><img src="<?php echo esc_url($rr_next['away_logo']); ?>" alt="<?php echo esc_attr($rr_next['away'].' sin logo'); ?>" referrerpolicy="no-referrer"><?php endif; ?>
        <strong><?php echo esc_html($rr_next['away']); ?></strong>
      </div>
    </div>
    <ul class="rr-next-details">
      <li><strong><?php echo esc_html(ucfirst($rr_date)); ?></strong></li>
      <li>Kl. <?php echo esc_html($rr_time); ?></li>
      <?php if ($rr_venue!==''): ?><li><?php echo esc_html($rr_venue); ?></li><?php endif; ?>
      <?php if ($rr_comp!==''): ?><li><?php echo esc_html($rr_comp); ?></li><?php endif; ?>
    </ul>
  </section>

  <section class="rr-next-section rr-next-article">
    <h2><?php echo esc_html($rr_next['home'].' møter '.$rr_next['away']); ?></h2>
    <p><?php echo esc_html(($rr_team_key==='kvinner'?'Bremnes sitt damelag':'Bremnes sitt herrelag').' møter '.$rr_opponent.' '.wp_date('l d.m.Y',$rr_ts,$rr_tz).' kl. '.$rr_time.'. '.($rr_venue!==''?'Oppgjøret spilles på '.$rr_venue.'. ':'').($rr_comp!==''?'Kampen hører til '.$rr_comp.'. ':'')); ?></p>
    <p>Ta turen og opplev kampen fra tribunen.</p>
    <h2>Tidligere oppgjør</h2>
    <?php if ($rr_meetings):
        $rr_last=$rr_meetings[0];
        $rr_last_us=(int)($rr_last['result']['us']??0);
        $rr_last_them=(int)($rr_last['result']['them']??0);
        $rr_outcome=$rr_last_us>$rr_last_them?'Bremnes vant':($rr_last_us<$rr_last_them?'Bremnes tapte':'Det endte uavgjort');
    ?>
      <p>I den kontrollerte 2026-oversikten har lagene møttes <?php echo count($rr_meetings)===1?'én gang':esc_html(count($rr_meetings).' ganger'); ?>. Seneste møte var <?php echo esc_html(wp_date('d.m.Y',$rr_last['_ts'],$rr_tz)); ?>. <?php echo esc_html($rr_outcome.' '.$rr_last_us.'–'.$rr_last_them); ?>.</p>
      <?php if (count($rr_meetings)>1): ?><p>Bremnes står med <?php echo (int)$rr_wins; ?> seier<?php echo $rr_wins===1?'':'e'; ?>, <?php echo (int)$rr_draws; ?> uavgjort og <?php echo (int)$rr_losses; ?> tap i disse registrerte møtene.</p><?php endif; ?>
      <ul class="rr-next-history-list">
      <?php foreach(array_slice($rr_meetings,0,3) as $rr_m): ?>
        <li><?php echo esc_html(wp_date('d.m.Y',$rr_m['_ts'],$rr_tz).' · Bremnes '.$rr_m['result']['us'].'–'.$rr_m['result']['them'].' '.$rr_m['opponent'].(!empty($rr_m['venue'])?' · '.$rr_m['venue']:'')); ?></li>
      <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Vi har foreløpig ikke et tidligere møte mellom disse lagene i Radio Rubbens kontrollerte 2026-resultatoversikt.</p>
    <?php endif; ?>
    <p class="rr-next-history-meta">Historikken bygger kun på kontrollerte kampresultater i Radio Rubbens 2026-oversikt. Vi fyller ikke inn eldre historikk uten bekreftede data.</p>
  </section>

  <section class="rr-next-section">
    <h2>Del kampen med venner</h2>
    <div class="rr-share-preview"><?php echo esc_html($rr_share_text); ?></div>
    <div class="rr-share-actions">
      <button type="button" class="rr-facebook" id="rr-facebook-share">Del på Facebook</button>
      <button type="button" id="rr-copy-share">Kopier delingstekst</button>
    </div>
    <p class="rr-share-status" id="rr-share-status" role="status"></p>
  </section>

  <p class="rr-next-source">Kampopplysninger fra <a href="<?php echo esc_url('https://www.fotball.no/fotballdata/kamp/?fiksId='.(int)$rr_next['id']); ?>">Fotball.no</a> · FIKS-ID <?php echo (int)$rr_next['id']; ?></p>

  <script>
  (() => {
    setInterval(async()=>{
      try {
        const response=await fetch(location.href,{cache:'no-store'});
        if(!response.ok)return;
        const doc=new DOMParser().parseFromString(await response.text(),'text/html');
        const next=doc.querySelector('.rr-next-page')?.dataset.matchId;
        const current=document.querySelector('.rr-next-page')?.dataset.matchId;
        if(next!==undefined&&next!==current)location.reload();
      }catch(e){}
    },60000);
    const countdown=document.querySelector('.rr-next-countdown');
    if(countdown){
      const target=Date.parse(countdown.dataset.kickoff);
      const pad=n=>String(n).padStart(2,'0');
      const tick=()=>{
        if(!Number.isFinite(target)){countdown.textContent='Tid ikke tilgjengelig';return;}
        const seconds=Math.max(0,Math.floor((target-Date.now())/1000));
        if(seconds<=0){countdown.textContent='Kampstart nå';return;}
        const days=Math.floor(seconds/86400),hours=Math.floor((seconds%86400)/3600),minutes=Math.floor((seconds%3600)/60);
        countdown.textContent=(days?days+' d ':'')+pad(hours)+':'+pad(minutes)+':'+pad(seconds%60);
      };
      tick();setInterval(tick,1000);
    }

    const shareText=<?php echo wp_json_encode($rr_share_text); ?>;
    const shareUrl=<?php echo wp_json_encode($rr_page_url); ?>;
    const status=document.getElementById('rr-share-status');
    const copy=async()=>{
      try{await navigator.clipboard.writeText(shareText);if(status)status.textContent='Delingsteksten er kopiert.';return true;}
      catch(e){if(status)status.textContent='Kunne ikke kopiere automatisk. Marker teksten over og kopier den.';return false;}
    };
    document.getElementById('rr-copy-share')?.addEventListener('click',copy);
    document.getElementById('rr-facebook-share')?.addEventListener('click',()=>{
      window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(shareUrl),'_blank','noopener,noreferrer,width=720,height=620');
      copy().then(ok=>{if(ok&&status)status.textContent='Facebook er åpnet. Delingsteksten er kopiert – lim den inn hvis Facebook ikke legger den inn automatisk.';});
    });
  })();
  </script>
<?php endif; ?>
</main>
<?php get_footer(); ?>
