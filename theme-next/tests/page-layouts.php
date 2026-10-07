<?php
require __DIR__ . '/page-layout-fixture.php';
$count = 0;
function check_layout( $ok, $message ) { global $count; if ( ! $ok ) { throw new RuntimeException( $message ); } $count++; }
function render_layout( $layout, $protected = false, $post = false, $paged = false ) {
    global $fixture;
    $fixture = array_merge( $fixture, array( 'loop'=>0, 'content_calls'=>0, 'protected'=>$protected, 'post'=>$post ) );
    $GLOBALS['multipage'] = $paged;
    ob_start(); get_template_part( 'templates/' . $layout ); return ob_get_clean();
}
foreach ( array( 'content', 'application', 'football', 'editorial' ) as $layout ) {
    foreach ( array( false, true ) as $protected ) {
        $html = render_layout( $layout, $protected );
        check_layout( 1 === $fixture['content_calls'], "$layout: content must render exactly once" );
        check_layout( 1 === substr_count( $html, '<main ' ) && 1 === substr_count( $html, '<h1>' ), "$layout: one main/title" );
        check_layout( ! str_contains( $html, 'rr-post-nav' ), "$layout: no post navigation on a page" );
        if ( $protected ) {
            check_layout( ! str_contains( $html, 'dagens-kamp' ) && ! str_contains( $html, get_the_excerpt() ) && ! str_contains( $html, 'data-fixture-component="source"' ) && ! str_contains( $html, 'Eksempelbilde' ), "$layout: protected content/metadata leak" );
        } elseif ( 'editorial' !== $layout ) {
            check_layout( 1 === substr_count( $html, 'aria-label="På denne siden"' ), "$layout: section links missing" );
        }
        $paged = render_layout( $layout, $protected, false, true );
        check_layout( str_contains( $paged, 'rr-page-links' ) && ! str_contains( $paged, 'rr-section-nav' ), "$layout: paginated content navigation" );
    }
}
$html = render_layout( 'editorial', false, true );
check_layout( str_contains( $html, 'rr-post-nav' ) && str_contains( $html, 'data-fixture-component="source"' ), 'Post navigation/source lost' );
$blocks = parse_blocks( '<!-- wp:heading {"anchor":"safe"} --><h2 id="safe">A &amp; B</h2><!-- /wp:heading --><!-- wp:heading {"anchor":"x\" onclick=\"bad"} --><h2>Unsafe</h2><!-- /wp:heading --><!-- wp:query --><div><!-- wp:heading {"anchor":"loop"} --><h2>Query</h2><!-- /wp:heading --></div><!-- /wp:query -->' );
check_layout( array( 'safe'=>'A & B' ) === rr_theme_section_links( $blocks ), 'Unsafe/query-loop anchors or escaped labels indexed' );
foreach ( array( 'section','cards','combined-football','combined-dashboard' ) as $pattern ) {
    $markup = fixture_pattern( $pattern ); $blocks = parse_blocks( $markup );
    check_layout( ! empty( array_filter( $blocks, static fn( $b ) => 'core/group' === $b['blockName'] ) ), "$pattern: core group not parsed" );
    check_layout( substr_count( $markup, '<!-- wp:group ' ) === substr_count( $markup, '<!-- /wp:group -->' ), "$pattern: unbalanced groups" );
}
check_layout( 3 === count( rr_theme_section_links( parse_blocks( fixture_pattern( 'combined-football' ) ) ) ), 'Football composition anchors' );
check_layout( 3 === count( rr_theme_section_links( parse_blocks( fixture_pattern( 'combined-dashboard' ) ) ) ), 'Dashboard composition anchors' );
echo "PASS: $count layout/content/password/pagination/anchor/pattern assertions.\n";
