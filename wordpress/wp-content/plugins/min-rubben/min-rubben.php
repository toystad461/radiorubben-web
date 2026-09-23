<?php
/**
 * Plugin Name: Min Rubben
 * Description: Leserkontoer, modererte kommentarer og privat innboks for ønskelåter og hilsener.
 * Version: 1.1.0
 * Author: Radio Rubben AS
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: min-rubben
 */
if (!defined('ABSPATH')) { exit; }
final class RRM_Min_Rubben {
    const VERSION = '1.1.0';
    const TYPE = 'rrm_innsending';
    const CONSENT = 'Jeg tillater at Radio Rubben leser opp denne hilsenen og visningsnavnet mitt på lufta.';
    private static $instance;
    public static function boot() { if (!self::$instance) { self::$instance = new self(); } return self::$instance; }
    private function __construct() {
        add_action('init', [$this, 'register_type']);
        add_shortcode('min_rubben', [$this, 'shortcode']);
        add_action('template_redirect', [$this, 'no_cache']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        foreach (['register', 'submit', 'profile', 'report'] as $action) {
            add_action('admin_post_rrm_' . $action, [$this, 'handle_' . $action]);
        }
        add_action('admin_post_nopriv_rrm_register', [$this, 'handle_register']);
        foreach (['submit', 'profile', 'report'] as $action) {
            add_action('admin_post_nopriv_rrm_' . $action, [$this, 'login_required']);
        }
        add_action('after_password_reset', [$this, 'verified']);
        add_filter('authenticate', [$this, 'authenticate'], 100, 3);
        add_action('wp_login_failed', [$this, 'login_failed']);
        add_action('lostpassword_post', [$this, 'reset_limit']);
        add_filter('pre_option_comment_registration', '__return_true');
        add_filter('pre_option_comment_previously_approved', '__return_true');
        add_filter('pre_comment_approved', [$this, 'moderate'], 99, 2);
        add_filter('preprocess_comment', [$this, 'comment_identity']);
        add_filter('the_content', [$this, 'theme_comments'], 30);
        add_filter('comment_text', [$this, 'report_link'], 20, 2);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_rrm_settings', [$this, 'save_settings']);
        add_action('add_meta_boxes_' . self::TYPE, [$this, 'meta_boxes']);
        add_action('save_post_' . self::TYPE, [$this, 'save_state']);
        add_filter('wp_insert_post_data', [$this, 'keep_private'], 10, 2);
        add_filter('manage_' . self::TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'erasers']);
        add_action('delete_user', [$this, 'delete_user_data']);
        add_action('admin_notices', [$this, 'notice']);
    }
    public static function activate() {
        if (is_multisite()) { wp_die('Min Rubben 1.0 er laget for enkeltstående WordPress-nettsteder.'); }
        add_option('rrm_options', ['registration' => 0, 'page' => 0, 'turnstile_site' => '', 'turnstile_secret' => ''], '', false);
    }
    public static function options() { return wp_parse_args(get_option('rrm_options', []), ['registration' => 0, 'page' => 0, 'turnstile_site' => '', 'turnstile_secret' => '']); }
    public static function url() { $id = (int)self::options()['page']; return $id && get_post_status($id) === 'publish' ? get_permalink($id) : home_url('/min-rubben/'); }
    private function input($key) { return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : ''; }
    private function checked($key) { return $this->input($key) === '1'; }
    private function fail($text, $code = 400) { wp_die(esc_html($text), 'Min Rubben', ['response' => $code, 'back_link' => true]); }
    private function check($action) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { $this->fail('Bruk skjemaet på Min Rubben.', 405); }
        if (!wp_verify_nonce($this->input('_rrm_nonce'), 'rrm_' . $action)) { $this->fail('Skjemaet er utløpt. Last siden på nytt.', 403); }
    }
    private function done($message) { wp_safe_redirect(add_query_arg('rrm', $message, self::url())); exit; }
    private function ip_key() { return hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('auth')); }
    private function key($scope, $identity) { return 'rrm_' . substr(hash_hmac('sha256', $scope . '|' . $identity, wp_salt('nonce')), 0, 40); }
    // Fixed-window application throttling. Not a replacement for a hosting firewall.
    private function consume($scope, $identity, $limit, $seconds) {
        $key = $this->key($scope, $identity); $now = time(); $v = get_transient($key);
        if (!is_array($v) || $v['until'] <= $now) { $v = ['count' => 0, 'until' => $now + $seconds]; }
        if ($v['count'] >= $limit) { return false; }
        $v['count']++; set_transient($key, $v, max(1, $v['until'] - $now)); return true;
    }
    public function login_failed($username) { $this->consume('login', $this->ip_key(), 12, 900); }
    public function authenticate($user, $username, $password) {
        if ($username === '' || $password === '') { return $user; }
        $v = get_transient($this->key('login', $this->ip_key()));
        if (is_array($v) && $v['until'] > time() && $v['count'] >= 12) { return new WP_Error('rrm_rate', 'For mange innloggingsforsøk. Vent opptil 15 minutter.'); }
        if ($user instanceof WP_User && get_user_meta($user->ID, '_rrm_pending', true)) { return new WP_Error('rrm_verify', 'Bruk lenken i e-posten for å sette passord før du logger inn.'); }
        return $user;
    }
    public function reset_limit($errors) { if (!$this->consume('reset', $this->ip_key(), 5, 3600)) { $errors->add('rrm_rate', 'For mange forespørsler. Prøv igjen senere.'); } }
    public function verified($user) { if (get_user_meta($user->ID, '_rrm_pending', true)) { delete_user_meta($user->ID, '_rrm_pending'); update_user_meta($user->ID, '_rrm_verified_at', gmdate('c')); } }
    public function login_required() { $this->fail('Logg inn før du fortsetter.', 401); }
    private function require_user() {
        if (!is_user_logged_in()) { $this->login_required(); }
        if (get_user_meta(get_current_user_id(), '_rrm_pending', true)) { $this->fail('Bekreft e-posten ved å sette passord først.', 403); }
    }
    private function captcha($action) {
        $o = self::options();
        if (!$o['turnstile_site'] && !$o['turnstile_secret']) { return; }
        if (!$o['turnstile_site'] || !$o['turnstile_secret']) { $this->fail('Spamkontrollen er ikke ferdig konfigurert.', 503); }
        $token = $this->input('cf-turnstile-response');
        if (!$token || strlen($token) > 2048) { $this->fail('Fullfør spamkontrollen og prøv igjen.', 403); }
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['timeout' => 10, 'body' => ['secret' => $o['turnstile_secret'], 'response' => $token]]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { $this->fail('Spamkontrollen svarte ikke. Prøv igjen senere.', 503); }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success']) || ($data['action'] ?? '') !== $action || strtolower($data['hostname'] ?? '') !== strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST))) { $this->fail('Spamkontrollen kunne ikke bekreftes. Last siden på nytt.', 403); }
    }
    private function captcha_html($action) {
        $o = self::options();
        if ($o['turnstile_site']) {
            wp_enqueue_script('rrm-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($o['turnstile_site']) . '" data-action="' . esc_attr($action) . '"></div>';
        }
    }
    public function handle_register() {
        $this->check('register'); $o = self::options();
        if (is_multisite() || !$o['registration'] || !is_ssl()) { $this->fail('Registrering er ikke åpen akkurat nå.', 403); }
        if (!$this->consume('register', $this->ip_key(), 5, 3600)) { $this->fail('For mange forsøk. Prøv igjen senere.', 429); }
        if ($this->input('website') !== '') { $this->done('email'); }
        $this->captcha('rrm-register');
        $email = sanitize_email($this->input('email')); $name = sanitize_text_field($this->input('display_name'));
        if (!is_email($email) || strlen($email) > 100 || mb_strlen($name) < 2 || mb_strlen($name) > 60 || !$this->checked('rules')) { $this->fail('Fyll inn gyldig e-post, visningsnavn på 2–60 tegn og godta reglene.'); }
        // Same public response for an existing email; never change existing accounts.
        if (email_exists($email)) { $this->done('email'); }
        $id = wp_insert_user(['user_login' => 'rr_' . strtolower(wp_generate_password(18, false, false)), 'user_pass' => wp_generate_password(64, true, true), 'user_email' => $email, 'display_name' => $name, 'nickname' => $name, 'role' => 'subscriber', 'show_admin_bar_front' => 'false', 'meta_input' => ['_rrm_pending' => 1, '_rrm_created' => gmdate('c'), '_rrm_rules_version' => '1.0']]);
        if (is_wp_error($id)) { $this->fail('Kontoen kunne ikke opprettes. Prøv igjen senere.', 503); }
        wp_new_user_notification($id, null, 'user');
        $this->done('email');
    }
    public function handle_profile() {
        $this->require_user(); $this->check('profile');
        $name = sanitize_text_field($this->input('display_name'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) { $this->fail('Visningsnavnet må være 2–60 tegn.'); }
        $result = wp_update_user(['ID' => get_current_user_id(), 'display_name' => $name, 'nickname' => $name]);
        if (is_wp_error($result)) { $this->fail('Navnet kunne ikke lagres.', 503); }
        $this->done('profile');
    }
    public function register_type() {
        register_post_type(self::TYPE, ['labels' => ['name' => 'Lytterinnboks', 'singular_name' => 'Innsending', 'edit_item' => 'Behandle innsending', 'not_found' => 'Ingen innsendinger ennå.'], 'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_rest' => false, 'exclude_from_search' => true, 'rewrite' => false, 'query_var' => false, 'menu_icon' => 'dashicons-format-audio', 'supports' => [], 'map_meta_cap' => false, 'capabilities' => ['edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'do_not_allow']]);
    }
    public function keep_private($data, $postarr) { if (($data['post_type'] ?? '') === self::TYPE && $data['post_status'] !== 'trash') { $data['post_status'] = 'private'; } return $data; }
    public function handle_submit() {
        $this->require_user(); $this->check('submit');
        if (!$this->consume('submit', (string)get_current_user_id(), 5, 3600)) { $this->fail('Du kan sende inntil fem innsendinger per time.', 429); }
        if ($this->input('website') !== '') { $this->fail('Innsendingen ble stoppet av spamkontrollen.'); }
        $this->captcha('rrm-submit');
        $type = sanitize_key($this->input('kind')); $artist = sanitize_text_field($this->input('artist')); $song = sanitize_text_field($this->input('song')); $message = sanitize_textarea_field($this->input('message')); $consent = $this->checked('on_air');
        if (!in_array($type, ['wish', 'greeting'], true) || mb_strlen($artist) > 120 || mb_strlen($song) > 160 || mb_strlen($message) > 1500) { $this->fail('Kontroller feltene og lengden på meldingen.'); }
        if ($type === 'wish' && (!$artist || !$song)) { $this->fail('Fyll inn både artist og låttittel.'); }
        if ($type === 'greeting' && !$message) { $this->fail('Skriv hilsenen før du sender.'); }
        $user = wp_get_current_user();
        $id = wp_insert_post(wp_slash(['post_type' => self::TYPE, 'post_status' => 'private', 'post_author' => $user->ID, 'post_title' => $type === 'wish' ? $artist . ' – ' . $song : 'Hilsen fra ' . $user->display_name, 'post_content' => $message, 'meta_input' => ['_rrm_kind' => $type, '_rrm_artist' => $artist, '_rrm_song' => $song, '_rrm_name' => $user->display_name, '_rrm_state' => 'new', '_rrm_on_air' => $consent ? '1' : '0', '_rrm_consent_text' => $consent ? self::CONSENT : '', '_rrm_consent_at' => $consent ? gmdate('c') : '']]), true);
        if (is_wp_error($id)) { $this->fail('Vi kunne ikke lagre innsendingen. Prøv igjen.', 503); }
        $this->done('sent');
    }
    public function moderate($approved, $data) {
        if (is_wp_error($approved) || in_array($approved, ['spam', 'trash'], true)) { return $approved; }
        $uid = get_current_user_id();
        if (!$uid || get_user_meta($uid, '_rrm_pending', true)) { return new WP_Error('rrm_login', 'Logg inn med en bekreftet konto for å kommentere.'); }
        if (!in_array($data['comment_type'] ?? '', ['', 'comment'], true)) { return new WP_Error('rrm_type', 'Bare vanlige kommentarer er åpne.'); }
        if (!current_user_can('moderate_comments')) {
            if (!$this->consume('comment', (string)$uid, 10, 3600)) { return new WP_Error('rrm_rate', 'For mange kommentarer. Prøv igjen senere.'); }
            $prior = get_comments(['user_id' => $uid, 'status' => 'approve', 'type' => 'comment', 'count' => true]);
            if (!$prior) { return 0; }
        }
        // Respect stricter WordPress/plugin moderation decisions.
        return $approved;
    }
    public function comment_identity($data) {
        if (is_user_logged_in() && !current_user_can('moderate_comments')) { $u = wp_get_current_user(); $data['user_id'] = $u->ID; $data['comment_author'] = $u->display_name; $data['comment_author_email'] = $u->user_email; $data['comment_author_url'] = ''; }
        return $data;
    }
    public function theme_comments($content) {
        // The supplied Radio Rubben One 1.2/1.3 single.php does not render comments.
        // Limit this compatibility bridge to those exact versions, once per request.
        static $shown = false;
        $theme = wp_get_theme();
        if ($shown || is_admin() || is_feed() || !is_singular('post') || !in_the_loop() || !is_main_query() || post_password_required() || get_stylesheet() !== 'radio-rubben-wordpress-v1' || !in_array($theme->get('Version'), ['1.2.0', '1.3.0'], true) || (!comments_open() && !get_comments_number())) { return $content; }
        $shown = true;
        add_filter('comments_template', [$this, 'comments_file'], 99);
        ob_start(); comments_template(); $html = ob_get_clean();
        remove_filter('comments_template', [$this, 'comments_file'], 99);
        return $content . $html;
    }
    public function comments_file($path) { return __DIR__ . '/templates/comments.php'; }
    public function report_link($text, $comment) {
        if (is_admin() || is_feed() || !$comment || !is_user_logged_in() || (string)$comment->comment_approved !== '1' || !is_singular('post')) { return $text; }
        $text .= '<form class="rrm-report" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rrm_report"><input type="hidden" name="comment_id" value="' . (int)$comment->comment_ID . '">' . wp_nonce_field('rrm_report', '_rrm_nonce', true, false) . '<button type="submit">Meld fra om kommentaren</button></form>';
        return $text;
    }
    public function handle_report() {
        $this->require_user(); $this->check('report');
        if (!$this->consume('report', (string)get_current_user_id(), 10, 3600)) { $this->fail('For mange varsler. Prøv igjen senere.', 429); }
        $comment = get_comment(absint($this->input('comment_id')));
        if (!$comment || (string)$comment->comment_approved !== '1' || get_post_status($comment->comment_post_ID) !== 'publish' || post_password_required($comment->comment_post_ID)) { $this->fail('Kommentaren er ikke tilgjengelig.', 404); }
        add_comment_meta($comment->comment_ID, '_rrm_reported', '1', true);
        $this->done('reported');
    }
    public function no_cache() {
        if (is_singular() && has_shortcode((string)get_post_field('post_content', get_queried_object_id()), 'min_rubben')) { if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); } nocache_headers(); }
    }
    public function assets() { if (is_singular()) { wp_enqueue_style('rrm-style', plugins_url('assets/min-rubben.css', __FILE__), [], self::VERSION); } if (is_singular('post') && comments_open() && get_option('thread_comments')) { wp_enqueue_script('comment-reply'); } }
    private function form_start($action) {
        echo '<form class="rrm-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rrm_' . esc_attr($action) . '">'; wp_nonce_field('rrm_' . $action, '_rrm_nonce');
    }
    private function trap() { echo '<div class="rrm-trap" aria-hidden="true"><label>La dette feltet stå tomt<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>'; }
    public function shortcode() {
        $o = self::options(); ob_start();
        echo '<div class="rrm"><p class="rrm-kicker">RADIO RUBBEN · LYTTERKONTO</p><h2>Min Rubben</h2><p>En låt du savner? En hilsen du vil sende? Her hører vi fra deg.</p>';
        $messages = ['email' => 'Hvis adressen kan registreres, får du en e-post med lenke for å sette passord. Har du konto fra før, bruk «Glemt passord».', 'sent' => 'Takk! Innsendingen er mottatt og venter på gjennomgang. Det er ingen garanti for at den blir brukt.', 'profile' => 'Visningsnavnet er lagret.', 'reported' => 'Takk. Kommentaren er merket for gjennomgang.'];
        $code = isset($_GET['rrm']) && is_string($_GET['rrm']) ? sanitize_key(wp_unslash($_GET['rrm'])) : '';
        if (isset($messages[$code])) { echo '<p class="rrm-notice" role="status">' . esc_html($messages[$code]) . '</p>'; }
        if (!is_user_logged_in()) {
            echo '<p><a class="rrm-button" href="' . esc_url(wp_login_url(self::url())) . '">Logg inn</a> <a href="' . esc_url(wp_lostpassword_url(self::url())) . '">Glemt passord?</a></p>';
            if ($o['registration'] && is_ssl()) {
                echo '<section class="rrm-card"><h3>Opprett leserkonto</h3><p>E-posten din vises ikke offentlig. Velg et visningsnavn andre kan se.</p>';
                $this->form_start('register');
                echo '<label>Visningsnavn<input name="display_name" required minlength="2" maxlength="60" autocomplete="nickname"></label><label>E-post<input type="email" name="email" required maxlength="100" autocomplete="email"></label><label class="rrm-check"><input type="checkbox" name="rules" value="1" required> Jeg godtar reglene nedenfor.</label>';
                $this->trap(); $this->captcha_html('rrm-register'); echo '<button type="submit">Send meg registreringslenke</button></form></section>';
            } else { echo '<p>Registrering av nye kontoer er ikke åpen akkurat nå.</p>'; }
        } else {
            $u = wp_get_current_user();
            echo '<p>Logget inn som <strong>' . esc_html($u->display_name) . '</strong> · <a href="' . esc_url(wp_logout_url(self::url())) . '">Logg ut</a></p>';
            if (get_user_meta($u->ID, '_rrm_pending', true)) { echo '<p>Bekreft e-posten via passordlenken før du sender inn.</p></div>'; return ob_get_clean(); }
            echo '<section class="rrm-card"><h3>Send ønskelåt eller hilsen</h3><p>Innsendingen er bare synlig for deg og administratorene. Alt vurderes før bruk.</p>';
            $this->form_start('submit');
            $greeting = isset($_GET['rrm_kind']) && $_GET['rrm_kind'] === 'greeting';
            echo '<label>Hva vil du sende?<select name="kind"><option value="wish">Ønskelåt</option><option value="greeting" ' . selected($greeting, true, false) . '>Hilsen</option></select></label><div class="rrm-grid"><label>Artist (ved ønskelåt)<input name="artist" maxlength="120"></label><label>Låttittel (ved ønskelåt)<input name="song" maxlength="160"></label></div><label>Hilsen eller beskjed<textarea name="message" rows="5" maxlength="1500"></textarea></label><label class="rrm-check"><input type="checkbox" name="on_air" value="1"> ' . esc_html(self::CONSENT) . '</label><p class="rrm-small">Valgfritt. Uten avkryssing brukes meldingen bare som privat beskjed til oss. Ikke skriv personlige opplysninger om andre.</p>';
            $this->trap(); $this->captcha_html('rrm-submit'); echo '<button type="submit">Send til Radio Rubben</button></form></section>';
            echo '<section class="rrm-card"><h3>Mine siste innsendinger</h3>';
            $posts = get_posts(['post_type' => self::TYPE, 'post_status' => 'private', 'author' => $u->ID, 'numberposts' => 10, 'orderby' => 'date', 'order' => 'DESC']);
            if (!$posts) { echo '<p>Du har ikke sendt inn noe ennå.</p>'; }
            foreach ($posts as $post) { $state = get_post_meta($post->ID, '_rrm_state', true); echo '<article class="rrm-entry"><strong>' . esc_html($post->post_title) . '</strong><p>' . esc_html($post->post_content) . '</p><small>' . esc_html(get_the_date('d.m.Y H:i', $post)) . ' · ' . esc_html(self::states()[$state] ?? 'Ny') . '</small></article>'; }
            echo '</section><details class="rrm-card"><summary>Endre visningsnavn</summary>'; $this->form_start('profile'); echo '<label>Visningsnavn<input name="display_name" value="' . esc_attr($u->display_name) . '" minlength="2" maxlength="60" required></label><button type="submit">Lagre navn</button></form></details>';
        }
        echo '<section class="rrm-rules"><h3>God tone på Rubben</h3><p>Skriv respektfullt. Ingen trakassering, reklamespam eller private opplysninger om andre. Første kommentar må godkjennes. Vi kan avvise eller fjerne innhold som bryter reglene. Ønskelåter og hilsener blir ikke automatisk publisert eller sendt på lufta.</p>';
        $privacy = get_privacy_policy_url(); if ($privacy) { echo '<p><a href="' . esc_url($privacy) . '">Slik behandler vi personopplysninger</a></p>'; }
        echo '</section></div>'; return ob_get_clean();
    }
    public static function states() { return ['new' => 'Ny', 'approved' => 'Godkjent', 'used' => 'Brukt', 'rejected' => 'Avvist']; }
    public function admin_menu() {
        add_submenu_page('edit.php?post_type=' . self::TYPE, 'Min Rubben – oppsett', 'Oppsett', 'manage_options', 'rrm-settings', [$this, 'settings']);
        add_submenu_page('edit.php?post_type=' . self::TYPE, 'Rapporterte kommentarer', 'Rapporterte kommentarer', 'manage_options', 'rrm-reports', [$this, 'reports']);
    }
    public function notice() {
        if (current_user_can('manage_options') && !self::options()['page']) { echo '<div class="notice notice-info"><p>Min Rubben: opprett en side med <code>[min_rubben]</code>, og velg den under <a href="' . esc_url(admin_url('edit.php?post_type=' . self::TYPE . '&page=rrm-settings')) . '">Lytterinnboks → Oppsett</a>.</p></div>'; }
    }
    public function settings() {
        if (!current_user_can('manage_options')) { return; } $o = self::options();
        echo '<div class="wrap"><h1>Min Rubben – oppsett</h1><p>Opprett en WordPress-side med kortkoden <code>[min_rubben]</code>. Velg siden her og test passord-e-posten før offentlig lansering. Registrering krever HTTPS.</p><p>Tofaktor for administratorer, sikkerhetskopiering og spamfilter for kommentarer må settes opp i tillegg. Utvidelsen beskytter ikke hele nettstedet.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rrm_settings">'; wp_nonce_field('rrm_settings', '_rrm_nonce');
        echo '<p><label>Side for Min Rubben '; wp_dropdown_pages(['name' => 'page', 'selected' => (int)$o['page'], 'show_option_none' => 'Velg side']); echo '</label></p><p><label><input name="registration" type="checkbox" value="1" ' . checked($o['registration'], 1, false) . '> Åpne registrering av leserkontoer</label></p><h2>Valgfri Cloudflare Turnstile</h2><p>Bruk begge nøkler fra en widget konfigurert for nettstedets domenenavn. Dette beskytter registrering og innsending, ikke standard innloggingsside eller kommentarfelt.</p><p><label>Site key<br><input class="regular-text" name="turnstile_site" value="' . esc_attr($o['turnstile_site']) . '"></label></p><p><label>Secret key (tomt beholder eksisterende)<br><input type="password" class="regular-text" name="turnstile_secret" value="" autocomplete="new-password"></label></p><p><label><input type="checkbox" name="clear_turnstile" value="1"> Fjern begge Turnstile-nøkler</label></p>';
        submit_button('Lagre oppsett'); echo '</form><h2>Kommentarer</h2><p>Mens utvidelsen er aktiv kreves innlogging, og første kommentar holdes tilbake. Strengere WordPress-moderering beholdes. Aktiver kommentarer per innlegg under Diskusjon. Ingen gamle kommentarer åpnes eller endres automatisk.</p><h2>Lagring og rydding</h2><p>Innsendinger beholdes til du sletter dem. Bruk personverneksport/-sletting under Verktøy ved forespørsler. Avinstallering sletter ikke kontoer eller innsendinger.</p></div>';
    }
    public function save_settings() {
        if (!current_user_can('manage_options')) { $this->fail('Ingen tilgang.', 403); } $this->check('settings'); $o = self::options();
        $page = absint($this->input('page'));
        if ($page && (get_post_type($page) !== 'page' || !has_shortcode((string)get_post_field('post_content', $page), 'min_rubben'))) { $this->fail('Velg en side som inneholder [min_rubben].'); }
        $o['page'] = $page; $o['registration'] = $this->checked('registration') ? 1 : 0;
        if ($this->checked('clear_turnstile')) { $o['turnstile_site'] = ''; $o['turnstile_secret'] = ''; }
        else { $o['turnstile_site'] = sanitize_text_field($this->input('turnstile_site')); if ($this->input('turnstile_secret') !== '') { $o['turnstile_secret'] = sanitize_text_field($this->input('turnstile_secret')); } }
        if ((bool)$o['turnstile_site'] !== (bool)$o['turnstile_secret']) { $this->fail('Turnstile trenger både site key og secret key.'); }
        if ($o['registration'] && (!$page || get_post_status($page) !== 'publish')) { $this->fail('Publiser og velg Min Rubben-siden før du åpner registrering.'); }
        update_option('rrm_options', $o, false); wp_safe_redirect(admin_url('edit.php?post_type=' . self::TYPE . '&page=rrm-settings&saved=1')); exit;
    }
    public function meta_boxes($post) { add_meta_box('rrm-details', 'Innsending og bruk', [$this, 'details'], self::TYPE, 'normal', 'high'); }
    public function details($post) {
        if (!current_user_can('manage_options')) { return; } wp_nonce_field('rrm_state_' . $post->ID, '_rrm_state_nonce');
        $consent = get_post_meta($post->ID, '_rrm_on_air', true) === '1';
        echo '<p><strong>Fra:</strong> ' . esc_html(get_post_meta($post->ID, '_rrm_name', true)) . '</p><p><strong>Type:</strong> ' . esc_html(get_post_meta($post->ID, '_rrm_kind', true) === 'wish' ? 'Ønskelåt' : 'Hilsen') . '</p><p><strong>Artist:</strong> ' . esc_html(get_post_meta($post->ID, '_rrm_artist', true)) . '<br><strong>Låt:</strong> ' . esc_html(get_post_meta($post->ID, '_rrm_song', true)) . '</p><p style="white-space:pre-wrap">' . esc_html($post->post_content) . '</p><p><strong>' . ($consent ? 'Tillatelse til opplesing: JA' : 'Tillatelse til opplesing: NEI – privat beskjed') . '</strong></p>';
        if ($consent) { echo '<p>' . esc_html(get_post_meta($post->ID, '_rrm_consent_text', true)) . '<br>' . esc_html(get_post_meta($post->ID, '_rrm_consent_at', true)) . '</p>'; }
        echo '<label>Status <select name="rrm_state">'; foreach (self::states() as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected(get_post_meta($post->ID, '_rrm_state', true), $key, false) . '>' . esc_html($label) . '</option>'; } echo '</select></label><p>Status er bare intern behandling. Ingen automatisk publisering eller lydavspilling skjer. En ønskelåt kan spilles uten å lese den private meldingen eller navnet. Hilsener uten opplesingstillatelse kan bare ha status Ny eller Avvist.</p>';
    }
    public function save_state($id) {
        if (!current_user_can('manage_options') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id) || get_post_type($id) !== self::TYPE || !wp_verify_nonce($this->input('_rrm_state_nonce'), 'rrm_state_' . $id)) { return; }
        $state = sanitize_key($this->input('rrm_state')); if (!isset(self::states()[$state])) { return; }
        // A greeting without permission cannot be approved/marked as broadcast.
        if (get_post_meta($id, '_rrm_kind', true) === 'greeting' && get_post_meta($id, '_rrm_on_air', true) !== '1' && in_array($state, ['approved', 'used'], true)) { return; }
        update_post_meta($id, '_rrm_state', $state);
    }
    public function columns($columns) { return ['cb' => $columns['cb'] ?? '', 'title' => 'Innsending', 'rrm_state' => 'Status', 'rrm_permission' => 'Opplesing', 'date' => 'Dato']; }
    public function column($key, $id) { if ($key === 'rrm_state') { echo esc_html(self::states()[get_post_meta($id, '_rrm_state', true)] ?? 'Ny'); } if ($key === 'rrm_permission') { echo get_post_meta($id, '_rrm_on_air', true) === '1' ? 'Tillatt' : 'Ikke tillatt'; } }
    public function reports() {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>Rapporterte kommentarer</h1><p>De siste 100 merkede kommentarene. Behandle dem i WordPress sitt kommentarfelt. Rapportering skjuler ikke en kommentar automatisk.</p>';
        foreach (get_comments(['meta_key' => '_rrm_reported', 'meta_value' => '1', 'number' => 100, 'status' => 'all']) as $c) { echo '<div class="card"><strong>' . esc_html($c->comment_author) . '</strong><p>' . esc_html($c->comment_content) . '</p><a href="' . esc_url(admin_url('comment.php?action=editcomment&c=' . (int)$c->comment_ID)) . '">Behandle kommentar</a></div>'; } echo '</div>';
    }
    public function exporters($items) { $items['min-rubben'] = ['exporter_friendly_name' => 'Min Rubben', 'callback' => [$this, 'export_data']]; return $items; }
    public function erasers($items) { $items['min-rubben'] = ['eraser_friendly_name' => 'Min Rubben', 'callback' => [$this, 'erase_data']]; return $items; }
    private function personal_posts($email, $page = 1) { $u = get_user_by('email', $email); return $u ? get_posts(['post_type' => self::TYPE, 'post_status' => ['private', 'trash'], 'author' => $u->ID, 'posts_per_page' => 50, 'paged' => max(1, (int)$page), 'orderby' => 'ID', 'order' => 'ASC']) : []; }
    public function export_data($email, $page = 1) {
        $posts = $this->personal_posts($email, $page); $data = [];
        foreach ($posts as $p) { $data[] = ['group_id' => 'min-rubben', 'group_label' => 'Min Rubben – innsendinger', 'item_id' => 'rrm-' . $p->ID, 'data' => [['name' => 'Innsending', 'value' => $p->post_title], ['name' => 'Melding', 'value' => $p->post_content], ['name' => 'Visningsnavn', 'value' => get_post_meta($p->ID, '_rrm_name', true)], ['name' => 'Status', 'value' => get_post_meta($p->ID, '_rrm_state', true)], ['name' => 'Dato', 'value' => $p->post_date_gmt], ['name' => 'Samtykke', 'value' => get_post_meta($p->ID, '_rrm_consent_text', true)], ['name' => 'Samtykkedato', 'value' => get_post_meta($p->ID, '_rrm_consent_at', true)]]]; }
        return ['data' => $data, 'done' => count($posts) < 50];
    }
    public function erase_data($email, $page = 1) {
        // Always fetch page 1 while deleting, so records are not skipped.
        $posts = $this->personal_posts($email, 1); $removed = false; $retained = false;
        foreach ($posts as $p) { if (wp_delete_post($p->ID, true)) { $removed = true; } else { $retained = true; } }
        return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $retained ? ['Noen innsendinger kunne ikke slettes. Kontroller Lytterinnboks.'] : [], 'done' => $retained || count($posts) < 50];
    }
    public function delete_user_data($id) {
        // Do not reassign private listener messages to another account on deletion.
        $ids = get_posts(['post_type' => self::TYPE, 'post_status' => ['private', 'trash'], 'author' => (int)$id, 'numberposts' => -1, 'fields' => 'ids']);
        foreach ($ids as $post_id) { wp_delete_post($post_id, true); }
    }
}
register_activation_hook(__FILE__, ['RRM_Min_Rubben', 'activate']);
RRM_Min_Rubben::boot();

require_once __DIR__ . '/duel.php';
