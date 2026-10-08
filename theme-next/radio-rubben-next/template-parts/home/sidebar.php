<?php defined( 'ABSPATH' ) || exit; ?>
<aside class="rr-home-sidebar" aria-label="Kamp, vær og annonser">
<?php
$match = rr_theme_match_data( 'header' );
if ( ! empty( $match['home'] ) && ! empty( $match['away'] ) ) {
    if ( empty( $match['cta'] ) ) { $match['cta'] = 'Se kampinfo →'; }
    rr_theme_component( 'match-card', $match );
}
if ( function_exists( 'rr_weather_card' ) ) {
    echo '<div class="rr-sidebar-weather">';
    rr_weather_card();
    echo '<a class="rr-text-link rr-weather-full-link" href="https://www.yr.no/nb/søk?q=Rubbestadneset">Se værvarselet på Yr →</a></div>';
}
rr_theme_sponsors( 'home-sidebar' );
$ad_name = rr_theme_text( rr_theme_mod( 'rr_sidebar_ad_name', '' ) );
if ( '' !== trim( $ad_name ) ) {
    rr_theme_component( 'sponsor', array(
        'name' => $ad_name,
        'image_id' => absint( rr_theme_mod( 'rr_sidebar_ad_image', 0 ) ),
        'url' => rr_theme_text( rr_theme_mod( 'rr_sidebar_ad_url', '' ) ),
        'text' => rr_theme_text( rr_theme_mod( 'rr_sidebar_ad_text', '' ) ),
    ) );
}
?>
</aside>

