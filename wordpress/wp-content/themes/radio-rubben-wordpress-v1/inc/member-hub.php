<?php
if (!defined('ABSPATH')) exit;
// Pause birthday collection until an optional choice and deletion routine exist.
require_once __DIR__ . '/member-delete.php';
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('rr-member-hub', get_template_directory_uri().'/assets/css/member-hub.css', ['rr-one-design'], '1.0.1');
    if (is_page(736)) {
        wp_enqueue_style('rr-member-tidy', get_template_directory_uri().'/assets/css/member-tidy.css', ['rr-member-hub'], '1.0.2');
        wp_enqueue_script('rr-member-tidy', get_template_directory_uri().'/assets/js/member-tidy.js', [], '1.0.0', true);
    }
});
add_action('template_redirect', function () {
    if (is_page(736)) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
    }
});
// A single sign-in entry on Min Rubben; preserve the logged-in member tools.
add_filter('pre_do_shortcode_tag', function ($output, $tag) {
    if ($tag === 'min_rubben' && is_page(736) && !is_user_logged_in() && shortcode_exists('continue-with-vipps')) {
        return '';
    }
    return $output;
}, 20, 2);
add_filter('the_content', function ($content) {
    if (is_page(736) && in_the_loop() && is_main_query() && !is_user_logged_in()) {
        $content = str_replace(
            'Bruk Vipps for å komme i gang med leserkontoen din.',
            'Logg inn eller opprett en gratis lytterkonto med Vipps. Første gang opprettes kontoen når du godkjenner delingen i Vipps. Du trenger ikke et eget passord til Min Rubben.',
            $content
        );
    }
    return $content;
}, 20);
function rr_member_entry_label() {
    return is_user_logged_in() ? 'Gå til Min Rubben' : 'Logg inn på Min Rubben';
}
function rr_member_dashboard() {
    if (!is_user_logged_in() || !function_exists('rrwq_week')) return;
    $user = wp_get_current_user();
    $name = trim((string)$user->first_name);
    if (!$name) $name = $user->display_name;
    $week = rrwq_week();
    $r = get_option(rrwq_key('result', $week['id'], $user->ID));
    $attempt = get_option(rrwq_key('attempt', $week['id'], $user->ID));
    $streak = (bool)$r;
    for ($i=1; $i<=2; $i++) {
        $date = (new DateTimeImmutable($week['id'], new DateTimeZone('Europe/Oslo')))->modify('-'.$i.' weeks')->format('Y-m-d');
        $streak = $streak && (bool)get_option(rrwq_key('result', $date, $user->ID));
    }
    $public = $r && !empty($r['public']) && !get_option(rrwq_key('hide',$week['id'],$user->ID));
    $rank = null;
    if ($public) {
        global $wpdb;
        $prefix = substr(rrwq_key('result', $week['id'], 0),0,-1);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix).'%'));
        $ahead = 0;
        foreach ($rows as $row) {
            $suffix = substr($row->option_name,strlen($prefix));
            if (!ctype_digit($suffix)) continue;
            $uid = (int)$suffix;
            $other = maybe_unserialize($row->option_value);
            if (!is_array($other) || empty($other['public']) || get_option(rrwq_key('hide',$week['id'],$uid)) || !get_userdata($uid)) continue;
            if ($other['score'] > $r['score'] || ($other['score'] == $r['score'] && ($other['seconds'] < $r['seconds'] || ($other['seconds'] == $r['seconds'] && $other['finished'] < $r['finished'])))) $ahead++;
        }
        $rank = $ahead + 1;
    }
    ?>
    <section class="rr-member-dashboard" aria-labelledby="rr-member-welcome">
      <div class="rr-member-account-bar"><p class="rr-eyebrow">DIN PLASS PÅ RADIO RUBBEN</p></div>
      <h2 id="rr-member-welcome">Hei, <?php echo esc_html($name); ?>!</h2>
      <p>Her finner du quiz, musikkønsker og hilsener samlet.</p>

      <div class="rr-member-grid">
        <article class="rr-member-quiz">
          <p class="rr-eyebrow">DITT QUIZKORT · <?php echo esc_html($week['label']); ?></p>
          <?php if ($r): ?>
          <div class="rr-member-score"><?php echo (int)$r['score']; ?><span>/ 20 riktige</span></div>
          <p>Tid: <strong><?php echo esc_html(floor($r['seconds']/60).':'.str_pad((string)($r['seconds']%60),2,'0',STR_PAD_LEFT)); ?></strong>
          · <?php echo $rank !== null ? 'Plass '.$rank.' blant offentlige resultater' : 'Resultatet ditt er privat'; ?></p>
          <?php else: ?>
          <h3><?php echo $attempt ? 'Runden din venter.' : 'Klar for ukens utfordring?'; ?></h3>
          <p><?php echo $attempt ? 'Du kan fortsette der du slapp. Klokken løper fortsatt.' : '20 spørsmål. Hvor mange tar du denne uken?'; ?></p>
          <?php endif; ?>
          <a class="rr-btn" href="<?php echo esc_url(home_url('/quiz/#rr-weekly')); ?>"><?php echo $r ? 'Se resultat og toppliste' : ($attempt ? 'Fortsett quizen' : 'Gå til ukens quiz'); ?> →</a>
        </article>
        <article class="rr-member-badges">
          <h3>Dine quizmerker</h3><p>Merker for denne uken – opptjent med ekte resultater.</p>
          <ul>
          <?php foreach ([
            ['Quizdeltaker','Fullført ukens quiz',(bool)$r],
            ['Full pott','20 av 20 riktige denne uken',$r && (int)$r['score'] === 20],
            ['Tre på rad','Fullført denne og de to foregående ukene',$streak]
          ] as $badge): ?>
          <li class="<?php echo $badge[2] ? 'is-earned' : 'is-locked'; ?>"><span aria-hidden="true"><?php echo $badge[2] ? '✓' : '○'; ?></span><div><strong><?php echo esc_html($badge[0]); ?></strong><small><?php echo esc_html($badge[1]); ?> · <?php echo $badge[2] ? 'Opptjent' : 'Ikke opptjent'; ?></small></div></li>
          <?php endforeach; ?>
          </ul>
        </article>
      </div>
      <nav class="rr-member-shortcuts" aria-label="Snarveier på Min Rubben">
        <a href="<?php echo esc_url(home_url('/min-side/?rrm_kind=wish#rrm-send')); ?>">Ønsk en låt →</a>
        <a href="<?php echo esc_url(home_url('/min-side/?rrm_kind=greeting#rrm-send')); ?>">Send en hilsen →</a>
        <a href="<?php echo esc_url(home_url('/quiz/#rr-weekly')); ?>">Spill ukens quiz →</a>
      </nav>
    </section>
    <?php
}
