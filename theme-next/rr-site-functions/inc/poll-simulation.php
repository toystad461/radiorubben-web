<?php
defined('ABSPATH') || exit;

// A private rehearsal, with no writes to live match, vote, archive or article keys.
function rr_poll_sim_new($match) {
    return ['match'=>$match, 'revision'=>wp_generate_uuid4(), 'votes'=>[], 'state'=>[
        'period'=>0,'elapsed'=>0,'started'=>0,'running'=>false,'opened'=>false,
        'closed'=>false,'finished'=>false,'entered'=>[],'eligible_players'=>[]]];
}
function rr_poll_sim_action($sim,$action,$number=0,$now=null) {
    $now=$now??time(); $state=&$sim['state']; $match=$sim['match'];
    if ($action==='reset') return rr_poll_sim_new($match);
    if (!empty($state['finished'])) return new WP_Error('finished','Start en ny test først.');
    if ($action==='start' && $state['period']===0 && rr_poll_lineup_ready($match)) {
        $state['period']=1; $state['opened']=true; $state['running']=true; $state['started']=$now;
    } elseif ($action==='half' && $state['period']===1 && $state['running']) {
        $state['elapsed']=rr_poll_elapsed($state,$now); $state['running']=false;
    } elseif ($action==='second' && $state['period']===1 && !$state['running']) {
        $state['period']=2; $state['elapsed']=2700; $state['started']=$now; $state['running']=true;
    } elseif ($action==='substitute' && $state['opened'] && !rr_poll_is_closed($state,$now)
        && in_array($number,$match['bench'],true) && isset($match['roster'][$number])) {
        $state['entered'][$number]=(int)floor(rr_poll_elapsed($state,$now)/60);
    } elseif ($action==='vote' && $state['opened'] && !rr_poll_is_closed($state,$now)
        && isset(rr_poll_allowed_players($match,$state)[$number])) {
        $sim['votes'][$number]=min(10000,($sim['votes'][$number]??0)+1);
    } elseif ($action==='close' && $state['opened']) {
        $state['closed']=true;
    } elseif ($action==='finish' && $state['period']===2) {
        $state['elapsed']=rr_poll_elapsed($state,$now); $state['running']=false;
        $state['closed']=true; $state['finished']=true;
    } else return new WP_Error('action','Handlingen er ikke tilgjengelig i denne testfasen.');
    $sim['revision']=wp_generate_uuid4(); return $sim;
}

add_action('template_redirect',static function() {
    $path=rtrim((string)wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');
    if ($path!==rtrim((string)wp_parse_url(home_url('/avstemningstest/'),PHP_URL_PATH),'/')) return;
    if (!is_user_logged_in()) { auth_redirect(); exit; }
    if (!current_user_can('manage_options')) wp_die('Kun tilgjengelig for administratorer.','Ingen tilgang',['response'=>403]);
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
    nocache_headers(); status_header(200); header('X-Robots-Tag: noindex, nofollow',true);
    $rr_poll_definitions_only=true;
    require_once __DIR__.'/bremnes-poll-match.php';
    require_once __DIR__.'/bremnes-poll-rules.php';
    $key='rr_poll_simulation_user_'.get_current_user_id();
    $sim=get_option($key,false); $error='';
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        check_admin_referer('rr_poll_simulation','rr_sim_nonce');
        // Atomic, short request lock. Never steal an expired lock from a running writer.
        $lock=$key.'_lock';
        if (!add_option($lock,time(),'',false)) {
            $error='En annen testhandling behandles. Prøv igjen. Ved fastlåst lås må administrator kontrollere den.';
        } else {
            $saved=false;
            try {
                $sim=get_option($key,false);
                $action=is_string($_POST['action']??null)?wp_unslash($_POST['action']):'';
                if ($action==='import') {
                    $input=is_string($_POST['match']??null)?wp_unslash($_POST['match']):'';
                    $id=rr_poll_parse_match_id($input);
                    $match=$id?rr_poll_fd_fetch_match($id,false):new WP_Error('id','Bruk FIKS-ID eller Fotball.no-lenke med fiksId.');
                    $next=is_wp_error($match)?$match:rr_poll_sim_new($match);
                } elseif (!$sim || !is_string($_POST['revision']??null) || !hash_equals($sim['revision'],wp_unslash($_POST['revision']))) {
                    $next=new WP_Error('stale','Testen er endret. Last siden på nytt før neste handling.');
                } else {
                    $number=is_string($_POST['number']??null)&&ctype_digit($_POST['number'])?(int)$_POST['number']:0;
                    $next=rr_poll_sim_action($sim,$action,$number);
                }
                if (is_wp_error($next)) $error=$next->get_error_message();
                elseif (update_option($key,$next,false)) {
                    $saved=true;
                } else $error='Testen kunne ikke lagres. Prøv igjen.';
            } finally { delete_option($lock); }
            if ($saved) { wp_safe_redirect(home_url('/avstemningstest/')); exit; }
        }
    }
    // Do not expose the archive's voter identities, award draw or other private state.
    $archive=$sim?get_option('rr_match_archive_'.(int)$sim['match']['id'],false):false;
    ?><!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Simulert avstemning – Radio Rubben</title>
    <style>body{font:18px system-ui;background:#101827;color:#fff;margin:2rem auto;padding:1rem;max-width:850px}a{color:#9dd7ff}button,input,select{font:inherit;padding:.6rem;margin:.3rem}section{border:1px solid #64748b;padding:1rem;margin:1rem 0}button{cursor:pointer}table{width:100%;text-align:left}td,th{padding:.4rem}</style>
    <h1>Simulert avstemning</h1><p>Privat test for din administratorbruker. Henter historisk kamp og lagoppstilling. Teststemmer påvirker ikke ekte avstemning, Dagens Bremnesing, arkiv eller referat.</p>
    <p><a href="<?php echo esc_url(home_url('/dashboard_test/')); ?>">Tilbake til dashboard</a></p>
    <?php if ($error): ?><p role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
    <section><form method="post"><?php wp_nonce_field('rr_poll_simulation','rr_sim_nonce'); ?>
    <input type="hidden" name="action" value="import"><label>Kamp-ID eller FIKS-lenke <input name="match" required maxlength="500" placeholder="8985501" value="<?php echo $sim?(int)$sim['match']['id']:''; ?>"></label>
    <button>Hent historisk kamp</button><p>Bruk tallet etter fiksId= i lenken. Det lange kampnummeret på kampsiden kan ikke brukes. Hver import starter en ny, tom test.</p></form></section>
    <?php if ($sim): $match=$sim['match']; $state=$sim['state']; ?>
    <section><h2><?php echo esc_html($match['home'].' – '.$match['away']); ?></h2>
    <p><?php echo esc_html($match['date_label']); ?> · <?php echo esc_html($match['venue']); ?></p>
    <?php if (is_array($archive) && isset($archive['score'])): ?><p>Historisk resultat fra kampens arkiv: <?php echo esc_html((string)($archive['score']['home']??'?').' – '.(string)($archive['score']['away']??'?')); ?>. Resultatet endres ikke av testen.</p><?php endif; ?>
    <p>Teststatus: <?php echo esc_html($state['finished']?'Avsluttet':($state['opened']?'Startet':'Klar til start')); ?>.
    Avstemning: <?php echo !$state['opened']?'Ikke åpnet':(rr_poll_is_closed($state)?'Stengt':'Åpen'); ?>.
    Omgang <?php echo (int)$state['period']; ?> · <?php echo (int)floor(rr_poll_elapsed($state)/60); ?> minutter.</p>
    <?php if (!rr_poll_lineup_ready($match)): ?><p>Startoppstilling mangler. Hent kampen på nytt når kilden har publisert den.</p><?php endif; ?>
    <form method="post"><?php wp_nonce_field('rr_poll_simulation','rr_sim_nonce'); ?><input type="hidden" name="revision" value="<?php echo esc_attr($sim['revision']); ?>">
    <?php foreach (['start'=>'Start kampen','half'=>'Pause','second'=>'Start andre omgang','close'=>'Steng testavstemning','finish'=>'Avslutt testkamp','reset'=>'Ny test med samme kamp'] as $action=>$label): ?><button name="action" value="<?php echo esc_attr($action); ?>"><?php echo esc_html($label); ?></button><?php endforeach; ?>
    </form></section><section><h2>Lagoppstilling og teststemmer</h2><table><tr><th>Nr.</th><th>Spiller</th><th>Rolle</th><th>Teststemmer</th></tr>
    <?php foreach ($match['roster'] as $number=>$name): ?><tr><td><?php echo (int)$number; ?></td><td><?php echo esc_html($name); ?></td><td><?php echo in_array((int)$number,$match['starters'],true)?'Starter':'Reserve'; ?></td><td><?php echo (int)($sim['votes'][$number]??0); ?></td></tr><?php endforeach; ?></table>
    <form method="post"><?php wp_nonce_field('rr_poll_simulation','rr_sim_nonce'); ?><input type="hidden" name="revision" value="<?php echo esc_attr($sim['revision']); ?>">
    <label>Spiller <select name="number"><?php foreach ($match['roster'] as $number=>$name): ?><option value="<?php echo (int)$number; ?>"><?php echo esc_html($number.' '.$name); ?></option><?php endforeach; ?></select></label>
    <button name="action" value="vote">Legg til én teststemme</button><button name="action" value="substitute">Simuler innbytte</button>
    </form><p>Teststemmer bruker ingen Vipps-konto og gir ingen lodd eller premier. Avstemningen følger samme spiller- og 85-minuttersregel som ordinær avstemning.</p></section>
    <?php endif; ?></html><?php exit;
},-30);

