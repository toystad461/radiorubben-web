<?php
defined( 'ABSPATH' ) || exit;
$radio = rr_home_radio_data();
$sport = rr_home_sport_data();
$major = rr_home_major_story();
$focus = rr_home_focus( $radio, $sport, $major, time() );
$news = rr_home_article_query();
$sports = rr_home_article_query( true );
$sections = array( 'radio', 'news', 'sport' );
if ( 'radio' !== $focus ) { $sections = array_merge( array( $focus ), array_diff( $sections, array( $focus ) ) ); }
?>
<div class="rr-universes" data-focus="<?php echo esc_attr( $focus ); ?>">
 <div class="rr-u-intro rr-wrap"><span>LOKALT HJERTE. STORE OPPLEVELSER.</span><nav aria-label="Forsidens tre univers"><a href="#nettradio">Nettradio</a><a href="#nyheter">Nyheter</a><a href="#sport">Sport</a></nav></div>
 <?php foreach ( $sections as $section ) : ?>
 <?php if ( 'radio' === $section ) : ?>
 <section id="nettradio" class="rr-u-radio rr-wrap" aria-labelledby="rr-u-radio-title">
  <div class="rr-u-radio-copy"><p class="rr-u-kicker">01 / NETTRADIO <span class="rr-u-status"><?php echo ! $radio['stream_url'] ? 'SENDING PÅ PAUSE' : ( rr_home_signal_live( $radio, time() ) ? '● LIVE FRA BØMLO' : 'NETTRADIO' ); ?></span></p>
   <h1 id="rr-u-radio-title">Din øy.<br>Din lyd.<br><em>Din Rubben.</em></h1><p class="rr-u-lead">Musikken du kjenner. Stemmene du har nær.<br>Radio med hjertet på Bømlo.</p>
   <div class="rr-u-listen"><button class="rr-play-toggle rr-u-play" aria-label="Spill Radio Rubben" <?php disabled( ! $radio['stream_url'] ); ?>>▶</button><strong><?php echo $radio['stream_url'] ? 'Hør live' : 'Sending på pause'; ?></strong><a href="<?php echo esc_url( rr_theme_page_url( array( 'lytt' ), '/lytt/' ) ); ?>">Til nettradioen ↗</a></div>
   <p class="rr-player-message rr-u-message" role="status"><?php echo $radio['stream_url'] ? 'Trykk play for å lytte.' : 'Sendingen er ikke tilgjengelig akkurat nå.'; ?></p>
  </div>
  <div class="rr-u-radio-art"><img class="rr-u-island" src="<?php echo esc_url( get_theme_file_uri( '/assets/images/mosterhamn.webp' ) ); ?>" alt="Mosterhamn på Bømlo"><div class="rr-u-art-overlay"><span>BØMLO, VESTLAND</span><div class="rr-u-wave" aria-hidden="true"><?php for ( $i = 0; $i < 23; $i++ ) : ?><i style="--bar:<?php echo (int) ( 18 + ( $i * 37 % 73 ) ); ?>%"></i><?php endfor; ?></div><p>Alltid nær.</p><small class="rr-u-photo-credit"><a href="https://commons.wikimedia.org/wiki/File:Mosterhamn.jpg" rel="external noopener">Foto: Jan-Tore Egge</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="external noopener">CC BY-SA 4.0</a> · beskåret i visning</small></div></div>
  <div class="rr-u-track"><div><span class="rr-u-kicker">NÅ SPILLES</span><strong><?php echo esc_html( trim( $radio['artist'] . ' ' . $radio['title'] ) ) ?: 'Spilleinformasjon kommer snart'; ?></strong></div><div><span class="rr-u-kicker">NESTE PROGRAM</span><strong><?php echo esc_html( $radio['next_program'] ?: 'Sendeplanen oppdateres' ); ?></strong></div><a href="<?php echo esc_url( rr_theme_page_url( array( 'pa-radio-rubben' ), '/pa-radio-rubben/' ) ); ?>">Programmer og sendeplan ↗</a></div>
 </section>
 <?php elseif ( 'news' === $section ) : ?>
 <section id="nyheter" class="rr-u-news rr-wrap" aria-labelledby="rr-u-news-title"><header class="rr-u-section-head"><div><p class="rr-u-kicker">02 / NYHETER</p><h2 id="rr-u-news-title">Det skjer <em>her.</em></h2></div><a href="<?php echo esc_url( rr_theme_page_url( array( 'nyheter' ), '/nyheter/' ) ); ?>">Alle nyheter ↗</a></header>
 <div class="rr-u-news-grid">
 <?php $lead_id = $major ? $major->ID : ( $news->posts[0]->ID ?? 0 ); ?>
 <?php if ( $lead_id ) : $lead = get_post( $lead_id ); ?>
  <article class="rr-u-story rr-u-main-story"><a href="<?php echo esc_url( get_permalink( $lead ) ); ?>"><div class="rr-u-story-image"><?php if ( has_post_thumbnail( $lead ) ) { echo get_the_post_thumbnail( $lead, 'rr-hero' ); } else { ?><div class="rr-u-placeholder" aria-hidden="true">RADIO<br>RUBBEN</div><?php } ?></div><div class="rr-u-story-copy"><p class="rr-u-kicker"><?php echo $major ? 'AKKURAT NÅ PÅ BØMLO' : 'SISTE FRA RADIO RUBBEN'; ?></p><h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3><p><?php echo esc_html( wp_trim_words( get_the_excerpt( $lead ), 28 ) ); ?></p><span>Les saken ↗</span></div></a></article>
 <?php else : ?><div class="rr-u-empty"><h3>Plass til de viktige historiene.</h3><p>Lokale nyheter vises her når de er godkjent og publisert.</p></div><?php endif; ?>
 <div class="rr-u-small-stories"><?php $shown = 0; foreach ( $news->posts as $story ) : if ( $story->ID === $lead_id || $shown >= 3 ) { continue; } $shown++; ?><article class="rr-u-small-story"><a href="<?php echo esc_url( get_permalink( $story ) ); ?>"><span class="rr-u-kicker"><?php echo esc_html( get_the_date( 'j. F', $story ) ); ?></span><h3><?php echo esc_html( get_the_title( $story ) ); ?></h3><span aria-hidden="true">↗</span></a></article><?php endforeach; ?></div></div>
 </section>
 <?php else : ?>
 <section id="sport" class="rr-u-sport" aria-labelledby="rr-u-sport-title"><div class="rr-wrap"><header class="rr-u-section-head"><div><p class="rr-u-kicker">03 / SPORT</p><h2 id="rr-u-sport-title">Hele øya.<br><em>Hele kampen.</em></h2></div><a href="<?php echo esc_url( rr_theme_page_url( array( 'sport' ), '/sport/' ) ); ?>">Alt om sport ↗</a></header>
 <div class="rr-u-sport-grid"><div class="rr-u-match"><p class="rr-u-kicker"><?php echo rr_home_signal_live( $sport, time() ) ? 'KAMPEN ER LIVE' : 'NESTE KAMP'; ?></p><?php if ( empty( $sport['match']['home'] ) || empty( $sport['match']['away'] ) ) : ?><h3>Vi venter på neste kamp.</h3><p>Kampinformasjon vises når den er bekreftet.</p><?php else : rr_theme_component( 'match-card', $sport['match'] ); endif; ?><div class="rr-u-sport-links"><a href="<?php echo esc_url( $sport['rrlive_url'] ?: rr_theme_page_url( array( 'rrlive' ), '/sport/' ) ); ?>">RR Live ↗</a><a href="<?php echo esc_url( rr_theme_page_url( array( 'kamp', 'dagenskamp', 'dagens-kamp' ), '/kamp/' ) ); ?>">Dagens Bremnesing ↗</a></div></div>
 <div class="rr-u-results"><p class="rr-u-kicker">SISTE RESULTAT</p><strong><?php echo esc_html( rr_theme_text( $sport['last_result'] ) ?: 'Resultat kommer når det er bekreftet' ); ?></strong><p class="rr-u-kicker">KOMMENDE KAMPER</p><?php $upcoming = is_array( $sport['upcoming'] ) ? array_slice( $sport['upcoming'], 0, 4 ) : array(); if ( $upcoming ) : ?><ul><?php foreach ( $upcoming as $fixture ) : ?><li><?php echo esc_html( rr_theme_text( $fixture ) ); ?></li><?php endforeach; ?></ul><?php else : ?><p>Se kampoversikten for oppdatert terminliste.</p><?php endif; ?><a href="<?php echo esc_url( rr_theme_page_url( array( 'nestekamp' ), '/nestekamp/' ) ); ?>">Kampoversikt ↗</a></div></div>
 <div class="rr-u-sports-stories"><?php foreach ( $sports->posts as $story ) : ?><article><a href="<?php echo esc_url( get_permalink( $story ) ); ?>"><?php if ( has_post_thumbnail( $story ) ) { echo get_the_post_thumbnail( $story, 'rr-card' ); } ?><p class="rr-u-kicker">SPORT · <?php echo esc_html( get_the_date( 'j. F', $story ) ); ?></p><h3><?php echo esc_html( get_the_title( $story ) ); ?></h3></a></article><?php endforeach; ?></div>
 </div></section>
 <?php endif; endforeach; ?>
 <div class="rr-u-more rr-wrap"><?php get_template_part( 'template-parts/home/member' ); get_template_part( 'template-parts/home/feed' ); do_action( 'rr_theme_home_after_news' ); if ( function_exists( 'rr_weather_card' ) ) { rr_weather_card(); } get_template_part( 'template-parts/home/about' ); rr_theme_sponsors( 'home-bottom' ); do_action( 'rr_theme_home_content' ); ?></div>
</div>
