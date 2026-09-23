<?php
/**
 * Plugin Name: Rubben – Live RSS Cards (Auto source)
 * Description: Viser RSS som live lenkekort uten å lagre som innlegg. Setter kilde-tekst/logo/note automatisk basert på domenet i RSS-url.
 * Version: 1.1.0
 * Author: Radio Rubben AS
 */

if (!defined('ABSPATH')) exit;

class Rubben_Live_RSS_Cards {
    const OPT_DEFAULT_LABEL     = 'rubben_rss_default_label';
    const OPT_DEFAULT_LOGO_URL  = 'rubben_rss_default_logo_url';
    const OPT_CACHE_MIN         = 'rubben_rss_cache_minutes';
    const OPT_SOURCE_MAP        = 'rubben_rss_source_map'; // lines: host|label|logo_url|note

    public function __construct() {
        add_shortcode('rubben_rss_cards', [$this, 'shortcode']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /* -------------------------
     * Admin / Settings
     * ------------------------- */
    public function admin_menu() {
        add_options_page(
            'Rubben Live RSS Cards',
            'Rubben Live RSS Cards',
            'manage_options',
            'rubben-live-rss-cards',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {
        register_setting('rubben_rss_cards_group', self::OPT_DEFAULT_LABEL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Nyheter',
        ]);

        register_setting('rubben_rss_cards_group', self::OPT_DEFAULT_LOGO_URL, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);

        register_setting('rubben_rss_cards_group', self::OPT_CACHE_MIN, [
            'type' => 'integer',
            'sanitize_callback' => function($v){ return max(1, min(60, intval($v))); },
            'default' => 10,
        ]);

        register_setting('rubben_rss_cards_group', self::OPT_SOURCE_MAP, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_source_map'],
            'default' => $this->default_source_map(),
        ]);
    }

    private function default_source_map() {
        // Format per line:
        // host|label|logo_url|note
        // (logo_url and note can be empty)
        return implode("\n", [
            "nrk.no|Nyheter fra NRK||Direkte lenker til nrk.no",
            "bomlo.kommune.no|Nyheiter frå Bømlo kommune||Direkte lenker til bomlo.kommune.no",
        ]);
    }

    public function sanitize_source_map($value) {
        $lines = preg_split("/\r\n|\n|\r/", (string)$value);
        $clean = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Basic cleanup; keep pipes
            $clean[] = $line;
        }
        return implode("\n", $clean);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;

        $default_label = get_option(self::OPT_DEFAULT_LABEL, 'Nyheter');
        $default_logo  = get_option(self::OPT_DEFAULT_LOGO_URL, '');
        $cache_min     = intval(get_option(self::OPT_CACHE_MIN, 10));
        $source_map    = get_option(self::OPT_SOURCE_MAP, $this->default_source_map());

        ?>
        <div class="wrap">
            <h1>Rubben – Live RSS Cards</h1>
            <p>Viser RSS som <strong>live lenkekort</strong> (ingen lagring som innlegg). Kilde-tekst/logo/note settes automatisk basert på domenet i RSS-url.</p>

            <form method="post" action="options.php">
                <?php settings_fields('rubben_rss_cards_group'); ?>

                <h2>Standard</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Standard label</th>
                        <td>
                            <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPT_DEFAULT_LABEL); ?>" value="<?php echo esc_attr($default_label); ?>">
                            <p class="description">Brukes hvis domenet ikke finnes i mappingen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Standard logo URL</th>
                        <td>
                            <input type="url" class="regular-text" name="<?php echo esc_attr(self::OPT_DEFAULT_LOGO_URL); ?>" value="<?php echo esc_attr($default_logo); ?>">
                            <p class="description">Valgfritt.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cache (minutter)</th>
                        <td>
                            <input type="number" min="1" max="60" name="<?php echo esc_attr(self::OPT_CACHE_MIN); ?>" value="<?php echo esc_attr($cache_min); ?>">
                            <p class="description">Ytelse: vi cacher feed-resultat midlertidig (ikke innlegg).</p>
                        </td>
                    </tr>
                </table>

                <h2>Kilde-mapping (auto)</h2>
                <p>Én linje per kilde: <code>host|label|logo_url|note</code></p>
                <p>Eksempel: <code>nrk.no|Nyheter fra NRK|https://.../nrk-logo.svg|Direkte lenker til nrk.no</code></p>
                <textarea name="<?php echo esc_attr(self::OPT_SOURCE_MAP); ?>" rows="8" class="large-text code"><?php echo esc_textarea($source_map); ?></textarea>

                <?php submit_button('Lagre'); ?>
            </form>

            <hr>
            <h2>Shortcode</h2>
            <p><code>[rubben_rss_cards urls="https://www.nrk.no/vestland/siste.rss" count="12" heading="NRK Vestland"]</code></p>
            <p>Flere RSS-lenker: <code>urls="URL1,URL2"</code></p>
            <p>Valgfritt: <code>label="..."</code> <code>note="..."</code> <code>logo_url="..."</code> <code>show_desc="1"</code></p>
        </div>
        <?php
    }

    /* -------------------------
     * Helpers
     * ------------------------- */
    private function parse_urls($urls_raw) {
        $urls_raw = (string)$urls_raw;
        $parts = preg_split('/[\s,]+/', $urls_raw, -1, PREG_SPLIT_NO_EMPTY);
        $urls = [];
        foreach ($parts as $p) {
            $u = esc_url_raw(trim($p));
            if ($u) $urls[] = $u;
        }
        return array_values(array_unique($urls));
    }

    private function normalize_host($host) {
        $host = strtolower(trim((string)$host));
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }

    private function get_hosts_from_urls(array $urls) {
        $hosts = [];
        foreach ($urls as $u) {
            $h = parse_url($u, PHP_URL_HOST);
            $h = $this->normalize_host($h);
            if ($h) $hosts[$h] = true;
        }
        return array_keys($hosts);
    }

    private function parse_source_map() {
        $raw = (string)get_option(self::OPT_SOURCE_MAP, $this->default_source_map());
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        $map = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = explode('|', $line);
            $host  = $this->normalize_host($parts[0] ?? '');
            if ($host === '') continue;

            $label = trim((string)($parts[1] ?? ''));
            $logo  = trim((string)($parts[2] ?? ''));
            $note  = trim((string)($parts[3] ?? ''));

            $map[$host] = [
                'label' => $label,
                'logo_url' => $logo,
                'note' => $note,
            ];
        }

        return $map;
    }

    private function resolve_source_meta(array $urls) {
        $hosts = $this->get_hosts_from_urls($urls);
        $map = $this->parse_source_map();

        $default_label = get_option(self::OPT_DEFAULT_LABEL, 'Nyheter');
        $default_logo  = get_option(self::OPT_DEFAULT_LOGO_URL, '');

        // If exactly one host, use mapping if exists
        if (count($hosts) === 1) {
            $host = $hosts[0];
            $m = $map[$host] ?? null;

            $label = $m && $m['label'] !== '' ? $m['label'] : $default_label;
            $logo  = $m && $m['logo_url'] !== '' ? $m['logo_url'] : $default_logo;
            $note  = $m && $m['note'] !== '' ? $m['note'] : ('Direkte lenker til ' . $host);

            return [
                'host' => $host,
                'label' => $label,
                'logo_url' => $logo,
                'note' => $note,
            ];
        }

        // Multiple hosts → list them
        $host_list = implode(', ', array_slice($hosts, 0, 4));
        if (count($hosts) > 4) $host_list .= ' …';

        return [
            'host' => '',
            'label' => $default_label,
            'logo_url' => $default_logo,
            'note' => 'Direkte lenker til: ' . $host_list,
        ];
    }

    private function fetch_feed_items_cached($url, $cache_seconds) {
        $key = 'rubben_rss_cards_' . md5($url);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;

        include_once ABSPATH . WPINC . '/feed.php';

        $feed = fetch_feed($url);
        if (is_wp_error($feed)) {
            set_transient($key, [], min($cache_seconds, 300));
            return [];
        }

        $items = $feed->get_items(0, 50);
        if (!$items) $items = [];

        $out = [];
        foreach ($items as $it) {
            $title = (string)$it->get_title();        // keep as-is
            $link  = (string)$it->get_link();
            $dateU = $it->get_date('U');
            $desc  = (string)$it->get_description(); // optional display, not rewritten

            if (!$link || !$title) continue;

            $out[] = [
                'title' => $title,
                'link'  => $link,
                'dateU' => $dateU ? intval($dateU) : 0,
                'desc'  => $desc,
            ];
        }

        set_transient($key, $out, $cache_seconds);
        return $out;
    }

    private function render_header($heading, $label, $logo_url, $note) {
        $logo_html = '';
        if ($logo_url) {
            $logo_html = '<img src="' . esc_url($logo_url) . '" alt="" style="height:18px;width:auto;display:inline-block;vertical-align:middle;margin-right:10px;">';
        }

        $h = $heading
            ? '<div style="font-weight:800;font-size:18px;line-height:1.2;margin:0 0 6px 0;">' . esc_html($heading) . '</div>'
            : '';

        $note_html = $note
            ? '<div style="opacity:.8;font-size:13px;">' . esc_html($note) . '</div>'
            : '';

        $l = '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;opacity:.95;">'
           . '<div style="font-weight:700;">' . $logo_html . esc_html($label) . '</div>'
           . $note_html
           . '</div>';

        return '<div class="rubben-rss-cards__head" style="border:1px solid rgba(0,0,0,.10);border-left:4px solid rgba(0,0,0,.35);padding:12px 14px;border-radius:12px;margin:0 0 14px 0;background:rgba(0,0,0,.03);">'
             . $h . $l
             . '</div>';
    }

    /* -------------------------
     * Shortcode
     * ------------------------- */
    public function shortcode($atts) {
        $atts = shortcode_atts([
            'urls' => '',
            'url'  => '',
            'count' => 12,
            'heading' => '',
            'show_desc' => '0',

            // Optional overrides (if you want to force something)
            'label' => '',
            'note' => '',
            'logo_url' => '',
            'cache_min' => '',
        ], $atts, 'rubben_rss_cards');

        $urls_raw = $atts['urls'] ?: $atts['url'];
        $urls = $this->parse_urls($urls_raw);

        if (empty($urls)) {
            return '<p>Mangler RSS-url. Bruk f.eks. <code>[rubben_rss_cards urls="https://www.nrk.no/vestland/siste.rss"]</code></p>';
        }

        $count = max(1, min(50, intval($atts['count'])));
        $show_desc = ($atts['show_desc'] === '1');

        $cache_min = trim((string)$atts['cache_min']);
        $cache_min = ($cache_min !== '') ? max(1, min(60, intval($cache_min))) : intval(get_option(self::OPT_CACHE_MIN, 10));
        $cache_seconds = max(60, $cache_min * 60);

        // Resolve source meta automatically from URL hosts
        $auto = $this->resolve_source_meta($urls);

        // Allow overrides via shortcode attributes
        $label = trim((string)$atts['label']);
        if ($label === '') $label = $auto['label'];

        $logo_url = trim((string)$atts['logo_url']);
        if ($logo_url === '') $logo_url = $auto['logo_url'];

        $note = trim((string)$atts['note']);
        if ($note === '') $note = $auto['note'];

        $heading = trim((string)$atts['heading']);

        // Collect and merge items
        $all = [];
        foreach ($urls as $u) {
            $items = $this->fetch_feed_items_cached($u, $cache_seconds);
            foreach ($items as $it) {
                // De-dupe by link
                $all[(string)$it['link']] = $it;
            }
        }
        $all = array_values($all);

        // Sort by date desc
        usort($all, function($a, $b){
            return intval($b['dateU'] ?? 0) <=> intval($a['dateU'] ?? 0);
        });

        $all = array_slice($all, 0, $count);

        ob_start();
        echo '<div class="rubben-rss-cards">';
        echo $this->render_header($heading, $label, $logo_url, $note);

        echo '<div class="rubben-rss-cards__list" style="display:grid;gap:12px;">';

        if (empty($all)) {
            echo '<div style="opacity:.85;">Ingen saker å vise akkurat nå.</div>';
        } else {
            foreach ($all as $it) {
                $title = (string)$it['title'];
                $link  = (string)$it['link'];
                $dateU = intval($it['dateU'] ?? 0);
                $desc  = (string)$it['desc'];

                $time_html = '';
                if ($dateU > 0) {
                    $time_html = '<div style="opacity:.75;font-size:13px;margin-top:6px;">'
                               . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $dateU))
                               . '</div>';
                }

                echo '<article class="rubben-rss-cards__item" style="border:1px solid rgba(0,0,0,.08);padding:12px 14px;border-radius:12px;background:#fff;">';

                echo '<div style="font-weight:800;line-height:1.25;">'
                   . '<a href="' . esc_url($link) . '" target="_blank" rel="noopener nofollow">'
                   . esc_html($title)
                   . '</a>'
                   . '</div>';

                if ($show_desc && $desc !== '') {
                    echo '<div style="margin-top:8px;opacity:.92;">' . wp_kses_post($desc) . '</div>';
                }

                echo $time_html;
                echo '<div style="opacity:.8;font-size:13px;margin-top:6px;">' . esc_html($label) . '</div>';

                echo '</article>';
            }
        }

        echo '</div></div>';
        return ob_get_clean();
    }
}

new Rubben_Live_RSS_Cards();
