<?php
defined( 'ABSPATH' ) || exit;
// Read-only queries. Sports win over news when a post belongs to both.
$rr_is_sport = ! empty( $args['sport'] );
$rr_terms = $rr_is_sport ? array( 'sport', 'fotball' ) : array( 'latest-updates', 'lokale_nyheter' );
$rr_tax = array( 'relation' => 'AND', array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $rr_terms, 'include_children' => true, 'operator' => 'IN' ) );
if ( ! $rr_is_sport ) {
    $rr_tax[] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array( 'sport', 'fotball' ), 'include_children' => true, 'operator' => 'NOT IN' );
}
$rr_stories = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
    'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true,
    'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'tax_query' => $rr_tax ) );
$rr_section = $rr_is_sport ? 'sport' : 'nyheter';
$rr_heading = $rr_is_sport ? 'Sport fra Bømlo' : 'Siste nytt';
?>
<section class="rr-news-priority rr-wrap<?php echo $rr_is_sport ? ' rr-news-priority-sport' : ''; ?>" id="<?php echo esc_attr( $rr_section ); ?>" aria-labelledby="<?php echo esc_attr( $rr_section . '-tittel' ); ?>">
  <header class="rr-priority-head"><div><p class="rr-eyebrow"><?php echo $rr_is_sport ? 'SPORT' : 'RADIO RUBBEN / NYHETER'; ?></p><h2 id="<?php echo esc_attr( $rr_section . '-tittel' ); ?>"><?php echo esc_html( $rr_heading ); ?></h2></div><a class="rr-btn-outline" href="<?php echo esc_url( rr_theme_page_url( array( $rr_section ), '/' . $rr_section . '/' ) ); ?>"><?php echo $rr_is_sport ? 'Alle sportssaker' : 'Alle nyheter'; ?> <span aria-hidden="true">→</span></a></header>
  <?php if ( ! $rr_stories->posts ) : ?>
  <p class="rr-priority-empty"><?php echo $rr_is_sport ? 'Sportssaker vises her når de er publisert.' : 'Nyhetssaker vises her når de er publisert.'; ?></p>
  <?php else : ?>
  <div class="rr-priority-grid">
    <?php foreach ( $rr_stories->posts as $rr_position => $rr_story ) : ?>
    <article class="rr-priority-story<?php echo 0 === $rr_position ? ' rr-priority-lead' : ''; ?>">
      <div class="rr-priority-copy"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $rr_story ) ); ?>"><?php echo esc_html( get_the_date( 'j. F Y', $rr_story ) . ' kl. ' . get_the_time( 'H:i', $rr_story ) ); ?></time><h3><a href="<?php echo esc_url( get_permalink( $rr_story ) ); ?>"><?php echo esc_html( get_the_title( $rr_story ) ); ?></a></h3>
      <?php if ( 0 === $rr_position ) : ?><p><?php echo esc_html( wp_trim_words( get_the_excerpt( $rr_story ), 26 ) ); ?></p><?php endif; ?></div>
      <?php if ( 0 === $rr_position && has_post_thumbnail( $rr_story ) ) : ?><a class="rr-priority-image" href="<?php echo esc_url( get_permalink( $rr_story ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $rr_story, 'rr-card', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?></a><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
