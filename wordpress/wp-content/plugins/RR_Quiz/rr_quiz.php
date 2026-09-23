<?php
/**
 * Plugin Name: Rubben Live Quiz (Hitster Mode)
 * Description: Live quiz for Radio Rubben: top-5 speed points, locking, sponsor area, anti-cheat, file picker for JSON in uploads, classic + Hitster-style (Year MC + Artist/Title text), Spotify preview clip (10s) with 1 play limit.
 * Version: 2.0.0
 * Author: Radio Rubben AS
 */

if (!defined('ABSPATH')) exit;

class Rubben_Live_Quiz {
  const OPT_PREFIX = 'rubben_live_quiz_';
  const OPT_DBVER  = 'rubben_live_quiz_dbver';
  const DBVER      = '2.0.0';

  const TABLE_PLAYERS = 'rubben_quiz_players';
  const TABLE_ANSWERS = 'rubben_quiz_answers';

  public function __construct() {
    register_activation_hook(__FILE__, [$this, 'activate']);
    add_action('plugins_loaded', [$this, 'maybe_upgrade_db']);
    add_action('admin_menu', [$this, 'admin_menu']);
    add_shortcode('rubben_live_quiz', [$this, 'shortcode']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    add_action('rest_api_init', [$this, 'register_routes']);
  }

  /* ---------------- DB ---------------- */

  private function db_install_or_upgrade() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $tp = $wpdb->prefix;

    $players = $tp . self::TABLE_PLAYERS;
    $answers = $tp . self::TABLE_ANSWERS;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql1 = "CREATE TABLE $players (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_key VARCHAR(64) NOT NULL,
      display_name VARCHAR(64) NOT NULL,
      created_at DATETIME NOT NULL,
      last_seen DATETIME NOT NULL,
      score INT NOT NULL DEFAULT 0,
      ip_hash VARCHAR(64) NOT NULL DEFAULT '',
      ua_hash VARCHAR(64) NOT NULL DEFAULT '',
      PRIMARY KEY (id),
      KEY session_key (session_key),
      KEY score (score),
      KEY ip_hash (ip_hash)
    ) $charset;";
    dbDelta($sql1);

    $sql2 = "CREATE TABLE $answers (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_key VARCHAR(64) NOT NULL,
      player_id BIGINT UNSIGNED NOT NULL,
      question_idx INT NOT NULL,
      answer_text TEXT NOT NULL,
      is_correct TINYINT NOT NULL DEFAULT 0,
      points_awarded INT NOT NULL DEFAULT 0,
      received_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_answer (session_key, player_id, question_idx),
      KEY session_q (session_key, question_idx),
      KEY player_id (player_id),
      KEY is_correct (is_correct)
    ) $charset;";
    dbDelta($sql2);

    update_option(self::OPT_DBVER, self::DBVER, false);
  }

  public function activate() {
    $this->db_install_or_upgrade();
    $this->ensure_session_defaults('rubben');
  }

  public function maybe_upgrade_db() {
    $ver = get_option(self::OPT_DBVER, '');
    if ($ver !== self::DBVER) $this->db_install_or_upgrade();
  }

  /* ---------------- Session config ---------------- */

  private function ensure_session_defaults($session_key) {
    $opt = $this->opt_key($session_key);
    $cfg = get_option($opt, null);

    if (!is_array($cfg)) {
      $cfg = [
        'title' => 'Radio Rubben Live Quiz',
        'status' => 'stopped', // running|stopped
        'current_idx' => 0,
        'opened_at' => 0,

        // Timing
        'question_seconds' => 60,
        'mode' => 'manual', // manual|auto
        'auto_start_ts' => 0,
        'gap_seconds' => 10,

        // Questions sources
        'questions_json' => $this->default_questions_json(),
        'questions_json_path' => '',
        'questions_file_choice' => '',

        // Per-question shuffle permutations
        'shuffles' => [], // idx => [perm]

        // Locking
        'locked' => [], // idx => timestamp
        'lock_on_next' => 1,

        // Sponsor
        'sponsor_html' => '<strong>Sponsor:</strong> (din sponsor her) • <a href="#">Les mer</a>',

        // Anti-cheat
        'max_players_per_ip' => 3,
        'enforce_same_ip' => 1,
        'require_access_code' => 0,
        'access_code' => '',
      ];
      update_option($opt, $cfg, false);
    } else {
      $defaults = [
        'questions_json' => $this->default_questions_json(),
        'questions_json_path' => '',
        'questions_file_choice' => '',
        'shuffles' => [],
        'locked' => [],
        'lock_on_next' => 1,
        'sponsor_html' => '',
        'max_players_per_ip' => 3,
        'enforce_same_ip' => 1,
        'require_access_code' => 0,
        'access_code' => '',
      ];
      $changed = false;
      foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $cfg)) {
          $cfg[$k] = $v;
          $changed = true;
        }
      }
      if ($changed) update_option($opt, $cfg, false);
    }
  }

  private function default_questions_json() {
    // Classic format fallback
    return json_encode([
      ["q"=>"Kven gav ut albumet \"Thriller\"?","choices"=>["Michael Jackson","Prince","Madonna","George Michael"],"a"=>0],
      ["q"=>"Kva band står bak \"Bohemian Rhapsody\"?","choices"=>["Queen","The Beatles","Pink Floyd","Led Zeppelin"],"a"=>0]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  }

  private function opt_key($session_key) {
    return self::OPT_PREFIX . sanitize_key($session_key);
  }

  private function get_cfg($session_key) {
    $this->ensure_session_defaults($session_key);
    $cfg = get_option($this->opt_key($session_key), []);
    return is_array($cfg) ? $cfg : [];
  }

  private function save_cfg($session_key, $cfg) {
    update_option($this->opt_key($session_key), $cfg, false);
  }

  /* ---------------- Utilities ---------------- */

  private function now_ts() { return time(); }

  private function compute_auto_idx($cfg) {
    $start = intval($cfg['auto_start_ts'] ?? 0);
    if ($start <= 0) return 0;
    $qsec = max(10, intval($cfg['question_seconds'] ?? 60));
    $gap  = max(0, intval($cfg['gap_seconds'] ?? 10));
    $block = $qsec + $gap;
    $elapsed = max(0, $this->now_ts() - $start);
    return intval(floor($elapsed / $block));
  }

  private function compute_window($cfg, $idx) {
    $qsec = max(10, intval($cfg['question_seconds'] ?? 60));

    if (($cfg['mode'] ?? 'manual') === 'auto') {
      $start = intval($cfg['auto_start_ts'] ?? 0);
      $gap = max(0, intval($cfg['gap_seconds'] ?? 10));
      $block = $qsec + $gap;
      $open = $start + ($idx * $block);
      $ends = $open + $qsec;
      return [$open, $ends];
    }

    $open = intval($cfg['opened_at'] ?? 0);
    if ($open <= 0) $open = $this->now_ts();
    $ends = $open + $qsec;
    return [$open, $ends];
  }

  private function is_locked($cfg, $idx) {
    $locked = $cfg['locked'] ?? [];
    return is_array($locked) && array_key_exists(strval($idx), $locked);
  }

  private function lock_question(&$cfg, $idx) {
    if (!isset($cfg['locked']) || !is_array($cfg['locked'])) $cfg['locked'] = [];
    $cfg['locked'][strval($idx)] = $this->now_ts();
  }

  private function unlock_question(&$cfg, $idx) {
    if (!isset($cfg['locked']) || !is_array($cfg['locked'])) return;
    unset($cfg['locked'][strval($idx)]);
  }

  private function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
  }

  private function get_user_agent() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return is_string($ua) ? $ua : '';
  }

  private function hash_token($s) {
    $salt = defined('AUTH_SALT') ? AUTH_SALT : 'rubben';
    return hash('sha256', (string)$s . '|' . $salt);
  }

  private function top5_points_for_rank($rank) {
    if ($rank < 1 || $rank > 5) return 0;
    return 6 - $rank; // 1=>5, 2=>4, ... 5=>1
  }

  private function norm_txt($s) {
    $s = remove_accents((string)$s);
    $s = mb_strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return $s;
  }

  /* ---------------- JSON file handling ---------------- */

  private function list_quiz_json_files() {
    $uploads = wp_get_upload_dir();
    $basedir = $uploads['basedir'] ?? '';
    if (!$basedir) return [];
    $dir = trailingslashit($basedir) . 'rubben-quiz';
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.json');
    if (!is_array($files)) return [];
    $out = [];
    foreach ($files as $abs) {
      $base = basename($abs);
      if (preg_match('/\.json$/i', $base)) $out[] = $base;
    }
    sort($out);
    return $out;
  }

  private function uploads_choice_to_path($filename) {
    $filename = basename((string)$filename);
    if (!$filename || !preg_match('/\.json$/i', $filename)) return '';
    return '/wp-content/uploads/rubben-quiz/' . $filename;
  }

  /**
   * Load JSON (array/object) from local file path under uploads.
   * Returns array on success, null on failure.
   */
  private function load_json_from_uploads_path($path_raw) {
    $path_raw = trim((string)$path_raw);
    if ($path_raw === '') return null;

    $relative = ltrim($path_raw, "/\\");
    $abs = ABSPATH . $relative;

    $uploads = wp_get_upload_dir();
    $uploads_dir = $uploads['basedir'] ?? '';
    if (!$uploads_dir) return null;

    $abs_real = realpath($abs);
    $uploads_real = realpath($uploads_dir);
    if (!$abs_real || !$uploads_real) return null;

    // Must be inside uploads
    if (strpos($abs_real, $uploads_real) !== 0) return null;

    // Must be JSON
    if (!preg_match('/\.json$/i', $abs_real)) return null;
    if (!is_readable($abs_real)) return null;

    $cache_key = 'rubben_quiz_json_' . md5($abs_real);
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $contents = file_get_contents($abs_real);
    if ($contents === false) return null;

    $data = json_decode($contents, true);
    if (!is_array($data)) return null;

    set_transient($cache_key, $data, 15);
    return $data;
  }

  /**
   * Supports:
   * - Classic: [ {q,choices,a,...}, ... ]
   * - Wrapped: {questions:[...]}
   * - Hitster: {mode:"hitster", songs:[{year,artist,title,audio:{url},clipSeconds}, ...]}
   */
  private function parse_questions($cfg) {
    $data = null;

    // 1) dropdown choice
    $choice = trim((string)($cfg['questions_file_choice'] ?? ''));
    if ($choice !== '') {
      $path = $this->uploads_choice_to_path($choice);
      $data = $this->load_json_from_uploads_path($path);
    }

    // 2) manual path
    if (!is_array($data)) {
      $data = $this->load_json_from_uploads_path($cfg['questions_json_path'] ?? '');
    }

    // 3) fallback json in DB
    if (!is_array($data)) {
      $data = json_decode((string)($cfg['questions_json'] ?? '[]'), true);
    }

    if (!is_array($data)) return [];

    // Hitster mode object
    if (isset($data['mode']) && $data['mode'] === 'hitster' && isset($data['songs']) && is_array($data['songs'])) {
      return $this->expand_hitster_songs($data['songs']);
    }

    // Wrapped classic
    if (isset($data['questions']) && is_array($data['questions'])) return $data['questions'];

    // Classic list
    if (array_keys($data) === range(0, count($data) - 1)) return $data;

    return [];
  }

  private function expand_hitster_songs($songs) {
    $questions = [];
    $i = 0;
    foreach ($songs as $s) {
      $year = intval($s['year'] ?? 0);
      $artist = trim((string)($s['artist'] ?? ''));
      $title  = trim((string)($s['title'] ?? ''));
      $clipSeconds = intval($s['clipSeconds'] ?? 10);
      $audio = (isset($s['audio']) && is_array($s['audio'])) ? $s['audio'] : null;
      $url = $audio ? trim((string)($audio['url'] ?? '')) : '';

      if ($year <= 0 || $artist === '' || $title === '' || $url === '') continue;

      if ($clipSeconds < 3) $clipSeconds = 10;
      if ($clipSeconds > 20) $clipSeconds = 20;

      $song_key = (string)($s['id'] ?? $i); // allow custom id, fallback index

      // Q1: Year (MC)
      $choices = $this->build_year_choices($year);
      $questions[] = [
        'type' => 'year_mc',
        'song_key' => $song_key,
        'q' => 'Hvilket år kom denne låta ut?',
        'choices' => $choices,
        'correct_value' => $year,
        'clipSeconds' => $clipSeconds,
        'audio' => [
          'source' => (string)($audio['source'] ?? 'spotify_preview'),
          'url' => $url
        ]
      ];

      // Q2: Identify (Text)
      $questions[] = [
        'type' => 'identify_text',
        'song_key' => $song_key,
        'q' => 'Skriv artist og låtnavn.',
        'answerHint' => 'Format: Artist – Låtnavn',
        'artist' => $artist,
        'title' => $title,
        'clipSeconds' => $clipSeconds,
        'audio' => [
          'source' => (string)($audio['source'] ?? 'spotify_preview'),
          'url' => $url
        ]
      ];

      $i++;
    }
    return $questions;
  }

  private function build_year_choices($year) {
    $min = 1950;
    $max = intval(date('Y'));
    $set = [$year];
    $tries = 0;
    while (count($set) < 4 && $tries < 60) {
      $tries++;
      $delta = wp_rand(1, 10) * (wp_rand(0, 1) ? 1 : -1);
      $cand = $year + $delta;
      if ($cand < $min || $cand > $max) continue;
      if (!in_array($cand, $set, true)) $set[] = $cand;
    }
    while (count($set) < 4) $set[] = min($max, max($min, $year + count($set)));
    shuffle($set);
    return array_values($set);
  }

  /* ---------------- Choice shuffle ---------------- */

  private function get_shuffle_for_question(&$cfg, $idx, $n) {
    if ($n <= 1) return range(0, max(0, $n - 1));

    if (!isset($cfg['shuffles']) || !is_array($cfg['shuffles'])) $cfg['shuffles'] = [];
    $key = strval($idx);
    $existing = $cfg['shuffles'][$key] ?? null;

    if (is_array($existing) && count($existing) === $n) {
      $tmp = $existing;
      sort($tmp);
      if ($tmp === range(0, $n - 1)) return $existing;
    }

    $perm = range(0, $n - 1);
    for ($i = $n - 1; $i > 0; $i--) {
      $j = wp_rand(0, $i);
      $t = $perm[$i];
      $perm[$i] = $perm[$j];
      $perm[$j] = $t;
    }
    $cfg['shuffles'][$key] = $perm;
    return $perm;
  }

  private function apply_shuffle_choices($choices, $perm) {
    $out = [];
    foreach ($perm as $p) {
      if (array_key_exists($p, $choices)) $out[] = $choices[$p];
    }
    return $out;
  }

  /* ---------------- Admin ---------------- */

  public function admin_menu() {
    add_options_page(
      'Rubben Live Quiz',
      'Rubben Live Quiz',
      'manage_options',
      'rubben-live-quiz',
      [$this, 'admin_page']
    );
  }

  public function admin_page() {
    if (!current_user_can('manage_options')) return;

    $session_key = isset($_GET['session']) ? sanitize_key($_GET['session']) : 'rubben';
    $cfg = $this->get_cfg($session_key);

    if (isset($_POST['rubben_quiz_action']) && check_admin_referer('rubben_quiz_admin')) {
      $action = sanitize_key($_POST['rubben_quiz_action']);

      if ($action === 'save') {
        $cfg['title'] = sanitize_text_field($_POST['title'] ?? $cfg['title']);
        $cfg['mode'] = in_array($_POST['mode'] ?? '', ['manual','auto'], true) ? $_POST['mode'] : 'manual';
        $cfg['question_seconds'] = max(10, min(300, intval($_POST['question_seconds'] ?? 60)));
        $cfg['gap_seconds'] = max(0, min(120, intval($_POST['gap_seconds'] ?? 10)));
        $cfg['lock_on_next'] = !empty($_POST['lock_on_next']) ? 1 : 0;
        $cfg['sponsor_html'] = wp_kses_post((string)($_POST['sponsor_html'] ?? ''));
        $cfg['max_players_per_ip'] = max(1, min(20, intval($_POST['max_players_per_ip'] ?? 3)));
        $cfg['enforce_same_ip'] = !empty($_POST['enforce_same_ip']) ? 1 : 0;
        $cfg['require_access_code'] = !empty($_POST['require_access_code']) ? 1 : 0;
        $cfg['access_code'] = sanitize_text_field((string)($_POST['access_code'] ?? ''));

        $cfg['questions_file_choice'] = sanitize_text_field((string)($_POST['questions_file_choice'] ?? ''));
        $cfg['questions_json_path'] = sanitize_text_field(trim((string)($_POST['questions_json_path'] ?? '')));

        $json = trim((string)($_POST['questions_json'] ?? ''));
        if ($json === '') $json = '[]'; // allow empty
        $parsed = json_decode($json, true);
        if (is_array($parsed)) {
          $cfg['questions_json'] = $json;
          $this->save_cfg($session_key, $cfg);
          echo '<div class="notice notice-success"><p>Lagret.</p></div>';
        } else {
          echo '<div class="notice notice-error"><p>Fallback JSON er ugyldig. Skriv <code>[]</code> hvis du kun bruker filvalg.</p></div>';
        }
      }

      if ($action === 'start') {
        $cfg['status'] = 'running';
        $cfg['shuffles'] = [];
        $cfg['locked'] = [];
        if ($cfg['mode'] === 'manual') {
          $cfg['opened_at'] = time();
        } else {
          $cfg['auto_start_ts'] = time();
          $cfg['current_idx'] = 0;
        }
        $this->save_cfg($session_key, $cfg);
        echo '<div class="notice notice-success"><p>Quiz startet.</p></div>';
      }

      if ($action === 'next') {
        $cfg['status'] = 'running';
        $current = max(0, intval($cfg['current_idx'] ?? 0));
        if (!empty($cfg['lock_on_next'])) $this->lock_question($cfg, $current);
        $cfg['current_idx'] = $current + 1;
        $cfg['opened_at'] = time();
        $this->save_cfg($session_key, $cfg);
        echo '<div class="notice notice-success"><p>Neste spørsmål åpnet. Forrige er låst.</p></div>';
      }

      if ($action === 'prev') {
        $cfg['current_idx'] = max(0, intval($cfg['current_idx'] ?? 0) - 1);
        $cfg['opened_at'] = time();
        $this->save_cfg($session_key, $cfg);
        echo '<div class="notice notice-success"><p>Forrige spørsmål vist (låsing beholdes).</p></div>';
      }

      if ($action === 'reopen') {
        $current = max(0, intval($cfg['current_idx'] ?? 0));
        $this->unlock_question($cfg, $current);
        $cfg['opened_at'] = time();
        $cfg['status'] = 'running';
        $this->save_cfg($session_key, $cfg);
        echo '<div class="notice notice-warning"><p>Spørsmålet er åpnet igjen (låsen fjernet).</p></div>';
      }

      if ($action === 'stop') {
        $cfg['status'] = 'stopped';
        $this->save_cfg($session_key, $cfg);
        echo '<div class="notice notice-warning"><p>Quiz stoppet.</p></div>';
      }

      if ($action === 'reset_scores') {
        global $wpdb;
        $tp = $wpdb->prefix;

        // Delete answers
        $wpdb->query($wpdb->prepare(
          "DELETE FROM {$tp}".self::TABLE_ANSWERS." WHERE session_key=%s",
          $session_key
        ));

        // Delete players (clears leaderboard + names)
        $wpdb->query($wpdb->prepare(
          "DELETE FROM {$tp}".self::TABLE_PLAYERS." WHERE session_key=%s",
          $session_key
        ));

        $cfg['current_idx'] = 0;
        $cfg['opened_at'] = 0;
        $cfg['auto_start_ts'] = 0;
        $cfg['status'] = 'stopped';
        $cfg['locked'] = [];
        $cfg['shuffles'] = [];
        $this->save_cfg($session_key, $cfg);

        echo '<div class="notice notice-success"><p>Hard reset: slettet alle svar og deltakere (toppliste + navn).</p></div>';
      }

      $cfg = $this->get_cfg($session_key);
    }

    $shortcode = '[rubben_live_quiz session="' . esc_attr($session_key) . '"]';
    $files = $this->list_quiz_json_files();
    $rest = esc_url_raw(rest_url('rubben-quiz/v1'));
    $nonce = wp_create_nonce('wp_rest');

    ?>
    <div class="wrap">
      <h1>Rubben Live Quiz</h1>
      <p><strong>Shortcode:</strong> <code><?php echo esc_html($shortcode); ?></code></p>
      <p style="opacity:.85">
        Legg quizfiler i <code>wp-content/uploads/rubben-quiz/</code>. Hitster-format: <code>{"mode":"hitster","songs":[...]}</code>.
      </p>

      <form method="post">
        <?php wp_nonce_field('rubben_quiz_admin'); ?>
        <input type="hidden" name="rubben_quiz_action" value="save" />

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">Session key</th>
            <td><code><?php echo esc_html($session_key); ?></code></td>
          </tr>

          <tr>
            <th scope="row">Tittel</th>
            <td><input type="text" class="regular-text" name="title" value="<?php echo esc_attr($cfg['title'] ?? ''); ?>"></td>
          </tr>

          <tr>
            <th scope="row">Mode</th>
            <td>
              <select name="mode">
                <option value="manual" <?php selected($cfg['mode'] ?? 'manual', 'manual'); ?>>Manual (du trykker Neste på lufta)</option>
                <option value="auto" <?php selected($cfg['mode'] ?? 'manual', 'auto'); ?>>Auto (tidsstyrt)</option>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row">Svarvindu per spørsmål (sek)</th>
            <td><input type="number" min="10" max="300" name="question_seconds" value="<?php echo esc_attr(intval($cfg['question_seconds'] ?? 60)); ?>"></td>
          </tr>

          <tr>
            <th scope="row">Pause mellom (auto) (sek)</th>
            <td><input type="number" min="0" max="120" name="gap_seconds" value="<?php echo esc_attr(intval($cfg['gap_seconds'] ?? 10)); ?>"></td>
          </tr>

          <tr>
            <th scope="row">Lås forrige når du trykker Neste</th>
            <td>
              <label>
                <input type="checkbox" name="lock_on_next" value="1" <?php checked(!empty($cfg['lock_on_next'])); ?>>
                Aktiv
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">Sponsorflate (HTML)</th>
            <td>
              <textarea name="sponsor_html" rows="4" class="large-text code"><?php echo esc_textarea($cfg['sponsor_html'] ?? ''); ?></textarea>
            </td>
          </tr>

          <tr>
            <th scope="row">Anti-juks: maks deltakere per IP</th>
            <td>
              <input type="number" min="1" max="20" name="max_players_per_ip" value="<?php echo esc_attr(intval($cfg['max_players_per_ip'] ?? 3)); ?>">
            </td>
          </tr>

          <tr>
            <th scope="row">Anti-juks: krev samme IP for svar</th>
            <td>
              <label>
                <input type="checkbox" name="enforce_same_ip" value="1" <?php checked(!empty($cfg['enforce_same_ip'])); ?>>
                Aktiv
              </label>
            </td>
          </tr>

          <tr>
            <th scope="row">Air code (kodeord på lufta)</th>
            <td>
              <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="require_access_code" value="1" <?php checked(!empty($cfg['require_access_code'])); ?>>
                Krev kodeord
              </label>
              <input type="text" class="regular-text" name="access_code" value="<?php echo esc_attr((string)($cfg['access_code'] ?? '')); ?>" placeholder="f.eks. RUBBEN2026">
            </td>
          </tr>

          <tr>
            <th scope="row">Velg spørsmålsfil (uploads/rubben-quiz)</th>
            <td>
              <select name="questions_file_choice">
                <option value="">— Ingen (bruk sti/fallback) —</option>
                <?php foreach ($files as $f): ?>
                  <option value="<?php echo esc_attr($f); ?>" <?php selected(($cfg['questions_file_choice'] ?? ''), $f); ?>>
                    <?php echo esc_html($f); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>

          <tr>
            <th scope="row">Alternativ sti (lokal sti i uploads)</th>
            <td>
              <input type="text" class="large-text" name="questions_json_path" value="<?php echo esc_attr((string)($cfg['questions_json_path'] ?? '')); ?>" placeholder="/wp-content/uploads/rubben-quiz/musikk_hitster.json">
            </td>
          </tr>

          <tr>
            <th scope="row">Fallback: Spørsmål (JSON)</th>
            <td>
              <textarea name="questions_json" rows="10" class="large-text code"><?php echo esc_textarea($cfg['questions_json'] ?? '[]'); ?></textarea>
              <p class="description">Tips: skriv <code>[]</code> hvis du kun bruker filvalg.</p>
            </td>
          </tr>
        </table>

        <?php submit_button('Lagre oppsett'); ?>
      </form>

      <hr>

      <h2>Kontroll</h2>
      <p>
        Status: <strong><?php echo esc_html($cfg['status'] ?? 'stopped'); ?></strong> •
        Spørsmål #: <strong><?php echo esc_html(intval($cfg['current_idx'] ?? 0) + 1); ?></strong>
      </p>

      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php wp_nonce_field('rubben_quiz_admin'); ?>
        <button class="button button-primary" name="rubben_quiz_action" value="start">Start</button>
        <button class="button" name="rubben_quiz_action" value="prev">Forrige</button>
        <button class="button button-primary" name="rubben_quiz_action" value="next">Neste (lås forrige)</button>
        <button class="button button-secondary" name="rubben_quiz_action" value="reopen">Reopen</button>
        <button class="button" name="rubben_quiz_action" value="stop">Stopp</button>
        <button class="button button-danger" name="rubben_quiz_action" value="reset_scores" onclick="return confirm('Hard reset: slette ALLE deltakere (navn/toppliste) og ALLE svar?');">Hard reset</button>
      </form>

      <hr>

      <h2>Live Monitor (admin)</h2>
      <p style="opacity:.8">Viser aktivt spørsmål + timer + toppliste når quizen går. Oppdaterer automatisk.</p>

      <div id="rubben-monitor" style="max-width:900px;border:1px solid rgba(0,0,0,.12);border-radius:14px;padding:14px;background:#fff;">
        <div id="rm-status" style="font-weight:800;margin-bottom:8px;">Laster…</div>
        <div id="rm-timer" style="height:10px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden;margin:10px 0;">
          <div id="rm-timer-fill" style="height:10px;width:0%;background:rgba(0,0,0,.65);"></div>
        </div>
        <div id="rm-question" style="font-size:16px;font-weight:800;margin-top:10px;"></div>
        <ol id="rm-choices" style="margin:10px 0 0 22px;"></ol>
        <div id="rm-board" style="margin-top:14px;"></div>
      </div>

      <script>
        (function(){
          const session = <?php echo json_encode($session_key); ?>;
          const rest = <?php echo json_encode($rest); ?>;
          const nonce = <?php echo json_encode($nonce); ?>;

          const elStatus = document.getElementById('rm-status');
          const elQ = document.getElementById('rm-question');
          const elChoices = document.getElementById('rm-choices');
          const elBoard = document.getElementById('rm-board');
          const fill = document.getElementById('rm-timer-fill');

          function escapeHtml(s){
            return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
          }

          function renderBoard(lb){
            if(!lb || !Array.isArray(lb) || lb.length === 0){
              elBoard.innerHTML = '<div style="opacity:.8">Ingen toppliste enda.</div>';
              return;
            }
            let html = '<div style="font-weight:800;margin-bottom:6px;">Toppliste</div><ol style="margin:0 0 0 22px;">';
            lb.forEach(r => {
              html += '<li><strong>' + escapeHtml(r.name) + '</strong> — ' + r.score + ' poeng</li>';
            });
            html += '</ol><div style="opacity:.8;margin-top:6px;">Poeng: 5 raskaste riktige per spørsmål (5→1).</div>';
            elBoard.innerHTML = html;
          }

          function updateTimer(state){
            const now = state.server_time || Math.floor(Date.now()/1000);
            const ends = state.ends_at || 0;
            const start = state.opened_at || (ends ? (ends - (state.question_seconds||60)) : 0);
            if(!ends || now >= ends){ fill.style.width = '0%'; return; }
            const total = Math.max(1, ends - start);
            const left = Math.max(0, ends - now);
            const pct = Math.max(0, Math.min(100, (left / total) * 100));
            fill.style.width = pct + '%';
          }

          async function getState(){
            const url = rest + '/state?session=' + encodeURIComponent(session);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': nonce }});
            return await res.json();
          }

          async function tick(){
            try{
              const st = await getState();
              if(st.error){ elStatus.textContent = st.error; return; }

              elStatus.textContent =
                'Status: ' + (st.status || 'stopped') +
                ' • Spørsmål #' + ((st.current_idx||0) + 1) +
                (st.locked ? ' • (LÅST)' : '');

              updateTimer(st);

              if(st.status !== 'running' || st.locked || !st.question){
                elQ.textContent = (st.status !== 'running') ? 'Quizen er ikkje i gang.' : (st.locked ? 'Spørsmålet er låst.' : 'Ingen spørsmål.');
                elChoices.innerHTML = '';
                renderBoard(st.leaderboard);
                return;
              }

              elQ.textContent = (st.question.type === 'identify_text')
                ? (st.question.q + ' (fritekst)')
                : st.question.q;

              elChoices.innerHTML = '';
              if(st.question.type !== 'identify_text'){
                (st.question.choices || []).forEach(c => {
                  const li = document.createElement('li');
                  li.innerHTML = escapeHtml(String(c));
                  elChoices.appendChild(li);
                });
              }

              renderBoard(st.leaderboard);
            } catch(e){
              elStatus.textContent = 'Feil ved oppdatering.';
            }
          }

          tick();
          setInterval(tick, 2000);
        })();
      </script>
    </div>
    <?php
  }

  /* ---------------- Frontend ---------------- */

  public function enqueue() {
    if (!is_singular()) return;
    global $post;
    if (!$post || strpos($post->post_content, 'rubben_live_quiz') === false) return;

    wp_register_script('rubben-live-quiz', '', [], self::DBVER, true);
    wp_enqueue_script('rubben-live-quiz');

    wp_localize_script('rubben-live-quiz', 'RUBBEN_QUIZ', [
      'rest' => esc_url_raw(rest_url('rubben-quiz/v1')),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);

    // CSS: ensure choices are visible (fix theme hover issues)
    $css = "
      .rq-wrap{border:1px solid rgba(0,0,0,.10);border-radius:16px;padding:16px;max-width:860px}
      .rq-head{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
      .rq-title{font-weight:800;font-size:20px;line-height:1.2}
      .rq-pill{font-size:13px;opacity:.85}
      .rq-card{border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:14px;margin-top:12px;background:#fff}
      .rq-q{font-weight:800;font-size:18px;line-height:1.25}
      .rq-choices{display:grid;gap:10px;margin-top:12px}
      .rq-btn{
        padding:12px 12px;border-radius:12px;border:1px solid rgba(0,0,0,.18);
        background:#fff;cursor:pointer;text-align:left;font-weight:700;
        opacity:1 !important; visibility:visible !important; color:#111 !important;
      }
      .rq-btn:hover{transform:translateY(-1px)}
      .rq-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center}
      .rq-inp{padding:12px;border-radius:12px;border:1px solid rgba(0,0,0,.12);min-width:220px}
      .rq-primary{padding:12px 14px;border-radius:12px;border:0;background:#111;color:#fff;font-weight:800;cursor:pointer}
      .rq-primary:disabled{opacity:.5;cursor:not-allowed}
      .rq-timer{height:10px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden;margin-top:12px}
      .rq-timer > div{height:10px;background:rgba(0,0,0,.65);width:0%}
      .rq-msg{margin-top:10px;opacity:.95}
      .rq-board ol{margin:10px 0 0 18px}
      .rq-small{font-size:13px;opacity:.8}
      .rq-sponsor{border:1px dashed rgba(0,0,0,.18);background:rgba(0,0,0,.02)}
      .rq-grid{display:grid;gap:12px}
      .rq-audio{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}
    ";
    wp_add_inline_style('wp-block-library', $css);
    wp_add_inline_script('rubben-live-quiz', $this->frontend_js());
  }

  private function frontend_js() {
    return <<<JS
(function(){
  const root = document.querySelector('[data-rubben-quiz]');
  if(!root) return;

  const session = root.getAttribute('data-session');
  const api = (path) => RUBBEN_QUIZ.rest + path;

  let playerId = localStorage.getItem('rubben_quiz_player_' + session) || '';
  let displayName = localStorage.getItem('rubben_quiz_name_' + session) || '';
  let accessCode = localStorage.getItem('rubben_quiz_code_' + session) || '';

  let audioEl = null;
  let stopTimer = null;

  const el = {
    title: root.querySelector('.rq-title'),
    pill: root.querySelector('.rq-pill'),
    q: root.querySelector('.rq-q'),
    choices: root.querySelector('.rq-choices'),
    msg: root.querySelector('.rq-msg'),
    timerFill: root.querySelector('.rq-timer > div'),
    nameBox: root.querySelector('#rq-name'),
    nameBtn: root.querySelector('#rq-name-btn'),
    codeRow: root.querySelector('.rq-code-row'),
    codeBox: root.querySelector('#rq-code'),
    codeBtn: root.querySelector('#rq-code-btn'),
    answerRow: root.querySelector('.rq-answer-row'),
    answerBox: root.querySelector('#rq-answer'),
    answerBtn: root.querySelector('#rq-answer-btn'),
    board: root.querySelector('.rq-board'),
    small: root.querySelector('.rq-small'),
    sponsor: root.querySelector('.rq-sponsor'),
    audioWrap: root.querySelector('.rq-audio')
  };

  function setMsg(t){ el.msg.textContent = t || ''; }
  function escapeHtml(s){
    return (s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function post(path, body){
    const res = await fetch(api(path), {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': RUBBEN_QUIZ.nonce },
      body: JSON.stringify(body||{})
    });
    return await res.json();
  }

  async function get(path){
    const res = await fetch(api(path), { headers:{'X-WP-Nonce': RUBBEN_QUIZ.nonce} });
    return await res.json();
  }

  function renderLeaderboard(lb){
    if(!lb || !Array.isArray(lb) || lb.length === 0) {
      el.board.innerHTML = '<div class="rq-small">Ingen toppliste enda.</div>';
      return;
    }
    let html = '<div style="font-weight:900">Toppliste</div><ol>';
    lb.forEach(r=>{
      html += '<li><strong>' + escapeHtml(r.name) + '</strong> — ' + r.score + ' poeng</li>';
    });
    html += '</ol><div class="rq-small">Poeng: 5 raskaste riktige per spørsmål (5→1).</div>';
    el.board.innerHTML = html;
  }

  function disableAnswer(disabled){
    el.answerBtn.disabled = disabled;
    const btns = el.choices.querySelectorAll('button');
    btns.forEach(b=> b.disabled = disabled);
  }

  function updateTimer(state){
    const now = state.server_time || Math.floor(Date.now()/1000);
    const ends = state.ends_at || 0;
    const start = state.opened_at || (ends ? (ends - (state.question_seconds||60)) : 0);

    if(!ends || now >= ends){
      el.timerFill.style.width = '0%';
      return;
    }
    const total = Math.max(1, ends - start);
    const left = Math.max(0, ends - now);
    const pct = Math.max(0, Math.min(100, (left / total) * 100));
    el.timerFill.style.width = pct + '%';
  }

  async function registerIfNeeded(){
    if(playerId && displayName) return true;

    displayName = (el.nameBox.value || '').trim().slice(0,64);
    if(!displayName){
      setMsg('Skriv inn navn for å delta.');
      return false;
    }

    const payload = {
      session,
      name: displayName,
      code: accessCode,
      hp: (root.querySelector('#rq-hp').value || '')
    };
    const r = await post('/register', payload);

    if(r && r.ok){
      playerId = String(r.player_id);
      localStorage.setItem('rubben_quiz_player_' + session, playerId);
      localStorage.setItem('rubben_quiz_name_' + session, displayName);
      setMsg('Klar! Vent på neste spørsmål.');
      return true;
    }
    setMsg(r && r.error ? r.error : 'Kunne ikke registrere.');
    return false;
  }

  async function setCode(){
    accessCode = (el.codeBox.value || '').trim().slice(0,40);
    localStorage.setItem('rubben_quiz_code_' + session, accessCode);
    setMsg('Kode lagret.');
  }

  function stopAudio(){
    try{
      if(stopTimer) clearTimeout(stopTimer);
      stopTimer = null;
      if(audioEl){
        audioEl.pause();
        audioEl.currentTime = 0;
      }
    }catch(e){}
    audioEl = null;
  }

  async function playClip(state){
    if(!state.question || !state.question.audio || !state.question.audio.url) return;

    if(!playerId){
      const ok = await registerIfNeeded();
      if(!ok) return;
    }

    const r = await post('/play', {
      session,
      player_id: playerId,
      question_idx: state.current_idx,
      song_key: state.question.song_key || '',
      code: accessCode,
      hp: (root.querySelector('#rq-hp').value || '')
    });

    if(!r || !r.ok){
      setMsg(r && r.error ? r.error : 'Kunne ikke spille klipp.');
      return;
    }

    const sec = state.question.audio.clipSeconds || 10;

    try{
      stopAudio();
      audioEl = new Audio(state.question.audio.url);
      audioEl.currentTime = 0;
      await audioEl.play();
      stopTimer = setTimeout(() => {
        stopAudio();
      }, sec * 1000);
    } catch(e){
      setMsg('Nettleseren blokkerer lyd. Trykk på "Spill klipp" en gang til.');
    }
  }

  async function submitAnswer(answerText){
    if (el.codeRow.style.display !== 'none') {
      accessCode = (el.codeBox.value || '').trim().slice(0,40);
      localStorage.setItem('rubben_quiz_code_' + session, accessCode);
    }

    if(!playerId){
      const ok = await registerIfNeeded();
      if(!ok) return;
    }

    disableAnswer(true);

    const r = await post('/submit', {
      session,
      player_id: playerId,
      answer: answerText,
      code: accessCode,
      hp: (root.querySelector('#rq-hp').value || '')
    });

    if(r && r.ok){
      if(r.correct){
        if(r.points > 0){
          setMsg('Riktig! ✅ Du var #' + r.rank + ' og fikk ' + r.points + ' poeng.');
        } else {
          setMsg('Riktig! ✅ Men ikke i topp 5 (0 poeng).');
        }
      } else {
        setMsg('Svar registrert.');
      }
      await refresh();
    } else {
      setMsg(r && r.error ? r.error : 'Kunne ikke sende svar.');
      disableAnswer(false);
    }
  }

  function renderAudio(state){
    el.audioWrap.innerHTML = '';
    if(!state.question || !state.question.audio || !state.question.audio.url || state.locked || state.status !== 'running') return;

    const sec = state.question.audio.clipSeconds || 10;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'rq-primary';
    btn.textContent = 'Spill ' + sec + ' sek';

    if(state.question.audioPlayed){
      btn.disabled = true;
      btn.textContent = 'Klipp spilt';
    }

    btn.addEventListener('click', async () => {
      await playClip(state);
      btn.disabled = true;
      btn.textContent = 'Klipp spilt';
      await refresh(); // update audioPlayed on server too
    });

    el.audioWrap.appendChild(btn);

    const hint = document.createElement('div');
    hint.className = 'rq-small';
    hint.textContent = 'Klippet kan spilles 1 gang per låt/spørsmål.';
    el.audioWrap.appendChild(hint);
  }

  function renderQuestion(state){
    el.title.textContent = state.title || 'Live quiz';
    el.pill.textContent = 'Radio Rubben Live Quiz';
    el.small.textContent = state.note || '';

    if (state.sponsor_html) {
      el.sponsor.style.display = '';
      el.sponsor.innerHTML = state.sponsor_html;
    } else {
      el.sponsor.style.display = 'none';
    }

    if (state.require_access_code) {
      el.codeRow.style.display = '';
      if(accessCode){ el.codeBox.value = accessCode; }
    } else {
      el.codeRow.style.display = 'none';
    }

    if(state.status !== 'running'){
      el.q.textContent = 'Quizen er ikke i gang akkurat nå.';
      el.choices.innerHTML = '';
      el.audioWrap.innerHTML = '';
      disableAnswer(true);
      return;
    }

    if(state.locked){
      el.q.textContent = 'Dette spørsmålet er låst.';
      el.choices.innerHTML = '';
      el.audioWrap.innerHTML = '';
      disableAnswer(true);
      return;
    }

    if(!state.question){
      el.q.textContent = 'Ingen spørsmål funnet.';
      el.choices.innerHTML = '';
      el.audioWrap.innerHTML = '';
      disableAnswer(true);
      return;
    }

    // Stop any playing audio when question changes
    stopAudio();

    const qType = state.question.type || 'mc';
    el.q.textContent = state.question.q || '';
    el.choices.innerHTML = '';

    renderAudio(state);

    // Show/hide answer input row
    if (qType === 'identify_text') {
      el.answerRow.style.display = '';
      el.answerBox.placeholder = state.question.answerHint || 'Skriv artist og låtnavn';
      el.choices.style.display = 'none';
    } else {
      el.answerRow.style.display = 'none';
      el.choices.style.display = '';
    }

    // Build MC buttons if not text question
    if(qType !== 'identify_text' && Array.isArray(state.question.choices) && state.question.choices.length){
      state.question.choices.forEach((c, idx)=>{
        const b = document.createElement('button');
        b.className = 'rq-btn';
        b.type = 'button';
        b.textContent = String(c);
        b.addEventListener('click', ()=> submitAnswer(String(idx)));
        el.choices.appendChild(b);
      });
    }

    if(state.alreadyAnswered){
      setMsg('Du har allerede svart på dette spørsmålet.');
      disableAnswer(true);
    } else {
      disableAnswer(false);
    }
  }

  async function refresh(){
    const state = await get('/state?session=' + encodeURIComponent(session) + (playerId ? '&player_id=' + encodeURIComponent(playerId) : ''));
    if(!state || state.error){
      setMsg(state && state.error ? state.error : 'Feil ved henting av status.');
      return;
    }
    renderQuestion(state);
    renderLeaderboard(state.leaderboard);
    updateTimer(state);
  }

  el.nameBtn.addEventListener('click', registerIfNeeded);
  if(displayName){ el.nameBox.value = displayName; }

  el.codeBtn.addEventListener('click', setCode);
  if(accessCode){ el.codeBox.value = accessCode; }

  el.answerBtn.addEventListener('click', async ()=>{
    const txt = (el.answerBox.value||'').trim();
    if(!txt){ setMsg('Skriv artist og låtnavn.'); return; }
    await submitAnswer(txt);
    el.answerBox.value = '';
  });

  refresh();
  setInterval(refresh, 2000);
})();
JS;
  }

  public function shortcode($atts) {
    $atts = shortcode_atts([
      'session' => 'rubben',
      'label' => 'Radio Rubben Quiz',
      'note' => 'Skriv inn navn, spill klipp, og svar mens vi er på lufta.',
    ], $atts, 'rubben_live_quiz');

    $session = sanitize_key($atts['session']);
    $this->ensure_session_defaults($session);

    $html  = '<div class="rq-wrap" data-rubben-quiz data-session="'.esc_attr($session).'">';
    $html .= '<div class="rq-head">';
    $html .= '<div class="rq-title">Live quiz</div>';
    $html .= '<div class="rq-pill">'.esc_html($atts['label']).'</div>';
    $html .= '</div>';
    $html .= '<div class="rq-small">'.esc_html($atts['note']).'</div>';

    $html .= '<div class="rq-card rq-sponsor" style="margin-top:12px;"><div class="rq-small">Sponsor-området lastes…</div></div>';

    $html .= '<div class="rq-card"><div class="rq-grid">';

    $html .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
    $html .= '<input class="rq-inp" id="rq-name" placeholder="Ditt navn" maxlength="64" />';
    $html .= '<button class="rq-primary" id="rq-name-btn" type="button">Bli med</button>';
    $html .= '</div>';

    $html .= '<div class="rq-code-row" style="display:none;gap:10px;flex-wrap:wrap;align-items:center;">';
    $html .= '<input class="rq-inp" id="rq-code" placeholder="Kodeord (på lufta)" maxlength="40" />';
    $html .= '<button class="rq-primary" id="rq-code-btn" type="button">Lagre kode</button>';
    $html .= '</div>';

    // Honeypot
    $html .= '<input type="text" id="rq-hp" value="" autocomplete="off" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" tabindex="-1" />';

    $html .= '<div class="rq-timer"><div></div></div>';
    $html .= '<div class="rq-q" style="margin-top:12px;">Laster…</div>';

    // Audio button container
    $html .= '<div class="rq-audio"></div>';

    // Choices
    $html .= '<div class="rq-choices"></div>';

    // Text answer row (hidden unless identify_text)
    $html .= '<div class="rq-row rq-answer-row" style="display:none;">';
    $html .= '<input class="rq-inp" id="rq-answer" placeholder="Skriv artist og låtnavn" />';
    $html .= '<button class="rq-primary" id="rq-answer-btn" type="button">Send svar</button>';
    $html .= '</div>';

    $html .= '<div class="rq-msg"></div>';
    $html .= '</div></div>';

    $html .= '<div class="rq-card rq-board"><div class="rq-small">Laster toppliste…</div></div>';
    $html .= '</div>';

    return $html;
  }

  /* ---------------- REST API ---------------- */

  public function register_routes() {
    register_rest_route('rubben-quiz/v1', '/register', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'api_register'],
    ]);

    register_rest_route('rubben-quiz/v1', '/state', [
      'methods' => 'GET',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'api_state'],
    ]);

    register_rest_route('rubben-quiz/v1', '/submit', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'api_submit'],
    ]);

    register_rest_route('rubben-quiz/v1', '/play', [
      'methods' => 'POST',
      'permission_callback' => '__return_true',
      'callback' => [$this, 'api_play'],
    ]);
  }

  public function api_register($req) {
    global $wpdb;
    $tp = $wpdb->prefix;

    $session = sanitize_key($req->get_param('session'));
    $name = sanitize_text_field($req->get_param('name'));
    $code = sanitize_text_field((string)$req->get_param('code'));
    $hp   = (string)$req->get_param('hp');

    if ($hp !== '') return ['ok'=>false,'error'=>'Ugyldig forespørsel.'];
    if (!$session) return ['ok'=>false,'error'=>'Mangler session.'];
    if (!$name) return ['ok'=>false,'error'=>'Mangler navn.'];

    $this->ensure_session_defaults($session);
    $cfg = $this->get_cfg($session);

    if (!empty($cfg['require_access_code'])) {
      $need = trim((string)($cfg['access_code'] ?? ''));
      if ($need === '' || trim($code) !== $need) {
        return ['ok'=>false,'error'=>'Feil kodeord. Hør på lufta og prøv igjen.'];
      }
    }

    $ip = $this->get_client_ip();
    $ua = $this->get_user_agent();
    $ip_hash = $this->hash_token($ip);
    $ua_hash = $this->hash_token($ua);

    $players = $tp . self::TABLE_PLAYERS;

    $max = max(1, intval($cfg['max_players_per_ip'] ?? 3));
    $cnt = intval($wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $players WHERE session_key=%s AND ip_hash=%s",
      $session, $ip_hash
    )));
    if ($cnt >= $max) return ['ok'=>false,'error'=>'For mange deltakere fra samme nett.'];

    $existing = $wpdb->get_row($wpdb->prepare(
      "SELECT id FROM $players WHERE session_key=%s AND display_name=%s LIMIT 1",
      $session, $name
    ));
    if ($existing && isset($existing->id)) {
      $wpdb->update($players, [
        'last_seen'=>current_time('mysql'),
        'ip_hash'=>$ip_hash,
        'ua_hash'=>$ua_hash
      ], ['id'=>intval($existing->id)]);
      return ['ok'=>true,'player_id'=>intval($existing->id)];
    }

    $wpdb->insert($players, [
      'session_key' => $session,
      'display_name' => $name,
      'created_at' => current_time('mysql'),
      'last_seen' => current_time('mysql'),
      'score' => 0,
      'ip_hash' => $ip_hash,
      'ua_hash' => $ua_hash
    ]);

    return ['ok'=>true,'player_id'=>intval($wpdb->insert_id)];
  }

  /**
   * Audio play limiter:
   * - If song_key exists => limit per song per player (Hitster feel)
   * - Else limit per question per player
   */
  public function api_play($req) {
    $session   = sanitize_key($req->get_param('session'));
    $player_id = intval($req->get_param('player_id') ?? 0);
    $qidx      = intval($req->get_param('question_idx') ?? -1);
    $song_key  = sanitize_text_field((string)($req->get_param('song_key') ?? ''));
    $code      = sanitize_text_field((string)$req->get_param('code'));
    $hp        = (string)$req->get_param('hp');

    if ($hp !== '') return ['ok'=>false,'error'=>'Ugyldig forespørsel.'];
    if (!$session || $player_id <= 0 || $qidx < 0) return ['ok'=>false,'error'=>'Mangler data.'];

    $cfg = $this->get_cfg($session);
    if (($cfg['status'] ?? 'stopped') !== 'running') return ['ok'=>false,'error'=>'Quizen er ikke i gang.'];

    if (!empty($cfg['require_access_code'])) {
      $need = trim((string)($cfg['access_code'] ?? ''));
      if ($need === '' || trim($code) !== $need) return ['ok'=>false,'error'=>'Feil kodeord.'];
    }

    if ($this->is_locked($cfg, $qidx)) return ['ok'=>false,'error'=>'Spørsmålet er låst.'];

    [$opened_at, $ends_at] = $this->compute_window($cfg, $qidx);
    if ($this->now_ts() > $ends_at) return ['ok'=>false,'error'=>'For seint – fristen er ute.'];

    $scope = ($song_key !== '') ? ('song:' . $song_key) : ('q:' . $qidx);
    $tkey = 'rubben_quiz_play_' . md5($session.'|'.$player_id.'|'.$scope);

    if (get_transient($tkey)) return ['ok'=>false,'error'=>'Du har allerede spilt klippet.'];

    $ttl = max(30, ($ends_at - $this->now_ts()) + 30);
    set_transient($tkey, 1, $ttl);

    return ['ok'=>true];
  }

  public function api_state($req) {
    global $wpdb;
    $tp = $wpdb->prefix;

    $session = sanitize_key($req->get_param('session'));
    $player_id = intval($req->get_param('player_id') ?? 0);
    if (!$session) return ['error'=>'Mangler session.'];

    $cfg = $this->get_cfg($session);
    $questions = $this->parse_questions($cfg);

    $idx = intval($cfg['current_idx'] ?? 0);
    if (($cfg['mode'] ?? 'manual') === 'auto' && ($cfg['status'] ?? 'stopped') === 'running') {
      $idx = $this->compute_auto_idx($cfg);
    }

    $q = $questions[$idx] ?? null;
    [$opened_at, $ends_at] = $this->compute_window($cfg, $idx);

    $already = false;
    if ($player_id > 0) {
      $answers = $tp . self::TABLE_ANSWERS;
      $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $answers WHERE session_key=%s AND player_id=%d AND question_idx=%d LIMIT 1",
        $session, $player_id, $idx
      ));
      $already = ($row && isset($row->id));
    }

    $players = $tp . self::TABLE_PLAYERS;
    $lb = $wpdb->get_results($wpdb->prepare(
      "SELECT display_name as name, score FROM $players WHERE session_key=%s ORDER BY score DESC, last_seen DESC LIMIT 10",
      $session
    ), ARRAY_A);

    $locked = $this->is_locked($cfg, $idx);
    $now = $this->now_ts();
    if ($now > $ends_at) $locked = true;

    $sponsor_html = (string)($cfg['sponsor_html'] ?? '');

    // Build question payload with choice shuffling and audio
    $question_payload = null;
    if ($q) {
      $type = (string)($q['type'] ?? 'mc');
      $song_key = (string)($q['song_key'] ?? '');

      $choices = [];
      if (isset($q['choices']) && is_array($q['choices'])) {
        $choices = array_values($q['choices']);
        $perm = $this->get_shuffle_for_question($cfg, $idx, count($choices));
        $choices = $this->apply_shuffle_choices($choices, $perm);
        // persist shuffle
        $this->save_cfg($session, $cfg);
      }

      // Audio object
      $audio = null;
      if (isset($q['audio']) && is_array($q['audio'])) {
        $url = trim((string)($q['audio']['url'] ?? ''));
        if ($url !== '') {
          $clip = intval($q['clipSeconds'] ?? 10);
          if ($clip < 3) $clip = 10;
          if ($clip > 20) $clip = 20;

          $audio = [
            'source' => (string)($q['audio']['source'] ?? 'spotify_preview'),
            'url' => esc_url_raw($url),
            'clipSeconds' => $clip
          ];
        }
      }

      // Has user already played audio for this song/question?
      $audioPlayed = false;
      if ($player_id > 0 && $audio) {
        $scope = ($song_key !== '') ? ('song:' . $song_key) : ('q:' . $idx);
        $tkey = 'rubben_quiz_play_' . md5($session.'|'.$player_id.'|'.$scope);
        $audioPlayed = (bool)get_transient($tkey);
      }

      $question_payload = [
        'type' => $type,
        'song_key' => $song_key,
        'q' => (string)($q['q'] ?? ''),
        'choices' => $choices,
        'answerHint' => (string)($q['answerHint'] ?? ''),
        'audio' => $audio ? array_merge($audio, ['audioPlayed' => $audioPlayed]) : null,
        // For correctness checks (server-side), we keep these in $q, not exposed
      ];
    }

    // If audio exists, merge audioPlayed into audio object for frontend convenience
    if ($question_payload && is_array($question_payload['audio'])) {
      $question_payload['audioPlayed'] = $question_payload['audio']['audioPlayed'] ?? false;
      $question_payload['audio']['audioPlayed'] = null; // not needed twice
      unset($question_payload['audio']['audioPlayed']);
    } else if ($question_payload) {
      $question_payload['audioPlayed'] = false;
    }

    return [
      'session' => $session,
      'title' => $cfg['title'] ?? 'Live quiz',
      'status' => $cfg['status'] ?? 'stopped',
      'mode' => $cfg['mode'] ?? 'manual',
      'server_time' => $now,
      'current_idx' => $idx,
      'opened_at' => $opened_at,
      'ends_at' => $ends_at,
      'question_seconds' => intval($cfg['question_seconds'] ?? 60),
      'locked' => $locked,
      'question' => $question_payload,
      'alreadyAnswered' => $already,
      'note' => 'Poeng: 5 raskaste riktige får 5→1. Når vi går vidare blir spørsmålet låst.',
      'leaderboard' => $lb ?: [],
      'sponsor_html' => $sponsor_html ? wp_kses_post($sponsor_html) : '',
      'require_access_code' => !empty($cfg['require_access_code']) ? 1 : 0,
    ];
  }

  public function api_submit($req) {
    global $wpdb;
    $tp = $wpdb->prefix;

    $session = sanitize_key($req->get_param('session'));
    $player_id = intval($req->get_param('player_id') ?? 0);
    $answer = (string)($req->get_param('answer') ?? '');
    $code = sanitize_text_field((string)$req->get_param('code'));
    $hp   = (string)$req->get_param('hp');

    if ($hp !== '') return ['ok'=>false,'error'=>'Ugyldig forespørsel.'];
    if (!$session) return ['ok'=>false,'error'=>'Mangler session.'];
    if ($player_id <= 0) return ['ok'=>false,'error'=>'Mangler deltaker.'];
    if (trim($answer) === '') return ['ok'=>false,'error'=>'Mangler svar.'];

    $cfg = $this->get_cfg($session);
    if (($cfg['status'] ?? 'stopped') !== 'running') return ['ok'=>false,'error'=>'Quizen er ikke i gang.'];

    if (!empty($cfg['require_access_code'])) {
      $need = trim((string)($cfg['access_code'] ?? ''));
      if ($need === '' || trim($code) !== $need) return ['ok'=>false,'error'=>'Feil kodeord.'];
    }

    $questions = $this->parse_questions($cfg);

    $idx = intval($cfg['current_idx'] ?? 0);
    if (($cfg['mode'] ?? 'manual') === 'auto') $idx = $this->compute_auto_idx($cfg);

    $q = $questions[$idx] ?? null;
    if (!$q) return ['ok'=>false,'error'=>'Ugyldig spørsmål.'];

    if ($this->is_locked($cfg, $idx)) return ['ok'=>false,'error'=>'Spørsmålet er låst.'];

    [$opened_at, $ends_at] = $this->compute_window($cfg, $idx);
    $now = $this->now_ts();
    if ($now > $ends_at) return ['ok'=>false,'error'=>'For seint – svarfristen er ute.'];

    // Check player exists + IP enforcement
    $players = $tp . self::TABLE_PLAYERS;
    $p = $wpdb->get_row($wpdb->prepare(
      "SELECT id, ip_hash FROM $players WHERE id=%d AND session_key=%s LIMIT 1",
      $player_id, $session
    ));
    if (!$p || !isset($p->id)) return ['ok'=>false,'error'=>'Ugyldig deltaker.'];

    $ip = $this->get_client_ip();
    $ua = $this->get_user_agent();
    $ip_hash = $this->hash_token($ip);
    $ua_hash = $this->hash_token($ua);

    if (!empty($cfg['enforce_same_ip'])) {
      if ((string)$p->ip_hash !== $ip_hash) return ['ok'=>false,'error'=>'Du kan ikke bytte nett under samme quiz (anti-juks).'];
    }

    // Determine correctness
    $type = (string)($q['type'] ?? 'mc');
    $correct = false;

    if ($type === 'identify_text') {
      $ans = $this->norm_txt($answer);
      $artist = $this->norm_txt((string)($q['artist'] ?? ''));
      $title  = $this->norm_txt((string)($q['title'] ?? ''));

      // Require both artist + title to appear somewhere in text (order independent)
      $correct = ($artist !== '' && $title !== '' && strpos($ans, $artist) !== false && strpos($ans, $title) !== false);
    } else {
      // MC: user submits display index
      $choices = (isset($q['choices']) && is_array($q['choices'])) ? array_values($q['choices']) : [];
      $n = count($choices);

      if ($n > 0 && is_numeric($answer)) {
        $display_idx = intval($answer);

        // Apply same shuffle used in state (server-stored perm)
        $perm = $this->get_shuffle_for_question($cfg, $idx, $n);
        $this->save_cfg($session, $cfg);
        $display_choices = $this->apply_shuffle_choices($choices, $perm);

        if ($display_idx >= 0 && $display_idx < count($display_choices)) {
          $chosen_value = $display_choices[$display_idx];

          // If question has correct_value (Hitster year question), compare value
          if (array_key_exists('correct_value', $q)) {
            $correct = (intval($chosen_value) === intval($q['correct_value']));
          } else if (is_numeric($q['a'] ?? null)) {
            // Classic: compare original index via perm mapping
            // display_idx -> original index
            $orig_index_chosen = $perm[$display_idx] ?? -1;
            $correct = ($orig_index_chosen === intval($q['a']));
          } else if (!is_numeric($q['a'] ?? null) && isset($q['a'])) {
            // Fallback: string answer key
            $correct = ($this->norm_txt((string)$chosen_value) === $this->norm_txt((string)$q['a']));
          }
        }
      }
    }

    // Insert answer (prevents double answer due to unique constraint)
    $answers = $tp . self::TABLE_ANSWERS;
    $ins = $wpdb->insert($answers, [
      'session_key' => $session,
      'player_id' => $player_id,
      'question_idx' => $idx,
      'answer_text' => $answer,
      'is_correct' => $correct ? 1 : 0,
      'points_awarded' => 0,
      'received_at' => current_time('mysql'),
    ]);
    if (!$ins) return ['ok'=>false,'error'=>'Du har allerede svart på dette spørsmålet.'];

    $points = 0;
    $rank = 0;

    if ($correct) {
      $answer_id = intval($wpdb->insert_id);
      $received_at = $wpdb->get_var($wpdb->prepare("SELECT received_at FROM $answers WHERE id=%d", $answer_id));

      $rank = 1 + intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $answers
         WHERE session_key=%s AND question_idx=%d AND is_correct=1
         AND (
           received_at < %s
           OR (received_at = %s AND id < %d)
         )",
        $session, $idx, $received_at, $received_at, $answer_id
      )));

      $points = $this->top5_points_for_rank($rank);
      $wpdb->update($answers, ['points_awarded' => $points], ['id' => $answer_id]);

      if ($points > 0) {
        $wpdb->query($wpdb->prepare(
          "UPDATE $players SET score = score + %d, last_seen=%s, ip_hash=%s, ua_hash=%s WHERE id=%d AND session_key=%s",
          $points, current_time('mysql'), $ip_hash, $ua_hash, $player_id, $session
        ));
      } else {
        $wpdb->query($wpdb->prepare(
          "UPDATE $players SET last_seen=%s, ip_hash=%s, ua_hash=%s WHERE id=%d AND session_key=%s",
          current_time('mysql'), $ip_hash, $ua_hash, $player_id, $session
        ));
      }
    } else {
      $wpdb->query($wpdb->prepare(
        "UPDATE $players SET last_seen=%s, ip_hash=%s, ua_hash=%s WHERE id=%d AND session_key=%s",
        current_time('mysql'), $ip_hash, $ua_hash, $player_id, $session
      ));
    }

    return ['ok'=>true, 'correct'=>$correct, 'points'=>$points, 'rank'=>$rank];
  }
}

new Rubben_Live_Quiz();
