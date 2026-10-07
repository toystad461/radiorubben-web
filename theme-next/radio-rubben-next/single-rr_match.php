<?php
/**
 * Single RRLive match template.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();
    if ( post_password_required() ) { the_content(); continue; }

    $match       = rr_theme_rrlive_data( get_the_ID() );
    $match = array_merge(array('rr_home_team'=>'','rr_away_team'=>'','rr_score_home'=>'','rr_score_away'=>'','rr_venue'=>''), is_array($match) ? $match : array());
    $status      = sanitize_key( $match['rr_status'] ?? 'scheduled' );
    $home        = $match['rr_home_team'] ?: 'Hjemmelag';
    $away        = $match['rr_away_team'] ?: 'Bortelag';
    $home_score  = $match['rr_score_home'];
    $away_score  = $match['rr_score_away'];
    $minute      = absint( $match['rr_minute'] ?? 0 );
    $is_live     = in_array( $status, array( 'live', 'in_progress', 'playing' ), true );
    $is_finished = in_array( $status, array( 'finished', 'full_time', 'ended' ), true );
    $status_text = $is_live ? 'LIVE' . ( $minute ? ' · ' . $minute . "'" : '' ) : ( $is_finished ? 'SLUTT' : 'IKKE STARTET' );
    $kickoff_raw = $match['rr_kickoff'] ?? '';
    $kickoff_ts  = $kickoff_raw ? strtotime( $kickoff_raw ) : false;
    $kickoff     = $kickoff_ts ? wp_date( 'j. F Y · H:i', $kickoff_ts, wp_timezone() ) : 'Ikke bekreftet';
    $kickoff_time = $kickoff_ts ? wp_date( 'H:i', $kickoff_ts, wp_timezone() ) : '–';
    $score       = ( '' !== (string) $home_score && '' !== (string) $away_score ) ? $home_score . '–' . $away_score : '–';
    $center_main = ( ! $is_live && ! $is_finished && $kickoff_ts ) ? $kickoff_time : $score;
    $center_sub  = $is_live ? 'Kampen pågår' : ( $is_finished ? 'Sluttresultat' : 'Kampstart' );
    $events      = is_array( $match['rr_events'] ?? null ) ? $match['rr_events'] : array();
    $home_lineup = is_array( $match['rr_home_lineup'] ?? null ) ? $match['rr_home_lineup'] : array();
    $away_lineup = is_array( $match['rr_away_lineup'] ?? null ) ? $match['rr_away_lineup'] : array();
    $table       = is_array( $match['rr_table'] ?? null ) ? $match['rr_table'] : array();
    $prototype   = 'Prototype' === ( $match['rr_data_source'] ?? '' ) || false !== stripos( get_the_title(), 'prototype' );

    usort( $events, function( $a, $b ) {
        return (int) ( $b['minute'] ?? 0 ) <=> (int) ( $a['minute'] ?? 0 );
    } );

    $crest = function( $attachment_id, $team ) {
        $attachment_id = absint( $attachment_id );
        if ( $attachment_id ) {
            return '<div class="rrlive-match__club-logo">' . wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => $team ) ) . '</div>';
        }
        $words = preg_split( '/\s+/', trim( $team ) );
        $short = '';
        foreach ( array_slice( $words, 0, 2 ) as $word ) {
            $short .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
        }
        return '<div class="rrlive-match__crest" aria-hidden="true">' . esc_html( strtoupper( $short ?: '?' ) ) . '</div>';
    };
?>
<div class="rrlive-match">
    <div class="rrlive-match__shell">
        <a class="rrlive-match__back" href="<?php echo esc_url( home_url( '/rrlive/' ) ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Til RRLive
        </a>

        <?php if ( $prototype ) : ?>
            <div class="rrlive-match__prototype"><b>PROTOTYPE</b><span>Alle kampdata på denne siden er eksempler.</span></div>
        <?php endif; ?>

        <section class="rrlive-match__hero<?php echo $is_live ? ' is-live' : ''; ?>" aria-labelledby="rrlive-match-title">
            <div class="rrlive-match__status">
                <span class="<?php echo $is_live ? 'rrlive-match__live' : ''; ?>"><?php echo esc_html( $status_text ); ?></span>
                <span><?php echo esc_html( $match['rr_venue'] ?: 'Arena ikke bekreftet' ); ?></span>
            </div>

            <h1 id="rrlive-match-title" class="screen-reader-text"><?php echo esc_html( $home . ' – ' . $away ); ?></h1>
            <div class="rrlive-match__scoreboard">
                <div class="rrlive-match__club">
                    <?php echo $crest( $match['rr_home_logo'] ?? 0, $home ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <strong><?php echo esc_html( $home ); ?></strong>
                </div>
                <div class="rrlive-match__result">
                    <b><?php echo esc_html( $center_main ); ?></b>
                    <span><?php echo esc_html( $center_sub ); ?></span>
                </div>
                <div class="rrlive-match__club">
                    <?php echo $crest( $match['rr_away_logo'] ?? 0, $away ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <strong><?php echo esc_html( $away ); ?></strong>
                </div>
            </div>

            <?php if ( ! empty( $match['rr_stream_provider'] ) || ! empty( $match['rr_radio_live'] ) ) : ?>
                <div class="rrlive-match__actions">
                    <?php if ( ! empty( $match['rr_stream_provider'] ) ) : ?>
                        <?php if ( ! empty( $match['rr_stream_url'] ) ) : ?>
                            <a class="rrlive-match__button" href="<?php echo esc_url( $match['rr_stream_url'] ); ?>" target="_blank" rel="noopener">
                        <?php else : ?>
                            <span class="rrlive-match__button" aria-disabled="true">
                        <?php endif; ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3V9Z"/></svg>
                            Se hos <?php echo esc_html( $match['rr_stream_provider'] ); ?>
                            <?php if ( ! empty( $match['rr_stream_requires_subscription'] ) ) : ?><small>Krever abonnement</small><?php endif; ?>
                        <?php echo ! empty( $match['rr_stream_url'] ) ? '</a>' : '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>

                    <?php if ( ! empty( $match['rr_radio_live'] ) ) : ?>
                        <a class="rrlive-match__button rrlive-match__button--red" href="<?php echo esc_url( $match['rr_radio_url'] ?: home_url( '/lytt/' ) ); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7L8 5Z"/></svg>
                            Hør Radio Rubben
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <nav class="rrlive-match__tabs" aria-label="Kampinnhold">
            <a href="#kampen">KAMPEN</a>
            <a href="#lagene">LAGENE</a>
            <a href="#tabellen">TABELL</a>
        </nav>

        <section class="rrlive-match__section rrlive-match__events" id="kampen">
            <h2>Kamphendelser</h2>
            <div class="rrlive-match__event-head"><strong><?php echo esc_html( $home ); ?></strong><span>Min.</span><strong><?php echo esc_html( $away ); ?></strong></div>
            <?php if ( $events ) : ?>
                <ol class="rrlive-match__event-list" aria-label="Kamphendelser, nyeste først">
                <?php foreach ( $events as $event ) :
                    $type = sanitize_key( $event['type'] ?? 'event' );
                    $side = ! empty( $event['team'] ) && 0 === strcasecmp( (string) $event['team'], (string) $away ) ? 'away' : 'home';
                    $icon_class = 'event';
                    $icon = '';
                    $label = $event['note'] ?? 'Hendelse';
                    if ( 'goal' === $type ) {
                        $icon_class = 'goal';
                        $icon = '⚽';
                        $label = $event['note'] ?: 'Spillemål';
                    } elseif ( 'yellow_card' === $type ) {
                        $icon_class = 'yellow';
                        $label = $event['note'] ?: 'Advarsel';
                    } elseif ( 'red_card' === $type ) {
                        $icon_class = 'red';
                        $label = $event['note'] ?: 'Utvisning';
                    } elseif ( in_array( $type, array( 'sub', 'substitution' ), true ) ) {
                        $icon_class = 'sub';
                        $icon = '⇄';
                        $label = $event['note'] ?: 'Spillerbytte';
                    }
                ?>
                    <li class="rrlive-match__event-row <?php echo esc_attr( $side ); ?>">
                        <span class="rrlive-match__event-icon <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"><?php echo esc_html( $icon ); ?></span>
                        <div class="rrlive-match__event-text"><strong><?php echo esc_html( $label ); ?></strong><span><?php echo esc_html( $event['player'] ?? '' ); ?></span></div>
                        <span class="rrlive-match__event-minute"><?php echo esc_html( (int) ( $event['minute'] ?? 0 ) . '′' ); ?></span>
                    </li>
                <?php endforeach; ?>
                </ol>
                <p class="rrlive-match__note"><?php echo esc_html( $match['rr_data_source'] ?: 'Datakilde ikke oppgitt' ); ?> · nyeste hendelse øverst.</p>
            <?php else : ?>
                <div class="rrlive-match__empty">Ingen kamphendelser er registrert ennå.</div>
            <?php endif; ?>
        </section>

        <section class="rrlive-match__section" id="lagene">
            <h2>Lagoppstillinger</h2>
            <div class="rrlive-match__squads">
                <?php foreach ( array( $home => $home_lineup, $away => $away_lineup ) as $team => $lineup ) : ?>
                    <div class="rrlive-match__squad">
                        <div class="rrlive-match__squad-head"><?php echo esc_html( $team ); ?></div>
                        <?php if ( $lineup ) : foreach ( $lineup as $player ) : ?>
                            <div class="rrlive-match__player"><b><?php echo esc_html( (int) ( $player['number'] ?? 0 ) ); ?></b><span class="rrlive-match__player-name"><?php echo esc_html( $player['player'] ?? 'Spiller' ); ?><?php if ( ! empty( $player['captain'] ) ) : ?><span class="rrlive-match__captain" title="Kaptein" aria-label="Kaptein">K</span><?php endif; ?></span><small><?php echo ! empty( $player['starter'] ) ? 'Starter' : 'Innbytter'; ?></small></div>
                        <?php endforeach; else : ?>
                            <div class="rrlive-match__empty">Lagoppstilling ikke bekreftet.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rrlive-match__section" id="tabellen">
            <h2>Tabell</h2>
            <?php if ( $table ) : ?>
                <table class="rrlive-match__table"><thead><tr><th>#</th><th>Lag</th><th>K</th><th>+/−</th><th>P</th></tr></thead><tbody>
                <?php foreach ( $table as $row ) : ?>
                    <tr<?php echo ! empty( $row['highlight'] ) ? ' class="is-highlighted"' : ''; ?>><td><?php echo esc_html( (int) ( $row['position'] ?? 0 ) ); ?></td><td><?php echo esc_html( $row['team'] ?? '' ); ?></td><td><?php echo esc_html( (int) ( $row['played'] ?? 0 ) ); ?></td><td><?php echo esc_html( $row['goal_difference'] ?? '' ); ?></td><td><?php echo esc_html( (int) ( $row['points'] ?? 0 ) ); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php else : ?>
                <div class="rrlive-match__empty">Tabellen blir tilgjengelig når den er bekreftet fra datakilden.</div>
            <?php endif; ?>
        </section>

        <section class="rrlive-match__section" id="kampinfo">
            <h2>Kampinfo</h2>
            <div class="rrlive-match__info">
                <div><small>Dato og tid</small><strong><?php echo esc_html( $kickoff ); ?></strong></div>
                <div><small>Arena</small><strong><?php echo esc_html( $match['rr_venue'] ?: 'Ikke bekreftet' ); ?></strong></div>
                <div><small>Turnering</small><strong><?php echo esc_html( $match['rr_competition'] ?: 'Ikke bekreftet' ); ?></strong></div>
                <div><small>Runde</small><strong><?php echo esc_html( $match['rr_round'] ?: 'Ikke bekreftet' ); ?></strong></div>
                <div><small>FIKS-ID</small><strong><?php echo esc_html( $match['rr_fiks_id'] ?: 'Ikke koblet' ); ?></strong></div>
                <div><small>Datakilde</small><strong><?php echo esc_html( $match['rr_data_source'] ?: 'Ikke oppgitt' ); ?></strong></div>
            </div>
        </section>

        <p class="rrlive-match__source">RRLive viser bare opplysninger som kan bekreftes fra valgt datakilde. Eksterne sendinger åpnes hos rettighetshaveren og kan kreve abonnement.</p>
    </div>
</div>
<?php
endwhile;

get_footer();
