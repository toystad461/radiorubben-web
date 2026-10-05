<?php defined( 'ABSPATH' ) || exit; ?>
<header class="rr-header">
<div class="rr-wrap rr-header-inner">
<a class="rr-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Radio Rubben hjem', 'radio-rubben-next' ); ?>"><?php rr_brand_header_logo(); ?></a>
<a class="rr-member-entry" href="<?php echo esc_url( rr_theme_page_url( array( 'min-side' ), '/min-side/' ) ); ?>" aria-label="<?php echo esc_attr( rr_theme_member_label() ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span>Min Rubben</span></a>
<button class="rr-menu-toggle" type="button" aria-label="<?php esc_attr_e( 'Åpne meny', 'radio-rubben-next' ); ?>" aria-expanded="false" aria-controls="rr-primary-nav"><span></span><span></span><span></span></button>
<?php get_template_part( 'template-parts/site/navigation' ); ?>
</div>
<?php if ( ! rr_home_universes_active() ) { get_template_part( 'template-parts/site/match-bar' ); } ?>
</header>
