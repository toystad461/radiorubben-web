<?php
/** Presentation bootstrap. No storage, routes, cron jobs or application engines. */
defined( 'ABSPATH' ) || exit;
define( 'RR_THEME_VERSION', '2.0.0-rc.1' );
foreach ( array( 'setup', 'presentation', 'journalists', 'compatibility' ) as $rr_file ) {
    require_once get_parent_theme_file_path( '/inc/' . $rr_file . '.php' );
}
unset( $rr_file );
