<?php defined( 'ABSPATH' ) || exit; ?>
<section class="rr-subhero rr-generic-hero"><div class="rr-wrap"><p class="rr-eyebrow">FRA RADIO RUBBEN</p><h1><?php echo esc_html( is_home() ? __( 'Aktuelt', 'radio-rubben-next' ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
<?php if ( is_category() ) { rr_theme_category_nav(); $term = get_queried_object(); echo '<p class="rr-breadcrumb">' . wp_kses_post( get_term_parents_list( $term->term_id, 'category', array( 'separator' => ' / ', 'inclusive' => false ) ) ) . esc_html( $term->name ) . '</p>'; } ?>
<div class="rr-archive-desc"><?php echo wp_kses_post( get_the_archive_description() ); ?></div></div></section>
<section class="rr-section rr-wrap"><?php get_template_part( 'template-parts/content/loop' ); ?></section>
