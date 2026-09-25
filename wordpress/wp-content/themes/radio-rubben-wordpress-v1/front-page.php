<?php get_header();
$artist = get_theme_mod('rr_now_artist','Radio Rubben');
$title = get_theme_mod('rr_now_title','Kjente låter. Nye opplevelser.');
$next_artist = get_theme_mod('rr_next_artist','');
$next_title = get_theme_mod('rr_next_title','');
$live = (bool)get_theme_mod('rr_live_status', false);
?>
<?php $configured = (bool) trim((string)get_theme_mod('rr_stream_url','')); ?>
<section class="rr-home-intro rr-wrap">
 <div class="rr-home-copy"><p class="rr-eyebrow">FRA BØMLO. MED MUSIKKGLEDE.</p><h1>Ingen valg.<br>Bare <span>god radio.</span></h1><p>Kjente låter. Nye opplevelser.<br>Du setter på Radio Rubben. Vi ordner resten.</p><a class="rr-btn" href="#lytt">Til avspilleren ↓</a>
</div>
 <aside class="rr-radio-card" id="lytt" aria-label="Radio Rubben avspiller">
  <div class="rr-radio-top"><strong>RADIO RUBBEN</strong><span class="rr-broadcast <?php echo $configured && $live ? 'is-live' : ''; ?>"><?php echo !$configured ? 'SENDINGENE ER PÅ PAUSE' : ($live ? 'LIVE NÅ' : 'AUTOMATIKK'); ?></span></div>
  <div class="rr-radio-cover"><img src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben"></div>
  <div class="rr-radio-track"><div><p class="rr-eyebrow">LYTT TIL RUBBEN</p><h2><?php echo $configured ? esc_html($artist) : 'Vi bygger videre.'; ?></h2><?php if($configured): ?><p><?php echo esc_html($title); ?></p><?php endif; ?></div><button class="rr-main-play rr-play-toggle" aria-label="Spill Radio Rubben" <?php disabled(!$configured); ?>><span>▶</span></button></div>
  <p class="rr-player-message" role="status"><?php echo $configured ? 'Trykk play for å lytte.' : 'Sendingen er ikke tilgjengelig akkurat nå. Vi jobber videre med Radio Rubben.'; ?></p>
  <?php if($configured): ?><label class="rr-volume">Volum <input id="rr-volume" type="range" min="0" max="1" value="0.8" step="0.05" aria-label="Volum"></label><?php endif; ?>
  <?php if($configured && ($next_artist || $next_title)): ?><p class="rr-small">Om litt: <?php echo esc_html(trim($next_artist . ' – ' . $next_title, ' –')); ?></p><?php endif; ?>
 </aside>
</section>

<section class="rr-wrap rr-member-invite" aria-labelledby="rr-member-invite-title">
  <div><p class="rr-eyebrow">MIN RUBBEN</p><h2 id="rr-member-invite-title">Din plass på Radio Rubben.</h2><p>Spill quiz, ønsk musikk og send oss en hilsen.</p></div>
  <div class="rr-home-member-actions"><a class="rr-btn" href="<?php echo esc_url(home_url('/min-side/')); ?>"><?php echo esc_html(rr_member_entry_label()); ?> →</a>
<?php
$weekly_quiz = rr_quiz_enabled('weekly');
$live_quiz = rr_quiz_enabled('live');
if ($weekly_quiz || $live_quiz):
    $quiz_anchor = $weekly_quiz && $live_quiz ? '' : ($weekly_quiz ? '#rr-weekly' : '#rr-live-quiz');
    $quiz_label = $weekly_quiz && $live_quiz ? 'Velg quiz →' : ($weekly_quiz ? 'Spill ukens quiz →' : 'Gå til LIVE-quizen →');
?>
<p><a class="rr-btn-outline rr-home-quiz-link" href="<?php echo esc_url(home_url('/quiz/') . $quiz_anchor); ?>"><?php echo esc_html($quiz_label); ?></a></p>
<?php endif; ?>
</div></section>

<style>
.rr-home-latest .rr-home-posts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.rr-home-latest .rr-post-card{display:grid;grid-template-columns:88px minmax(0,1fr);grid-template-rows:auto auto;gap:6px 14px;padding:14px;border:1px solid #343740;border-radius:12px;background:#17191f;align-content:center;min-width:0}
.rr-home-latest .rr-post-thumb{grid-column:1;grid-row:1/3;display:block;width:88px;height:72px;margin:0;align-self:center;aspect-ratio:auto}
.rr-home-latest .rr-post-thumb img{width:88px;height:72px;object-fit:cover;border-radius:8px}
.rr-home-latest .rr-post-card h3{grid-column:2;grid-row:1;margin:0;font-size:1rem;line-height:1.4;overflow-wrap:anywhere}
.rr-home-latest .rr-post-card time{grid-column:2;grid-row:2;font-size:.78rem;color:#b7bac3}
.rr-home-latest .rr-post-card:not(:has(.rr-post-thumb)){grid-template-columns:minmax(0,1fr)}
.rr-home-latest .rr-post-card:not(:has(.rr-post-thumb)) h3,.rr-home-latest .rr-post-card:not(:has(.rr-post-thumb)) time{grid-column:1}
@media(max-width:650px){.rr-home-latest .rr-home-posts{grid-template-columns:1fr}.rr-home-latest .rr-post-card{padding:12px}}
</style>
<section class="rr-section rr-alt rr-home-latest"><div class="rr-wrap"><div class="rr-section-head"><div><p class="rr-eyebrow">AKTUELT</p><h2>Siste fra Radio Rubben.</h2><p>Artikler, tanker og lokale saker.</p></div><a class="rr-btn-outline" href="<?php echo esc_url(rr_one_get_first_existing_url(['nyheter'], '/nyheter/')); ?>">Se alt →</a></div><?php $latest=new WP_Query(['post_type'=>'post','posts_per_page'=>6,'post_status'=>'publish','ignore_sticky_posts'=>true]); if($latest->have_posts()): ?><div class="rr-post-grid rr-home-posts"><?php while($latest->have_posts()):$latest->the_post();?><article class="rr-post-card"><?php if(has_post_thumbnail()):?><a class="rr-post-thumb" href="<?php the_permalink();?>"><?php the_post_thumbnail('medium_large', ['style' => 'object-fit:contain;object-position:center;']);?></a><?php endif;?><h3><a href="<?php the_permalink();?>"><?php the_title();?></a></h3><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></article><?php endwhile;?></div><?php wp_reset_postdata(); else:?><p class="rr-large-copy">Her kommer artikler og oppdateringer fra Radio Rubben.</p><?php endif;?></div></section>


<section class="rr-section rr-home-rss" aria-labelledby="rr-home-rss-title">
  <div class="rr-wrap">
    <div class="rr-section-head"><div><p class="rr-eyebrow">LOKALT NÅ</p><h2 id="rr-home-rss-title">Siste fra Bømlo kommune.</h2><p>Aktuelle saker hentet fra kommunens RSS-feed.</p></div><a class="rr-btn-outline" href="<?php echo esc_url(home_url('/aktuelt-og-kunngjoringer-fra-bomlo-kommune/')); ?>">Se alle saker →</a></div>
    <?php if (shortcode_exists('rubben_rss_cards')) echo do_shortcode('[rubben_rss_cards urls="https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk" count="3"]'); ?>
    <p class="rr-home-rss-credit">Foto: <a href="https://commons.wikimedia.org/wiki/File:Mosterhamn.jpg" target="_blank" rel="noopener">Jan-Tore Egge / Wikimedia Commons</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC BY-SA 4.0</a> · beskåret i visning</p>
  </div>
</section>
<style>
.rr-home-rss{position:relative;isolation:isolate;padding-block:48px;background:#15171e}
.rr-home-rss::before{position:absolute;z-index:0;inset:0;content:"";background:url("<?php echo esc_url(wp_get_attachment_url(923)); ?>") center 58% / cover no-repeat;opacity:.2;pointer-events:none}
.rr-home-rss>.rr-wrap{position:relative;z-index:1}
.rr-home-rss-credit{margin:14px 0 0;color:#b9bec9;font-size:11px;text-align:right}
.rr-home-rss-credit a{color:inherit;text-decoration:underline}
.rr-home-rss .rubben-rss-cards__head{display:none}
.rr-home-rss .rubben-rss-cards__list{grid-template-columns:repeat(3,minmax(0,1fr));gap:15px!important}
.rr-home-rss .rubben-rss-cards__item{background:#20232b!important;border:1px solid #393d48!important;padding:20px!important;min-height:130px;color:#eef0f5}
.rr-home-rss .rubben-rss-cards__item a{color:#fff;text-decoration:none}
.rr-home-rss .rubben-rss-cards__item a:hover{text-decoration:underline;text-decoration-color:#f5cb45}
@media(max-width:760px){.rr-home-rss .rubben-rss-cards__list{grid-template-columns:1fr}.rr-home-rss{padding-block:32px}}
</style>

<div class="rr-home-weather-space"><?php rr_weather_card(); ?></div>\n<style>.rr-home-weather-space{padding-block:48px;background:#0b0d11}@media(max-width:760px){.rr-home-weather-space{padding-block:32px}}</style>

<section class="rr-wrap rr-home-music-brief" aria-labelledby="rr-music-title"><div><p class="rr-eyebrow">MUSIKKEN PÅ RUBBEN</p><h2 id="rr-music-title">Kjente låter. Nye opplevelser.</h2><p>Gode favoritter, nye bekjentskaper og kjente låter i nye versjoner.</p></div><nav aria-label="Utforsk radioen"><a class="rr-text-link" href="<?php echo esc_url(rr_one_get_first_existing_url(['reimagined'], '/reimagined/')); ?>">Oppdag Reimagined →</a><a class="rr-text-link" href="<?php echo esc_url(rr_one_get_first_existing_url(['pa-radio-rubben','pages-2'], '/pa-radio-rubben/')); ?>">På Radio Rubben →</a></nav></section>

<section class="rr-local-section"><div class="rr-wrap rr-local-grid rr-personal-grid"><figure class="rr-thomas-portrait"><?php echo wp_get_attachment_image(760, 'large', false, ['loading'=>'lazy', 'decoding'=>'async']); ?><figcaption>Thomas Magne Sellevold-Øystad · Studiobakgrunnen er AI-redigert.</figcaption></figure><div><p class="rr-eyebrow">MENNESKET BAK RADIO RUBBEN</p><h2>Liten radio.<br><span>Stor musikkglede.</span></h2><p>Hei, jeg er Thomas. Jeg bygger Radio Rubben her på Bømlo, med plass til gode låter, lokale historier og litt tørr humor.</p><a class="rr-text-link" href="<?php echo esc_url(rr_one_get_first_existing_url(['om-radio-rubben','about-us'], '/om-radio-rubben/')); ?>">Bli kjent med Rubben ↗</a></div></div></section>

<section class="rr-wrap rr-collab">
  <div><p class="rr-eyebrow">SAMARBEID</p><h2>Bli en del av Radio Rubben.</h2><p>Vil du samarbeide med en digital lokalradio med tydelig lokal identitet? Ta kontakt, så finner vi en løsning som passer.</p></div>
  <a class="rr-btn" href="<?php echo esc_url(rr_one_get_first_existing_url(['samarbeid','sponsor'], '/samarbeid/')); ?>">Snakk med oss →</a>
</section>
<?php get_footer(); ?>
