<?php
/** Local fixture pages only. Run with wp eval-file on the isolated test site. */
if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== '127.0.0.1' || (int) wp_parse_url( home_url(), PHP_URL_PORT ) !== 8877 ) { throw new RuntimeException('Local staging only'); }
$results = array();
foreach ( array('content', 'application', 'editorial', 'football') as $template ) {
    $slug = 'rr-block-preview-' . $template;
    $existing = get_page_by_path( $slug );
    $content = '<!-- wp:paragraph --><p>Dette er en lokal testside for Radio Rubben sitt blokktema.</p><!-- /wp:paragraph -->';
    if ( 'football' === $template ) {
        $content .= '<!-- wp:heading {"anchor":"dagens-kamp"} --><h2 class="wp-block-heading" id="dagens-kamp">Dagens kamp og Dagens Bremnesing</h2><!-- /wp:heading --><!-- wp:shortcode -->[rr_match_day]<!-- /wp:shortcode --><!-- wp:heading {"anchor":"spillere-ute"} --><h2 class="wp-block-heading" id="spillere-ute">Bømlo-spillere ute</h2><!-- /wp:heading --><!-- wp:shortcode -->[rr_player_matches]<!-- /wp:shortcode -->';
    }
    $id = wp_insert_post(array('ID'=>$existing ? $existing->ID : 0, 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Blokktema · '.$template, 'post_name'=>$slug, 'post_content'=>$content), true);
    if ( is_wp_error($id) ) { throw new RuntimeException($id->get_error_message()); }
    update_post_meta($id, '_wp_page_template', $template);
    $results[$template] = get_permalink($id);
}
echo wp_json_encode($results) . "\n";
