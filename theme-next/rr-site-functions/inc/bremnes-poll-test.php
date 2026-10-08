<?php
if (!defined('ABSPATH')) exit;
/* Keep the existing match-scoped option namespace so current votes and match states remain accessible. */
$rr_roster = [
1=>'Mads Katla Ellingsen',2=>'Niklas Rasmussen',3=>'Jonas Bjelland Håstø',
4=>'Marius Kvernøy',5=>'Ola Gjerde',7=>'Aron Skippervik Simonsen',8=>'Pavlo Buriak',
9=>'Jonas Hammer Simonsen',10=>'Daniel Våge Nilsen',11=>'Erlend Meling Nesse',
12=>'Sindre Koldal Stølen',13=>'Kato Gjermund Aasheim',14=>'Nikolai Mæland',
15=>'Sivert Tysse Fylkesnes',19=>'Adrian Alvsvåg Bukkøy',
20=>'Johannes Fylkesnes Johannessen',21=>'Tobias Sortland Kallevåg',22=>'Adrian Søvold'];
require __DIR__.'/bremnes-poll-match.php';
require_once __DIR__.'/bremnes-poll-rules.php';
require_once __DIR__.'/bremnes-speaker-events.php';
$rr_key = 'rr_poll_test_'.$rr_match_id.'_vipps_v3_75';
$rr_archive=get_option('rr_match_archive_'.$rr_match_id,false);
$rr_archive_public=!$rr_control && !isset($_GET['rr_poll_api']) && $_SERVER['REQUEST_METHOD']==='GET' && is_array($rr_archive);
if ($rr_archive_public) { $rr_match=$rr_archive['match']; $rr_roster=$rr_match['roster']??[]; }
$rr_admin = current_user_can('manage_options');
$rr_dashboard_section = ($rr_control && $rr_admin && isset($_GET['rr_dashboard_section']) && is_string($_GET['rr_dashboard_section']))
    ? sanitize_key(wp_unslash($_GET['rr_dashboard_section'])) : 'oversikt';
if (!in_array($rr_dashboard_section,['oversikt','kampstyring','hendelser','oppsett'],true)) $rr_dashboard_section='oversikt';
$rr_live_section = in_array($rr_dashboard_section,['oversikt','kampstyring'],true);
// Use the plugin's verified account mapping, never a phone number or browser cookie.
$rr_vipps_sub = '';
if (is_user_logged_in() && is_callable(['VippsLogin','instance'])) {
    $rr_vipps_plugin = VippsLogin::instance();
    if (is_callable([$rr_vipps_plugin,'get_vipps_account'])) {
        $rr_vipps_account = $rr_vipps_plugin->get_vipps_account(get_current_user_id());
        if (is_array($rr_vipps_account) && isset($rr_vipps_account[1]) && is_string($rr_vipps_account[1])) {
            $rr_vipps_sub = trim($rr_vipps_account[1]);
        }
    }
}
$rr_admin_vote = $rr_admin && is_user_logged_in() && $rr_vipps_sub === '';
$rr_eligible = $rr_vipps_sub !== '' || $rr_admin_vote;
if ($rr_vipps_sub !== '') {
    $rr_voter = hash_hmac('sha256', 'vipps:'.$rr_vipps_sub, wp_salt('auth'));
} elseif ($rr_admin_vote) {
    $rr_voter = hash_hmac('sha256', 'wp-admin:'.get_current_user_id(), wp_salt('auth'));
} else {
    $rr_voter = '';
}

$rr_default = ['session'=>'initial','period'=>0,'elapsed'=>0,'started'=>0,'running'=>false,'opened'=>false,'closed'=>false,'entered'=>[],'entered_away'=>[],'eligible_players'=>[],'events'=>[],'finished'=>false,'poll_award'=>null];
$rr_state = $rr_archive_public ? $rr_archive['state'] : get_option($rr_key, $rr_default);
require __DIR__.'/bremnes-nff-refresh.php';
if ($rr_archive_public) { $rr_nff=$rr_archive['nff']; $rr_nff_source=$rr_archive['source']; }
$rr_seconds = 'rr_poll_elapsed';
$rr_closed = 'rr_poll_is_closed';
$rr_token = $rr_eligible ? hash_hmac('sha256', $rr_voter.'|'.$rr_match_id.'|'.$rr_state['session'].'|'.wp_get_session_token(), wp_salt('nonce')) : '';
$rr_error = static function($message, $code=400) { wp_send_json(['ok'=>false,'message'=>$message],$code); };
$rr_public_events=[];
$rr_event_labels=['goal'=>'Mål','yellow'=>'Gult kort','red'=>'Rødt kort','sub'=>'Bytte'];
foreach (($rr_state['events']??[]) as $event) {
    if (!isset($rr_event_labels[$event['type']??''])) continue;
    $side=($event['side']??'home')==='away'?'away':'home';
    $names=$side==='away'?($rr_match['away_roster']??[]):$rr_roster;
    $player=$names[(int)($event['player']??0)]??'Ukjent spiller';
    $description=$event['type']==='sub' ? 'Nr. '.(int)($event['out']??0).' '.($names[(int)($event['out']??0)]??'Ukjent spiller').' ut → Nr. '.(int)($event['player']??0).' '.$player.' inn' : $player;
    $rr_public_events[]=['side'=>$side,'type'=>$event['type'],'minute'=>(int)floor(max(0,(int)($event['seconds']??0))/60)+1,
        'label'=>!empty($event['dismissed'])?'Andre gule · utvist':$rr_event_labels[$event['type']],
        'description'=>$description,'dismissed'=>!empty($event['dismissed'])];
}
$rr_public_events=array_reverse($rr_public_events);
$rr_manual_events=$rr_public_events;
$rr_display_score=rr_speaker_score($rr_state);
if ($rr_nff_source==='nff') {
    // Substitutions are managed by the speaker even when NFF supplies other events.
    $manual_subs=array_values(array_filter($rr_public_events,static function($e){return $e['type']==='sub';}));
    foreach ($manual_subs as &$sub) $sub['label']='Bytte · speaker';
    unset($sub);
    $nff_events=array_values(array_filter($rr_nff['events']??[],static function($e){return ($e['type']??'')!=='sub';}));
    $rr_public_events=array_merge($nff_events,$manual_subs);
    foreach ($rr_public_events as $i=>&$item) {
        $parts=explode('+',(string)$item['minute']);
        $item['_minute']=(int)$parts[0]+(int)($parts[1]??0);
        $item['_order']=$i;
    }
    unset($item);
    usort($rr_public_events,static function($a,$b){return ($b['_minute']<=>$a['_minute'])?:($a['_order']<=>$b['_order']);});
    foreach ($rr_public_events as &$item) { unset($item['_minute'],$item['_order']); }
    unset($item);
    $rr_display_score=$rr_nff['score']??rr_speaker_score($rr_state);
}
$rr_event_credit=$rr_nff_source==='nff'?'Fotball.no + bytter registrert av speaker · sist hentet '.wp_date('d.m.Y H:i',(int)($rr_nff['fetched']??time())):'Registrert av speaker';

$rr_vote_results = static function($state) use ($rr_key,$rr_roster,$rr_archive_public,$rr_archive) {
    if ($rr_archive_public) return $rr_archive['results'];
    global $wpdb;
    $prefix = $wpdb->esc_like($rr_key.'_vote_'.($state['session']??'').'_').'%';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_value AS player, COUNT(*) AS total FROM {$wpdb->options} WHERE option_name LIKE %s GROUP BY option_value", $prefix), ARRAY_A);
    $results=array_map(static function($row) use ($rr_roster) {
        $number=(int)$row['player'];
        return ['number'=>$number,'player'=>$rr_roster[$number]??'Ukjent','total'=>(int)$row['total']];
    },$rows);
    usort($results,static function($a,$b){return ($b['total']<=>$a['total']) ?: strcasecmp($a['player'],$b['player']);});
    return $results;
};

if (!$rr_archive_public && !empty($rr_state['opened']) && $rr_state['period']===2 && $rr_seconds($rr_state)>=5160 && empty($rr_state['poll_award'])) {
    $rr_award_results=$rr_vote_results($rr_state);
    if ($rr_award_results) {
        $top=(int)$rr_award_results[0]['total'];
        $leaders=array_values(array_filter($rr_award_results,static function($row) use ($top){return (int)$row['total']===$top;}));
        $description=count($leaders)===1
            ? 'Nr. '.$leaders[0]['number'].' '.$leaders[0]['player'].' · '.$top.' stemmer'
            : 'Delt avstemning: '.implode(' / ',array_map(static function($row){return 'Nr. '.$row['number'].' '.$row['player'];},$leaders)).' · '.$top.' stemmer hver';
        // One chance per accepted vote, including votes for other players and tied polls.
        // Fix the selected vote atomically so concurrent requests cannot redraw it.
        global $wpdb;
        $session=(string)($rr_state['session']??'');
        $prefix=$rr_key.'_vote_'.$session.'_';
        $vote_keys=$wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($prefix).'%'
        ));
        $entrants=[];
        foreach ($vote_keys as $vote_key) {
            $hash=substr($vote_key,strlen($prefix));
            if (preg_match('/^[a-f0-9]{64}$/D',$hash)) $entrants[]=$hash;
        }
        if ($entrants) {
            $draw_vote_key=$rr_key.'_prize_vote_'.$session;
            $selected_hash=$entrants[random_int(0,count($entrants)-1)];
            if (!add_option($draw_vote_key,$selected_hash,'',false)) $selected_hash=(string)get_option($draw_vote_key,'');
            $selected=(int)get_option($rr_key.'_entrant_'.$session.'_'.$selected_hash,0);
            // Resolve older administrator votes recorded before entrant metadata existed.
            if (!$selected) foreach (get_users(['capability'=>'manage_options','fields'=>'ID']) as $admin_id) {
                if (hash_equals(hash_hmac('sha256','wp-admin:'.$admin_id,wp_salt('auth')),$selected_hash)) { $selected=(int)$admin_id; break; }
            }
            $user=$selected ? get_userdata($selected) : false;
            if ($user) {
                add_option($rr_key.'_prize_'.$session,$selected,'',false);
                $first=trim((string)get_user_meta($selected,'first_name',true));
                $last=trim((string)get_user_meta($selected,'last_name',true));
                $full_name=trim($first.' '.$last);
                if ($full_name==='') $full_name=trim((string)$user->display_name);
                $description.=' · Trukket stemmevinner: '.sanitize_text_field($full_name);
            } else {
                $description.=' · Stemmevinner er trukket, men navnet mangler i registreringen';
            }
        }
        // One-match exception requested for 26 September: retain the two announced winners.
        if ($rr_match_id===8984413 && ($rr_state['session']??'')==='fe10c131dd40471d907ecc04f2bdebda') {
            $three_key='rr_poll_three_prizes_8984413_'.$session;
            $three=get_option($three_key,false);
            if ($three===false) {
                $unique=[];
                foreach ($entrants as $hash) {
                    $id=(int)get_option($rr_key.'_entrant_'.$session.'_'.$hash,0);
                    if ($id && get_userdata($id)) $unique[$id]=$id;
                }
                $fixed=[26923,28361];
                $pool=array_values(array_diff($unique,$fixed));
                if (isset($unique[$fixed[0]],$unique[$fixed[1]]) && $pool) {
                    $candidate=['match_id'=>8984413,'session'=>$session,'winner_ids'=>[$fixed[0],$fixed[1],$pool[random_int(0,count($pool)-1)]],
                        'drawn_at'=>time(),'eligible_count'=>count($unique),'additional_pool_count'=>count($pool)];
                    add_option($three_key,$candidate,'',false);
                    $three=get_option($three_key,false);
                }
            }
            if (is_array($three) && count(array_unique($three['winner_ids']??[]))===3) {
                $names=[];
                foreach ($three['winner_ids'] as $id) {
                    $person=get_userdata((int)$id);
                    $name=trim((string)get_user_meta($id,'first_name',true).' '.(string)get_user_meta($id,'last_name',true));
                    $names[]=sanitize_text_field($name!==''?$name:($person?$person->display_name:'Navn mangler – konto '.(int)$id));
                }
                $description=preg_replace('/ · Trukket stemmevinner: .*$/u','',$description);
                $description.=' · Premievinnere (3): '.implode(' / ',$names);
            } else {
                $description.=' · OBS: Tre premievinnere kunne ikke bekreftes. Kontroller trekningen.';
            }
        }
        $rr_state['poll_award']=[
            'source_id'=>'rr_poll_award_'.$rr_match_id.'_'.($rr_state['session']??''),
            'side'=>'home','type'=>'award','minute'=>'86','label'=>'Dagens Bremnesing',
            'description'=>$description,'dismissed'=>false,'sort'=>86,'sequence'=>PHP_INT_MAX,
        ];
        update_option($rr_key,$rr_state,false);
    }
}

$rr_dashboard_events=$rr_public_events;
if ($rr_nff_source==='nff') {
    foreach ($rr_manual_events as $manual) {
        if (($manual['type']??'')==='sub') continue;
        $manual_minute=(int)explode('+',(string)($manual['minute']??0))[0];
        $duplicate=false;
        foreach ($rr_public_events as $visible) {
            if (($visible['type']??'')!==($manual['type']??'') || ($visible['side']??'home')!==($manual['side']??'home')) continue;
            if (trim((string)($visible['description']??''))!==trim((string)($manual['description']??''))) continue;
            $visible_minute=(int)explode('+',(string)($visible['minute']??0))[0];
            if (abs($visible_minute-$manual_minute)<=1) { $duplicate=true; break; }
        }
        if (!$duplicate) {
            $manual['label']=($manual['label']??'Hendelse').' · speaker';
            $rr_dashboard_events[]=$manual;
        }
    }
}
if (!empty($rr_state['poll_award']) && is_array($rr_state['poll_award'])) {
    $award=$rr_state['poll_award'];
    // Older awards stored an abbreviated name; resolve the full name from the fixed draw ID.
    $drawn_id=(int)get_option($rr_key.'_prize_'.($rr_state['session']??''),0);
    if ($drawn_id && ($drawn_user=get_userdata($drawn_id))) {
        $first=trim((string)get_user_meta($drawn_id,'first_name',true));
        $last=trim((string)get_user_meta($drawn_id,'last_name',true));
        $full_name=trim($first.' '.$last);
        if ($full_name==='') $full_name=trim((string)$drawn_user->display_name);
        $award['description']=preg_replace_callback('/( · Trukket stemmevinner: ).*$/u',static function($matches) use ($full_name) { return $matches[1].sanitize_text_field($full_name); },(string)$award['description']);
    }
    $rr_dashboard_events[]=$award;
}
usort($rr_dashboard_events,static function($a,$b){
    $minute=static function($event){
        if (isset($event['sort'])) return (int)$event['sort'];
        $parts=explode('+',(string)($event['minute']??0));
        return (int)$parts[0]+(int)($parts[1]??0);
    };
    return ($minute($b)<=>$minute($a)) ?: ((int)($b['sequence']??0)<=>(int)($a['sequence']??0));
});
$rr_match_events=$rr_control ? $rr_dashboard_events : $rr_public_events;
if ($rr_archive_public) $rr_display_score=$rr_archive['score'];
$rr_archive_save_current=static function($state) use ($rr_match,$rr_nff,$rr_nff_source,$rr_display_score,$rr_vote_results) {
    return rr_poll_archive_save($rr_match,$state,$rr_nff,$rr_nff_source,$rr_display_score,$rr_vote_results($state));
};
// Also preserve already-finished matches on the administrator's next visit.
if ($rr_admin && !$rr_archive_public && !empty($rr_state['finished'])) $rr_archive_save_current($rr_state);

if (isset($_GET['rr_poll_api'])) {
    // Every response is session-specific, including errors and unauthenticated responses.
    nocache_headers();
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Vary: Cookie', false);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
        if ($action === 'vote') {
            if (!$rr_eligible) $rr_error('Logg inn med en Vipps-tilknyttet konto for å stemme.',403);
            if (!isset($_POST['token']) || !hash_equals($rr_token, (string)wp_unslash($_POST['token']))) $rr_error('Last siden på nytt før du stemmer.',403);
            if (!$rr_state['opened'] || $rr_closed($rr_state)) $rr_error('Avstemningen er stengt.',409);
            $player = isset($_POST['player']) ? absint($_POST['player']) : 0;
            if (!isset(rr_poll_allowed_players($rr_match,$rr_state)[$player])) $rr_error('Spilleren har ikke startet eller blitt markert som byttet inn.',409);
            $vote_key = $rr_key.'_vote_'.$rr_state['session'].'_'.$rr_voter;
            if (!add_option($vote_key, $player, '', false)) $rr_error('Du har allerede stemt i denne avstemningen.',409);
            // Store the account ID separately so a randomly chosen voter can be contacted.
            // Every accepted voter participates, regardless of their chosen player.
            if ($rr_eligible) add_option($rr_key.'_entrant_'.$rr_state['session'].'_'.$rr_voter,get_current_user_id(),'',false);
            wp_send_json(['ok'=>true,'message'=>'Takk! Stemmen din er registrert.']);
        }
        if (!$rr_admin || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'rr_poll_admin')) $rr_error('Logg inn som redaktør for å styre avstemningen.',403);
        if ($action==='refresh_nff_events') {
            if (empty($rr_state['opened']) || !empty($rr_state['finished']) || $rr_nff_source!=='nff') wp_send_json(['ok'=>true,'skipped'=>true]);
            $lock='rr_nff_auto_lock_'.$rr_match_id;
            $last=(int)get_option($lock,0);
            if ($last && time()-$last<60) wp_send_json(['ok'=>true,'skipped'=>true]);
            if ($last) delete_option($lock);
            if (!add_option($lock,time(),'',false)) wp_send_json(['ok'=>true,'skipped'=>true]);
            $response=wp_safe_remote_get(add_query_arg('underside','kamphendelser',rr_poll_source_url($rr_match_id)),['timeout'=>20,'redirection'=>0,'limit_response_size'=>2000000,'headers'=>['Accept'=>'text/html']]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) $rr_error('Kunne ikke oppdatere Fotball.no. Tidligere hendelser beholdes. Nytt forsøk om omtrent ett minutt.',502);
            $fresh=rr_nff_event_snapshot(wp_remote_retrieve_body($response),$rr_match);
            if (is_wp_error($fresh)) $rr_error($fresh->get_error_message(),502);
            $current=get_option($rr_key,$rr_default);
            if (empty($current['opened']) || !empty($current['finished']) || ($current['session']??'')!==($rr_state['session']??'') || get_option($rr_nff_key.'_source','nff')!=='nff') wp_send_json(['ok'=>true,'skipped'=>true]);
            update_option($rr_nff_key,$fresh,false);
            wp_send_json(['ok'=>true,'fetched'=>$fresh['fetched']]);
        }
        /* A closed poll cannot be reopened by correcting or rewinding the clock. */
        $rr_state['closed'] = (bool)$rr_closed($rr_state);
        if ($action === 'new') {
            $rr_state = $rr_default;
            $rr_state['session'] = str_replace('-','',wp_generate_uuid4());
        } elseif ($action === 'close') {
            $rr_state['closed'] = true;
        } elseif ($action === 'event') {
            $next=rr_speaker_event($rr_match,$rr_state,sanitize_key($_POST['type']??''),absint($_POST['player']??0),absint($_POST['out']??0),isset($_POST['side']) && is_string($_POST['side']) ? sanitize_key(wp_unslash($_POST['side'])) : 'home');
            if (is_wp_error($next)) $rr_error($next->get_error_message(),409);
            $rr_state=$next;
        } elseif ($action === 'undo_event') {
            if (empty($rr_state['events'])) $rr_error('Ingen manuelle hendelser å angre.',409);
            $last=array_pop($rr_state['events']);
            if (($last['type']??'')==='sub') {
                $key=(($last['side']??'home')==='away')?'entered_away':'entered';
                unset($rr_state[$key][(int)($last['player']??0)]);
                if (($last['side']??'home')==='home') unset($rr_state['eligible_players'][(int)($last['player']??0)]);
            }
        } elseif ($action === 'finish' && $rr_state['period']===2 && empty($rr_state['finished'])) {
            $rr_state['elapsed']=$rr_seconds($rr_state); $rr_state['running']=false;
            $rr_state['finished']=true; $rr_state['closed']=true;
        } elseif (!empty($rr_state['finished'])) {
            $rr_error('Kampen er avsluttet.',409);
        } elseif ($action === 'start' && $rr_state['period']===0) {
            if ($rr_state['closed']) $rr_error('Avstemningen er stengt. Start en ny avstemning først.',409);
            if (!rr_poll_lineup_ready($rr_match)) $rr_error('Startoppstillingen mangler eller er ugyldig. Hent kampen på nytt før du åpner avstemningen.',409);
            $rr_state['period']=1; $rr_state['opened']=true; $rr_state['running']=true; $rr_state['started']=time();
            $rr_state['eligible_players']=[];
            foreach (($rr_match['starters']??[]) as $no) if (isset($rr_match['roster'][(int)$no])) $rr_state['eligible_players'][(int)$no]=(string)$rr_match['roster'][(int)$no];
        } elseif ($action === 'half' && $rr_state['period']===1 && $rr_state['running']) {
            $rr_state['elapsed']=$rr_seconds($rr_state); $rr_state['running']=false;
        } elseif ($action === 'second' && $rr_state['period']===1 && !$rr_state['running']) {
            $rr_state['period']=2; $rr_state['elapsed']=2700; $rr_state['started']=time(); $rr_state['running']=true;
        } elseif ($action === 'correct' && $rr_state['period']===2) {
            $raw = isset($_POST['seconds']) ? (string)wp_unslash($_POST['seconds']) : '';
            if (!ctype_digit($raw) || (int)$raw<2700 || (int)$raw>7200) $rr_error('Bruk et tidspunkt mellom 45:00 og 120:00.');
            $rr_state['elapsed']=(int)$raw; $rr_state['started']=time();
            if ((int)$raw>=5100) $rr_state['closed']=true;
        } else { $rr_error('Denne handlingen passer ikke til kampens status.'); }
        if (in_array($action,['new','start','half','second','correct','finish'],true)) $rr_state['clock_revision']=wp_generate_uuid4();
        update_option($rr_key,$rr_state,false);
        if ($action==='finish') {
            if (!$rr_archive_save_current($rr_state)) $rr_error('Kampen er avsluttet, men arkivering feilet. Last dashboardet på nytt for å prøve arkiveringen igjen.',500);
            wp_send_json(['ok'=>true,'message'=>'Kampen er avsluttet og arkivert.']);
        }
        wp_send_json(['ok'=>true]);
    }
    if ($rr_closed($rr_state) && !$rr_state['closed']) {
        $rr_state['closed']=true;
        update_option($rr_key,$rr_state,false);
    }
    $rr_api_admin_view=$rr_admin && isset($_GET['rr_admin_view']) && $_GET['rr_admin_view']==='1';
    $rr_api_now=microtime(true);
    $payload = ['ok'=>true,'match_events'=>$rr_api_admin_view?$rr_dashboard_events:$rr_public_events,'elapsed'=>$rr_seconds($rr_state,(int)$rr_api_now),'server_now_ms'=>(int)round($rr_api_now*1000),'session'=>$rr_state['session'],'clock_revision'=>$rr_state['clock_revision']??'','started'=>(int)$rr_state['started'],'period'=>$rr_state['period'],'running'=>$rr_state['running'],
        'finished'=>!empty($rr_state['finished']),'opened'=>$rr_state['opened'],'closed'=>(bool)$rr_closed($rr_state),'token'=>$rr_token,
        'event_credit'=>$rr_event_credit,'score'=>$rr_display_score,'eligible'=>$rr_eligible,'roster_ready'=>rr_poll_lineup_ready($rr_match),'candidates'=>rr_poll_allowed_players($rr_match,$rr_state),'entered'=>$rr_state['entered']??[],
        'authenticated'=>is_user_logged_in(),
        'account_name'=>is_user_logged_in() ? trim((string)get_user_meta(get_current_user_id(),'first_name',true)) : '',
        'logout_nonce'=>is_user_logged_in() ? wp_create_nonce('rr_poll_logout') : '',
        'login_nonce'=>wp_create_nonce('rr_poll_vipps_'.$rr_match_id),
        'voted'=>$rr_eligible && get_option($rr_key.'_vote_'.$rr_state['session'].'_'.$rr_voter,false)!==false];
    if ($rr_admin) {
        $payload['nff_source']=$rr_nff_source;
        $payload['nff_fetched']=(int)($rr_nff['fetched']??0);
        $payload['events']=$rr_state['events']??[];
        $away_team=rr_speaker_team($rr_match,'away');
        $payload['away_ready']=rr_poll_lineup_ready($away_team) || !empty($away_team['roster']);
        $payload['away_lineup_ready']=rr_poll_lineup_ready($away_team);
        $payload['away_on_pitch']=rr_speaker_on_pitch($rr_match,$rr_state,'away');
        $payload['away_bench']=array_intersect_key($away_team['roster'],array_fill_keys(array_diff($away_team['bench'],array_keys($rr_state['entered_away']??[])),true));
        $payload['on_pitch']=rr_speaker_on_pitch($rr_match,$rr_state);
        $payload['bench']=array_intersect_key($rr_roster,array_fill_keys(array_diff($rr_match['bench']??[],array_keys($rr_state['entered']??[])),true));
        $payload['results']=$rr_vote_results($rr_state);
    }
    wp_send_json($payload);
}
// Match-specific URL and permissions are prepared by bremnes-poll-match.php.
require __DIR__.'/bremnes-poll-sponsor.php';
require __DIR__.'/bremnes-speaker-welcome.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rr_poll_logout'])) {
    check_admin_referer('rr_poll_logout','rr_logout_nonce');
    wp_logout();
    wp_safe_redirect($rr_url,303);
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rr_vipps_begin'])) {
    check_admin_referer('rr_poll_vipps_'.$rr_match_id,'rr_vipps_nonce');
    if (!is_callable(['VippsLogin','instance'])) wp_die('Vipps er ikke tilgjengelig. Prøv igjen senere.');
    $login=VippsLogin::instance();
    if (!is_callable([$login,'get_vipps_login_link'])) wp_die('Vipps er ikke tilgjengelig. Prøv igjen senere.');
    $target=$login->get_vipps_login_link('wordpress',['referer'=>$rr_url]);
    if (!is_string($target) || wp_parse_url($target,PHP_URL_SCHEME)!=='https') wp_die('Kunne ikke starte Vipps-innlogging. Prøv igjen senere.');
    // The installed Vipps plugin creates the OAuth state, browser cookie and trusted provider URL.
    wp_redirect($target,303);
    exit;
}
if ($rr_archive_public) { nocache_headers(); }
if (empty($rr_embedded)) get_header();
?>
<style>
.rr-poll{max-width:760px;margin:30px auto;padding:0 18px 120px;color:#f5f6fa}
.rr-poll *{box-sizing:border-box}.rr-poll .card{background:#171d29;border:1px solid #515d74;padding:24px;border-radius:18px;margin:18px 0}
.rr-poll.rr-poll-embedded{max-width:100%;margin:0;padding:0}
.rr-poll h1{font-size:clamp(30px,7vw,48px);line-height:1.1}.rr-poll .tag{color:#ffd479;font-weight:bold;letter-spacing:.08em}
.rr-poll .muted{color:#c3cddd;line-height:1.6}.rr-poll .clock{font-size:48px;font-variant-numeric:tabular-nums;font-weight:800}
.rr-poll button,.rr-poll select,.rr-poll input{font:inherit;min-height:48px;padding:12px;border-radius:9px;border:1px solid #76839c}
.rr-poll select{width:100%;background:#0c1220;color:white;margin:12px 0}.rr-poll button{background:#b7243d;color:white;cursor:pointer}
.rr-poll button:disabled{opacity:.45;cursor:default}.rr-poll .controls{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}
.rr-poll a{color:#aaceff;text-decoration:underline}.rr-poll li{padding:9px 0}.rr-poll :focus-visible{outline:3px solid #ffc857;outline-offset:3px}
.rr-poll .poll-intro{margin:18px 0 24px;padding:20px 22px;border:1px solid #46546c;border-left:4px solid #f5cb45;border-radius:13px;background:#171d29}
.rr-poll .poll-intro p{margin:0 0 13px;line-height:1.6;color:#d5dce8}
.rr-poll .poll-intro p:last-child{margin-bottom:0}
.rr-poll .poll-intro-lead{font-size:17px;font-weight:700;color:#fff!important}
.rr-poll .poll-intro strong{color:#f5cb45}
.rr-poll .poll-intro-invite{font-weight:700}
.rr-poll .poll-intro-source{font-size:11px;color:#aab7cc!important}
.rr-poll .poll-match{background:linear-gradient(145deg,#202b3e,#121925);border:1px solid #62718a;border-top:3px solid #f5cb45;border-radius:18px;padding:24px 16px;text-align:center;margin:22px 0}
.rr-poll .poll-match h2{font-size:13px;text-transform:uppercase;letter-spacing:.1em;color:#f5cb45;margin:0 0 22px}
.rr-poll .poll-teams{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:12px;align-items:center}
.rr-poll #poll-match-events[hidden],.rr-poll #poll-score[hidden]{display:none!important}
.rr-poll .poll-teams.is-pregame{grid-template-columns:repeat(2,minmax(0,1fr))}
.rr-poll .poll-events h2{font-size:22px}
.rr-poll .poll-match .poll-events-inline{margin:24px 0 0;padding:20px 0 0;border-top:1px solid #46546c;text-align:left}
.rr-poll .poll-match .poll-events-inline h2{font-size:18px;text-transform:none;letter-spacing:0;margin:0 0 16px;color:#f5f6fa}
.rr-poll .poll-event-head{display:grid;grid-template-columns:minmax(0,1fr) 52px minmax(0,1fr);gap:8px;align-items:center;font-size:13px;color:#c3cddd}
.rr-poll .poll-event-head>span{text-align:center}.rr-poll .poll-event-head>strong:last-child{text-align:right}
.rr-poll #poll-events-list{list-style:none;margin:12px 0;padding:0}
.rr-poll .poll-event-row{display:grid;grid-template-columns:22px minmax(0,1fr) 52px minmax(0,1fr) 22px;gap:8px;align-items:center;padding:16px 0;border-top:1px solid #46546c}
.rr-poll .poll-event-minute{grid-column:3;grid-row:1;text-align:center;font-weight:800;color:#ffd479;font-variant-numeric:tabular-nums}
.rr-poll .poll-event-icon{grid-column:1;grid-row:1;justify-self:center;font-size:21px;line-height:1}
.rr-poll .poll-event-text{grid-column:2;grid-row:1;min-width:0;font-size:14px;line-height:1.45;overflow-wrap:anywhere}
.rr-poll .poll-event-text strong,.rr-poll .poll-event-text span{display:block}
.rr-poll .poll-event-text span{color:#c3cddd;margin-top:4px}
.rr-poll .poll-event-row.away .poll-event-text{grid-column:4;text-align:right}
.rr-poll .poll-event-row.away .poll-event-icon{grid-column:5}
.rr-poll .poll-event-icon.yellow,.rr-poll .poll-event-icon.red{width:13px;height:19px;border-radius:2px;background:#f5cb45}
.rr-poll .poll-event-icon.red{background:#f04452}.rr-poll .poll-event-icon.dismissed{box-shadow:4px 3px 0 #f04452}
.rr-poll .poll-event-icon.sub{color:#63d5a2}
@media(max-width:420px){.rr-poll .poll-events{padding:16px 12px}.rr-poll .poll-event-row{grid-template-columns:18px minmax(0,1fr) 40px minmax(0,1fr) 18px;gap:5px}.rr-poll .poll-event-head{grid-template-columns:minmax(0,1fr) 40px minmax(0,1fr);gap:5px}.rr-poll .poll-event-text{font-size:12px}}
.rr-poll .poll-team{min-width:0;display:flex;flex-direction:column;align-items:center;gap:10px}
.rr-poll .poll-team img{width:76px;height:76px;object-fit:contain;background:#fff;border-radius:12px;padding:7px}
.rr-poll .poll-team strong{font-size:clamp(20px,5vw,28px);line-height:1.2;overflow-wrap:anywhere}
.rr-poll .poll-team small{color:#c3cddd;font-size:12px}
.rr-poll .poll-versus{color:#f5f6fa;font-size:clamp(24px,6vw,36px);font-weight:800;white-space:nowrap;font-variant-numeric:tabular-nums}
.rr-poll .poll-match .poll-details{border-top:1px solid #46546c;margin:22px 0 0;padding-top:16px;color:#e2e8f2;line-height:1.8;font-size:15px}
.rr-poll .poll-match .poll-note{color:#f5cb45;margin:12px 0 0;font-size:14px}
@media(max-width:380px){.rr-poll .poll-team img{width:60px;height:60px}.rr-poll .poll-match{padding:20px 12px}.rr-poll .poll-teams{gap:8px}}
.rr-speaker h2{font-size:22px;margin:0 0 12px}.rr-speaker h3{font-size:18px}
.rr-speaker .speaker-nav{display:flex;flex-wrap:wrap;gap:8px;margin:22px 0}
.rr-speaker .speaker-nav a{padding:10px 14px;background:#202b3e;border:1px solid #515d74;border-radius:9px;text-decoration:none}.rr-speaker .speaker-nav a[aria-current="page"]{background:#f5cb45;border-color:#f5cb45;color:#161d29;font-weight:800}
.rr-speaker .poll-match{padding:16px;margin:16px 0}.rr-speaker .poll-match h2{margin-bottom:14px}
.rr-speaker .poll-team img{width:48px;height:48px}.rr-speaker .poll-team strong{font-size:20px}
.rr-speaker .poll-details{font-size:13px!important;margin-top:14px!important;padding-top:10px!important}
.rr-speaker .clock{font-size:64px;line-height:1.2}.rr-speaker #poll-status{font-weight:700;color:#ffd479}
.rr-speaker .controls button{flex:1 1 150px}
.rr-speaker summary{cursor:pointer;min-height:48px;padding:12px 0;font-weight:700}
.rr-speaker .speaker-actions,.rr-speaker .speaker-starters{border-top:1px solid #46546c;margin-top:18px}
.rr-speaker .speaker-player{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 0;border-bottom:1px solid #46546c}
.rr-speaker .speaker-player strong{overflow-wrap:anywhere}.rr-speaker .speaker-player button{flex-shrink:0;font-size:14px}
.rr-speaker .speaker-setup>.card{padding:18px;background:#121925}.rr-speaker input{max-width:100%}
.rr-speaker section,.rr-speaker details{scroll-margin-top:24px}.rr-speaker #poll-feedback:empty{display:none}
@media(max-width:600px){
.rr-poll .poll-teams{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 16px;align-items:start}
.rr-poll .poll-team{display:contents}
.rr-poll .poll-team img{grid-row:1;justify-self:center}
.rr-poll .poll-team strong{grid-row:2;font-size:clamp(14px,4vw,20px);line-height:1.3;word-break:normal;hyphens:none;overflow-wrap:normal;min-width:0;max-width:100%}
.rr-poll .poll-team small{grid-row:3;line-height:1.4}
.rr-poll .poll-team:first-child>*{grid-column:1}
.rr-poll .poll-team:last-child>*{grid-column:2}
.rr-poll .poll-versus{grid-column:1 / -1;grid-row:3;justify-self:center;font-size:34px;margin-top:8px}
}
@media(max-width:540px){.rr-speaker .card{padding:18px}.rr-speaker .speaker-player{align-items:stretch;flex-direction:column;gap:10px}.rr-speaker .speaker-player button{width:100%}.rr-speaker .speaker-nav a{flex:1 1 40%;text-align:center}}
</style>
<style>
.rr-poll .poll-vote-card{padding:28px;border:1px solid #354359;border-radius:24px;background:linear-gradient(145deg,#202b3d,#111824);box-shadow:0 16px 40px #0003}
.poll-vote-card .poll-vote-heading{margin:0 0 22px}.poll-vote-card .poll-vote-eyebrow{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.16em;color:#ffd479}
.rr-poll .poll-vote-card h2{font-size:clamp(25px,6vw,34px);line-height:1.15;margin:9px 0}
.poll-vote-card .poll-vote-heading p{font-size:15px;color:#b9c6d8;margin:0}
.rr-poll .poll-vote-card #poll-status{display:inline-flex;align-items:center;gap:8px;font-size:13px;line-height:1.4;font-weight:700;padding:9px 13px;border-radius:24px;background:#ffffff0d;color:#d8e2ef;margin:0}
.poll-vote-card #poll-status::before{content:"";width:7px;height:7px;flex-shrink:0;border-radius:50%;background:currentColor}
.rr-poll .poll-vote-card #poll-status[data-status="open"],.rr-poll .poll-vote-card #poll-status[data-status="voted"]{background:#183c32;color:#9de8bf}
.rr-poll .poll-vote-card .poll-vote-deadline{font-size:12px;margin:10px 0 24px;color:#b9c6d8}
.poll-vote-card #poll-login{padding:20px;background:#0b111c80;border:1px solid #354359;border-radius:16px;margin:0 0 24px}
.rr-poll .poll-vote-card .poll-login-title{font-size:17px;font-weight:750;margin:0 0 5px}
.rr-poll .poll-vote-card .poll-login-note{font-size:14px;color:#b9c6d8;margin:0 0 16px}
.rr-poll .poll-vote-card #poll-login button{width:100%;border:0;border-radius:12px;min-height:52px;font-size:16px}
.poll-vote-card #poll-form{display:flex;flex-direction:column}
.poll-vote-card #poll-form label{order:0;font-size:15px;font-weight:700}
.rr-poll .poll-vote-card .poll-player-hint{order:1;font-size:12px;line-height:1.5;margin:6px 0 10px;color:#b9c6d8}
.rr-poll .poll-vote-card #poll-player{order:2;margin:0 0 14px;min-height:56px;border:1px solid #586980;border-radius:12px;font-size:16px;background:#0d1522}
.rr-poll .poll-vote-card #poll-submit{order:3;width:100%;min-height:54px;border:0;border-radius:12px;background:#f5cb45;color:#141821;font-size:16px;font-weight:800}
.rr-poll .poll-vote-card #poll-submit:disabled{background:#303c4e;color:#adb8c9;opacity:1}
.rr-poll .poll-vote-card .poll-vote-trust{text-align:center;font-size:12px;color:#d3ddeb;margin:18px 0 4px}
.rr-poll .poll-vote-card .poll-vote-privacy{text-align:center;font-size:12px;color:#a8b6ca;margin:0}
.poll-vote-card #poll-feedback:empty{display:none}.poll-vote-card #poll-feedback:not(:empty){padding:14px;border-radius:12px;background:#ffffff0d;font-size:14px}
.poll-vote-card .poll-account{padding:16px;margin-bottom:20px;background:#0b111c80;border-radius:12px;font-size:14px}
@media(max-width:480px){.rr-poll .poll-vote-card{padding:22px 18px}.poll-vote-card #poll-login{padding:16px}}

.rr-poll .poll-partner{margin:24px 0 0;padding:24px 12px;border-top:1px solid #46546c;text-align:center}
.rr-poll .poll-partner-label{color:#f5cb45;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin:0 0 16px}
.rr-poll .poll-partner-logo{display:flex;align-items:center;justify-content:center;width:min(100%,320px);min-height:120px;padding:20px 24px;margin:0 auto;background:#fff;color:#171d29;border-radius:12px}
.rr-poll .poll-partner-logo img{display:block;width:auto;height:auto;max-width:100%;max-height:120px;object-fit:contain}
.rr-poll .poll-partner-thanks{font-size:13px;color:#c3cddd;line-height:1.5;margin:14px auto 0;max-width:360px}
.rr-poll .poll-coverage{display:flex;flex-direction:column;align-items:center;gap:10px;margin:20px 0 8px;padding-top:18px;border-top:1px solid #46546c}
.rr-poll .poll-coverage span{font-size:11px;color:#aab7cc}
.rr-poll .poll-coverage img{width:120px;max-width:100%;height:auto;object-fit:contain}
.rr-poll .poll-coverage small{font-size:11px;color:#c3cddd;letter-spacing:.04em;text-align:center}
</style>
<style>
.rr-poll:not(.rr-speaker){max-width:1120px}
.rr-poll .poll-public-layout{margin:22px 0 0;padding:14px 28px 22px;border:1px solid #48566e;border-radius:22px;background:linear-gradient(145deg,#202b3d,#151d2a)}
.rr-poll .poll-public-layout>.poll-match{display:grid;grid-template-columns:minmax(0,1fr) minmax(170px,230px) minmax(0,1fr);gap:12px 20px;align-items:center;margin:0;padding:14px 0 20px;border:0;border-bottom:1px solid #536078;border-radius:0;background:none;text-align:center}
.rr-poll .poll-public-layout>.poll-match>h2{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.rr-poll .poll-public-layout .poll-teams{display:contents}
.rr-poll .poll-public-layout .poll-team,.rr-poll .poll-public-layout .poll-team:last-child{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;min-width:0;text-align:center}
.rr-poll .poll-public-layout .poll-team:first-child{grid-column:1;grid-row:1}
.rr-poll .poll-public-layout .poll-team:last-child{grid-column:3;grid-row:1}
.rr-poll .poll-public-layout .poll-team img{display:block;width:90px;height:90px;flex-shrink:0;object-fit:contain}
.rr-poll .poll-public-layout .poll-team strong{font-size:clamp(18px,2.5vw,26px);text-align:center;overflow-wrap:anywhere}
.rr-poll .poll-public-layout .poll-timer{grid-column:2;grid-row:1;margin:0!important;padding:10px 12px;background:#111927;text-align:center}
.rr-poll .poll-public-layout #poll-clock{font-size:clamp(23px,3vw,32px)}
.rr-poll .poll-public-layout #poll-clock-label,.rr-poll .poll-public-layout #poll-clock-note{font-size:11px}
.rr-poll .poll-public-layout .poll-versus{grid-column:2;grid-row:2}
.rr-poll .poll-public-layout .poll-match .poll-details{grid-column:1/-1;margin:0;padding:0;border:0;font-size:13px;color:#c3cddd;text-align:center}
.rr-poll .poll-public-layout .poll-events-inline{grid-column:1/-1}
.rr-poll .poll-content-grid{display:grid;grid-template-columns:minmax(0,1.14fr) minmax(0,.86fr);gap:24px;align-items:start;margin:0;padding:26px 0}
.rr-poll .poll-content-grid>.poll-intro,.rr-poll .poll-content-grid>.poll-vote-card{margin:0;min-width:0}
.rr-poll .poll-content-grid .poll-intro{padding:4px 12px 0 0;border:0;border-radius:0;background:none}
.rr-poll .poll-content-grid .poll-intro h2{font-size:clamp(24px,3vw,32px);line-height:1.2;color:#fff;margin:0 0 18px}
.rr-poll .poll-content-grid .poll-intro-lead{font-size:17px;line-height:1.65}
.rr-poll .poll-content-grid .poll-intro p{margin-bottom:18px}
.rr-poll .poll-content-grid .poll-vote-card{padding:23px;background:#172131;box-shadow:none;border:1px solid #4b5970;border-radius:16px}
.rr-poll .poll-public-layout>.poll-partner{margin:0;padding:26px 0;border:0;border-top:1px solid #536078;background:none;text-align:center}
.rr-poll .poll-public-layout>.poll-partner .poll-partner-logo{width:min(100%,320px);min-height:90px}
.rr-poll .poll-public-layout>.poll-coverage{margin:0;padding-top:14px}
@media(max-width:800px){
.rr-poll .poll-public-layout{padding:12px 14px 20px}
.rr-poll .poll-public-layout>.poll-match{grid-template-columns:minmax(0,1fr) minmax(104px,140px) minmax(0,1fr);gap:8px;padding:12px 0 18px}
.rr-poll .poll-public-layout .poll-teams{display:contents}
.rr-poll .poll-public-layout .poll-team,.rr-poll .poll-public-layout .poll-team:last-child{display:flex;flex-direction:column;align-items:center;text-align:center}
.rr-poll .poll-public-layout .poll-team:first-child{grid-column:1;grid-row:1}
.rr-poll .poll-public-layout .poll-team:last-child{grid-column:3;grid-row:1}
.rr-poll .poll-public-layout .poll-team img{width:clamp(54px,15vw,72px);height:clamp(54px,15vw,72px)}
.rr-poll .poll-public-layout .poll-team strong{grid-row:auto;grid-column:auto;font-size:clamp(14px,4vw,20px);line-height:1.2}
.rr-poll .poll-public-layout .poll-timer{grid-column:2;grid-row:1;padding:7px 4px}
.rr-poll .poll-public-layout #poll-clock{font-size:clamp(19px,5vw,26px)}
.rr-poll .poll-public-layout #poll-clock-note{font-size:10px;line-height:1.2}
.rr-poll .poll-content-grid{grid-template-columns:1fr;gap:18px;padding:22px 0}
.rr-poll .poll-content-grid .poll-intro{padding:0}
}
</style>
<style>
.rr-poll .poll-match-vote-link{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin:14px auto 0;padding:10px 18px;border-radius:999px;background:#f5cb45;color:#151b24;font-size:14px;font-weight:800;text-decoration:none}
.rr-poll .poll-public-layout .poll-match-vote-link{grid-column:1/-1;justify-self:center}
.rr-poll .poll-match-vote-link:hover{background:#ffe079;color:#151b24}
.rr-speaker .speaker-vote-cue{margin:12px 0 0;padding:14px 16px;border:1px solid #74839a;border-radius:12px;background:#111927;text-align:left}
.rr-speaker .speaker-vote-cue[hidden]{display:none}
.rr-speaker .speaker-vote-cue strong{display:block;color:#f5cb45;font-size:13px;text-transform:uppercase;letter-spacing:.08em}
.rr-speaker .speaker-vote-cue p{margin:7px 0 0;line-height:1.5;font-size:15px}
.rr-speaker .speaker-vote-cue a{color:#f5cb45;font-weight:800}
.rr-poll .poll-steps{margin:0 0 18px;padding:12px 14px;border-radius:12px;background:#111927;color:#dce5f0;font-size:13px;line-height:1.5}
@media(max-width:800px){
.rr-poll .poll-content-grid>.poll-vote-card{order:1}
.rr-poll .poll-content-grid>.poll-intro{order:2}
}
</style>
<style>
/* Preserve the clock when legacy Code Snippets still override the public card. */
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock>.poll-match{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(104px,180px) minmax(0,1fr);text-align:center}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-teams{display:contents!important}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-team{grid-column:1!important;grid-row:1!important;flex-direction:column;justify-content:center!important;text-align:center}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-team:last-child{grid-column:3!important;grid-row:1!important;flex-direction:column;justify-content:center!important;text-align:center}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-match .poll-timer{display:block!important;grid-column:2;grid-row:1;min-width:0;font-variant-numeric:tabular-nums}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-versus{grid-column:2;grid-row:2}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-team strong{font-size:clamp(14px,2vw,23px);overflow-wrap:anywhere}
.rr-site-match-clock .rr-dk-pre-score{display:none!important}
@media(max-width:700px){
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock>.poll-match{grid-template-columns:minmax(0,1fr) 104px minmax(0,1fr);gap:8px;padding:14px 10px}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-team strong{font-size:14px}
.rr-poll:not(.rr-speaker) .poll-public-layout.rr-site-match-clock .poll-team img{width:54px;height:54px;max-width:none}
}
</style>
<section class="rr-poll<?php echo !empty($rr_embedded) ? ' rr-poll-embedded' : ''; ?><?php echo $rr_control ? ' rr-speaker' : ''; ?><?php echo ($rr_control && $rr_live_section) ? ' rr-speaker-live' : ''; ?>">
<p class="tag"><?php echo $rr_control ? 'Dagens Bremnesing · Kampstyring' : ($rr_archive_public?'Kamparkiv':'Dagens Kamp'); ?></p>
<?php if (empty($rr_embedded)): ?><h1><?php echo $rr_control ? 'Speakerboard' : ($rr_archive_public?esc_html($rr_match['home'].' – '.$rr_match['away']):'Kampdag med Bremnes'); ?></h1><?php endif; ?>
<?php if ($rr_control && $rr_admin && $rr_match_error): ?>
<p class="card" role="alert">Kampen ble ikke endret: <?php echo esc_html($rr_match_error); ?></p>
<?php elseif ($rr_control && $rr_admin && isset($_GET['rr_match_saved'])): ?>
<p class="card" role="status">Kamp hentet og valgt: <strong><?php echo esc_html($rr_match['home'].' – '.$rr_match['away'].' · '.$rr_match['date_label']); ?></strong>.</p>
<?php endif; ?>
<?php if ($rr_control && $rr_admin): ?>
<nav class="speaker-nav" aria-label="Speakerboard">
<a href="<?php echo esc_url(rr_poll_dashboard_url($rr_url)); ?>"<?php if($rr_live_section) echo ' aria-current="page"'; ?>>LIVE</a>
<a href="<?php echo esc_url(rr_poll_dashboard_url($rr_url,'hendelser')); ?>"<?php if($rr_dashboard_section==='hendelser') echo ' aria-current="page"'; ?>>Historikk</a>
</nav>
<p id="poll-feedback" role="status" aria-live="polite"></p>
<?php endif; ?>
<?php if (!$rr_control): ?><div class="poll-public-layout rr-site-match-clock"><?php endif; ?>
<section class="poll-match" aria-labelledby="poll-match-heading">
<h2 id="poll-match-heading"><?php echo $rr_control ? 'Valgt kamp' : esc_html($rr_match['home'].' mot '.$rr_match['away']); ?></h2>
<div class="poll-teams<?php echo empty($rr_state['opened'])?' is-pregame':''; ?>">
<div class="poll-team"><?php if ($rr_match['home_logo']): ?><img src="<?php echo esc_url(rr_site_club_logo_url($rr_match['home_logo'])); ?>" alt="<?php echo esc_attr($rr_match['home'].' sin logo'); ?>" width="76" height="76"><?php endif; ?><strong><?php echo esc_html($rr_match['home']); ?></strong></div>
<span class="poll-versus" id="poll-score"<?php echo empty($rr_state['opened'])?' hidden':''; ?> aria-label="Registrert kampresultat" aria-live="polite"><?php $rr_score=$rr_display_score; echo $rr_state['opened'] ? esc_html($rr_score['home'].' – '.$rr_score['away']) : '–'; ?></span>
<div class="poll-team"><?php if ($rr_match['away_logo']): ?><img src="<?php echo esc_url(rr_site_club_logo_url($rr_match['away_logo'])); ?>" alt="<?php echo esc_attr($rr_match['away'].' sin logo'); ?>" width="76" height="76" referrerpolicy="no-referrer"><?php endif; ?><strong><?php echo esc_html($rr_match['away']); ?></strong></div>
</div>
<?php
$rr_waiting=empty($rr_state['opened']);
$rr_remaining=max(0,(int)strtotime($rr_match['kickoff'])-time());
$rr_initial_clock=$rr_waiting ? ($rr_remaining>0 ? (intdiv($rr_remaining,86400)?intdiv($rr_remaining,86400).' d · ':'').sprintf('%02d:%02d:%02d',intdiv($rr_remaining%86400,3600),intdiv($rr_remaining%3600,60),$rr_remaining%60) : 'Venter på start') : sprintf('%02d:%02d',intdiv($rr_seconds($rr_state),60),$rr_seconds($rr_state)%60);
?>
<div class="poll-timer" style="margin-top:22px">
<p class="muted" id="poll-clock-label"><?php echo $rr_waiting?'Til planlagt kampstart':'Spilt kamptid'; ?></p>
<div class="clock" id="poll-clock" aria-labelledby="poll-clock-label"><?php echo esc_html($rr_initial_clock); ?></div>
<p class="muted" id="poll-clock-note"><?php echo $rr_waiting?'Kampklokken starter når speaker starter 1. omgang.':($rr_archive_public?'Kampen er avsluttet og arkivert.':'Henter kampstatus …'); ?></p>
</div>
<?php if (!$rr_control || ($rr_admin && $rr_dashboard_section==='hendelser')): ?>
<section class="poll-events poll-events-inline" id="poll-match-events" aria-labelledby="poll-events-heading"<?php echo $rr_waiting?' hidden':''; ?>>
<h2 id="poll-events-heading">Kamphendelser</h2>
<div class="poll-event-head"><strong><?php echo esc_html($rr_match['home']); ?></strong><span>Min.</span><strong><?php echo esc_html($rr_match['away']); ?></strong></div>
<p id="poll-events-empty" class="muted"<?php echo $rr_match_events?' hidden':''; ?>>Ingen hendelser registrert ennå.</p>
<ol id="poll-events-list" aria-label="Kamphendelser, nyeste først">
<?php foreach ($rr_match_events as $event): ?>
<li class="poll-event-row <?php echo esc_attr($event['side']); ?>">
<span class="poll-event-icon <?php echo esc_attr($event['type']); ?><?php echo $event['dismissed']?' dismissed':''; ?>" aria-hidden="true"><?php echo $event['type']==='goal'?'⚽':($event['type']==='sub'?'⇄':($event['type']==='award'?'🏆':'')); ?></span>
<div class="poll-event-text"><strong><?php echo esc_html($event['label']); ?></strong><span><?php echo esc_html($event['description']); ?></span></div>
<span class="poll-event-minute"><?php echo esc_html($event['minute']); ?>&#8242;</span>
</li>
<?php endforeach; ?>
</ol>
<p class="muted" style="font-size:12px"><span id="poll-event-credit"><?php echo esc_html($rr_event_credit); ?></span> · nyeste hendelse øverst.</p>
</section>
<?php endif; ?>
<p class="poll-details"><time datetime="<?php echo esc_attr($rr_match['kickoff']); ?>"><?php echo esc_html($rr_match['date_label']); ?></time><?php if ($rr_control): ?> · <?php echo esc_html($rr_match['venue']); ?><?php else: ?> <span aria-hidden="true">·</span> <?php echo esc_html($rr_match['venue']); ?> <span aria-hidden="true">·</span> <?php echo esc_html($rr_match['competition']); ?><?php endif; ?></p>
<?php if (!$rr_control && !$rr_archive_public): ?><a class="poll-match-vote-link" href="#poll-live">Gå til avstemningen ↓</a><?php endif; ?>
<?php if ($rr_control && $rr_admin): ?>
<p class="speaker-referees"><?php if (!empty($rr_welcome_info['refs'])): ?><strong>Dommere:</strong> <?php $rr_ref_parts=[]; foreach($rr_welcome_info['refs'] as $rr_ref) $rr_ref_parts[]=trim(($rr_ref['role']??'').': '.($rr_ref['name']??''),': '); echo esc_html(implode(' · ',$rr_ref_parts)); ?><?php else: ?><strong>Dommere:</strong> ikke publisert<?php endif; ?></p>
<?php endif; ?>
<?php if ($rr_control && $rr_admin && $rr_live_section): ?>
<div class="speaker-live-status" aria-label="Live status">
<a id="speaker-nff-status" class="speaker-status-pill" href="<?php echo esc_url(rr_poll_dashboard_url($rr_url,'hendelser')); ?>">NFF …</a>
<button type="button" id="speaker-poll-summary" class="speaker-status-pill" aria-expanded="false" aria-controls="speaker-poll-panel">AVSTEMNING …</button>
</div>
<div id="speaker-poll-panel" class="speaker-poll-panel" hidden>
<div class="speaker-section-head"><strong>Avstemning</strong><span id="speaker-poll-total"></span></div>
<ol id="speaker-poll-live-results"></ol>
</div>
<aside class="speaker-vote-cue" id="speaker-vote-cue" aria-label="Kort oppfordring til publikum"<?php echo empty($rr_state['opened']) || $rr_closed($rr_state) || !empty($rr_state['finished'])?' hidden':''; ?>>
<strong>Si til publikum</strong>
<p>«Gå til <a href="<?php echo esc_url(home_url('/kamp/')); ?>" target="_blank" rel="noopener">radiorubben.no/kamp</a>. Logg inn med Vipps, velg spilleren og send stemmen. Det er gratis, og du kan stemme én gang.»</p>
</aside>
<?php endif; ?>
<?php if ($rr_control && $rr_match_sponsor !== '' && !$rr_live_section): ?>
<section class="poll-partner" aria-label="Dagens kampsponsor">
<p class="poll-partner-label">Dagens kampsponsor</p>
<div class="poll-partner-logo">
<?php if ($rr_sponsor_logo_id): ?>
<?php echo wp_get_attachment_image($rr_sponsor_logo_id,'large',false,['alt'=>$rr_match_sponsor,'class'=>'poll-partner-image']); ?>
<?php else: ?><strong><?php echo esc_html($rr_match_sponsor); ?></strong><?php endif; ?>
</div>
<p class="poll-partner-thanks">Takk til <?php echo esc_html($rr_match_sponsor); ?> for støtten til lokalfotballen.</p>
</section>
<?php elseif ($rr_control && $rr_admin && !$rr_live_section): ?>
<section class="poll-partner"><p class="poll-partner-label">Dagens kampsponsor</p><p class="muted">Legg til sponsornavn og logo under Kampoppsett og sponsor.</p></section>
<?php endif; ?>

</section>

<?php if ($rr_archive_public) rr_poll_archive_details($rr_archive); ?>
<?php if (!$rr_archive_public): ?>
<?php if (!$rr_control): ?><div class="poll-content-grid"><?php endif; ?>
<?php if (!$rr_control): ?>
<?php $rr_public_welcome=rr_poll_public_welcome($rr_match); ?>
<section class="poll-intro" aria-labelledby="poll-article-heading">
<h2 id="poll-article-heading"><?php echo esc_html($rr_public_welcome['headline']); ?></h2>
<p class="poll-intro-lead"><?php echo esc_html($rr_public_welcome['lead']); ?></p>
<?php if ($rr_public_welcome['context'] !== ''): ?><p><?php echo esc_html($rr_public_welcome['context']); ?></p><?php endif; ?>
<p class="poll-intro-close"><?php if ($rr_public_welcome['scorer_sentence'] !== ''): ?><?php echo esc_html($rr_public_welcome['scorer_sentence']); ?> <a href="<?php echo esc_url($rr_public_welcome['scorer_url']); ?>" target="_blank" rel="noopener">Se statistikken hos Fotball.no</a> <?php endif; ?><?php echo esc_html($rr_public_welcome['invite']); ?></p>
<p class="poll-intro-source">Kilder: <a href="<?php echo esc_url(rr_poll_source_url($rr_match_id)); ?>" target="_blank" rel="noopener">Fotball.no</a><?php if ($rr_public_welcome['verified_table']): ?> (tabell sjekket <?php echo esc_html(wp_date('d.m H:i',$rr_public_welcome['fetched'],new DateTimeZone('Europe/Oslo'))); ?>)<?php endif; ?> · Radio Rubbens kontrollerte 2026-resultater.</p>
</section>

<?php endif; ?>
<?php if (!$rr_control || ($rr_admin && $rr_live_section)): ?>
<section class="card<?php echo !$rr_control?' poll-vote-card':''; ?>" id="poll-live">
<?php if (!$rr_control): ?><header class="poll-vote-heading"><span class="poll-vote-eyebrow">Din stemme teller</span><h2>Dagens Bremnesing</h2><p>Hvem fortjener din stemme i dag?</p></header><p class="poll-steps"><?php echo $rr_eligible ? '1. Velg spiller · 2. Send stemmen' : '1. Logg inn med Vipps · 2. Velg spiller · 3. Send stemmen'; ?></p><?php endif; ?>
<?php if ($rr_control): ?><h2>Kampstyring</h2><?php endif; ?>

<style>
.rr-poll .poll-timer{text-align:center;padding:20px 12px;background:#101724;border-radius:12px}
.rr-poll #poll-clock-label{margin:0 0 8px;font-size:14px;font-weight:700}
.rr-poll #poll-clock{font-size:clamp(30px,8vw,56px);line-height:1.2;letter-spacing:.02em;overflow-wrap:anywhere}
.rr-poll #poll-clock-note{font-size:14px;margin:10px 0 0}
</style>
<div class="poll-account" id="poll-account" hidden>
<p id="poll-account-status">Kontrollerer innloggingen …</p>
<form method="post" action="<?php echo esc_url($rr_url); ?>">
<?php wp_nonce_field('rr_poll_logout','rr_logout_nonce'); ?>
<input type="hidden" name="rr_poll_logout" value="1"><button type="submit" disabled>Logg ut</button>
</form>
</div>
<p id="poll-status" role="status">Henter avstemningen …</p>
<p class="muted poll-vote-deadline">Stenger ved <strong>85:00</strong> · pausen teller ikke med.</p>
<?php if (!$rr_control): ?>
<div id="poll-login" hidden>
<p class="poll-login-title">Logg inn for å stemme</p>
<p class="poll-login-note">Bruk en Vipps-tilknyttet konto, og velg din favoritt.</p>
<?php if (is_callable(['VippsLogin','instance'])): ?>
<form method="post" action="<?php echo esc_url($rr_url); ?>">
<?php wp_nonce_field('rr_poll_vipps_'.$rr_match_id,'rr_vipps_nonce'); ?>
<input type="hidden" name="rr_vipps_begin" value="1">
<button type="submit" disabled style="background:#ff5b24;color:#111;font-weight:700">Logg inn med Vipps</button>
</form>
<?php else: ?>
<p>Vipps-innlogging er ikke tilgjengelig akkurat nå. Prøv igjen senere.</p>
<?php endif; ?>
</div>
<form id="poll-form">
<p class="muted poll-player-hint" id="poll-player-hint">Startspillere og innbyttere som har kommet på banen.</p>
<label for="poll-player">Velg en spiller</label>
<select id="poll-player" aria-describedby="poll-player-hint" required disabled><option value="">Velg draktnummer og navn</option>
<?php foreach(rr_poll_allowed_players($rr_match,$rr_state) as $no=>$name): ?><option value="<?php echo (int)$no; ?>"><?php echo esc_html($no.' · '.$name); ?></option><?php endforeach; ?>
</select><button id="poll-submit" disabled>Send inn stemmen din</button>
</form>
<p class="poll-vote-trust">Gratis å stemme · Én stemme per Vipps-bruker</p><p class="muted poll-vote-privacy">Én vinner trekkes tilfeldig blant alle som har stemt, uansett hvilken spiller de valgte. Kåringen og navnet på den som trekkes, vises bare for administrator på dashboardet. <a href="<?php echo esc_url(home_url('/vilkar/')); ?>">Vilkår</a> · <a href="<?php echo esc_url(home_url('/personvern/')); ?>">Personvern</a>.</p>
<?php elseif (!$rr_admin): ?>
<p><a href="<?php echo esc_url(wp_login_url(rr_poll_dashboard_url($rr_url))); ?>">Logg inn for å styre avstemningen og se resultatet</a></p>
<?php else: ?>
<section id="speaker-workflow" class="speaker-workflow"<?php echo !empty($rr_state['opened'])?' hidden':''; ?> aria-label="Kampforberedelse">

<details class="speaker-step"<?php echo $rr_match_error?' open':''; ?>>
<summary><span class="speaker-step-no">1</span><span>Hent kamp</span><small><?php echo esc_html($rr_match['home'].' – '.$rr_match['away']); ?></small></summary>
<?php if ($rr_match_error): ?><p class="speaker-step-error" role="alert"><?php echo esc_html($rr_match_error); ?></p><?php endif; ?>
<form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($rr_url)); ?>" class="speaker-compact-form">
<?php wp_nonce_field('rr_select_match','rr_match_nonce'); ?>
<input type="hidden" name="rr_return_section" value="">
<input type="hidden" name="rr_auto_match" value="1">
<label for="rr-live-team">Lag</label>
<select id="rr-live-team" name="rr_team_choice">
<option value="30365" <?php selected((int)$rr_match['home_id'],30365); ?>>Herrer A</option>
<option value="48835" <?php selected((int)$rr_match['home_id'],48835); ?>>Damer A</option>
</select>
<button type="submit">Hent kamp</button>
</form>
<details class="speaker-inline-more"><summary>Bruk FIKS-ID</summary>
<form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($rr_url)); ?>" class="speaker-compact-form">
<?php wp_nonce_field('rr_select_match','rr_match_nonce'); ?>
<input type="hidden" name="rr_return_section" value="">
<input type="hidden" name="rr_select_match" value="1">
<label for="rr-live-match-id">FIKS-ID / lenke</label>
<input id="rr-live-match-id" name="rr_match_input" type="text" required maxlength="500" value="<?php echo (int)$rr_match['id']; ?>">
<button type="submit">Hent</button>
</form>
</details>
</details>

<details class="speaker-step"<?php echo ($rr_sponsor_error||isset($_GET['rr_sponsor_saved']))?' open':''; ?>>
<summary><span class="speaker-step-no">2</span><span>Kampsponsor</span><small><?php echo $rr_match_sponsor!==''?esc_html($rr_match_sponsor):'Ikke lagt inn'; ?></small></summary>
<?php if ($rr_sponsor_error): ?><p class="speaker-step-error" role="alert"><?php echo esc_html($rr_sponsor_error); ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(rr_poll_dashboard_url($rr_url)); ?>" class="speaker-compact-form">
<?php wp_nonce_field('rr_sponsor_'.$rr_match_id,'rr_sponsor_nonce'); ?>
<input type="hidden" name="rr_return_section" value="">
<input type="hidden" name="rr_save_sponsor" value="1">
<label for="rr-live-sponsor">Sponsor</label>
<input id="rr-live-sponsor" name="rr_sponsor_name" type="text" maxlength="120" value="<?php echo esc_attr($rr_sponsor_settings['name']??''); ?>" placeholder="Navn">
<label for="rr-live-sponsor-logo">Logo</label>
<input id="rr-live-sponsor-logo" name="rr_sponsor_logo" type="file" accept="image/png,image/jpeg,image/webp">
<?php if (!empty($rr_sponsor_settings['logo'])): ?><label class="speaker-check"><input type="checkbox" name="rr_remove_logo" value="1"> Fjern eksisterende logo</label><?php endif; ?>
<button type="submit">Lagre sponsor</button>
</form>
</details>

<details class="speaker-step"<?php echo $rr_welcome_script!==''?' open':''; ?>>
<summary><span class="speaker-step-no">3</span><span>Velkomstmelding</span><small><?php echo $rr_welcome_script!==''?'Klar':'Lag manus'; ?></small></summary>
<form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($rr_url)); ?>" class="speaker-compact-form">
<?php wp_nonce_field('rr_welcome_'.$rr_match_id,'rr_welcome_nonce'); ?>
<button type="submit" name="rr_welcome_generate" value="1">Lag velkomstmelding</button>
</form>
<?php if ($rr_welcome_script!==''): ?>
<?php if ($rr_welcome_notes): ?><div class="speaker-welcome-notes" role="status"><?php foreach($rr_welcome_notes as $rr_note): ?><p><?php echo esc_html($rr_note); ?></p><?php endforeach; ?></div><?php endif; ?>
<textarea id="speaker-welcome-text" rows="13" aria-label="Velkomstmelding"><?php echo esc_textarea($rr_welcome_script); ?></textarea>
<button type="button" id="speaker-welcome-copy">Kopier melding</button><span id="speaker-welcome-copy-status" role="status"></span>
<?php endif; ?>
</details>

</section>

<div class="speaker-primary-row">
<button type="button" id="speaker-main-command" class="speaker-main-command" disabled>4 · START KAMP</button>
<button type="button" id="speaker-undo" class="speaker-undo" aria-label="Angre siste manuelle registrering" title="Angre siste manuelle registrering" disabled>↶ Angre</button>
</div>

<section id="speaker-quick" class="speaker-quick"<?php echo empty($rr_state['opened'])?' hidden':''; ?> aria-label="Registrer hendelser">
<p class="speaker-stage-label">5 · Registrer hendelser</p>
<div class="speaker-field-grid">
<label for="speaker-team">Lag
<select id="speaker-team"><option value="home"><?php echo esc_html($rr_match['home']); ?></option><option value="away"><?php echo esc_html($rr_match['away']); ?></option></select>
</label>
<label for="speaker-player">Spiller
<select id="speaker-player" disabled><option value="">Velg spiller</option></select>
</label>
</div>
<p class="speaker-lineup-alert" id="speaker-lineup-status" role="status"></p>

<div class="speaker-event-buttons">
<button type="button" data-event="goal" disabled>⚽ <span>Mål</span></button>
<button type="button" id="speaker-card-toggle" aria-expanded="false" aria-controls="speaker-card-panel" disabled>🟨 <span>Kort</span></button>
<button type="button" id="speaker-sub-toggle" aria-expanded="false" aria-controls="speaker-sub-panel" disabled>⇄ <span>Bytte</span></button>
</div>

<div id="speaker-card-panel" class="speaker-reveal" hidden>
<button type="button" data-event="yellow" disabled>🟨 Gult</button>
<button type="button" data-event="red" disabled>🟥 Rødt</button>
</div>

<div id="speaker-sub-panel" class="speaker-reveal" hidden>
<label for="speaker-in">Spiller inn
<select id="speaker-in" disabled><option value="">Velg innbytter</option></select>
</label>
<button type="button" data-event="sub" disabled>Registrer bytte</button>
</div>
</section>

<section id="speaker-recent" class="speaker-recent"<?php echo empty($rr_state['opened'])?' hidden':''; ?> aria-labelledby="speaker-recent-title">
<div class="speaker-section-head"><h3 id="speaker-recent-title">Siste hendelser</h3><a href="<?php echo esc_url(rr_poll_dashboard_url($rr_url,'hendelser')); ?>">Alle →</a></div>
<ol id="speaker-recent-events" class="speaker-recent-events" aria-live="polite"></ol>
<span id="nff-auto-status" hidden></span>
</section>

<details class="speaker-tools">
<summary>••• Verktøy</summary>
<div class="speaker-tools-body">
<button data-command="close" disabled>Steng avstemning</button>
<div class="speaker-clock-correct">
<label>Min <input id="poll-min" type="number" min="45" max="120" value="45"></label>
<label>Sek <input id="poll-sec" type="number" min="0" max="59" value="0"></label>
<button data-command="correct" disabled>Sett klokke</button>
</div>
<?php rr_nff_refresh_form('players',$rr_url,$rr_nff_source,''); ?>
<button data-command="new" disabled>Ny avstemning</button>
</div>
</details>

<dialog id="speaker-comment-dialog" aria-labelledby="speaker-comment-title" style="width:min(92vw,640px);max-height:85vh;overflow:auto;background:#171d29;color:#f5f6fa;border:1px solid #76839c;border-radius:18px;padding:24px">
<h2 id="speaker-comment-title">Speakerforslag</h2>
<p id="speaker-comment-context" class="muted"></p>
<label for="speaker-comment-text">Tekst</label>
<textarea id="speaker-comment-text" rows="6" style="width:100%;font:inherit;line-height:1.6;background:#0c1220;color:white;padding:14px;border:1px solid #76839c;border-radius:9px"></textarea>
<div class="controls"><button type="button" id="speaker-comment-copy">Kopier</button><button type="button" id="speaker-comment-close">Lukk</button></div>
<p id="speaker-comment-feedback" role="status"></p>
</dialog>

<style>
.rr-speaker-live>.tag,.rr-speaker-live>h1{display:none}
.rr-speaker-live #poll-match-heading{display:none}
.rr-speaker-live .poll-match{margin:12px 0;padding:14px 16px}
.rr-speaker-live .speaker-referees{margin:7px 0 0;color:#9eabbf;font-size:11px;line-height:1.45;text-align:center}
.rr-speaker .speaker-workflow{display:grid;gap:8px;margin:14px 0}
.rr-speaker .speaker-workflow[hidden]{display:none}
.rr-speaker .speaker-step{border:1px solid #46546c;border-radius:12px;background:#111927;overflow:hidden}
.rr-speaker .speaker-step>summary{display:grid;grid-template-columns:30px minmax(0,1fr) auto;align-items:center;gap:10px;padding:11px 13px;min-height:48px;cursor:pointer;list-style:none}
.rr-speaker .speaker-step>summary::-webkit-details-marker{display:none}
.rr-speaker .speaker-step>summary small{color:#9eabbf;font-size:11px;text-align:right}
.rr-speaker .speaker-step-no{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:#f5cb45;color:#151b24;font-weight:900;font-size:13px}
.rr-speaker .speaker-compact-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;padding:12px;border-top:1px solid #344054}
.rr-speaker .speaker-compact-form label{grid-column:1/-1;margin:0;color:#c3cddd;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.rr-speaker .speaker-compact-form select,.rr-speaker .speaker-compact-form input[type="text"],.rr-speaker .speaker-compact-form input[type="file"]{width:100%;margin:0;min-height:46px;background:#0c1220;color:#fff}
.rr-speaker .speaker-compact-form button{min-height:46px;white-space:nowrap}
.rr-speaker .speaker-check{grid-column:1/-1!important;text-transform:none!important;letter-spacing:0!important}
.rr-speaker .speaker-inline-more{grid-column:1/-1;padding:0 12px 12px}
.rr-speaker .speaker-inline-more>summary{font-size:12px;color:#aaceff;cursor:pointer}
.rr-speaker .speaker-inline-more .speaker-compact-form{padding:10px 0 0;border:0}
.rr-speaker .speaker-step-error{margin:10px 12px 0;color:#ffabb8;font-size:13px}
.rr-speaker .speaker-welcome-notes{padding:0 12px;color:#ffd479;font-size:12px}
.rr-speaker .speaker-step textarea{display:block;width:calc(100% - 24px);margin:0 12px 10px;padding:12px;background:#0c1220;color:#fff;border:1px solid #586980;border-radius:9px;font:inherit;line-height:1.55}
.rr-speaker .speaker-step>#speaker-welcome-copy{margin:0 12px 12px}
.rr-speaker .speaker-stage-label{margin:0 0 10px;color:#f5cb45;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em}
.rr-speaker-live .poll-details{margin-top:10px!important;padding-top:8px!important}
.rr-speaker-live .poll-timer{padding:14px 10px!important}
.rr-speaker-live #poll-feedback:not(:empty){position:sticky;top:8px;z-index:20;margin:8px 0;padding:9px 12px;border-left:3px solid #63d5a2;border-radius:8px;background:#13261f;color:#dff8e9;font-size:13px}
.rr-speaker #poll-live{padding:18px}
.rr-speaker #poll-live>h2{display:none}
.rr-speaker #poll-live>.poll-account,.rr-speaker #poll-live>#poll-status,.rr-speaker #poll-live>.poll-vote-deadline{display:none}
.rr-speaker #poll-clock-note{display:none}
.rr-speaker .speaker-primary-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin:0 0 18px}
.rr-speaker .speaker-main-command{min-height:58px;background:#f5cb45;color:#151b24;font-size:18px;font-weight:900;border-color:#f5cb45}
.rr-speaker .speaker-main-command.danger{background:#b7243d;color:#fff;border-color:#b7243d}
.rr-speaker .speaker-undo{min-width:96px;background:#202b3e}
.rr-speaker .speaker-quick{padding:18px 0;border-top:1px solid #46546c;border-bottom:1px solid #46546c}
.rr-speaker .speaker-field-grid{display:grid;grid-template-columns:160px minmax(0,1fr);gap:12px}
.rr-speaker .speaker-field-grid label,.rr-speaker .speaker-sub-panel label{font-size:12px;font-weight:800;color:#c3cddd;text-transform:uppercase;letter-spacing:.05em}
.rr-speaker .speaker-field-grid select,.rr-speaker .speaker-sub-panel select{margin:5px 0 0;min-height:50px;font-size:16px;text-transform:none;letter-spacing:0}
.rr-speaker .speaker-lineup-alert:empty{display:none}.rr-speaker .speaker-lineup-alert{margin:8px 0 0;color:#ffd479;font-size:13px}
.rr-speaker .speaker-event-buttons{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:14px}
.rr-speaker .speaker-event-buttons button{min-height:58px;background:#202b3e;font-size:17px;font-weight:850}
.rr-speaker .speaker-event-buttons [data-event="goal"]{background:#f5cb45;color:#151b24;border-color:#f5cb45}
.rr-speaker .speaker-reveal{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;padding:12px;background:#101724;border-radius:12px}
.rr-speaker .speaker-reveal[hidden]{display:none}
.rr-speaker #speaker-card-panel [data-event="yellow"]{background:#f5cb45;color:#151b24;border-color:#f5cb45}
.rr-speaker #speaker-card-panel [data-event="red"]{background:#b7243d;color:#fff;border-color:#b7243d}
.rr-speaker #speaker-sub-panel{grid-template-columns:minmax(0,1fr) 160px;align-items:end}
.rr-speaker #speaker-sub-panel button{min-height:50px;background:#f5cb45;color:#151b24;border-color:#f5cb45;font-weight:800}
.rr-speaker .speaker-recent{padding-top:16px}
.rr-speaker .speaker-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px}
.rr-speaker .speaker-section-head h3{margin:0}.rr-speaker .speaker-section-head a{font-size:13px}
.rr-speaker .speaker-recent-events{list-style:none;padding:0;margin:8px 0 0}
.rr-speaker .speaker-recent-events li{border-top:1px solid #344054;padding:10px 0;font-size:14px}
.rr-speaker .speaker-tools{margin-top:12px;border-top:1px solid #46546c}
.rr-speaker .speaker-tools-body{display:grid;gap:10px;padding:8px 0}
.rr-speaker .speaker-clock-correct{display:grid;grid-template-columns:90px 90px minmax(0,1fr);gap:8px;align-items:end}
.rr-speaker .speaker-clock-correct input{width:100%;margin-top:4px}
.rr-speaker .speaker-live-status{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:12px}
.rr-speaker .speaker-status-pill{display:inline-flex;align-items:center;min-height:30px;padding:5px 10px;border:1px solid #515d74;border-radius:999px;background:#111927;color:#dbe4ef;font:inherit;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}
.rr-speaker button.speaker-status-pill{width:auto}
.rr-speaker .speaker-poll-panel{margin-top:10px;padding:12px;background:#101724;border:1px solid #46546c;border-radius:12px;text-align:left}
.rr-speaker .speaker-poll-panel[hidden]{display:none}
.rr-speaker .speaker-poll-panel ol{margin:8px 0 0;padding-left:22px}
.rr-speaker .speaker-poll-panel li{padding:5px 0;font-size:13px}
.rr-speaker .speaker-status-pill.ok{border-color:#2f7d57;color:#9de8bf;background:#153126}
.rr-speaker .speaker-status-pill.warn{border-color:#8a6c24;color:#ffd479;background:#302713}
.rr-speaker .speaker-status-pill.danger{border-color:#8a3341;color:#ffabb8;background:#32151c}
#speaker-comment-dialog::backdrop{background:rgba(0,0,0,.7)}
.rr-poll .poll-event-row[role="button"]{cursor:pointer;border-radius:8px}
.rr-poll .poll-event-row[role="button"]:hover{background:#263249}
.rr-poll .poll-event-row[role="button"]:focus-visible{outline:3px solid #ffc857;outline-offset:3px}
@media(max-width:520px){
 .rr-speaker .speaker-field-grid{grid-template-columns:1fr}
 .rr-speaker .speaker-event-buttons button span{display:block;font-size:13px;margin-top:2px}
 .rr-speaker #speaker-sub-panel{grid-template-columns:1fr}
 .rr-speaker .speaker-clock-correct{grid-template-columns:1fr 1fr}.rr-speaker .speaker-clock-correct button{grid-column:1/-1}
}
</style>
<?php endif; ?>
<?php if (!$rr_control): ?><p id="poll-feedback" role="status" aria-live="polite"></p><?php endif; ?>
</section>
<?php endif; ?>
<?php if (!$rr_control): ?>
</div>
<?php if ($rr_match_sponsor !== ''): ?>
<section class="poll-partner" aria-label="Dagens kampsponsor">
<p class="poll-partner-label">Dagens kampsponsor</p>
<div class="poll-partner-logo">
<?php if ($rr_sponsor_logo_id): ?>
<?php echo wp_get_attachment_image($rr_sponsor_logo_id,'large',false,['alt'=>$rr_match_sponsor,'class'=>'poll-partner-image']); ?>
<?php else: ?><strong><?php echo esc_html($rr_match_sponsor); ?></strong><?php endif; ?>
</div>
<p class="poll-partner-thanks">Takk til <?php echo esc_html($rr_match_sponsor); ?> for støtten til lokalfotballen.</p>
</section>
<?php endif; ?>
<?php if ($rr_match_sponsor === ''): ?><section class="poll-partner" aria-label="Dagens kampsponsor"><h2>Dagens kampsponsor</h2><p>Ikke registrert for denne kampen ennå.</p></section><?php endif; ?>
<div class="poll-coverage"><span>Utviklet for lokalfotballen – i samarbeid med Radio Rubben</span><img src="<?php echo esc_url(rr_one_logo_url('compact')); ?>" alt="Radio Rubben" width="120"><small>Digitalt engasjement rundt kampen</small></div>
</div>
<?php endif; ?>
<?php endif; // Live controls and voting are excluded from the immutable public report. ?>
<?php if ($rr_archive_public) echo '</div>'; ?>
<?php if ($rr_control && $rr_admin && $rr_dashboard_section==='hendelser'): ?>
<section class="card" id="poll-lineup"><h2>Kamphendelser</h2>
<?php if ($rr_nff_message): ?><p role="status"><?php echo esc_html($rr_nff_message); ?></p><?php endif; ?>
<?php rr_nff_refresh_form('events',$rr_url,$rr_nff_source,'hendelser'); ?>
<p class="muted" id="nff-auto-status" role="status">Med Fotball.no valgt oppdateres hendelser omtrent hvert minutt etter kampstart, mens dashboardet er åpent. Manuelle bytter beholdes.</p>
<h3>Kontroll og historikk</h3>
<p class="muted">Kamphendelsene vises i kamprammen over. Bruk denne siden til å kontrollere datakilden og oppdatere Fotball.no manuelt ved behov.</p>
<p class="muted">Manuelle mål, kort og bytter registreres nå under <a href="<?php echo esc_url(rr_poll_dashboard_url($rr_url,'kampstyring')); ?>">Kampstyring</a>. Dagens Bremnesing og en tilfeldig trukket Vipps-stemmevinner vises på dashboardet ved 86:00. Stemmevinneren trekkes blant alle som har stemt, uansett valgt spiller og også ved delt førsteplass.</p>
</section>
<?php endif; ?>
<?php if ($rr_control && $rr_admin && $rr_dashboard_section==='oppsett'): ?>
<section class="card speaker-setup" id="poll-setup">
<h2>Kampoppsett og sponsor</h2>
<p class="muted">Velg lag eller legg inn kampnummer. Legg til navn og logo for dagens kampsponsor.</p>
<?php rr_poll_match_form($rr_match,$rr_match_error,$rr_url); rr_poll_sponsor_form($rr_sponsor_settings,$rr_sponsor_error,$rr_url,$rr_match); ?>
</section>
<?php endif; ?>
<?php if ($rr_control && $rr_admin): ?><p><a href="<?php echo esc_url($rr_url); ?>">Åpne publikumsavstemningen</a></p><?php endif; ?>
<?php if (!$rr_control) rr_poll_archive_list(); ?>
<?php if ($rr_control && !empty($rr_state['finished'])): ?><p><a href="<?php echo esc_url($rr_url); ?>">Åpne arkivert kamprapport</a></p><?php endif; ?>
<p class="muted">Kamptropp: <a href="<?php echo esc_url(rr_poll_source_url($rr_match_id)); ?>" target="_blank" rel="noopener">Fotball.no · FIKS-ID <?php echo (int)$rr_match_id; ?></a>. Startspillere er valgbare fra kampstart. Innbyttere blir valgbare når de markeres som byttet inn. <?php if (!$rr_roster): ?>Venter på kamptropp.<?php else: ?><?php echo count($rr_roster); ?> spillere.<?php endif; ?></p>
</section>
<?php if (!$rr_archive_public): ?>
<?php
// Block templates format rendered content. Keep application JavaScript in the footer queue.
if ( ! empty( $rr_embedded ) ) { ob_start(); } else { echo '<script>'; }
?>
(()=>{
'use strict';
const endpoint=<?php echo wp_json_encode(add_query_arg($rr_control?['rr_poll_api'=>1,'rr_admin_view'=>1]:['rr_poll_api'=>1],$rr_url)); ?>;
const nonce=<?php echo wp_json_encode($rr_admin ? wp_create_nonce('rr_poll_admin') : ''); ?>;
const el=id=>document.getElementById(id);
const kickoff=Date.parse(<?php echo wp_json_encode($rr_match['kickoff']); ?>);
const pad=n=>String(n).padStart(2,'0');
let candidateSignature='';
let eventSignature='';
let state=null, busy=false, healthy=false;
let clockAnchor=null, countdownAnchor=null, refreshSerial=0, refreshPending=false, refreshController=null;
function syncClock(next,at,force=false){
 const key=[next.session,next.clock_revision,next.period,next.started,next.running,next.finished].join('|');
 const serverMs=Number(next.server_now_ms);
 const reported=(Number(next.elapsed)||0)+(next.running&&Number.isFinite(serverMs)?(serverMs%1000)/1000:0);
 if(force||!clockAnchor||clockAnchor.key!==key||!next.running){
  clockAnchor={key,at,elapsed:reported};
 }else{
  const predicted=clockAnchor.elapsed+Math.max(0,(at-clockAnchor.at)/1000);
  // Ignore network and second-boundary jitter; correct only a real discrepancy.
  if(reported-predicted>2)clockAnchor={key,at,elapsed:reported};
 }
 if(Number.isFinite(serverMs)&&serverMs>0){
  const predicted=countdownAnchor ? countdownAnchor.serverMs+at-countdownAnchor.at : 0;
  if(!countdownAnchor||Math.abs(serverMs-predicted)>1500)countdownAnchor={serverMs,at};
 }
}
function clockElapsed(){
 if(!clockAnchor)return Number(state?.elapsed)||0;
 return clockAnchor.elapsed+(state?.running?Math.max(0,(performance.now()-clockAnchor.at)/1000):0);
}
async function request(body,controller=new AbortController()){
 const timer=setTimeout(()=>controller.abort(),body?30000:12000);
 try{
  // Use this document's origin, including www/HTTPS, for the WordPress cookie.
  const url=new URL(endpoint);url.protocol=location.protocol;url.host=location.host;
  if(!body)url.searchParams.set('_rr_check',Date.now()+'-'+refreshSerial);
  const response=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,...(body?{headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)}:{})});
  const data=await response.json();
  if(!response.ok||!data.ok)throw new Error(data.message||'Kunne ikke kontakte avstemningen.');
  return data;
 }catch(error){
  if(error.name==='AbortError')throw new Error('Kontrollen tok for lang tid. Prøver igjen.');
  throw error;
 }finally{clearTimeout(timer);}
}
function renderAccount(){
 const account=el('poll-account'),login=el('poll-login');
 if(account)account.hidden=!state?.authenticated;
 if(el('poll-account-status'))el('poll-account-status').textContent=!healthy?'Kontrollerer innloggingen …':
  state?.authenticated?'Logget inn'+(state.account_name?' som '+state.account_name:'')+'.':'';
 if(login)login.hidden=!healthy||!!state?.eligible;
 for(const [name,key,container] of [['rr_logout_nonce','logout_nonce',account],['rr_vipps_nonce','login_nonce',login]]){
  const input=container?.querySelector('[name="'+name+'"]');
  if(input)input.value=healthy?(state?.[key]||''):'';
  const button=container?.querySelector('button[type="submit"]');
  if(button)button.disabled=!healthy||!state?.[key];
 }
}
function updatePlayers(id,players){
 const select=el(id);if(!select)return;
 const signature=JSON.stringify(players||{});if(select.dataset.players===signature)return;
 const chosen=select.value;select.replaceChildren(new Option('Velg spiller',''));
 Object.entries(players||{}).forEach(([no,name])=>select.add(new Option(no+' · '+name,no)));
 if(Object.hasOwn(players||{},chosen))select.value=chosen;
 select.dataset.players=signature;
}
const speakerRosters=<?php echo wp_json_encode(['home'=>$rr_match['roster']??[],'away'=>$rr_match['away_roster']??[]]); ?>;
const speakerInitialScore=<?php echo wp_json_encode($rr_display_score); ?>;
let speakerVisibleEvents=[];
function speakerGoalContext(event,events,expected){
 const goals=events.filter(e=>e.type==='goal');
 if(goals.some(e=>/selvmål/i.test(e.label||'')))return null;
 const totals={home:0,away:0};
 goals.forEach(e=>totals[e.side==='away'?'away':'home']++);
 if(!expected||totals.home!==Number(expected.home)||totals.away!==Number(expected.away))return null;
 const score={home:0,away:0};
 for(const e of [...goals].reverse()){
  const before={...score};score[e.side==='away'?'away':'home']++;
  if(e===event){
   if(goals.some(other=>other!==e&&String(other.minute)===String(e.minute)&&other.side!==e.side))return null;
   return {before,after:{...score}};
  }
 }
 return null;
}
function speakerPlayer(name,side,rosters){
 name=String(name||'').trim();
 const explicit=name.match(/^Nr\.\s*(\d+)\s+(.+)$/i);
 if(explicit)return 'spiller nummer '+explicit[1]+' '+explicit[2];
 const normalize=s=>String(s).trim().replace(/\s+/g,' ').toLocaleLowerCase('nb-NO');
 const matches=Object.entries(rosters[side]||{}).filter(([no,n])=>normalize(n)===normalize(name));
 return 'spiller nummer '+(matches.length===1?matches[0][0]:'[fyll inn nummer]')+' '+(name||'[fyll inn navn]');
}
function speakerGoalText(event,teams,rosters,context){
 const side=event.side==='away'?'away':'home',team=teams[side];
 const name=String(event.description||'').trim();
 const scorer=speakerPlayer(name,side,rosters);
 const minute=String(event.minute||'').replace('+',' pluss ');
 if(side==='away')return 'I det '+minute+'. minutt scorer '+team+'.'+(scorer?' Målscorer er '+scorer+'.':'');
 const prefix=context&&context.after.home===3&&context.after.away===0?'jabba dabba doool! ':'';
 if(context&&context.before.home<=context.before.away&&context.after.home>context.after.away)
  return prefix+'Jaaa! Bremnes tar ledelsen, '+context.after.home+'–'+context.after.away+'!'+(scorer?' Målscorer er '+scorer+'.':'');
 return prefix+(prefix?'Mål for Bremnes!':'Herlig! Mål for Bremnes!')+(scorer?' Målscorer er '+scorer+'.':'')+(context?' Stillingen er '+context.after.home+'–'+context.after.away+'.':'');
}
function speakerComment(event,teams){
 const side=event.side==='away'?'away':'home',team=teams[side];
 const name=String(event.description||'').trim();
 const player=speakerPlayer(name,side,speakerRosters);
 const minute=String(event.minute||'');
 const stamp=minute?' Registrert i kampminutt '+minute.replace('+',' pluss ')+'.':'';
 if(event.type==='sub') {
   const parts=name.split(' ut → ');
   if(parts.length===2) return 'Spillerbytte for '+team+'. Ut går '+speakerPlayer(parts[0],side,speakerRosters)+'. Inn kommer '+speakerPlayer(parts[1].replace(/ inn$/,''),side,speakerRosters)+'.'+stamp;
   return 'Spillerbytte for '+team+'. '+name+'.'+stamp;
 }
 if(event.type==='goal'){
   if(/selvmål/i.test(event.label||''))return 'Det er registrert et selvmål på '+team+' ved '+player+'.'+stamp;
   return speakerGoalText(event,teams,speakerRosters,speakerGoalContext(event,speakerVisibleEvents,state?.score||speakerInitialScore));
 }
 if(event.dismissed)return 'Andre gule kort og utvisning til '+team+', '+player+'.';
 if(event.type==='yellow')return 'Gult kort til '+team+', '+player+'.';
 if(event.type==='red')return 'Rødt kort til '+team+', '+player+'.';
 return String(event.label||'Hendelse')+' for '+team+': '+player+'.'+stamp;
}
const speakerTeams=<?php echo wp_json_encode(['home'=>$rr_match['home'],'away'=>$rr_match['away']]); ?>;
let speakerCommentTrigger=null;
function showSpeakerComment(event,trigger){
 const dialog=el('speaker-comment-dialog');if(!dialog)return;
 speakerCommentTrigger=trigger;
 el('speaker-comment-context').textContent=speakerTeams[event.side==='away'?'away':'home']+' · '+event.label+' · '+event.minute+String.fromCharCode(8242);
 el('speaker-comment-text').value=speakerComment(event,speakerTeams);
 el('speaker-comment-feedback').textContent='';
 dialog.showModal();
 el('speaker-comment-text').focus();
}
if(el('speaker-comment-dialog')){
 el('speaker-comment-close').onclick=()=>el('speaker-comment-dialog').close();
 el('speaker-comment-dialog').addEventListener('close',()=>{if(speakerCommentTrigger?.isConnected)speakerCommentTrigger.focus();});
 el('speaker-comment-copy').onclick=async()=>{
  try{await navigator.clipboard.writeText(el('speaker-comment-text').value);el('speaker-comment-feedback').textContent='Teksten er kopiert.';}
  catch(e){el('speaker-comment-text').focus();el('speaker-comment-text').select();el('speaker-comment-feedback').textContent='Teksten er markert. Velg Kopier på enheten din.';}
 };
}
function updateMatchEvents(events){
 speakerVisibleEvents=events||[];
 const list=el('poll-events-list');if(!list)return;
 const signature=JSON.stringify(events||[]);if(signature===eventSignature)return;
 eventSignature=signature;list.replaceChildren();
 el('poll-events-empty').hidden=!!events?.length;
 for(const event of events||[]){
 const row=document.createElement('li');row.className='poll-event-row '+(event.side==='away'?'away':'home');
 const icon=document.createElement('span');icon.className='poll-event-icon '+event.type+(event.dismissed?' dismissed':'');icon.setAttribute('aria-hidden','true');icon.textContent=event.type==='goal'?'⚽':event.type==='sub'?'⇄':event.type==='award'?'🏆':'';
 const detail=document.createElement('div');detail.className='poll-event-text';
 const label=document.createElement('strong');label.textContent=event.label;
 const player=document.createElement('span');player.textContent=event.description;detail.append(label,player);
 const minute=document.createElement('span');minute.className='poll-event-minute';minute.textContent=event.minute+String.fromCharCode(8242);
 if(event.type!=='award'&&el('speaker-comment-dialog')){
  row.setAttribute('role','button');row.tabIndex=0;row.setAttribute('aria-haspopup','dialog');
  row.setAttribute('aria-label','Speakerforslag: '+event.label+', '+event.description+', '+event.minute+' minutter');
  row.onclick=()=>showSpeakerComment(event,row);
  row.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();showSpeakerComment(event,row);}};
 }
 row.append(icon,detail,minute);list.appendChild(row);
 }
}
function updateRecentEvents(events){
 const list=el('speaker-recent-events');if(!list)return;
 list.replaceChildren();
 const recent=(events||[]).slice(0,3);
 if(!recent.length){const li=document.createElement('li');li.textContent='Ingen hendelser registrert ennå.';list.appendChild(li);return;}
 for(const event of recent){
  const li=document.createElement('li');
  const icon=event.type==='goal'?'⚽ ':event.type==='sub'?'⇄ ':event.type==='award'?'🏆 ':event.type==='yellow'?'🟨 ':event.type==='red'?'🟥 ':'';
  li.textContent=event.minute+String.fromCharCode(8242)+' · '+icon+event.label+(event.description?' · '+event.description:'');
  list.appendChild(li);
 }
}
updateMatchEvents(<?php echo wp_json_encode($rr_match_events); ?>);
updateRecentEvents(<?php echo wp_json_encode($rr_match_events); ?>);
function updateSpeaker(){
 if(!state||!el('speaker-team'))return;
 const away=el('speaker-team').value==='away';
 updatePlayers('speaker-player',away?state.away_on_pitch:state.on_pitch);
 updatePlayers('speaker-in',away?state.away_bench:state.bench);
 if(away && state.away_ready && !state.away_lineup_ready) el('speaker-lineup-status').textContent='Motstander: kamptrupp brukes · bytte krever startoppstilling.';
 else el('speaker-lineup-status').textContent=(away?state.away_ready:state.roster_ready)?'':'Startoppstillingen mangler.';
}
function setStatusPill(node,text,kind=''){
 if(!node)return;
 node.textContent=text;
 node.classList.remove('ok','warn','danger');
 if(kind)node.classList.add(kind);
}
function votePercent(votes,total){return (total>0?100*Number(votes||0)/total:0).toLocaleString('nb-NO',{minimumFractionDigits:0,maximumFractionDigits:0})+' %';}
function updateLiveStatus(elapsed,closed){
 const nff=el('speaker-nff-status');
 if(nff){
  if(!healthy)setStatusPill(nff,'● FORBINDELSE','danger');
  else if(state.nff_source!=='nff')setStatusPill(nff,'KILDE · MANUELL','warn');
  else if(!state.opened)setStatusPill(nff,'NFF · KLAR','');
  else {
   const age=state.nff_fetched?Math.max(0,Math.floor(Date.now()/1000-state.nff_fetched)):9999;
   if(age<=120)setStatusPill(nff,'● NFF OK','ok');
   else if(age<=240)setStatusPill(nff,'● NFF '+age+'s','warn');
   else setStatusPill(nff,'● NFF FORSINKET','danger');
  }
 }
 const poll=el('speaker-poll-summary');
 if(poll){
  const rows=state.results||[],total=rows.reduce((sum,row)=>sum+Number(row.total||0),0);
  const award=(state.match_events||[]).find(event=>event.type==='award');
  if(award)setStatusPill(poll,'🏆 '+award.description,'ok');
  else if(closed)setStatusPill(poll,'🔒 STENGT · '+total+' stemmer','warn');
  else if(state.opened)setStatusPill(poll,'● ÅPEN · '+total+' stemmer','ok');
  else setStatusPill(poll,'AVSTEMNING · KLAR','');
  if(el('speaker-poll-total'))el('speaker-poll-total').textContent='Andel av stemmene (%)';
  if(el('speaker-poll-live-results')){
   const list=el('speaker-poll-live-results');list.replaceChildren();
   if(!rows.length){const li=document.createElement('li');li.textContent='Ingen stemmer ennå.';list.appendChild(li);}
   else rows.forEach(row=>{const li=document.createElement('li');li.textContent=(row.number?'Nr. '+row.number+' ':'')+row.player+' · '+votePercent(row.total,total);list.appendChild(li);});
  }
 }
}
function render(){
 renderAccount();
 if(!state)return;
 el('poll-score').hidden=!state.opened;
 el('poll-score').parentElement.classList.toggle('is-pregame',!state.opened);
 el('poll-score').textContent=state.opened?state.score.home+' – '+state.score.away:'';
 const bannerScore=document.querySelector('.rr-match-vote-score');
 const bannerLink=bannerScore?.closest('.rr-match-vote-inner')?.querySelector('.rr-match-vote-button');
 if(state.opened&&bannerScore&&bannerLink&&new URL(bannerLink.href).searchParams.get('rr_match')===new URL(endpoint).searchParams.get('rr_match')){
  bannerScore.textContent=state.score.home+'–'+state.score.away;
  bannerScore.setAttribute('aria-label','Stillingen er '+state.score.home+' mot '+state.score.away);
  const banner=bannerScore.closest('.rr-match-vote-inner'),label=banner?.querySelector('.rr-match-vote-label');
  if(label)label.textContent=state.finished?'KAMP SLUTT':state.period===1&&!state.running?'PAUSE':'KAMPEN ER I GANG';
  if(bannerLink)bannerLink.textContent=state.finished?'Se kamprapport ↗':state.closed?'Se kampen ↗':'Stem på Dagens Bremnesing ↗';
  if(banner?.parentElement)banner.parentElement.setAttribute('aria-label',state.finished?'Kamp slutt':'Kampen er i gang');
 }
 const elapsed=Math.floor(clockElapsed());
 const closed=state.closed||(state.period===2&&elapsed>=5100);
 if(el('speaker-vote-cue'))el('speaker-vote-cue').hidden=!state.opened||closed||state.finished;
 const nowMs=countdownAnchor ? countdownAnchor.serverMs+performance.now()-countdownAnchor.at : Date.now();
  const remaining=Math.max(0,Math.ceil((kickoff-nowMs)/1000));
 const waiting=!state.opened;
 if(el('poll-match-events'))el('poll-match-events').hidden=waiting;
 el('poll-clock-label').textContent=waiting?'TIL KAMPSTART':state.finished?'KAMP SLUTT':state.period===1&&!state.running?'PAUSE':state.period===2?'2. OMGANG':'1. OMGANG';
 if(waiting){
 const days=Math.floor(remaining/86400),hours=Math.floor(remaining%86400/3600),minutes=Math.floor(remaining%3600/60);
 el('poll-clock').textContent=Number.isFinite(remaining)&&remaining>0?(days?days+' d · ':'')+pad(hours)+':'+pad(minutes)+':'+pad(remaining%60):'Venter på start';
 el('poll-clock-note').textContent='Kampklokken starter når speaker starter 1. omgang.';
 }else{
 el('poll-clock').textContent=pad(Math.floor(elapsed/60))+':'+pad(elapsed%60);
 el('poll-clock-note').textContent=state.finished?'Kampklokken er stoppet.':closed?'Avstemningen er stengt. Kampføringen fortsetter.':state.period===1&&!state.running?'Pausen teller ikke med i spilletiden.':state.period===2?'2. omgang · avstemningen stenger ved 85:00.':'1. omgang · avstemningen stenger ved 85:00.';
 }
 if(el('poll-status')){
  el('poll-status').textContent=!healthy?'Forbindelsen er brutt. Prøver igjen …':closed?'Avstemningen er stengt':!state.opened?'Avstemningen er ikke åpnet':state.voted?'Takk! Du har stemt.':!state.authenticated?'Logg inn med Vipps for å stemme.':!state.eligible?'Kontoen er innlogget, men mangler Vipps-tilknytning.':!state.roster_ready?'Venter på startoppstillingen.':state.period===1&&!state.running?'Pause · avstemningen er åpen':'Avstemningen er åpen';
  el('poll-status').dataset.status=!healthy?'offline':closed?'closed':!state.opened?'waiting':state.voted?'voted':'open';
 }
 for(const id of ['poll-player','poll-submit'])if(el(id))el(id).disabled=busy||!healthy||!state.eligible||!state.roster_ready||closed||!state.opened||state.voted;
 document.querySelectorAll('[data-sub-in]').forEach(b=>{
 const entered=Object.prototype.hasOwnProperty.call(state.entered||{},b.dataset.subIn);
 b.disabled=busy||!healthy||closed||!state.opened||entered;
 b.textContent=entered?'Byttet inn · åpen for stemmer':'Marker byttet inn';
 });
 updateLiveStatus(elapsed,closed);
 const teamReady=el('speaker-team')?.value==='away'?state.away_ready:state.roster_ready;
 const playerChosen=!!el('speaker-player')?.value;
 if(el('speaker-team'))el('speaker-team').disabled=busy;
 for(const id of ['speaker-player','speaker-in'])if(el(id))el(id).disabled=busy||!healthy||!teamReady||!state.opened||state.finished;

 document.querySelectorAll('[data-event]').forEach(b=>{
  const isSub=b.dataset.event==='sub';
  b.disabled=busy||!healthy||!teamReady||!state.opened||state.finished||!playerChosen||(isSub?!el('speaker-in')?.value:!state.running);
 });
 if(el('speaker-card-toggle'))el('speaker-card-toggle').disabled=busy||!healthy||!teamReady||!state.opened||state.finished||!playerChosen||!state.running;
 if(el('speaker-sub-toggle'))el('speaker-sub-toggle').disabled=busy||!healthy||!teamReady||!state.opened||state.finished||!playerChosen||(el('speaker-team')?.value==='away'&&!state.away_lineup_ready);

 const main=el('speaker-main-command');
 if(main){
  let action='',label='KAMP SLUTT',disabled=busy||!healthy;
  if(state.finished){disabled=true;}
  else if(state.period===0){action='start';label='4 · START KAMP';disabled=disabled||closed||!state.roster_ready;}
  else if(state.period===1&&state.running){action='half';label='SLUTT 1. OMGANG';}
  else if(state.period===1&&!state.running){action='second';label='START 2. OMGANG';}
  else if(state.period===2){action='finish';label='AVSLUTT KAMP';}
  main.dataset.action=action;main.textContent=label;main.disabled=disabled||!action;
  main.classList.toggle('danger',action==='finish');
 }
 if(el('speaker-undo'))el('speaker-undo').disabled=busy||!healthy||!state.events?.length||state.finished;
 if(el('speaker-workflow'))el('speaker-workflow').hidden=!!state.opened;
 if(el('speaker-quick'))el('speaker-quick').hidden=!state.opened;
 if(el('speaker-recent'))el('speaker-recent').hidden=!state.opened;

 document.querySelectorAll('[data-command]').forEach(b=>{
  const a=b.dataset.command;
  b.disabled=busy||!healthy||(a!=='new'&&state.finished)||(a==='close'&&closed)||(['correct'].includes(a)&&state.period!==2);
 });
}
async function refresh(forceClock=false){
 if(refreshPending&&!forceClock)return;
 const serial=++refreshSerial;
 refreshController?.abort();
 const controller=new AbortController();refreshController=controller;
 refreshPending=true;
 try{
 const next=await request(undefined,controller);
 if(serial!==refreshSerial)return;
 state=next;syncClock(next,performance.now(),forceClock);healthy=true;
 updateSpeaker();
 updateMatchEvents(state.match_events);
 updateRecentEvents(state.match_events);
 if(el('poll-event-credit'))el('poll-event-credit').textContent=state.event_credit||'Registrert av speaker';
 if(el('speaker-events')){
 el('speaker-events').replaceChildren();
 const rosters=<?php echo wp_json_encode(['home'=>$rr_roster,'away'=>$rr_match['away_roster']??[]]); ?>;
 const teams=<?php echo wp_json_encode(['home'=>$rr_match['home'],'away'=>$rr_match['away']]); ?>;
 const labels={goal:'Mål',yellow:'Gult kort',red:'Rødt kort',sub:'Bytte'};
 for(const event of [...(state.events||[])].reverse()){
 const li=document.createElement('li'),side=event.side||'home',names=rosters[side]||{};
 li.textContent=(Math.floor(event.seconds/60)+1)+String.fromCharCode(8217)+' · '+teams[side]+' · '+labels[event.type]+' · '+(event.type==='sub'?(names[event.out]||event.out)+' ut → ':'')+(names[event.player]||event.player)+(event.type==='sub'?' inn':'')+(event.dismissed?' · andre gule, utvist':'');
 el('speaker-events').appendChild(li);
 }
 if(!state.events?.length){const li=document.createElement('li');li.textContent='Ingen hendelser registrert.';el('speaker-events').appendChild(li);}
 }
 if(el('poll-player')){
 const candidates=state.candidates||{}, signature=JSON.stringify(candidates);
 if(signature!==candidateSignature){
 const select=el('poll-player'), selected=select.value;
 select.replaceChildren(new Option('Velg draktnummer og navn',''));
 Object.entries(candidates).forEach(([no,name])=>select.add(new Option(no+' · '+name,no)));
 if(Object.prototype.hasOwnProperty.call(candidates,selected))select.value=selected;
 candidateSignature=signature;
 }
 }
 if(el('poll-results')){
 el('poll-results').replaceChildren();
 const rows=state.results||[];
 for(const row of rows){const li=document.createElement('li');li.textContent=row.player+' — '+votePercent(row.total,rows.reduce((sum,r)=>sum+Number(r.total||0),0));el('poll-results').appendChild(li);}
 el('poll-total').textContent='Andel av stemmene (%)';
 const leaders=rows.length?rows.filter(r=>r.total===rows[0].total):[];
 el('poll-winner').textContent=state.closed?(leaders.length>1?'Delt førsteplass: '+leaders.map(r=>r.player).join(', '):leaders.length?'Flest stemmer: '+leaders[0].player:'Ingen stemmer registrert.'):'Resultatet oppdateres mens avstemningen er åpen.';
 }
 }catch(e){if(serial!==refreshSerial)return;healthy=false;if(el('poll-feedback'))el('poll-feedback').textContent=e.message;}
 finally{if(serial===refreshSerial){refreshPending=false;refreshController=null;}}
 render();
}
async function command(body){
 ++refreshSerial;busy=true;render();
 try{
  const data=await request(body);
  if(el('poll-feedback'))el('poll-feedback').textContent=data.message||'Oppdatert.';
  return true;
 }catch(e){
  if(el('poll-feedback'))el('poll-feedback').textContent=e.message;
  return false;
 }finally{
  await refresh(true);busy=false;render();
 }
}
if(el('poll-form'))el('poll-form').onsubmit=e=>{e.preventDefault();if(state&&!busy&&healthy&&!el('poll-submit').disabled)command({action:'vote',player:el('poll-player').value,token:state.token});};
for(const id of ['speaker-player','speaker-in'])if(el(id))el(id).onchange=render;
if(el('speaker-team'))el('speaker-team').onchange=()=>{for(const id of ['speaker-player','speaker-in']){el(id).value='';delete el(id).dataset.players;}updateSpeaker();render();};

if(el('speaker-card-toggle'))el('speaker-card-toggle').onclick=()=>{
 const button=el('speaker-card-toggle'),panel=el('speaker-card-panel'),sub=el('speaker-sub-panel');if(!panel)return;
 panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));
 if(sub){sub.hidden=true;if(el('speaker-sub-toggle'))el('speaker-sub-toggle').setAttribute('aria-expanded','false');}
};
if(el('speaker-sub-toggle'))el('speaker-sub-toggle').onclick=()=>{
 const button=el('speaker-sub-toggle'),panel=el('speaker-sub-panel'),card=el('speaker-card-panel');if(!panel)return;
 panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));
 if(card){card.hidden=true;if(el('speaker-card-toggle'))el('speaker-card-toggle').setAttribute('aria-expanded','false');}
};
if(el('speaker-poll-summary'))el('speaker-poll-summary').onclick=()=>{
 const button=el('speaker-poll-summary'),panel=el('speaker-poll-panel');if(!panel)return;
 panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));
};

document.querySelectorAll('[data-event]').forEach(b=>b.onclick=async()=>{
 if(busy)return;
 const type=b.dataset.event,player=el(type==='sub'?'speaker-in':'speaker-player').value;
 const selected=el('speaker-player')?.selectedOptions?.[0]?.textContent||'spiller';
 const ok=await command({action:'event',type,player,side:el('speaker-team').value,out:el('speaker-player').value,nonce});
 if(!ok)return;
 const names={goal:'Mål',yellow:'Gult kort',red:'Rødt kort',sub:'Bytte'};
 if(el('poll-feedback'))el('poll-feedback').textContent='✓ '+(names[type]||'Hendelse')+' registrert · '+selected;
 if(el('speaker-player'))el('speaker-player').value='';
 if(el('speaker-in'))el('speaker-in').value='';
 if(el('speaker-card-panel'))el('speaker-card-panel').hidden=true;
 if(el('speaker-sub-panel'))el('speaker-sub-panel').hidden=true;
 render();
});

if(el('speaker-main-command'))el('speaker-main-command').onclick=async()=>{
 const action=el('speaker-main-command').dataset.action;if(!action||busy)return;
 if(action==='finish'&&!confirm('Avslutte kampen og stoppe klokken?'))return;
 await command({action,nonce});
};
if(el('speaker-undo'))el('speaker-undo').onclick=async()=>{
 if(busy)return;
 const ok=await command({action:'undo_event',nonce});
 if(ok&&el('poll-feedback'))el('poll-feedback').textContent='↶ Siste manuelle registrering er angret.';
};

document.querySelectorAll('[data-command]').forEach(b=>b.onclick=()=>{
 const action=b.dataset.command, body={action,nonce};
 if(busy)return;
 if(action==='close'&&!confirm('Stenge avstemningen nå? Den kan ikke åpnes igjen.'))return;
 if(action==='new'&&!confirm('Starte en ny avstemning med separat stemmetelling?'))return;
 if(action==='correct'){
  const m=Number(el('poll-min').value),s=Number(el('poll-sec').value);
  if(!Number.isInteger(m)||m<45||m>120||!Number.isInteger(s)||s<0||s>59||m*60+s>7200){el('poll-feedback').textContent='Bruk 45:00 til 120:00.';return;}
  body.seconds=m*60+s;
 }
 command(body);
});
let nffAutoBusy=false,nffAutoLast=0;
async function refreshNffEvents(){
 if(!el('nff-auto-status')||nffAutoBusy||busy||!healthy||!state||document.hidden)return;
 const note=el('nff-auto-status');
 if(state.nff_source!=='nff'){note.textContent='Velg Fotball.no som kilde for automatisk oppdatering av hendelser.';return;}
 if(!state.opened||state.finished){note.textContent=state.finished?'Automatisk henting er stoppet ved kampslutt.':'Automatisk henting starter når kampen startes.';return;}
 if(Date.now()-nffAutoLast<60000)return;
 nffAutoLast=Date.now();nffAutoBusy=true;
 try{
  const result=await request({action:'refresh_nff_events',nonce});
  note.textContent='Hendelser oppdateres omtrent hvert minutt. Manuelle bytter beholdes.';
  if(!result.skipped)await refresh();
 }catch(e){note.textContent=e.message;}
 finally{nffAutoBusy=false;}
}
// Revalidate on entry, BFCache restore, focus, visibility and network recovery.
// A resumed tab replaces a suspended GET; old replies cannot overwrite newer state.
function revalidate(){
 if(document.hidden||busy)return; // Commands always refresh before releasing busy.
 healthy=false;render();
 refresh(true);
}
revalidate();setInterval(()=>{if(!busy&&!document.hidden)refresh();},5000);setInterval(render,500);
window.addEventListener('pageshow',revalidate);
window.addEventListener('focus',revalidate);
window.addEventListener('online',revalidate);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)revalidate();});
if(el('nff-auto-status')){
 setInterval(refreshNffEvents,5000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshNffEvents();});
}
})();
<?php
if ( ! empty( $rr_embedded ) ) {
    $rr_match_script = ob_get_clean();
    wp_register_script( 'rr-match-day-view', false, array(), RR_SITE_VERSION, true );
    wp_enqueue_script( 'rr-match-day-view' );
    wp_add_inline_script( 'rr-match-day-view', $rr_match_script );
} else { echo '</script>'; }
?>
<?php endif; ?>
<?php if (empty($rr_embedded)) get_footer(); ?>
