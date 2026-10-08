<?php
/** Editor components and presentation compatibility; service logic remains in plugins. */
defined( 'ABSPATH' ) || exit;
add_action( 'init', function () {
    wp_register_script( 'rr-block-editor', get_theme_file_uri( '/assets/js/block-editor.js' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor' ), RR_THEME_VERSION, true );
    foreach ( array( 'legacy-home' => 'template-parts/home/legacy-layout', 'player' => 'template-parts/site/mini-player', 'match-bar' => 'template-parts/site/match-bar' ) as $name => $part ) {
        register_block_type( 'radio-rubben/' . $name, array(
            'api_version' => 3,
            'editor_script' => 'rr-block-editor',
            'supports' => array( 'html' => false, 'multiple' => false ),
            'render_callback' => static function () use ( $name, $part ) {
                // A compatibility homepage must never expose another page's protected content.
                if ( 'legacy-home' === $name && ! is_front_page() ) { return ''; }
                static $rendered = false;
                if ( $rendered ) { return ''; }
                $rendered = true;
                ob_start();
                get_template_part( $part );
                return ob_get_clean();
            },
        ) );
    }
} );
add_action( 'enqueue_block_assets', function () {
    wp_enqueue_style( 'rr-block-theme', get_theme_file_uri( '/assets/css/block-theme.css' ), array(), RR_THEME_VERSION );
} );
add_action( 'enqueue_block_editor_assets', function () { wp_enqueue_script( 'rr-block-editor' ); } );

/** Seed native Navigation blocks from the existing menu, without writing menu data. */
function rr_block_navigation( $location = 'primary' ) {
    $locations = (array) rr_theme_mod( 'nav_menu_locations', array() );
    $items = wp_get_nav_menu_items( absint( $locations[ $location ] ?? 0 ) ) ?: array();
    $build = static function ( $parent, $ancestors = array() ) use ( &$build, $items ) {
        $blocks = array();
        foreach ( $items as $item ) {
            if ( (int) $item->menu_item_parent !== $parent || in_array( (int) $item->ID, $ancestors, true ) ) { continue; }
            $children = $build( (int) $item->ID, array_merge( $ancestors, array( (int) $item->ID ) ) );
            $attrs = array( 'label' => wp_strip_all_tags( $item->title ), 'url' => esc_url_raw( $item->url ), 'kind' => 'custom' );
            if ( '_blank' === $item->target ) { $attrs['opensInNewTab'] = true; }
            $blocks[] = array( 'blockName' => $children ? 'core/navigation-submenu' : 'core/navigation-link', 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '', 'innerContent' => array_fill( 0, count( $children ), null ) );
        }
        return $blocks;
    };
    $blocks = $build( 0 );
    if ( ! $blocks && 'primary' === $location ) {
        foreach ( array( '/' => 'Hjem', '/lytt/' => 'Lytt', '/nyheter/' => 'Aktuelt', '/sport/' => 'Sport', '/min-side/' => 'Min Rubben', '/kontakt/' => 'Kontakt' ) as $path => $label ) {
            $blocks[] = array( 'blockName' => 'core/navigation-link', 'attrs' => array( 'label' => $label, 'url' => home_url( $path ), 'kind' => 'custom' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
        }
    }
    return serialize_blocks( $blocks );
}
