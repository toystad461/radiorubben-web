<?php
/**
 * RRLive data layer.
 *
 * Registers a REST-ready match content type and editable metadata.
 * External providers can later write to the same fields through WordPress REST.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function rrlive_register_match_type() {
    $labels = array(
        'name'               => 'RRLive-kamper',
        'singular_name'      => 'RRLive-kamp',
        'add_new'            => 'Legg til kamp',
        'add_new_item'       => 'Legg til RRLive-kamp',
        'edit_item'          => 'Rediger RRLive-kamp',
        'new_item'           => 'Ny RRLive-kamp',
        'view_item'          => 'Vis RRLive-kamp',
        'search_items'       => 'Søk i RRLive-kamper',
        'not_found'          => 'Ingen kamper funnet',
        'not_found_in_trash' => 'Ingen kamper i papirkurven',
        'menu_name'          => 'RRLive',
    );

    register_post_type( 'rr_match', array(
        'labels'             => $labels,
        'public'             => true,
        'show_in_rest'       => true,
        'rest_base'          => 'rrlive-matches',
        'has_archive'        => false,
        'rewrite'            => array( 'slug' => 'rrlive/kamp', 'with_front' => false ),
        'menu_icon'          => 'dashicons-tickets-alt',
        'menu_position'      => 21,
        'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
        'show_in_nav_menus'  => false,
        'delete_with_user'   => false,
    ) );
}
add_action( 'init', 'rrlive_register_match_type' );

function rrlive_register_match_fields() {
    if ( ! function_exists( 'wpvibe_field_register' ) ) {
        return;
    }

    $text_fields = array(
        'rr_fiks_id'       => 'NFF/FIKS kamp-ID',
        'rr_ntb_id'        => 'NTB kamp-ID',
        'rr_sport'         => 'Idrett',
        'rr_competition'   => 'Turnering',
        'rr_round'         => 'Runde',
        'rr_kickoff'       => 'Kampstart (ISO 8601)',
        'rr_venue'         => 'Arena',
        'rr_home_team'     => 'Hjemmelag',
        'rr_away_team'     => 'Bortelag',
        'rr_home_team_id'  => 'Hjemmelag-ID',
        'rr_away_team_id'  => 'Bortelag-ID',
        'rr_club_name'     => 'Klubbfilter',
        'rr_status'        => 'Status',
        'rr_data_source'   => 'Datakilde',
        'rr_stream_provider' => 'Strømmetjeneste',
    );

    foreach ( $text_fields as $key => $label ) {
        wpvibe_field_register( 'rr_match', $key, array(
            'type'  => 'text',
            'label' => $label,
        ) );
    }

    wpvibe_field_register( 'rr_match', 'rr_home_logo', array(
        'type'  => 'image',
        'label' => 'Hjemmelagets logo',
    ) );
    wpvibe_field_register( 'rr_match', 'rr_away_logo', array(
        'type'  => 'image',
        'label' => 'Bortelagets logo',
    ) );

    foreach ( array(
        'rr_minute'     => 'Kampminutt',
        'rr_score_home' => 'Mål hjemmelag',
        'rr_score_away' => 'Mål bortelag',
    ) as $key => $label ) {
        wpvibe_field_register( 'rr_match', $key, array(
            'type'  => 'number',
            'label' => $label,
        ) );
    }

    wpvibe_field_register( 'rr_match', 'rr_stream_url', array(
        'type'  => 'url',
        'label' => 'Lenke til ekstern sending',
    ) );
    wpvibe_field_register( 'rr_match', 'rr_stream_requires_subscription', array(
        'type'  => 'checkbox',
        'label' => 'Sendingen krever abonnement',
    ) );
    wpvibe_field_register( 'rr_match', 'rr_radio_live', array(
        'type'  => 'checkbox',
        'label' => 'Radio Rubben sender kampen',
    ) );
    wpvibe_field_register( 'rr_match', 'rr_radio_url', array(
        'type'  => 'url',
        'label' => 'Lenke til Radio Rubben-spilleren',
    ) );

    $event_fields = array(
        'minute' => array( 'type' => 'number', 'label' => 'Minutt' ),
        'type'   => array( 'type' => 'text', 'label' => 'Hendelse' ),
        'team'   => array( 'type' => 'text', 'label' => 'Lag' ),
        'player' => array( 'type' => 'text', 'label' => 'Spiller' ),
        'score'  => array( 'type' => 'text', 'label' => 'Stilling' ),
        'note'   => array( 'type' => 'text', 'label' => 'Tilleggsinfo' ),
    );
    wpvibe_field_register( 'rr_match', 'rr_events', array(
        'type'       => 'repeater',
        'label'      => 'Kamphendelser',
        'sub_fields' => $event_fields,
    ) );

    $lineup_fields = array(
        'number'  => array( 'type' => 'number', 'label' => 'Draktnummer' ),
        'player'  => array( 'type' => 'text', 'label' => 'Spiller' ),
        'starter' => array( 'type' => 'checkbox', 'label' => 'Starter' ),
        'captain' => array( 'type' => 'checkbox', 'label' => 'Kaptein' ),
    );
    wpvibe_field_register( 'rr_match', 'rr_home_lineup', array(
        'type'       => 'repeater',
        'label'      => 'Hjemmelagets spillere',
        'sub_fields' => $lineup_fields,
    ) );
    wpvibe_field_register( 'rr_match', 'rr_away_lineup', array(
        'type'       => 'repeater',
        'label'      => 'Bortelagets spillere',
        'sub_fields' => $lineup_fields,
    ) );

    wpvibe_field_register( 'rr_match', 'rr_table', array(
        'type'       => 'repeater',
        'label'      => 'Tabell',
        'sub_fields' => array(
            'position'        => array( 'type' => 'number', 'label' => 'Plass' ),
            'team'            => array( 'type' => 'text', 'label' => 'Lag' ),
            'played'          => array( 'type' => 'number', 'label' => 'Kamper' ),
            'goal_difference' => array( 'type' => 'text', 'label' => 'Målforskjell' ),
            'points'          => array( 'type' => 'number', 'label' => 'Poeng' ),
            'highlight'       => array( 'type' => 'checkbox', 'label' => 'Fremhev lag' ),
        ),
    ) );
}
add_action( 'init', 'rrlive_register_match_fields', 20 );

function rrlive_register_internal_meta() {
    register_post_meta( 'rr_match', 'rr_last_synced', array(
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ) );
}
add_action( 'init', 'rrlive_register_internal_meta', 25 );

function rrlive_match_meta_keys() {
    return array(
        'rr_fiks_id', 'rr_ntb_id', 'rr_sport', 'rr_competition', 'rr_round',
        'rr_kickoff', 'rr_venue', 'rr_home_team', 'rr_away_team',
        'rr_home_team_id', 'rr_away_team_id', 'rr_club_name', 'rr_home_logo', 'rr_away_logo',
        'rr_status', 'rr_minute', 'rr_score_home', 'rr_score_away',
        'rr_data_source', 'rr_stream_provider', 'rr_stream_url',
        'rr_stream_requires_subscription', 'rr_radio_live', 'rr_radio_url',
        'rr_events', 'rr_home_lineup', 'rr_away_lineup', 'rr_table', 'rr_last_synced',
    );
}

function rrlive_get_match_data( $post_id ) {
    $post_id = absint( $post_id );
    $data    = array( 'id' => $post_id );

    foreach ( rrlive_match_meta_keys() as $key ) {
        $data[ $key ] = get_post_meta( $post_id, $key, true );
    }

    $data['title']     = get_the_title( $post_id );
    $data['permalink'] = get_permalink( $post_id );

    return $data;
}

function rrlive_match_admin_columns( $columns ) {
    return array(
        'cb'          => $columns['cb'],
        'title'       => 'Kamp',
        'rr_kickoff'  => 'Kampstart',
        'rr_status'   => 'Status',
        'rr_score'    => 'Resultat',
        'rr_source'   => 'Datakilde',
        'date'        => $columns['date'],
    );
}
add_filter( 'manage_rr_match_posts_columns', 'rrlive_match_admin_columns' );

function rrlive_match_admin_column_value( $column, $post_id ) {
    if ( 'rr_kickoff' === $column ) {
        echo esc_html( get_post_meta( $post_id, 'rr_kickoff', true ) ?: '—' );
    } elseif ( 'rr_status' === $column ) {
        echo esc_html( get_post_meta( $post_id, 'rr_status', true ) ?: '—' );
    } elseif ( 'rr_score' === $column ) {
        $home = get_post_meta( $post_id, 'rr_score_home', true );
        $away = get_post_meta( $post_id, 'rr_score_away', true );
        echo ( '' === $home || '' === $away ) ? '—' : esc_html( $home . '–' . $away );
    } elseif ( 'rr_source' === $column ) {
        echo esc_html( get_post_meta( $post_id, 'rr_data_source', true ) ?: '—' );
    }
}
add_action( 'manage_rr_match_posts_custom_column', 'rrlive_match_admin_column_value', 10, 2 );
