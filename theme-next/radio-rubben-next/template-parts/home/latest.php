<?php defined( 'ABSPATH' ) || exit; ?>
<section class="rr-section rr-alt rr-home-latest"><div class="rr-wrap"><div class="rr-section-head"><div><p class="rr-eyebrow">AKTUELT</p><h2>Siste fra Radio Rubben.</h2><p>Artikler, tanker og lokale saker.</p></div><a class="rr-btn-outline" href="<?php echo esc_url( rr_theme_page_url( array( 'nyheter' ), '/nyheter/' ) ); ?>">Se alt →</a></div>
<?php $latest = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 6, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'no_found_rows' => true ) ); ?>
<?php if ( $latest->have_posts() ) : ?><div class="rr-post-grid rr-home-posts rr-compact-grid"><?php while ( $latest->have_posts() ) : $latest->the_post(); get_template_part( 'template-parts/content/card', null, array( 'compact' => true ) ); endwhile; ?></div>
<?php else : ?><p>Her kommer artikler og oppdateringer fra Radio Rubben.</p><?php endif; wp_reset_postdata(); ?>
</div></section>
