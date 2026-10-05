<?php defined( 'ABSPATH' ) || exit; ?>
<header class="rr-page-heading">
<p class="rr-eyebrow">RADIO RUBBEN</p>
<h1><?php the_title(); ?></h1>
<?php if ( has_excerpt() && ! post_password_required() ) : ?>
<p class="rr-page-intro"><?php echo esc_html( get_the_excerpt() ); ?></p>
<?php endif; ?>
</header>
