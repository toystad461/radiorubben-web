<?php
/** Presentation bootstrap. No storage, routes, cron jobs or application engines. */
defined( 'ABSPATH' ) || exit;
define( 'RR_THEME_VERSION', '3.0.0-alpha.1' );
foreach ( array( 'setup', 'presentation', 'brand', 'journalists', 'compatibility', 'home-universes', 'page-layouts', 'block-theme' ) as $rr_file ) {
    require_once get_parent_theme_file_path( '/inc/' . $rr_file . '.php' );
}
unset( $rr_file );
