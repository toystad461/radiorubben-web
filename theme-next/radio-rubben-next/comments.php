<?php defined( 'ABSPATH' ) || exit; if ( post_password_required() ) { return; } ?>
<section class="rr-wrap rr-section rr-comments" aria-label="<?php esc_attr_e( 'Kommentarer', 'radio-rubben-next' ); ?>">
<?php if ( have_comments() ) : ?><h2><?php esc_html_e( 'Kommentarer', 'radio-rubben-next' ); ?></h2><ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 48 ) ); ?></ol><?php the_comments_navigation(); endif; comment_form(); ?></section>
