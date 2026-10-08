<?php defined( 'ABSPATH' ) || exit; ?>
<nav id="rr-primary-nav" class="rr-nav" aria-label="<?php esc_attr_e( 'Hovedmeny', 'radio-rubben-next' ); ?>">
<?php if ( rr_home_universes_active() ) : ?>
<ul><li><a href="#nettradio">Nettradio</a></li><li><a href="#nyheter">Nyheter</a></li><li><a href="#sport">Sport</a></li><li><details class="rr-u-all-menu"><summary>Mer</summary><?php rr_theme_menu( 'primary' ); ?></details></li></ul>
<?php else : ?>
<ul>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'lytt' ), '/lytt/' ) ); ?>">Lytt</a></li>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'nyheter' ), '/nyheter/' ) ); ?>">Aktuelt</a></li>
<li><details class="rr-nav-more"><summary>Musikken</summary><ul>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'pa-radio-rubben' ), '/pa-radio-rubben/' ) ); ?>">På Radio Rubben</a></li>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'reimagined' ), '/reimagined/' ) ); ?>">Reimagined – kjente låter i nye versjoner</a></li>
</ul></details></li>
<li><details class="rr-nav-more"><summary>Om Rubben</summary><ul>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'om-radio-rubben' ), '/om-radio-rubben/' ) ); ?>">Om Radio Rubben</a></li>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'samarbeid' ), '/samarbeid/' ) ); ?>">Samarbeid og annonsering</a></li>
</ul></details></li>
<li><a href="<?php echo esc_url( rr_theme_page_url( array( 'kontakt' ), '/kontakt/' ) ); ?>">Kontakt</a></li>
</ul>
<?php endif; ?>
</nav>

