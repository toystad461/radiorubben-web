<?php
/**
 * RRLive match overview.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$statuses     = current_user_can( 'edit_posts' ) ? array( 'publish', 'draft' ) : array( 'publish' );
$valid_views  = array( 'upcoming', 'played', 'all' );
$current_view = isset( $_GET['rr_view'] ) ? sanitize_key( wp_unslash( $_GET['rr_view'] ) ) : 'upcoming';
$current_view = in_array( $current_view, $valid_views, true ) ? $current_view : 'upcoming';
$club_request = isset( $_GET['rr_club'] ) ? sanitize_title( wp_unslash( $_GET['rr_club'] ) ) : '';

$match_ids = get_posts( array(
    'post_type'      => 'rr_match',
    'post_status'    => $statuses,
    'posts_per_page' => 200,
    'fields'         => 'ids',
    'orderby'        => 'ID',
    'order'          => 'ASC',
) );

$clubs = array();
foreach ( $match_ids as $match_id ) {
    $club_name = get_post_meta( $match_id, 'rr_club_name', true );
    if ( ! $club_name ) {
        $club_name = 'Bremnes';
    }
    $clubs[ sanitize_title( $club_name ) ] = $club_name;
}
natcasesort( $clubs );

$selected_club = isset( $clubs[ $club_request ] ) ? $clubs[ $club_request ] : '';
$done_statuses = array( 'finished', 'full_time', 'ended' );
$meta_query    = array( 'relation' => 'AND' );

if ( 'played' === $current_view ) {
    $meta_query[] = array(
        'key'     => 'rr_status',
        'value'   => $done_statuses,
        'compare' => 'IN',
    );
} elseif ( 'upcoming' === $current_view ) {
    $meta_query[] = array(
        'relation' => 'OR',
        array(
            'key'     => 'rr_status',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'rr_status',
            'value'   => $done_statuses,
            'compare' => 'NOT IN',
        ),
    );
}

if ( $selected_club ) {
    $meta_query[] = array(
        'key'     => 'rr_club_name',
        'value'   => $selected_club,
        'compare' => '=',
    );
}

$query_args = array(
    'post_type'      => 'rr_match',
    'post_status'    => $statuses,
    'posts_per_page' => 60,
    'meta_key'       => 'rr_kickoff',
    'orderby'        => 'meta_value',
    'order'          => 'played' === $current_view ? 'DESC' : 'ASC',
);
if ( count( $meta_query ) > 1 ) {
    $query_args['meta_query'] = $meta_query;
}
$matches = new WP_Query( $query_args );

$filter_url = get_permalink();
$view_url = function( $view ) use ( $filter_url, $club_request ) {
    $args = array();
    if ( 'upcoming' !== $view ) {
        $args['rr_view'] = $view;
    }
    if ( $club_request ) {
        $args['rr_club'] = $club_request;
    }
    return $args ? add_query_arg( $args, $filter_url ) : $filter_url;
};
$club_url = function( $club_slug ) use ( $filter_url, $current_view ) {
    $args = array();
    if ( 'upcoming' !== $current_view ) {
        $args['rr_view'] = $current_view;
    }
    if ( $club_slug ) {
        $args['rr_club'] = $club_slug;
    }
    return $args ? add_query_arg( $args, $filter_url ) : $filter_url;
};

$crest = function( $attachment_id, $team ) {
    $attachment_id = absint( $attachment_id );
    if ( $attachment_id ) {
        return '<span class="rrlive-board__logo">' . wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) ) . '</span>';
    }
    $words = preg_split( '/\s+/', trim( $team ) );
    $short = '';
    foreach ( array_slice( $words, 0, 2 ) as $word ) {
        $short .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
    }
    return '<span class="rrlive-board__crest" aria-hidden="true">' . esc_html( strtoupper( $short ?: '?' ) ) . '</span>';
};
?>
<main class="rrlive-board" id="main">
    <div class="rrlive-board__shell">
        <header class="rrlive-board__top">
            <div>
                <h1>RR<span>LIVE</span></h1>
                <p>Lokal fotball på Bømlo</p>
            </div>
            <div class="rrlive-board__sport">Fotball</div>
        </header>

        <div class="rrlive-board__source">
            <strong>KAMPDATA</strong>
            <span>Bekreftet fra NFF/Fotball.no. Strømmelenker vises bare når de er kontrollert.</span>
        </div>

        <div class="rrlive-board__filters">
            <nav class="rrlive-board__view-filter" aria-label="Velg kamptype">
                <?php foreach ( array( 'upcoming' => 'Kommende', 'played' => 'Spilte', 'all' => 'Alle' ) as $view_key => $view_label ) : ?>
                    <a href="<?php echo esc_url( $view_url( $view_key ) ); ?>"<?php echo $current_view === $view_key ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo esc_html( $view_label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ( count( $clubs ) > 1 ) : ?>
                <details class="rrlive-board__club-filter">
                    <summary><?php echo esc_html( $selected_club ? $selected_club : 'Klubber' ); ?></summary>
                    <div>
                        <a href="<?php echo esc_url( $club_url( '' ) ); ?>"<?php echo ! $selected_club ? ' class="is-active"' : ''; ?>>Alle klubber</a>
                        <?php foreach ( $clubs as $club_slug => $club_name ) : ?>
                            <a href="<?php echo esc_url( $club_url( $club_slug ) ); ?>"<?php echo $selected_club === $club_name ? ' class="is-active"' : ''; ?>><?php echo esc_html( $club_name ); ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>

        <?php if ( $matches->have_posts() ) : ?>
            <?php
            $current_day = '';
            while ( $matches->have_posts() ) :
                $matches->the_post();
                $id            = get_the_ID();
                $data          = function_exists( 'rrlive_get_match_data' ) ? rrlive_get_match_data( $id ) : array();
                $kick_raw      = $data['rr_kickoff'] ?? '';
                $kick_ts       = $kick_raw ? strtotime( $kick_raw ) : false;
                $day_key       = $kick_ts ? wp_date( 'Y-m-d', $kick_ts, wp_timezone() ) : 'unknown';
                $day_name      = $kick_ts ? wp_date( 'l j. F', $kick_ts, wp_timezone() ) : 'Dato ikke bekreftet';
                $time          = $kick_ts ? wp_date( 'H:i', $kick_ts, wp_timezone() ) : '–';
                $home          = $data['rr_home_team'] ?: 'Hjemmelag';
                $away          = $data['rr_away_team'] ?: 'Bortelag';
                $featured_club = $data['rr_club_name'] ?: 'Bremnes';
                $status        = sanitize_key( $data['rr_status'] ?? 'scheduled' );
                $is_live       = in_array( $status, array( 'live', 'in_progress', 'playing' ), true );
                $is_done       = in_array( $status, $done_statuses, true );
                $score_ok      = ( '' !== (string) ( $data['rr_score_home'] ?? '' ) && '' !== (string) ( $data['rr_score_away'] ?? '' ) );
                $url           = 'draft' === get_post_status( $id ) ? get_preview_post_link( $id ) : get_permalink( $id );

                if ( $day_key !== $current_day ) :
                    if ( $current_day ) {
                        echo '</div></section>';
                    }
                    $current_day = $day_key;
            ?>
                    <section class="rrlive-board__day">
                        <div class="rrlive-board__day-head">
                            <h2><?php echo esc_html( ucfirst( $day_name ) ); ?></h2>
                            <span><?php echo esc_html( $data['rr_competition'] ?? '' ); ?></span>
                        </div>
                        <div class="rrlive-board__list">
                <?php endif; ?>

                <article class="rrlive-board__card<?php echo $is_live ? ' is-live' : ''; ?>">
                    <a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $home . ' mot ' . $away ); ?>">
                        <div class="rrlive-board__card-top">
                            <?php if ( $is_live ) : ?>
                                <span class="rrlive-board__live">LIVE<?php echo ! empty( $data['rr_minute'] ) ? ' · ' . esc_html( absint( $data['rr_minute'] ) ) . "'" : ''; ?></span>
                            <?php elseif ( $is_done ) : ?>
                                <span>SLUTT</span>
                            <?php else : ?>
                                <span>IKKE STARTET</span>
                            <?php endif; ?>
                            <span><?php echo esc_html( $data['rr_venue'] ?: 'Arena ikke bekreftet' ); ?></span>
                        </div>

                        <div class="rrlive-board__match">
                            <div class="rrlive-board__teams">
                                <div class="rrlive-board__team">
                                    <?php echo $crest( $data['rr_home_logo'] ?? 0, $home ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <strong><?php echo esc_html( $home ); ?></strong>
                                </div>
                                <div class="rrlive-board__team">
                                    <?php echo $crest( $data['rr_away_logo'] ?? 0, $away ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <strong><?php echo esc_html( $away ); ?></strong>
                                </div>
                            </div>
                            <div class="rrlive-board__time">
                                <?php if ( ( $is_live || $is_done ) && $score_ok ) : ?>
                                    <strong><?php echo esc_html( $data['rr_score_home'] . '–' . $data['rr_score_away'] ); ?></strong>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $time ); ?></strong>
                                    <span><?php echo esc_html( 0 === strcasecmp( $featured_club, $home ) ? 'Hjemme' : 'Borte' ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rrlive-board__details">
                            <span><?php echo esc_html( $data['rr_competition'] ?: 'Turnering ikke bekreftet' ); ?></span>
                            <b>Kampinfo <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></b>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
                </div>
            </section>
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <?php
            $empty_title = 'played' === $current_view ? 'Ingen spilte kamper' : ( 'all' === $current_view ? 'Ingen kamper' : 'Ingen kommende kamper' );
            ?>
            <div class="rrlive-board__empty">
                <h2><?php echo esc_html( $empty_title ); ?></h2>
                <p>RRLive oppdateres når nye, bekreftede kamper er tilgjengelige.</p>
            </div>
        <?php endif; ?>

        <p class="rrlive-board__foot">Tidspunkt og arena kan bli endret av NFF. RRLive viser ikke kampinformasjon som ikke kan bekreftes.</p>
    </div>
</main>
<?php get_footer(); ?>
