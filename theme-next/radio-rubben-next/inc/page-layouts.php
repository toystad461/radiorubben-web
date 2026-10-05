<?php
/** Shared layout presentation only; content and permissions stay with WordPress/plugins. */
defined( 'ABSPATH' ) || exit;

/** Links only to explicit H2 anchors on the current content page. Never renders blocks. */
function rr_theme_section_links( $blocks ) {
    $links = array();
    foreach ( $blocks as $block ) {
        $name = $block['blockName'] ?? '';
        $attrs = $block['attrs'] ?? array();
        $anchor = $attrs['anchor'] ?? '';
        if ( 'core/heading' === $name && 2 === ( $attrs['level'] ?? 2 ) && is_string( $anchor ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $anchor ) ) {
            $label = trim( wp_strip_all_tags( $block['innerHTML'] ?? '' ) );
            if ( '' !== $label ) { $links[ $anchor ] = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ); }
        }
        // Do not index query loops, hidden plugin output, synced patterns or other pages.
        if ( in_array( $name, array( 'core/group', 'core/columns', 'core/column' ), true ) ) {
            $links += rr_theme_section_links( $block['innerBlocks'] ?? array() );
        }
    }
    return $links;
}
