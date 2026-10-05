<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(trim((string)get_theme_mod('rr_stream_url','')) === '' ? 'rr-stream-unavailable' : 'rr-stream-configured'); ?>>
<?php wp_body_open(); ?>
<a class="rr-skip-link" href="#content">Hopp til innhold</a>
<header class="rr-header">
  <div class="rr-wrap rr-header-inner">
    <a class="rr-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Radio Rubben hjem">
      <picture>
        <source media="(max-width: 720px)" srcset="<?php echo esc_url(rr_one_logo_url('compact')); ?>" width="1700" height="670">
        <img src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben" width="1700" height="760">
      </picture>
    </a>
    <a class="rr-member-entry" href="<?php echo esc_url(home_url('/min-side/')); ?>" aria-label="<?php echo esc_attr(rr_member_entry_label()); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span>Min Rubben</span></a>
    <button class="rr-menu-toggle" type="button" aria-label="Åpne meny" aria-expanded="false" aria-controls="rr-primary-nav"><span></span><span></span><span></span></button>
    <nav id="rr-primary-nav" class="rr-nav" aria-label="Hovedmeny">
      <?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>'rr_one_fallback_menu','items_wrap'=>'<ul>%3$s</ul>']); ?>
    </nav>
  </div>
  <?php $rr_active_vote=function_exists('rr_poll_active_vote')?rr_poll_active_vote():[]; ?>
  <?php
    $rr_next_match=(!$rr_active_vote && function_exists('rr_poll_next_match_header'))?rr_poll_next_match_header():[];
    $rr_banner_days=max(0,min(60,(int)get_theme_mod('rr_next_match_banner_days',7)));
    $rr_banner_kickoff=$rr_next_match ? strtotime((string)($rr_next_match['kickoff']??'')) : false;
    if (!$rr_banner_days || !$rr_banner_kickoff || $rr_banner_kickoff > time()+$rr_banner_days*DAY_IN_SECONDS) $rr_next_match=[];
  ?>
  <?php if ($rr_active_vote): ?>
  <div class="rr-match-vote-bar" aria-label="Kampen er i gang">
    <div class="rr-wrap rr-match-vote-inner">
      <span class="rr-match-vote-label"><span class="rr-live-dot" aria-hidden="true"></span> KAMPEN ER I GANG</span>
      <div class="rr-match-vote-fixture">
        <strong><?php echo esc_html($rr_active_vote['home']); ?></strong>
        <span class="rr-match-vote-score" aria-label="<?php echo esc_attr('Stillingen er '.(int)$rr_active_vote['score']['home'].' mot '.(int)$rr_active_vote['score']['away']); ?>"><?php echo (int)$rr_active_vote['score']['home'].'–'.(int)$rr_active_vote['score']['away']; ?></span>
        <strong><?php echo esc_html($rr_active_vote['away']); ?></strong>
      </div>
      <a class="rr-match-vote-button" href="<?php echo esc_url($rr_active_vote['url']); ?>">Stem på Dagens Bremnesing <span aria-hidden="true">↗</span></a>
    </div>
  </div>
  <?php elseif ($rr_next_match): ?>
  <?php
    $rr_next_kickoff=strtotime((string)$rr_next_match['kickoff']);
    $rr_next_when=$rr_next_kickoff ? wp_date('d.m · ', $rr_next_kickoff, new DateTimeZone('Europe/Oslo')).'kl. '.wp_date('H:i', $rr_next_kickoff, new DateTimeZone('Europe/Oslo')) : '';
    $rr_header_remaining=max(0,$rr_next_kickoff-time());
    $rr_header_countdown=$rr_header_remaining ? (intdiv($rr_header_remaining,86400)?intdiv($rr_header_remaining,86400).' d · ':'').sprintf('%02d:%02d:%02d',intdiv($rr_header_remaining%86400,3600),intdiv($rr_header_remaining%3600,60),$rr_header_remaining%60) : 'Kampstart nå';
  ?>
  <a class="rr-next-match-bar" href="<?php echo esc_url($rr_next_match['url']); ?>" aria-label="<?php echo esc_attr('Neste kamp: '.$rr_next_match['home'].' mot '.$rr_next_match['away'].($rr_next_when!==''?', '.$rr_next_when:'')); ?>">
    <div class="rr-wrap rr-next-match-inner">
      <div class="rr-next-club">
        <span class="rr-next-side-label">HJEMME</span><strong><?php echo esc_html($rr_next_match['home']); ?></strong>
      </div>
      <div class="rr-next-center">
        <span class="rr-next-eyebrow"><span class="rr-next-dot" aria-hidden="true"></span> NESTE KAMP</span>
        <strong class="rr-next-countdown" data-kickoff="<?php echo esc_attr($rr_next_match['kickoff']); ?>"><?php echo esc_html($rr_header_countdown); ?></strong>
        <?php if ($rr_next_when!==''): ?><span class="rr-next-when"><?php echo esc_html($rr_next_when); ?></span><?php endif; ?>
        <span class="rr-next-invite">TA TUREN PÅ KAMP <span aria-hidden="true">↗</span></span>
      </div>
      <div class="rr-next-club rr-next-club-away">
        <span class="rr-next-side-label">BORTE</span><strong><?php echo esc_html($rr_next_match['away']); ?></strong>
      </div>
    </div>
  </a>
  <?php endif; ?>
  <?php if ($rr_active_vote || $rr_next_match): ?>
  <style>
  .rr-match-vote-bar{border-top:1px solid #33323a;border-bottom:1px solid #39323a;background:#191b24}
  .rr-next-match-bar{position:relative;isolation:isolate;border-top:1px solid #6f3039;border-bottom:2px solid #e43843;background:radial-gradient(ellipse at 50% -80%,#bb35445c,transparent 70%),linear-gradient(100deg,#241b24 0%,#2b2027 50%,#241b24 100%)}
  .rr-next-match-bar::before{position:absolute;z-index:-1;inset:0;content:"";background:url("https://www.radiorubben.no/wp-content/uploads/2026/09/Radio-Rubben-%E2%80%93-Fotball.png") center 84% / cover no-repeat;opacity:.2;pointer-events:none}
  a.rr-next-match-bar{display:block;color:#fff;text-decoration:none}
  a.rr-next-match-bar:hover{background:radial-gradient(ellipse at 50% -80%,#d33d4d80,transparent 70%),linear-gradient(100deg,#30212c 0%,#39242b 50%,#30212c 100%)}
  a.rr-next-match-bar:focus-visible,.rr-match-vote-button:focus-visible{outline:3px solid #f5cb45;outline-offset:-3px}
  .rr-next-match-inner{display:grid;grid-template-columns:minmax(0,1fr) minmax(175px,230px) minmax(0,1fr);align-items:center;gap:10px;min-height:64px;padding-block:6px}
  .rr-next-club{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-width:0;text-align:center;line-height:1.1}
  .rr-next-club strong{color:#fff;font-size:clamp(16px,2vw,23px);font-weight:900;letter-spacing:-.035em;overflow-wrap:anywhere;text-shadow:0 2px 16px #0006}
  .rr-next-side-label{color:#f5cb45;font-size:10px;font-weight:900;letter-spacing:.17em}
  .rr-next-center{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:52px;border-inline:1px solid #ffffff32;text-align:center;line-height:1.15}
  .rr-next-eyebrow{display:inline-flex;align-items:center;gap:6px;color:#f5cb45;font-size:10px;font-weight:900;letter-spacing:.13em;white-space:nowrap}
  .rr-next-dot,.rr-live-dot{width:7px;height:7px;border-radius:50%;background:#f5cb45;flex:none}
  .rr-live-dot{background:#ff5369;box-shadow:0 0 0 4px #ff53691f}
  .rr-next-countdown{margin-top:5px;color:#fff;font-size:20px;font-weight:900;font-variant-numeric:tabular-nums;letter-spacing:.025em;white-space:nowrap}
  .rr-next-when{margin-top:2px;color:#e8d5d7;font-size:11px;font-weight:650;white-space:nowrap}
  .rr-next-invite{margin-top:3px;color:#f5cb45;font-size:10px;font-weight:900;letter-spacing:.04em;white-space:nowrap}
  .rr-match-vote-inner{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:20px;min-height:78px;padding-block:10px}
  .rr-match-vote-label{display:inline-flex;align-items:center;gap:8px;color:#ffcf58;font-size:11px;font-weight:900;letter-spacing:.08em;white-space:nowrap}
  .rr-match-vote-fixture{display:flex;align-items:center;justify-content:center;gap:14px;min-width:0;color:#f5f6fa;font-size:15px}
  .rr-match-vote-fixture strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rr-match-vote-score{padding:5px 10px;border-radius:8px;background:#0f141e;font-size:22px;font-weight:900;font-variant-numeric:tabular-nums;white-space:nowrap}
  .rr-match-vote-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:9px 15px;border-radius:9px;background:#f5cb45;color:#141821!important;text-decoration:none!important;font-size:13px;font-weight:900;white-space:nowrap}
  .rr-match-vote-button:hover{background:#ffdb68}
  @media(max-width:720px){
    .rr-next-match-inner{grid-template-columns:minmax(0,1fr) minmax(130px,150px) minmax(0,1fr);gap:4px;min-height:60px;padding-block:5px}
    .rr-next-club{gap:5px}
    .rr-next-club strong{font-size:clamp(14px,4vw,21px);line-height:1.12}
    .rr-next-side-label{font-size:8px}
    .rr-next-center{min-height:48px}
    .rr-next-eyebrow{font-size:9px;letter-spacing:.08em}
    .rr-next-countdown{font-size:16px}
    .rr-next-when{font-size:10px}
    .rr-next-invite{display:none}
    .rr-match-vote-inner{grid-template-columns:minmax(0,1fr) auto;gap:7px 10px;min-height:92px}
    .rr-match-vote-label{grid-column:1/-1;font-size:10px}
    .rr-match-vote-fixture{justify-content:flex-start;gap:7px;font-size:12px}
    .rr-match-vote-score{font-size:17px;padding:4px 7px}
    .rr-match-vote-button{min-height:38px;padding:7px 9px;font-size:11px}
  }
  </style>
  <?php endif; ?>
  <?php if ($rr_next_match): ?>
  <script>
  (()=>{const el=document.querySelector('.rr-next-countdown');if(!el)return;const target=Date.parse(el.dataset.kickoff);if(!Number.isFinite(target))return;const pad=n=>String(n).padStart(2,'0');let wallAt=Date.now(),monoAt=performance.now();function tick(){const now=wallAt+performance.now()-monoAt;const s=Math.max(0,Math.ceil((target-now)/1000));if(s<=0){el.textContent='Kampstart nå';return;}const d=Math.floor(s/86400),h=Math.floor((s%86400)/3600),m=Math.floor((s%3600)/60);el.textContent=(d?d+' d · ':'')+pad(h)+':'+pad(m)+':'+pad(s%60);}tick();setInterval(tick,1000);document.addEventListener('visibilitychange',()=>{if(!document.hidden){wallAt=Date.now();monoAt=performance.now();tick();}});})();
  </script>
  <?php endif; ?>
</header>
<main id="content">
