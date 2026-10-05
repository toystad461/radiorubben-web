<?php
/** Template Name: Journalistprofil */
get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">RADIO RUBBEN · REDAKSJONEN</p><h1><?php the_title(); ?></h1></div></section><section class="rr-section rr-wrap"><div class="rr-content"><?php the_content(); ?></div>
<?php if ( ! post_password_required() ) { rr_theme_component( 'journalist' ); $id = rr_theme_journalist_id( get_the_ID() ); if ( $id ) {
$identity_query = array( array( 'key' => '_rr_journalist_id', 'value' => $id ) );
if ( 'rr-fotball' === $id ) { $identity_query = array( 'relation' => 'OR', $identity_query[0], array( 'relation' => 'AND', array( 'key' => '_rrfr_ai_match', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ), array( 'relation' => 'OR', array( 'key' => '_rr_journalist_id', 'compare' => 'NOT EXISTS' ), array( 'key' => '_rr_journalist_id', 'value' => '' ) ) ) ); }
$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'meta_query' => $identity_query, 'posts_per_page' => 12, 'paged' => max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) ) ) );
echo '<h2>' . esc_html__( 'Saker fra journalisten', 'radio-rubben-next' ) . '</h2><div class="rr-post-grid">'; while ( $q->have_posts() ) { $q->the_post(); get_template_part( 'template-parts/content/card' ); } echo '</div>'; rr_theme_pagination( $q ); wp_reset_postdata(); } } ?>
</section><?php endwhile; get_footer(); ?>
