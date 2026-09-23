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
      <img src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben">
    </a>
    <a class="rr-member-entry" href="<?php echo esc_url(home_url('/min-side/')); ?>" aria-label="<?php echo esc_attr(rr_member_entry_label()); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span>Min Rubben</span></a>
    <button class="rr-menu-toggle" type="button" aria-label="Åpne meny" aria-expanded="false" aria-controls="rr-primary-nav"><span></span><span></span><span></span></button>
    <nav id="rr-primary-nav" class="rr-nav" aria-label="Hovedmeny">
      <?php wp_nav_menu(['theme_location'=>'primary','container'=>false,'fallback_cb'=>'rr_one_fallback_menu','items_wrap'=>'<ul>%3$s</ul>']); ?>
    </nav>
  </div>
  <?php $rr_active_vote=function_exists('rr_poll_active_vote')?rr_poll_active_vote():[]; ?>
  <?php $rr_next_match=(!$rr_active_vote && function_exists('rr_poll_next_match_header'))?rr_poll_next_match_header():[]; ?>
  <?php if ($rr_active_vote): ?>
  <div class="rr-match-vote-bar">
    <div class="rr-wrap rr-match-vote-inner">
      <span class="rr-match-vote-label">KAMPEN ER I GANG</span>
      <span class="rr-match-vote-fixture"><?php echo esc_html($rr_active_vote['home'].' '.(int)$rr_active_vote['score']['home'].'–'.(int)$rr_active_vote['score']['away'].' '.$rr_active_vote['away']); ?></span>
      <a class="rr-match-vote-button" href="<?php echo esc_url($rr_active_vote['url']); ?>">Stem på Dagens Bremnesing</a>
    </div>
  </div>
  <?php elseif ($rr_next_match): ?>
  <a class="rr-next-match-bar" href="<?php echo esc_url($rr_next_match['url']); ?>" aria-label="<?php echo esc_attr('Neste kamp: '.$rr_next_match['home'].' mot '.$rr_next_match['away']); ?>">
    <div class="rr-wrap rr-next-match-inner">
      <div class="rr-next-club">
        <?php if (!empty($rr_next_match['home_logo'])): ?><img src="<?php echo esc_url($rr_next_match['home_logo']); ?>" alt="" width="34" height="34"><?php endif; ?>
        <strong><?php echo esc_html($rr_next_match['home']); ?></strong>
      </div>
      <div class="rr-next-center">
        <span>NESTE KAMP</span>
        <strong class="rr-next-countdown" data-kickoff="<?php echo esc_attr($rr_next_match['kickoff']); ?>">--:--:--</strong>
      </div>
      <div class="rr-next-club rr-next-club-away">
        <strong><?php echo esc_html($rr_next_match['away']); ?></strong>
        <?php if (!empty($rr_next_match['away_logo'])): ?><img src="<?php echo esc_url($rr_next_match['away_logo']); ?>" alt="" width="34" height="34" referrerpolicy="no-referrer"><?php endif; ?>
      </div>
    </div>
  </a>
  <?php endif; ?>
  <?php if ($rr_active_vote || $rr_next_match): ?>
  <style>
  .rr-match-vote-bar,.rr-next-match-bar{border-top:1px solid #2b303a;border-bottom:1px solid #2b303a;background:#15181f}
  .rr-match-vote-inner{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:14px;min-height:54px;padding-block:7px}
  .rr-match-vote-label{color:#ffcf58;font-size:11px;font-weight:900;letter-spacing:.08em;white-space:nowrap}
  .rr-match-vote-fixture{min-width:0;color:#d5d9e0;font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rr-match-vote-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 14px;border-radius:9px;background:#f5cb45;color:#141821!important;text-decoration:none!important;font-size:13px;font-weight:900;white-space:nowrap}
  .rr-match-vote-button:hover{background:#ffdb68}
  a.rr-next-match-bar{display:block;color:#fff;text-decoration:none}
  .rr-next-match-inner{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:16px;min-height:58px;padding-block:7px}
  .rr-next-club{display:flex;align-items:center;gap:9px;min-width:0;font-size:13px}
  .rr-next-club img{width:34px;height:34px;object-fit:contain;background:#fff;border-radius:7px;padding:3px;flex:none}
  .rr-next-club strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rr-next-club-away{justify-content:flex-end;text-align:right}
  .rr-next-center{display:flex;flex-direction:column;align-items:center;line-height:1.1}
  .rr-next-center span{font-size:9px;font-weight:900;letter-spacing:.12em;color:#ffcf58}
  .rr-next-countdown{margin-top:4px;font-size:16px;font-variant-numeric:tabular-nums;white-space:nowrap}
  @media(max-width:720px){
    .rr-match-vote-inner{grid-template-columns:minmax(0,1fr) auto;gap:8px;min-height:50px}
    .rr-match-vote-label{display:none}
    .rr-match-vote-fixture{font-size:12px}
    .rr-match-vote-button{min-height:38px;padding:8px 10px;font-size:12px}
    .rr-next-match-inner{gap:8px;min-height:54px}
    .rr-next-club{font-size:11px;gap:5px}
    .rr-next-club img{width:28px;height:28px}
    .rr-next-center span{font-size:8px}
    .rr-next-countdown{font-size:13px}
  }
  </style>
  <?php endif; ?>
  <?php if ($rr_next_match): ?>
  <script>
  (()=>{const el=document.querySelector('.rr-next-countdown');if(!el)return;const target=Date.parse(el.dataset.kickoff);if(!Number.isFinite(target))return;const pad=n=>String(n).padStart(2,'0');function tick(){const s=Math.max(0,Math.floor((target-Date.now())/1000));if(s<=0){el.textContent='Kampstart nå';return;}const d=Math.floor(s/86400),h=Math.floor((s%86400)/3600),m=Math.floor((s%3600)/60);el.textContent=(d?d+'d ':'')+pad(h)+':'+pad(m)+':'+pad(s%60);}tick();setInterval(tick,1000);})();
  </script>
  <?php endif; ?>
</header>
<main id="content">
