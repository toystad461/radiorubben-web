<?php
/**
 * Plugin Name: Radio Rubben Radio.co v1
 * Plugin URI: https://www.radiorubben.no/
 * Description: Enkel Radio.co-integrasjon for WordPress med admin-innstillinger, shortcode for spiller og shortcode for nå spiller.
 * Version: 1.0.0
 * Author: Thomas Magne Sellevold-Øystad / ChatGPT
 * License: GPL2+
 * Text Domain: radiorubben-radio-co
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RR_Radio_Co_Plugin {
    const OPTION_KEY = 'rr_radio_co_settings';
    const CACHE_GROUP = 'rr_radio_co';
    const STATUS_TRANSIENT = 'rr_radio_co_status_data';
    const INFO_TRANSIENT = 'rr_radio_co_info_data';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_shortcode( 'rr_player', [ $this, 'shortcode_player' ] );
        add_shortcode( 'rr_now_playing', [ $this, 'shortcode_now_playing' ] );
        add_shortcode( 'rr_listen_live_button', [ $this, 'shortcode_listen_live_button' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            'Radio Rubben Radio.co',
            'Radio Rubben Radio.co',
            'manage_options',
            'rr-radio-co',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting(
            'rr_radio_co_settings_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );

        add_settings_section(
            'rr_radio_co_main_section',
            'Hovedinnstillinger',
            function() {
                echo '<p>Legg inn Radio.co-verdiene dine her. Pluginen bruker Radio.co sin offentlige stations/info/status-løsning og player-oppsett via stream-URL.</p>';
            },
            'rr-radio-co'
        );

        $fields = [
            'station_id' => 'Station ID',
            'stream_url' => 'Stream URL',
            'station_name' => 'Stationsnavn',
            'default_artwork' => 'Standard cover-bilde URL',
            'accent_color' => 'Accent-farge',
            'autoplay' => 'Autoplay',
            'show_volume' => 'Vis volumslider',
            'cache_minutes' => 'Cache i minutter',
        ];

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                $key,
                $label,
                [ $this, 'render_field' ],
                'rr-radio-co',
                'rr_radio_co_main_section',
                [ 'key' => $key, 'label' => $label ]
            );
        }
    }

    public function sanitize_settings( $input ) {
        $old = $this->get_settings();

        $sanitized = [
            'station_id'       => isset( $input['station_id'] ) ? sanitize_text_field( $input['station_id'] ) : '',
            'stream_url'       => isset( $input['stream_url'] ) ? esc_url_raw( $input['stream_url'] ) : '',
            'station_name'     => isset( $input['station_name'] ) ? sanitize_text_field( $input['station_name'] ) : 'Radio Rubben',
            'default_artwork'  => isset( $input['default_artwork'] ) ? esc_url_raw( $input['default_artwork'] ) : '',
            'accent_color'     => isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '#F47A20',
            'autoplay'         => ! empty( $input['autoplay'] ) ? 1 : 0,
            'show_volume'      => ! empty( $input['show_volume'] ) ? 1 : 0,
            'cache_minutes'    => isset( $input['cache_minutes'] ) ? max( 1, absint( $input['cache_minutes'] ) ) : 5,
        ];

        if ( $old['station_id'] !== $sanitized['station_id'] || $old['stream_url'] !== $sanitized['stream_url'] ) {
            delete_transient( self::STATUS_TRANSIENT );
            delete_transient( self::INFO_TRANSIENT );
        }

        return $sanitized;
    }

    public function get_settings() {
        $defaults = [
            'station_id'      => '',
            'stream_url'      => '',
            'station_name'    => 'Radio Rubben',
            'default_artwork' => '',
            'accent_color'    => '#F47A20',
            'autoplay'        => 0,
            'show_volume'     => 1,
            'cache_minutes'   => 5,
        ];

        $settings = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( is_array( $settings ) ? $settings : [], $defaults );
    }

    public function render_field( $args ) {
        $settings = $this->get_settings();
        $key = $args['key'];
        $name = self::OPTION_KEY . '[' . $key . ']';
        $value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';

        switch ( $key ) {
            case 'autoplay':
            case 'show_volume':
                printf(
                    '<label><input type="checkbox" name="%1$s" value="1" %2$s> Aktiv</label>',
                    esc_attr( $name ),
                    checked( 1, (int) $value, false )
                );
                break;

            case 'accent_color':
                printf(
                    '<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="#F47A20">',
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                echo '<p class="description">Bruk Radio Rubben-oransje, for eksempel #F47A20.</p>';
                break;

            case 'cache_minutes':
                printf(
                    '<input type="number" min="1" max="60" name="%1$s" value="%2$d">',
                    esc_attr( $name ),
                    (int) $value
                );
                echo '<p class="description">Hvor lenge status-data skal caches før ny henting.</p>';
                break;

            case 'station_id':
                printf(
                    '<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="s48df93692">',
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                echo '<p class="description">Brukes mot public.radio.co-endepunktene.</p>';
                break;

            case 'stream_url':
                printf(
                    '<input type="url" class="regular-text code" name="%1$s" value="%2$s" placeholder="https://streaming.radio.co/xxxxx/listen">',
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                break;

            default:
                printf(
                    '<input type="text" class="regular-text" name="%1$s" value="%2$s">',
                    esc_attr( $name ),
                    esc_attr( $value )
                );
                break;
        }
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        $status = $this->get_station_status();
        $info   = $this->get_station_info();
        ?>
        <div class="wrap">
            <h1>Radio Rubben Radio.co</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rr_radio_co_settings_group' );
                do_settings_sections( 'rr-radio-co' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2>Shortcodes</h2>
            <p><code>[rr_player]</code> – spiller med enkel Radio Rubben-styling.</p>
            <p><code>[rr_now_playing]</code> – viser nå spiller med cover, artist og låt.</p>
            <p><code>[rr_listen_live_button]</code> – enkel lytteknapp.</p>

            <h2>Live forhåndsvisning</h2>
            <p><strong>Stationsnavn:</strong> <?php echo esc_html( $settings['station_name'] ); ?></p>
            <p><strong>API station ID:</strong> <?php echo esc_html( $settings['station_id'] ?: 'Ikke satt' ); ?></p>
            <p><strong>Stream URL:</strong> <?php echo esc_html( $settings['stream_url'] ?: 'Ikke satt' ); ?></p>

            <?php if ( is_wp_error( $status ) ) : ?>
                <div class="notice notice-warning"><p><?php echo esc_html( $status->get_error_message() ); ?></p></div>
            <?php else : ?>
                <p><strong>Nå spiller:</strong> <?php echo esc_html( $this->extract_now_playing_text( $status ) ); ?></p>
            <?php endif; ?>

            <?php if ( ! is_wp_error( $info ) && ! empty( $info['name'] ) ) : ?>
                <p><strong>API stasjonsnavn:</strong> <?php echo esc_html( $info['name'] ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function register_assets() {
        $settings = $this->get_settings();

        wp_register_style(
            'rr-radio-co-style',
            plugins_url( 'assets/css/rr-radio-co.css', __FILE__ ),
            [],
            '1.0.0'
        );

        wp_register_script(
            'rr-radio-co-script',
            plugins_url( 'assets/js/rr-radio-co.js', __FILE__ ),
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rr-radio-co-script',
            'rrRadioCo',
            [
                'accentColor' => $settings['accent_color'],
            ]
        );
    }

    private function build_api_url( $type = 'status' ) {
        $settings = $this->get_settings();
        if ( empty( $settings['station_id'] ) ) {
            return '';
        }

        if ( 'info' === $type ) {
            return 'https://public.radio.co/api/v2/' . rawurlencode( $settings['station_id'] );
        }

        return 'https://public.radio.co/stations/' . rawurlencode( $settings['station_id'] ) . '/status';
    }

    public function get_station_info() {
        $cached = get_transient( self::INFO_TRANSIENT );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = $this->build_api_url( 'info' );
        if ( empty( $url ) ) {
            return new WP_Error( 'missing_station_id', 'Station ID mangler i plugin-innstillingene.' );
        }

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $code || ! is_array( $data ) ) {
            return new WP_Error( 'api_error', 'Klarte ikke hente stasjonsinfo fra Radio.co.' );
        }

        set_transient( self::INFO_TRANSIENT, $data, $this->get_settings()['cache_minutes'] * MINUTE_IN_SECONDS );
        return $data;
    }

    public function get_station_status() {
        $cached = get_transient( self::STATUS_TRANSIENT );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = $this->build_api_url( 'status' );
        if ( empty( $url ) ) {
            return new WP_Error( 'missing_station_id', 'Station ID mangler i plugin-innstillingene.' );
        }

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 !== $code || ! is_array( $data ) ) {
            return new WP_Error( 'api_error', 'Klarte ikke hente status fra Radio.co.' );
        }

        set_transient( self::STATUS_TRANSIENT, $data, $this->get_settings()['cache_minutes'] * MINUTE_IN_SECONDS );
        return $data;
    }

    private function extract_now_playing( $status ) {
        $fallback = [
            'title'   => 'Direkte fra Radio Rubben',
            'artist'  => 'Live nå',
            'artwork' => '',
        ];

        if ( ! is_array( $status ) ) {
            return $fallback;
        }

        $source = [];
        if ( ! empty( $status['current_track'] ) && is_array( $status['current_track'] ) ) {
            $source = $status['current_track'];
        } elseif ( ! empty( $status['source'] ) && is_array( $status['source'] ) ) {
            $source = $status['source'];
        }

        $title = $source['title'] ?? $source['name'] ?? $fallback['title'];
        $artist = $source['artist'] ?? $source['metadata']['artist'] ?? $fallback['artist'];
        $artwork = $source['artwork_url_large'] ?? $source['artwork_url'] ?? $source['artwork'] ?? '';

        return [
            'title'   => $title,
            'artist'  => $artist,
            'artwork' => $artwork,
        ];
    }

    private function extract_now_playing_text( $status ) {
        $now = $this->extract_now_playing( $status );
        if ( empty( $now['artist'] ) ) {
            return $now['title'];
        }
        return $now['artist'] . ' – ' . $now['title'];
    }

    public function shortcode_player( $atts ) {
        $settings = $this->get_settings();
        if ( empty( $settings['stream_url'] ) ) {
            return '<div class="rr-radio-co-error">Radio.co stream-URL er ikke satt opp ennå.</div>';
        }

        wp_enqueue_style( 'rr-radio-co-style' );
        wp_enqueue_script( 'rr-radio-co-script' );

        $atts = shortcode_atts(
            [
                'title' => $settings['station_name'],
                'show_now_playing' => 'true',
            ],
            $atts,
            'rr_player'
        );

        $status = $this->get_station_status();
        $now = is_wp_error( $status ) ? $this->extract_now_playing( [] ) : $this->extract_now_playing( $status );
        $artwork = $now['artwork'] ?: $settings['default_artwork'];

        ob_start();
        ?>
        <div class="rr-player-card" style="--rr-accent: <?php echo esc_attr( $settings['accent_color'] ); ?>;">
            <div class="rr-player-top">
                <?php if ( $artwork ) : ?>
                    <div class="rr-player-artwrap">
                        <img class="rr-player-art" src="<?php echo esc_url( $artwork ); ?>" alt="Cover art">
                    </div>
                <?php endif; ?>
                <div class="rr-player-meta">
                    <div class="rr-player-badge">LIVE</div>
                    <h3 class="rr-player-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                    <?php if ( 'true' === strtolower( $atts['show_now_playing'] ) ) : ?>
                        <div class="rr-player-nowplaying"><?php echo esc_html( $this->extract_now_playing_text( $status ) ); ?></div>
                    <?php endif; ?>
                    <audio class="rr-audio" controls preload="none" <?php echo ! empty( $settings['autoplay'] ) ? 'autoplay' : ''; ?>>
                        <source src="<?php echo esc_url( $settings['stream_url'] ); ?>" type="audio/mpeg">
                        Nettleseren din støtter ikke avspilling av lyd.
                    </audio>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_now_playing( $atts ) {
        $settings = $this->get_settings();
        wp_enqueue_style( 'rr-radio-co-style' );

        $status = $this->get_station_status();
        if ( is_wp_error( $status ) ) {
            return '<div class="rr-radio-co-error">Klarte ikke hente nå spiller akkurat nå.</div>';
        }

        $now = $this->extract_now_playing( $status );
        $artwork = $now['artwork'] ?: $settings['default_artwork'];

        ob_start();
        ?>
        <div class="rr-now-playing-card" style="--rr-accent: <?php echo esc_attr( $settings['accent_color'] ); ?>;">
            <?php if ( $artwork ) : ?>
                <img class="rr-now-playing-art" src="<?php echo esc_url( $artwork ); ?>" alt="Cover art">
            <?php endif; ?>
            <div class="rr-now-playing-text">
                <span class="rr-now-playing-label">Nå spiller</span>
                <strong class="rr-now-playing-artist"><?php echo esc_html( $now['artist'] ); ?></strong>
                <span class="rr-now-playing-title"><?php echo esc_html( $now['title'] ); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode_listen_live_button( $atts ) {
        $settings = $this->get_settings();
        if ( empty( $settings['stream_url'] ) ) {
            return '';
        }

        wp_enqueue_style( 'rr-radio-co-style' );

        $atts = shortcode_atts(
            [
                'text' => 'Lytt live',
            ],
            $atts,
            'rr_listen_live_button'
        );

        return sprintf(
            '<a class="rr-listen-live-button" style="--rr-accent:%1$s;" href="%2$s" target="_blank" rel="noopener">%3$s</a>',
            esc_attr( $settings['accent_color'] ),
            esc_url( $settings['stream_url'] ),
            esc_html( $atts['text'] )
        );
    }
}

new RR_Radio_Co_Plugin();
