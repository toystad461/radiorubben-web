<?php
if (!defined('ABSPATH')) exit;
$events = [
    [2,'home','goal','Karoline Heimvik Haugland','','1–0'],
    [30,'home','goal','Brenna Marie Lovera','','2–0'],
    [32,'away','card','Mathea Meyer Theting','',''],
    [40,'home','goal','Brenna Marie Lovera','','3–0'],
    [45,'home','sub','Emma-Helena Alexandra Peuhkurinen','Amalie Vevle Eikeland',''],
    [45,'home','sub','Mia Authen','Joanna Maria Tynnilä',''],
    [56,'home','sub','Anna Nerland Aahjem','Heidi Steinsbø Halbmayr',''],
    [56,'home','sub','Stella Nyamekye','Brenna Marie Lovera',''],
    [62,'away','sub','Sarah Marie Foley','Imani Jenkins',''],
    [69,'away','sub','Cecilie Falch','Rachel Rose Kutella',''],
    [69,'away','sub','Signe Tømmerås','Claudia Nicole Cagnina',''],
    [76,'home','sub','Dilja Yr Zomers','Carina Wik Alfredsen',''],
    [79,'home','goal','Dilja Yr Zomers','','4–0'],
    [86,'away','sub','Katja Kaspersen Rengård','Anna Isabella Johansen',''],
    [86,'away','sub','Gerd Anine Johansen','Amie Sannes',''],
    [90,'end','end','Kampen er slutt','','4–0']
];
get_header();
?>
<style>
.rr-demo{max-width:780px;margin:32px auto;padding:0 18px 100px;color:#f5f6fa}
.rr-demo *{box-sizing:border-box}
.rr-demo .eyebrow{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#ffacb7;font-weight:700}
.rr-demo h1{font-size:clamp(28px,6vw,42px);line-height:1.15;margin:10px 0 16px}
.rr-demo .note{color:#bec3ce;font-size:13px;line-height:1.6}
.rr-demo .scoreboard{border:1px solid #444956;border-radius:18px;background:radial-gradient(ellipse at top,#49202a,#171923 70%);padding:25px 18px;margin:22px 0}
.rr-demo .status{text-align:center;color:#fff;font-size:12px;font-weight:800;letter-spacing:.16em}
.rr-demo .scoreline{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:12px;text-align:center;margin:22px 0 12px}
.rr-demo .club{font-size:clamp(15px,4vw,25px);font-weight:750;overflow-wrap:anywhere}
.rr-demo .score{font-size:clamp(42px,9vw,66px);font-weight:800;line-height:1}
.rr-demo .matchmeta{text-align:center;color:#c5c8d2;font-size:13px;line-height:1.7;margin:0}
.rr-demo h2{font-size:24px;margin:28px 0 6px}
.rr-demo .timeline{list-style:none;padding:0;margin:22px 0;position:relative}
.rr-demo .timeline:before{content:"";position:absolute;top:12px;bottom:12px;left:65px;width:2px;background:#505461}
.rr-demo .event{position:relative;display:grid;grid-template-columns:44px 28px minmax(0,1fr);gap:8px;align-items:start;margin:0 0 14px}
.rr-demo .minute{font-size:15px;font-weight:700;padding-top:17px;text-align:right}
.rr-demo .symbol{width:28px;height:28px;margin-top:12px;display:flex;align-items:center;justify-content:center;background:#171923;border:1px solid #535966;border-radius:50%;font-size:18px;z-index:1}
.rr-demo .details{min-width:0;padding:14px 16px;border:1px solid #3b404d;background:#1b1e28;border-radius:12px;line-height:1.5}
.rr-demo .event.goal .details{border-left:3px solid #e8edf3}
.rr-demo .event.card .details{border-left:3px solid #ffdf38}
.rr-demo .event.sub .details{border-left:3px solid #76808f}
.rr-demo .event.end .details{background:#1b1e28;border-color:#626c7a}
.rr-demo .club img{display:block;width:clamp(54px,13vw,88px);height:clamp(54px,13vw,88px);object-fit:contain;margin:0 auto 10px;border-radius:0;filter:none}
.rr-demo .symbol svg{width:22px;height:22px;display:block}
.rr-demo .out-label{color:#ff8794}
.rr-demo .in-label{color:#76e6ac}
.rr-demo .event-head .event-team{display:flex;align-items:center;gap:7px;min-width:0}
.rr-demo .event-team img{width:22px;height:22px;object-fit:contain;flex-shrink:0;border-radius:0}
.rr-demo .event-head{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#c8ccd5;margin-bottom:5px}
.rr-demo .event-score{font-size:17px;color:#fff;letter-spacing:0;font-weight:800}
.rr-demo .player{font-size:16px;font-weight:650;overflow-wrap:anywhere}
.rr-demo .out{font-size:14px;color:#bec3ce;margin-top:4px;overflow-wrap:anywhere}
.rr-demo .in-label{color:#8de0b7}
.rr-demo .yellow{width:11px;height:16px;border-radius:2px;background:#ffdf38;display:block}
.rr-demo .source{font-size:12px;line-height:1.6;color:#bdc2cc;border-top:1px solid #3b404d;padding-top:18px}
.rr-demo .back{display:inline-block;margin-top:16px;font-size:14px}
@media(max-width:420px){.rr-demo .details{padding:12px}.rr-demo .event{grid-template-columns:35px 26px minmax(0,1fr);gap:7px}.rr-demo .timeline:before{left:54px}.rr-demo .symbol{width:26px;height:26px}.rr-demo .player{font-size:15px}}
</style>
<main class="rr-demo">
<div class="eyebrow">Radio Rubben · Kampdemonstrasjon</div>
<h1>Brann – Bodø/Glimt</h1>
<p class="note">Eksempel med registrerte hendelser fra 19. september 2026. Dette er et engangsuttrekk, ikke en løpende livesending.</p>
<section class="scoreboard" aria-label="Kampresultat">
<div class="status">SLUTT · TOPPSERIEN</div>
<div class="scoreline"><div class="club"><img src="https://www.radiorubben.no/wp-content/uploads/2026/09/SK-Brann-–-laglogo.png" alt="SK Brann laglogo" width="88" height="88">SK Brann</div><div class="score">4 – 0</div><div class="club"><img src="https://www.radiorubben.no/wp-content/uploads/2026/09/Bodo-Glimt-–-laglogo.png" alt="Bodø/Glimt laglogo" width="88" height="88">Bodø/Glimt</div></div>
<p class="matchmeta">Pause: 3–0<br>19. september 2026 · kl. 15.00 · Brann stadion</p>
</section>
<h2>Alle kamphendelser</h2>
<p class="note">16 registrerte hendelser · fra kampstart til slutt</p>
<ol class="timeline">
<?php foreach ($events as [$minute,$side,$kind,$player,$out,$score]):
$label = ['goal'=>'Mål','card'=>'Gult kort','sub'=>'Spillerbytte','end'=>'Kampslutt'][$kind];
$club = $side === 'home' ? 'SK Brann' : ($side === 'away' ? 'Bodø/Glimt' : '');
?>
<li class="event <?php echo esc_attr($kind . ' ' . $side); ?>">
<div class="minute"><?php echo (int)$minute; ?>′</div>
<div class="symbol" aria-hidden="true">
<?php if ($kind === 'card'): ?><svg viewBox="0 0 24 24"><rect x="6" y="3" width="12" height="18" rx="2" fill="#ffdf38"/></svg>
<?php elseif ($kind === 'sub'): ?><svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h15m-4-4 4 4-4 4" stroke="#76e6ac"/><path d="M20 17H5m4-4-4 4 4 4" stroke="#ff8794"/></svg>
<?php elseif ($kind === 'goal'): ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#fff" stroke="#131720" stroke-width="1.5"/><path d="m12 7 5 4-2 6H9l-2-6Z" fill="#131720"/><path d="M12 2v5M2.5 9l4.5 2m10 0 4.5-2M6 20l3-3m6 0 3 3" stroke="#131720" stroke-width="2"/></svg>
<?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="#e8edf3" stroke-width="2" stroke-linecap="round"><path d="M5 21V3m0 1h14l-3 4 3 4H5"/></svg><?php endif; ?>
</div>
<div class="details">
<div class="event-head"><span class="event-team"><?php if ($club): ?><img src="<?php echo esc_url($side === 'home' ? 'https://www.radiorubben.no/wp-content/uploads/2026/09/SK-Brann-–-laglogo.png' : 'https://www.radiorubben.no/wp-content/uploads/2026/09/Bodo-Glimt-–-laglogo.png'); ?>" alt="" width="22" height="22" loading="lazy"><?php endif; ?><span><?php echo esc_html($club ? $club . ' · ' . $label : $label); ?></span></span><?php if ($score): ?><span class="event-score"><?php echo esc_html($score); ?></span><?php endif; ?></div>
<div class="player"><?php if ($kind === 'sub'): ?><span class="in-label"><span aria-hidden="true">→ </span>Inn: </span><?php endif; ?><?php echo esc_html($player); ?></div>
<?php if ($out): ?><div class="out"><span class="out-label"><span aria-hidden="true">← </span>Ut: </span><?php echo esc_html($out); ?></div><?php endif; ?>
</div></li>
<?php endforeach; ?>
</ol>
<p class="source">Kilde: <a href="https://www.fotball.no/fotballdata/kamp/?fiksId=8998086&amp;underside=kamphendelser" target="_blank" rel="noopener noreferrer">fotball.no</a> · Kamp-ID 8998086.<br>Hendelsene er lest fra kamphendelsesfanen etter kampslutt. Eventuelle senere rettelser hos NFF er ikke automatisk hentet inn.</p>
<a class="back" href="<?php echo esc_url(remove_query_arg('rr_match_demo')); ?>">← Til den manuelle Bremnes-testen</a>
</main>
<?php get_footer(); ?>
