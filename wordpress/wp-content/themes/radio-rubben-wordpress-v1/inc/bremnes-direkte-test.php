<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__.'/bremnes-poll-rules.php';

function rr_poll_selected_match_data() {
    $match_id=(int)get_option('rr_poll_selected_match',0);
    if (!$match_id) return [];
    $match=get_option('rr_poll_match_'.$match_id,[]);
    if (!is_array($match) || empty($match['home']) || empty($match['away']) || empty($match['kickoff'])) return [];
    $state=get_option('rr_poll_test_'.$match_id.'_vipps_v3_75',[]);
    $match['id']=$match_id;
    $match['state']=is_array($state)?$state:[];
    return $match;
}
function rr_poll_active_vote() {
    $match=rr_poll_selected_match_data();
    if (!$match) return [];
    $state=$match['state']??[];
    if (empty($state['opened']) || !empty($state['finished']) || rr_poll_is_closed($state)) return [];

    $score=['home'=>0,'away'=>0];
    $nff_source=get_option('rr_poll_nff_'.(int)$match['id'].'_source','nff');
    $nff=get_option('rr_poll_nff_'.(int)$match['id'],[]);
    if ($nff_source==='nff' && isset($nff['score']['home'],$nff['score']['away']) && is_numeric($nff['score']['home']) && is_numeric($nff['score']['away'])) {
        $score=['home'=>(int)$nff['score']['home'],'away'=>(int)$nff['score']['away']];
    } else {
        foreach (($state['events']??[]) as $event) {
            if (($event['type']??'')!=='goal') continue;
            $score[(($event['side']??'home')==='away')?'away':'home']++;
        }
    }

    return [
        'id'=>(int)$match['id'],
        'home'=>sanitize_text_field($match['home']),
        'away'=>sanitize_text_field($match['away']),
        'score'=>$score,
        'url'=>add_query_arg('rr_match',(int)$match['id'],home_url('/dagenskamp/')),
    ];
}
function rr_poll_next_match_data() {
    // Verified 2026 fixture snapshot for both senior teams. Do not show the live selected match as upcoming.
    $fixtures=require __DIR__.'/bremnes-season-2026.php';
    $selected=rr_poll_selected_match_data();
    $started=!empty($selected['state']['opened']) || !empty($selected['state']['finished']);
    $current_id=(int)($selected['id']??0);
    $now=time();
    $upcoming=[];
    foreach ($fixtures as $id=>$fixture) {
        $kickoff=strtotime((string)($fixture['kickoff']??''));
        $waiting_today=!$started && (int)$id===$current_id && wp_date('Y-m-d',$kickoff,new DateTimeZone('Europe/Oslo'))===wp_date('Y-m-d',$now,new DateTimeZone('Europe/Oslo'));
        if (!$kickoff || ($kickoff <= $now && !$waiting_today) || ($started && (int)$id===$current_id)) continue;
        $upcoming[]=['id'=>(int)$id,'fixture'=>$fixture,'timestamp'=>$kickoff];
    }
    usort($upcoming,static function($a,$b){return $a['timestamp']<=>$b['timestamp'];});
    if (!$upcoming) return [];
    $next=$upcoming[0]; $id=$next['id']; $fixture=$next['fixture'];
    // Use an imported record when available; the verified fixture supplies a safe fallback.
    $stored=get_option('rr_poll_match_'.$id,[]);
    if (is_array($stored) && !empty($stored['home']) && !empty($stored['away']) && !empty($stored['kickoff'])) {
        $match=$stored;
    } else {
        $home=($fixture['home']??'')==='yes';
        $bremnes_logo='https://www.radiorubben.no/wp-content/uploads/2026/09/Bremnes-laglogo.png';
        $match=[
            'home'=>$home?'Bremnes':$fixture['opponent'],
            'away'=>$home?$fixture['opponent']:'Bremnes',
            'home_id'=>$home?(($fixture['team']??'')==='kvinner'?48835:30365):0,
            'home_logo'=>$home?$bremnes_logo:$fixture['logo'],
            'away_logo'=>$home?$fixture['logo']:$bremnes_logo,
            'kickoff'=>$fixture['kickoff'],
            'venue'=>$fixture['venue'],
            'competition'=>($fixture['team']??'')==='kvinner'?'3. div. kvinner Vestland':'5. div. menn avd. 03',
            'team'=>($fixture['team']??'')==='kvinner'?'Damer A':'Herrer A',
        ];
    }
    $match['id']=$id;
    $match['fixture_team']=$fixture['team'];
    $match['url']=home_url('/nestekamp/');
    return $match;
}
function rr_poll_next_match_header() {
    if (rr_poll_active_vote()) return [];
    return rr_poll_next_match_data();
}
function rr_poll_next_match_share_data() {
    $match=rr_poll_next_match_data();
    if (!$match) return [];
    $tz=new DateTimeZone('Europe/Oslo');
    $ts=strtotime((string)$match['kickoff']);
    $date=wp_date('l d.m.Y',$ts,$tz);
    $time=wp_date('H:i',$ts,$tz);
    $venue=sanitize_text_field($match['venue']??'');
    $title='Jeg skal på kamp – bli med!';
    $description=sanitize_text_field($match['home'].' – '.$match['away'].' · '.$date.' kl. '.$time.($venue!==''?' · '.$venue:''));
    $text=$title."\n".$match['home'].' – '.$match['away']."\n".'Kampstart: '.$date.' kl. '.$time.($venue!==''?"\n".'Bane: '.$venue:'')."\n".home_url('/nestekamp/');
    return ['match'=>$match,'title'=>$title,'description'=>$description,'text'=>$text,'url'=>home_url('/nestekamp/')];
}
function rr_poll_is_next_match_request() {
    $path=rtrim(wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'','/');
    return $path===rtrim(wp_parse_url(home_url('/nestekamp/'),PHP_URL_PATH),'/');
}
add_filter('rank_math/frontend/description',function($description){
    if (!rr_poll_is_next_match_request()) return $description;
    $share=rr_poll_next_match_share_data();
    return $share['description']??$description;
},1000);
add_filter('rank_math/opengraph/facebook/title',function($title){
    if (!rr_poll_is_next_match_request()) return $title;
    $share=rr_poll_next_match_share_data();
    return $share['title']??$title;
},1000);
add_filter('rank_math/opengraph/facebook/description',function($description){
    if (!rr_poll_is_next_match_request()) return $description;
    $share=rr_poll_next_match_share_data();
    return $share['description']??$description;
},1000);
add_filter('rank_math/opengraph/twitter/title',function($title){
    if (!rr_poll_is_next_match_request()) return $title;
    $share=rr_poll_next_match_share_data();
    return $share['title']??$title;
},1000);
add_filter('rank_math/opengraph/twitter/description',function($description){
    if (!rr_poll_is_next_match_request()) return $description;
    $share=rr_poll_next_match_share_data();
    return $share['description']??$description;
},1000);

function rr_poll_dashboard_url($url,$section='') {
    parse_str(wp_parse_url($url,PHP_URL_QUERY)??'', $args);
    unset($args['rr_bremnes_test'],$args['rr_vote'],$args['rr_poll_control'],$args['rr_poll_api'],$args['rr_admin_view'],$args['rr_dashboard_section']);
    $paths=['kampstyring'=>'/dashboard/kampstyring/','hendelser'=>'/dashboard/hendelser/','oppsett'=>'/dashboard/oppsett/'];
    return add_query_arg($args,home_url($paths[$section]??'/dashboard/'));
}
add_filter('login_with_vipps_remember_user',function($remember,$user,$session){
    return rr_poll_vipps_return($session) ? false : $remember;
},1000,3);

function rr_poll_special_document_title($title='') {
    $path=rtrim(wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'','/');
    $dashboard=rtrim(wp_parse_url(home_url('/dashboard/'),PHP_URL_PATH),'/');
    $vote=rtrim(wp_parse_url(home_url('/dagenskamp/'),PHP_URL_PATH),'/');
    $next=rtrim(wp_parse_url(home_url('/nestekamp/'),PHP_URL_PATH),'/');
    $site=wp_specialchars_decode(get_bloginfo('name'),ENT_QUOTES);
    if ($path===$vote) return 'Dagens kamp – '.$site;
    if ($path===$next) return 'Jeg skal på kamp – bli med! – '.$site;
    if ($path===$dashboard || str_starts_with($path,$dashboard.'/')) {
        if ($path===$dashboard.'/hendelser') return 'Speakerboard Historikk – '.$site;
        return 'Speakerboard LIVE – '.$site;
    }
    return $title;
}
add_filter('pre_get_document_title','rr_poll_special_document_title',1000);
add_filter('rank_math/frontend/title','rr_poll_special_document_title',1000);

add_action('template_redirect',function(){
    $path=rtrim(wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']),PHP_URL_PATH)??'','/');
    $dashboard=rtrim(wp_parse_url(home_url('/dashboard/'),PHP_URL_PATH),'/');
    $vote=rtrim(wp_parse_url(home_url('/dagenskamp/'),PHP_URL_PATH),'/');
    $next=rtrim(wp_parse_url(home_url('/nestekamp/'),PHP_URL_PATH),'/');
    $dashboard_routes=[
        $dashboard=>'oversikt',
        $dashboard.'/kampstyring'=>'kampstyring',
        $dashboard.'/hendelser'=>'hendelser',
        $dashboard.'/oppsett'=>'oppsett',
    ];
    if ($path!==$vote && $path!==$next && !isset($dashboard_routes[$path])) return;
    if ($path===$next) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
        global $wp_query;
        if ($wp_query instanceof WP_Query) { $wp_query->is_404=false; $wp_query->is_page=true; }
        nocache_headers(); status_header(200);
        require get_template_directory().'/inc/bremnes-next-match.php';
        exit;
    }
    $_GET['rr_bremnes_test']='1'; $_GET['rr_vote']='1';
    if (isset($dashboard_routes[$path])) {
        $_GET['rr_poll_control']='1';
        $_GET['rr_dashboard_section']=$dashboard_routes[$path];
    }
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404=false;
        $wp_query->is_page=true;
    }
    nocache_headers(); status_header(200);

    if (false && isset($_GET['rr_poll_control']) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rr_speaker_admin_action'])) {
        if (!current_user_can('manage_options')) wp_die('Kun WordPress-administrator kan endre Speakerboard-administratorer.','Ingen tilgang',['response'=>403]);
        check_admin_referer('rr_speaker_admins','rr_speaker_admin_nonce');
        $action=is_string($_POST['rr_speaker_admin_action'])?sanitize_key(wp_unslash($_POST['rr_speaker_admin_action'])):'';
        $rows=rr_poll_speaker_admins();

        if ($action==='add') {
            $name=isset($_POST['rr_speaker_admin_name']) && is_string($_POST['rr_speaker_admin_name']) ? sanitize_text_field(wp_unslash($_POST['rr_speaker_admin_name'])) : '';
            $phone=isset($_POST['rr_speaker_admin_phone']) && is_string($_POST['rr_speaker_admin_phone']) ? rr_poll_normalize_phone(wp_unslash($_POST['rr_speaker_admin_phone'])) : '';
            if ($name==='' || !$phone) wp_die('Oppgi navn og et gyldig norsk mobilnummer.','Ugyldig administrator',['response'=>400]);
            $found=false;
            foreach ($rows as &$row) {
                if ($row['phone']===$phone) { $row['name']=$name; $found=true; break; }
            }
            unset($row);
            if (!$found) $rows[]=['id'=>sanitize_key(wp_generate_uuid4()),'name'=>$name,'phone'=>$phone];
            update_option('rr_poll_speaker_admins_v1',$rows,false);
        } elseif ($action==='remove') {
            $id=isset($_POST['rr_speaker_admin_id']) && is_string($_POST['rr_speaker_admin_id']) ? sanitize_key(wp_unslash($_POST['rr_speaker_admin_id'])) : '';
            $rows=array_values(array_filter($rows,static function($row) use($id){return ($row['id']??'')!==$id;}));
            update_option('rr_poll_speaker_admins_v1',$rows,false);
        } else {
            wp_die('Ugyldig handling.','Feil',['response'=>400]);
        }

        wp_safe_redirect(add_query_arg('rr_admins_saved','1',home_url('/dashboard/')).'#speaker-admins',303);
        exit;
    }

    if (isset($_GET['rr_poll_control']) && !current_user_can('manage_options')) {
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        wp_die('Speakerboardet er kun tilgjengelig for administratorer.','Ingen tilgang',['response'=>403]);
        get_header();
        ?>
        <style>
        .rr-dashboard-login{width:min(100% - 32px,520px);margin:48px auto 120px;padding:28px;border:1px solid #46546c;border-radius:18px;background:#171d29;color:#f5f6fa;text-align:center}
        .rr-dashboard-login h1{margin:0 0 10px;font-size:30px}.rr-dashboard-login p{color:#c3cddd;line-height:1.6}
        .rr-dashboard-login .continue-with-vipps-wrapper{margin:22px 0}.rr-dashboard-login .continue-with-vipps{display:block}
        .rr-dashboard-login .rr-login-error{color:#ffb4bf}.rr-dashboard-login a:not(.continue-with-vipps){color:#aaceff}
        </style>
        <main class="rr-dashboard-login">
        <h1>Speakerboard</h1>
        <?php if (is_user_logged_in()): ?>
          <p class="rr-login-error">Denne Vipps-brukeren er ikke forhåndsgodkjent for Speakerboard.</p>
          <p>Kontakt Radio Rubben-administrator for tilgang.</p>
          <p><a href="<?php echo esc_url(wp_logout_url(home_url('/dashboard/'))); ?>">Logg ut og prøv en annen bruker</a></p>
        <?php else: ?>
          <p>Logg inn med Vipps. Kun forhåndsgodkjente administratorer får tilgang.</p>
          <?php echo do_shortcode('[login-with-vipps application="wordpress" verb="login" stretched="true"]'); ?>
        <?php endif; ?>
        </main>
        <?php
        get_footer();
        exit;
    }
    require get_template_directory().'/inc/bremnes-poll-test.php';
    exit;
},0);

/* Return successful Vipps logins to the public vote page, never the controls. */
function rr_poll_vipps_return($session) {
    $referer = isset($session['referer']) ? $session['referer'] : '';
    if (!is_string($referer)) return '';
    $origin = wp_parse_url($referer);
    $home = wp_parse_url(home_url('/'));
    if (!is_array($origin) || empty($origin['host']) || strtolower($origin['host']) !== strtolower($home['host'])) return '';

    $path=rtrim($origin['path']??'/','/');
    $vote_path=rtrim(wp_parse_url(home_url('/dagenskamp/'),PHP_URL_PATH),'/');
    $dashboard_path=rtrim(wp_parse_url(home_url('/dashboard/'),PHP_URL_PATH),'/');
    parse_str($origin['query'] ?? '', $args);

    if ($path===$dashboard_path || str_starts_with($path,$dashboard_path.'/')) {
        $target=home_url('/dashboard/');
    } elseif ($path===$vote_path || isset($args['rr_bremnes_test'],$args['rr_vote'])) {
        $target=home_url('/dagenskamp/');
        if (isset($args['rr_match']) && is_string($args['rr_match']) && ctype_digit($args['rr_match'])) $target=add_query_arg('rr_match',absint($args['rr_match']),$target);
    } else {
        return '';
    }

    if (isset($args['wpvibe_preview']) && is_string($args['wpvibe_preview'])) {
        $target=add_query_arg('wpvibe_preview',sanitize_text_field($args['wpvibe_preview']),$target);
    }
    return $target;
}
add_action('continue_with_vipps_before_wordpress_login_redirect', function($user,$session) {
    $target = rr_poll_vipps_return($session);
    if (!$target) return;
    add_filter('login_redirect',function($redirect,$requested,$logged_in_user) use ($target) {
        return $logged_in_user instanceof WP_User ? $target : $redirect;
    },1000,3);
},20,2);
add_filter('continue_with_vipps_wordpress_confirm_redirect',function($redirect,$user_id,$session) {
    return rr_poll_vipps_return($session) ?: $redirect;
},1000,3);
/* Isolated manual match trial. No fixture feed or scheduled publication. */
add_action('template_redirect', function () {
    if (!isset($_GET['rr_bremnes_test'])) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    if (isset($_GET['rr_vote'])) {
        require get_template_directory() . '/inc/bremnes-poll-test.php';
        exit;
    }
    if (isset($_GET['rr_speaker_demo'])) {
        require get_template_directory() . '/inc/rubben-speaker-fiction.php';
        exit;
    }
    if (isset($_GET['rr_match_demo']) && $_GET['rr_match_demo'] === '8985483') {
        require get_template_directory() . '/inc/bremnes-match-demo.php';
        exit;
    }
    if (isset($_GET['rr_match_demo']) && $_GET['rr_match_demo'] === '8998086') {
        require get_template_directory() . '/inc/brann-match-demo.php';
        exit;
    }
    $team = isset($_GET['lag']) && $_GET['lag'] === 'kvinner' ? 'kvinner' : 'herrer';
    $editable = current_user_can('edit_others_posts');
    $editing = $editable && isset($_GET['rr_edit']);
    $fixtures = require get_template_directory() . '/inc/bremnes-season-2026.php';
    $match_id = isset($_GET['kamp']) ? absint($_GET['kamp']) : ($team === 'herrer' ? 8985491 : 8984413);
    if (!isset($fixtures[$match_id]) || $fixtures[$match_id]['team'] !== $team) {
        wp_die('Ukjent kamp for valgt lag.', '', ['response'=>404]);
    }
    $fixture = $fixtures[$match_id];
    $key = 'rr_manual_match_trial_v1_' . $team . '_' . $match_id;
    $defaults = ['opponent'=>$fixture['opponent'], 'venue'=>$fixture['venue'], 'home'=>$fixture['home'], 'us'=>'', 'them'=>'', 'status'=>'Ikke startet', 'minute'=>'', 'events'=>'', 'updated'=>0];
    $state = wp_parse_args(get_option($key, []), $defaults);
    $statuses = ['Ikke startet', '1. omgang', 'Pause', '2. omgang', 'Slutt', 'Utsatt'];
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$editable) wp_die('Du må være innlogget med redaktørtilgang.', '', ['response'=>403]);
        check_admin_referer('rr_match_trial_' . $team . '_' . $match_id);
        $input = isset($_POST['match']) && is_array($_POST['match']) ? wp_unslash($_POST['match']) : [];
        $next = $defaults;
        foreach (['opponent','venue','minute'] as $field) {
            $next[$field] = sanitize_text_field($input[$field] ?? '');
        }
        $next['opponent'] = $fixture['opponent'];
        $next['home'] = $fixture['home'];
        $next['status'] = in_array($input['status'] ?? '', $statuses, true) ? $input['status'] : 'Ikke startet';
        foreach (['us','them'] as $field) {
            $score = trim((string)($input[$field] ?? ''));
            if ($score !== '' && (!ctype_digit($score) || (int)$score > 99)) $error = 'Resultatet må være et helt tall mellom 0 og 99, eller stå tomt.';
            $next[$field] = $score === '' ? '' : (string)(int)$score;
        }
        if (($next['us'] === '') !== ($next['them'] === '')) $error = 'Fyll inn begge måltallene, eller la begge stå tomme.';
        $next['events'] = sanitize_textarea_field($input['events'] ?? '');
        foreach (['players_home','players_away','standings'] as $field) {
            $next[$field] = sanitize_textarea_field($input[$field] ?? '');
        }
        foreach (array_filter(array_map('trim', explode("\n", $next['standings']))) as $row) {
            if (count(explode('|', $row)) !== 5) $error = 'Tabellrader må ha fem felt: plass | lag | kamper | mål | poeng.';
        }
        if (!$error) {
            $next['updated'] = time();
            update_option($key, $next, false);
            wp_safe_redirect(add_query_arg('lagret','1', wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }
        $state = $next;
    }
    $base = remove_query_arg(['lag','kamp','rr_edit','lagret','rr_match_demo']);
    $home = $state['home'] === 'yes';
    $left = $home ? 'Bremnes' : $state['opponent'];
    $right = $home ? $state['opponent'] : 'Bremnes';
    $left_score = $home ? $state['us'] : $state['them'];
    $right_score = $home ? $state['them'] : $state['us'];
    get_header();
?>
<style>
.rr-match-trial{max-width:760px;margin:36px auto;padding:0 18px 100px;color:#f4f5f7}
.rr-match-trial *{box-sizing:border-box}
.rr-match-trial .trial-label{display:inline-block;background:#453514;color:#ffe09a;padding:7px 12px;border-radius:6px;font-size:13px;font-weight:700}
.rr-match-trial h1{font-size:clamp(30px,7vw,46px);margin:18px 0 8px;line-height:1.15}
.rr-match-trial .muted{color:#bcc1cc;font-size:14px;line-height:1.6}
.rr-match-trial .team-tabs{display:flex;gap:10px;margin:24px 0}
.rr-match-trial .team-tabs a,.rr-match-trial .action{display:inline-block;padding:12px 17px;border:1px solid #626977;border-radius:10px;color:#fff;text-decoration:none}
.rr-match-trial .team-tabs a[aria-current="page"]{background:#c62c40;border-color:#c62c40}
.rr-match-trial .scorecard{background:linear-gradient(135deg,#242630,#12141c);border:1px solid #444956;border-radius:18px;padding:26px 18px}
.rr-match-trial .status{text-align:center;color:#ffbdc7;font-size:14px;font-weight:700}
.rr-match-trial .scoreline{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:12px;align-items:center;margin:26px 0;text-align:center}
.rr-match-trial .club{font-size:clamp(15px,4vw,24px);font-weight:700;overflow-wrap:anywhere}
.rr-match-trial .score{font-size:clamp(30px,8vw,48px);font-weight:800;white-space:nowrap}
.rr-match-trial .venue{text-align:center}
.rr-match-trial h2{font-size:22px;margin:28px 0 16px}
.rr-match-trial .events{list-style:none;padding:0;margin:0}
.rr-match-trial .events li{border-left:3px solid #8b92a0;padding:12px 16px;margin:10px 0;background:#1b1e27;border-radius:0 9px 9px 0;white-space:pre-wrap;overflow-wrap:anywhere}
.rr-match-trial form{margin-top:24px;padding:22px;background:#1b1e27;border:1px solid #444956;border-radius:14px}
.rr-match-trial label{display:block;font-size:15px;font-weight:600;margin:0 0 16px}
.rr-match-trial input,.rr-match-trial select,.rr-match-trial textarea{display:block;width:100%;min-height:46px;margin-top:7px;padding:11px;border:1px solid #747b89;border-radius:7px;background:#0d1017;color:#fff;font:inherit;font-size:16px}
.rr-match-trial .fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.rr-match-trial button{background:#c62c40;color:#fff;border:0;border-radius:8px;min-height:48px;padding:12px 20px;font-size:16px;font-weight:700;cursor:pointer}
.rr-match-trial a:focus-visible,.rr-match-trial button:focus-visible{outline:3px solid #fff;outline-offset:3px}
.rr-match-trial .notice{padding:12px;border:1px solid #9abbb0;border-radius:8px}
</style>
<main class="rr-match-trial">
<span class="trial-label">TESTVISNING · Ingen offisiell kampdekning</span>
<h1>Bremnes direkte</h1>
<p class="muted">Manuell kampoppdatering fra Radio Rubben.</p>
<?php if (isset($_GET['kamp'])): ?>
<p class="muted"><?php echo esc_html(wp_date('d.m.Y H:i', strtotime($fixture['kickoff']), new DateTimeZone('Europe/Oslo'))); ?> · Norsk tid<br><a href="<?php echo esc_url('https://www.fotball.no/fotballdata/kamp/?fiksId=' . $match_id); ?>" target="_blank" rel="noopener noreferrer">fotball.no ↗</a><br>Ingen automatisk innhenting er aktivert. Terminlisten ble kontrollert 19. september.</p>
<?php endif; ?>
<nav class="team-tabs" aria-label="Velg A-lag">
<?php foreach (['herrer'=>'A-lag herrer','kvinner'=>'A-lag kvinner'] as $slug=>$label): ?>
<a href="<?php echo esc_url(add_query_arg('lag',$slug,$base)); ?>" <?php if ($team === $slug) echo 'aria-current="page"'; ?>><?php echo esc_html($label); ?></a>
<?php endforeach; ?>
</nav>
<?php require get_template_directory() . '/inc/bremnes-fixture-tabs.php'; ?>
<?php if (!isset($_GET['kamp'])) { echo '</main>'; get_footer(); exit; } ?>
<section class="scorecard" aria-label="Kampstatus">
<div class="status"><?php echo esc_html($state['status']); ?><?php if ($state['minute'] !== '') echo ' · ' . esc_html($state['minute']) . ' min'; ?></div>
<div class="scoreline">
<div class="club"><img style="display:block;width:64px;height:64px;object-fit:contain;margin:0 auto 12px" src="<?php echo esc_url($home ? 'https://www.radiorubben.no/wp-content/uploads/2026/09/Bremnes-laglogo.png' : $fixture['logo']); ?>" alt="" referrerpolicy="no-referrer"><?php echo esc_html($left); ?></div>
<div class="score" aria-label="Resultat"><?php echo esc_html($left_score === '' ? '–' : $left_score); ?> : <?php echo esc_html($right_score === '' ? '–' : $right_score); ?></div>
<div class="club"><img style="display:block;width:64px;height:64px;object-fit:contain;margin:0 auto 12px" src="<?php echo esc_url($home ? $fixture['logo'] : 'https://www.radiorubben.no/wp-content/uploads/2026/09/Bremnes-laglogo.png'); ?>" alt="" referrerpolicy="no-referrer"><?php echo esc_html($right); ?></div>
</div>
<?php if ($state['venue'] !== ''): ?><p class="muted venue"><?php echo esc_html($state['venue']); ?></p><?php endif; ?>
</section>
<?php require get_template_directory() . '/inc/bremnes-match-tabs.php'; ?>
<?php if ($view === 'hendelser'): ?>
<h2>Fra kampen</h2>
<?php $events = array_filter(array_map('trim', explode("\n", $state['events']))); ?>
<?php if (!$events): ?><p class="muted">Ingen hendelser lagt inn ennå.</p><?php else: ?>
<ol class="events"><?php foreach ($events as $event): ?><li><?php echo esc_html($event); ?></li><?php endforeach; ?></ol>
<?php endif; ?>
<?php endif; ?>
<p class="muted"><?php echo $state['updated'] ? 'Sist lagret: ' . esc_html(wp_date('d.m.Y H:i:s', $state['updated'])) : 'Venter på første manuelle oppdatering.'; ?><br>Resultat og hendelser registreres manuelt. Testdata er ikke ekte kampresultater.</p>
<?php if ($editing): ?>
<?php if ($error): ?><p class="notice" role="alert"><?php echo esc_html($error); ?></p><?php elseif (isset($_GET['lagret'])): ?><p class="notice" role="status">Testkampen er lagret.</p><?php endif; ?>
<form method="post">
<h2>Oppdater <?php echo esc_html($team); ?></h2>
<?php wp_nonce_field('rr_match_trial_' . $team . '_' . $match_id); ?>
<label>Motstander<input name="match[opponent]" maxlength="100" value="<?php echo esc_attr($state['opponent']); ?>" required></label>
<label>Bane / sted<input name="match[venue]" maxlength="150" value="<?php echo esc_attr($state['venue']); ?>"></label>
<label>Bremnes spiller<select name="match[home]"><option value="yes" <?php selected($state['home'],'yes'); ?>>Hjemme</option><option value="no" <?php selected($state['home'],'no'); ?>>Borte</option></select></label>
<div class="fields">
<label>Mål Bremnes<input type="number" inputmode="numeric" min="0" max="99" name="match[us]" value="<?php echo esc_attr($state['us']); ?>"></label>
<label>Mål motstander<input type="number" inputmode="numeric" min="0" max="99" name="match[them]" value="<?php echo esc_attr($state['them']); ?>"></label>
</div>
<div class="fields">
<label>Status<select name="match[status]"><?php foreach ($statuses as $status): ?><option <?php selected($state['status'],$status); ?>><?php echo esc_html($status); ?></option><?php endforeach; ?></select></label>
<label>Spilt minutt<input name="match[minute]" maxlength="10" placeholder="57 eller 90+2" value="<?php echo esc_attr($state['minute']); ?>"></label>
</div>
<label>Hendelser – nyeste øverst<textarea name="match[events]" rows="7" maxlength="12000" placeholder="57′ Mål til Bremnes – spillernavn"><?php echo esc_textarea($state['events']); ?></textarea></label>
<p class="muted">Én hendelse per linje. Oppdater måltallene separat ved scoring. Minuttet settes manuelt. Hver kamp har separat lagring. Bruk gjerne ⚽ for mål, 🟨/🟥 for kort og 🟢 inn / 🔴 ut ved bytter.</p>
<details><summary>Spillere og tabell</summary>
<label>Hjemmelag – <?php echo esc_html($left); ?><textarea name="match[players_home]" rows="8" maxlength="12000" placeholder="Én spiller per linje. Skill startoppstilling og innbyttere."><?php echo esc_textarea($state['players_home'] ?? ''); ?></textarea></label>
<label>Bortelag – <?php echo esc_html($right); ?><textarea name="match[players_away]" rows="8" maxlength="12000" placeholder="Én spiller per linje. Skill startoppstilling og innbyttere."><?php echo esc_textarea($state['players_away'] ?? ''); ?></textarea></label>
<label>Tabell – én rad per lag<textarea name="match[standings]" rows="10" maxlength="12000" placeholder="Plass | Lag | Kamper | Mål | Poeng"><?php echo esc_textarea($state['standings'] ?? ''); ?></textarea></label>
<p class="muted">Bruk kun kontrollerte kamptropper og tabelltall. Opplysningene lagres for denne kampen.</p></details>
<button type="submit">Lagre testkamp</button>
<a class="action" href="<?php echo esc_url(remove_query_arg(['rr_edit','lagret'])); ?>">Se kampvisning</a>
</form>
<?php elseif ($editable): ?>
<a class="action" href="<?php echo esc_url(add_query_arg('rr_edit','1')); ?>">Oppdater testkamp</a>
<?php else: ?>
<a class="action" href="<?php echo esc_url(wp_login_url(add_query_arg('rr_edit','1'))); ?>">Logg inn for å oppdatere</a>
<?php endif; ?>
<?php if (!$editing): ?>
<p class="muted">Visningen henter siste lagrede oppdatering hvert 30. sekund.</p>
<script>
(function(){
let busy=false;
setInterval(async function(){
if(document.hidden || busy) return;
busy=true;
try {
const response=await fetch(location.href,{cache:'no-store',credentials:'same-origin'});
if(!response.ok) throw new Error('network');
const doc=new DOMParser().parseFromString(await response.text(),'text/html');
const next=doc.querySelector('.rr-match-trial');
const current=document.querySelector('.rr-match-trial');
if(!next || !current) throw new Error('markup');
next.querySelectorAll('script').forEach(function(el){el.remove();});
current.replaceWith(next);
} catch(error) {
const current=document.querySelector('.rr-match-trial');
if(current && !current.querySelector('.refresh-error')){
const notice=document.createElement('p');
notice.className='muted refresh-error';
notice.setAttribute('role','status');
notice.textContent='Kunne ikke hente oppdatering. Viser sist hentede data og prøver igjen.';
current.appendChild(notice);
}
} finally {busy=false;}
},30000);
})();
</script>
<?php endif; ?>
</main>
<?php get_footer(); exit;
});
