<?php
if (!defined('ABSPATH')) exit;
function rr_poll_parse_match_id($input) {
    $input=trim($input);
    if (preg_match('/^[1-9][0-9]{0,8}$/D',$input)) return (int)$input;
    $p=wp_parse_url($input);
    if (!is_array($p) || !in_array(strtolower($p['host']??''),['fotball.no','www.fotball.no'],true)
        || !in_array(strtolower($p['scheme']??''),['https','http'],true)
        || rtrim($p['path']??'','/')!=='/fotballdata/kamp') return 0;
    parse_str($p['query']??'', $q);
    return isset($q['fiksId']) && is_string($q['fiksId']) && preg_match('/^[1-9][0-9]{0,8}$/D',$q['fiksId']) ? (int)$q['fiksId'] : 0;
}
function rr_poll_source_url($id) { return 'https://www.fotball.no/fotballdata/kamp/?fiksId='.(int)$id; }
function rr_poll_class_xpath($class) { return "contains(concat(' ',normalize-space(@class),' '),' ".$class." ')"; }
function rr_poll_parse_nff($html,$id) {
    if (!class_exists('DOMDocument')) return new WP_Error('parser','Serveren mangler støtte for kampimport.');
    $doc=new DOMDocument();
    $prior=libxml_use_internal_errors(true);
    $ok=$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prior);
    if (!$ok) return new WP_Error('format','Kampsiden kunne ikke leses.');
    $xp=new DOMXPath($doc);
    $c='rr_poll_class_xpath';
    $text=static function($node){return $node ? trim(preg_replace('/\s+/u',' ',$node->textContent)) : '';};
    $card=$xp->query('//*['.$c('a_matchCard').']')->item(0);
    if (!$card) return new WP_Error('format','Fant ikke kampdata. Den valgte kampen er ikke endret.');
    $teams=$xp->query('.//*['.$c('teamName').']/a',$card);
    if ($teams->length!==2) return new WP_Error('teams','Kunne ikke bekrefte begge lagene.');
    $home=$teams->item(0); $away=$teams->item(1);
    parse_str(wp_parse_url($home->getAttribute('href'),PHP_URL_QUERY)??'',$home_args);
    $home_id=(int)($home_args['fiksId']??0);
    if (!in_array($home_id,[30365,48835],true)) return new WP_Error('home','Velg en hjemmekamp for Bremnes Menn A eller Kvinner A.');
    $headings=$xp->query('.//*['.$c('headingElement').']',$card);
    $date_text=$text($headings->item(0)); $time_text=$text($headings->item(1));
    if ($time_text==='') $time_text=$text($xp->query('.//*['.$c('time').']',$card)->item(0));
    if (!preg_match('/(\d{2})\.(\d{2})\.(\d{2}|\d{4})$/',$date_text,$date)
        || !preg_match('/^\d{2}:\d{2}$/',$time_text)) return new WP_Error('date','Kampens dato eller klokkeslett kunne ikke bekreftes.');
    $year=strlen($date[3])===2 ? '20'.$date[3] : $date[3];
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$year.'-'.$date[2].'-'.$date[1].' '.$time_text,new DateTimeZone('Europe/Oslo'));
    $dt_errors=DateTimeImmutable::getLastErrors();
    if (!$dt || ($dt_errors && ($dt_errors['warning_count'] || $dt_errors['error_count']))) return new WP_Error('date','Ugyldig kamptid fra kilden.');
    $logos=$xp->query('.//img['.$c('a_clubLogo').']',$card);
    $logo=static function($node) {
        if (!$node) return '';
        $url=$node->getAttribute('src');
        return wp_parse_url($url,PHP_URL_SCHEME)==='https' && wp_parse_url($url,PHP_URL_HOST)==='images.fotball.no' ? esc_url_raw($url) : '';
    };
    $roster=[]; $starters=[]; $bench=[];
    $wrapper=$xp->query('//*['.$c('homeTeamWrapper').']')->item(0);
    if ($wrapper) {
        $players=$xp->query('.//*['.$c('playerContent').']',$wrapper);
        foreach ($players as $player) {
            $no=$text($xp->query('.//*['.$c('playerNumber').']',$player)->item(0));
            $name=$text($xp->query('.//*['.$c('playerName').']',$player)->item(0));
            if ($name==='') continue;
            if (!ctype_digit($no) || (int)$no<1 || (int)$no>999 || isset($roster[(int)$no])) return new WP_Error('roster','Kamptroppen har manglende eller like draktnumre. Importen er stoppet for kontroll.');
            $roster[(int)$no]=sanitize_text_field($name);
            $list=$xp->query('ancestor::*['.$c('a_matchPlayerList').'][1]',$player)->item(0);
            $heading=$list ? $text($xp->query('preceding-sibling::h4[1]',$list)->item(0)) : '';
            if ($heading==='Startoppstilling:') $starters[]=(int)$no;
            elseif ($heading==='Innbyttere:') $bench[]=(int)$no;
            else return new WP_Error('lineup','Kunne ikke skille startspillere fra innbyttere. Importen er stoppet.');
        }
    }
    $away_roster=[]; $away_starters=[]; $away_bench=[];
    $wrapper=$xp->query('//*['.$c('awayTeamWrapper').']')->item(0);
    if ($wrapper) {
        $players=$xp->query('.//*['.$c('playerContent').']',$wrapper);
        foreach ($players as $player) {
            $no=$text($xp->query('.//*['.$c('playerNumber').']',$player)->item(0));
            $name=$text($xp->query('.//*['.$c('playerName').']',$player)->item(0));
            if ($name==='') continue;
            if (!ctype_digit($no) || (int)$no<1 || (int)$no>999 || isset($away_roster[(int)$no])) { $away_roster=[]; $away_starters=[]; $away_bench=[]; break; }
            $away_roster[(int)$no]=sanitize_text_field($name);
            $list=$xp->query('ancestor::*['.$c('a_matchPlayerList').'][1]',$player)->item(0);
            $heading=$list ? $text($xp->query('preceding-sibling::h4[1]',$list)->item(0)) : '';
            if ($heading==='Startoppstilling:') $away_starters[]=(int)$no;
            elseif ($heading==='Innbyttere:') $away_bench[]=(int)$no;
            else { $away_roster=[]; $away_starters=[]; $away_bench=[]; break; }
        }
    }
    ksort($roster);
    return ['id'=>(int)$id,'home'=>sanitize_text_field($text($home)),'away'=>sanitize_text_field($text($away)),
        'home_id'=>$home_id,'team'=>$home_id===30365?'Menn A':'Kvinner A',
        'home_logo'=>$logo($logos->item(0)),'away_logo'=>$logo($logos->item(1)),
        'kickoff'=>$dt->format(DATE_ATOM),'date_label'=>$dt->format('d.m.Y').' · kl. '.$dt->format('H.i'),
        'venue'=>sanitize_text_field($text($xp->query('.//*['.$c('footerElement').']',$card)->item(0))),
        'competition'=>sanitize_text_field($text($xp->query('//a[contains(@href,"/fotballdata/turnering/hjem/")]')->item(0))),
        'roster'=>$roster,'starters'=>$starters,'bench'=>$bench,'away_roster'=>$away_roster,'away_starters'=>$away_starters,'away_bench'=>$away_bench,'fetched'=>time()];
}
function rr_poll_fetch_nff($id) {
    $response=wp_safe_remote_get(rr_poll_source_url($id),['timeout'=>20,'redirection'=>0,'limit_response_size'=>2000000,'headers'=>['Accept'=>'text/html']]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) return new WP_Error('fetch','Kunne ikke hente kampen fra Fotball.no. Prøv igjen senere. Gjeldende kamp er beholdt.');
    return rr_poll_parse_nff(wp_remote_retrieve_body($response),$id);
}
require_once __DIR__.'/bremnes-poll-fixtures.php';
function rr_poll_match_form($match,$error,$url,$section='oppsett') {
?>
<section class="card" id="rr-match-settings">
<h2>Velg lag og kamp</h2>
<form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($url,$section)); ?>">
<input type="hidden" name="rr_return_section" value="<?php echo esc_attr($section); ?>">
<?php wp_nonce_field('rr_select_match','rr_match_nonce'); ?>
<label for="rr-team-choice">Lag</label>
<select id="rr-team-choice" name="rr_team_choice">
<option value="30365" <?php selected((int)$match['home_id'],30365); ?>>Herrer A</option>
<option value="48835" <?php selected((int)$match['home_id'],48835); ?>>Damer A</option>
</select>
<input type="hidden" name="rr_auto_match" value="1">
<button type="submit">Hent dagens eller neste hjemmekamp</button>
<p class="muted">Dagens hjemmekamp prioriteres. Ellers velges neste hjemmekamp, etter norsk dato. Kamptropp hentes når den er tilgjengelig.</p>
</form>
<?php if ($error): ?><p role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
<form method="post" action="<?php echo esc_url(rr_poll_dashboard_url($url,$section)); ?>">
<input type="hidden" name="rr_return_section" value="<?php echo esc_attr($section); ?>">
<?php wp_nonce_field('rr_select_match','rr_match_nonce'); ?>
<label for="rr-match-input">FIKS-ID eller lenke til kampen</label>
<input style="width:100%;margin:10px 0;background:#0c1220;color:white" id="rr-match-input" name="rr_match_input" type="text" required maxlength="500" value="<?php echo (int)$match['id']; ?>" placeholder="8985476 eller hele Fotball.no-lenken">
<p class="muted">Bruk tallet etter fiksId= i lenken. Dette er ikke det lange kampnummeret som står inne på kampsiden.</p>
<input type="hidden" name="rr_select_match" value="1">
<button type="submit">Hent og velg kamp</button>
</form>
<p class="muted">Valgt: <?php echo esc_html($match['home'].' – '.$match['away'].' · '.$match['team']); ?>.
<?php echo count($match['roster']); ?> spillere i hjemmelagets kamptropp. <?php echo count($match['away_roster']??[]); ?> spillere i bortelagets kamptropp.</p>
<?php if (empty($match['starters'])): ?><p role="status">Venter på bekreftet startoppstilling. Hent kampen på nytt når den er publisert. Avstemningen kan ikke åpnes uten startspillere.</p><?php endif; ?>
<p class="muted">Hentingen skjer kun når du trykker på knappen. Når avstemningen er åpnet, beholdes hjemmelagets spillerliste. Manglende startoppstilling for bortelaget kan hentes ved å trykke «Hent og velg kamp» med samme FIKS-ID.</p>
</section>
<?php
}
$rr_admin=current_user_can('manage_options');
$rr_control=isset($_GET['rr_poll_control']);
if ($rr_control && !$rr_admin) {
    if (!is_user_logged_in()) { auth_redirect(); exit; }
    wp_die('Speakerboardet er kun tilgjengelig for administratorer.','Ingen tilgang',['response'=>403]);
}
$rr_match_id=isset($_GET['rr_match']) && is_string($_GET['rr_match']) ? rr_poll_parse_match_id(wp_unslash($_GET['rr_match'])) : (int)get_option('rr_poll_selected_match',8985476);
if (!$rr_match_id) wp_die('Ugyldig FIKS-ID.', '', ['response'=>400]);
$rr_seed=['id'=>8985476,'home'=>'Bremnes','away'=>'Ørnen','home_id'=>30365,'team'=>'Menn A',
'home_logo'=>'https://www.radiorubben.no/wp-content/uploads/2026/09/Bremnes-laglogo.png','away_logo'=>'https://images.fotball.no/clublogos/3260.png',
'kickoff'=>'2026-09-11T20:30:00+02:00','date_label'=>'11.09.2026 · kl. 20.30','venue'=>'ScaleAQ Stadion','competition'=>'5. divisjon menn · avdeling 03',
'roster'=>$rr_roster,'starters'=>[1,5,14,21,2,10,15,20,22,8,11],'bench'=>[12,3,4,7,9,13,19],'fetched'=>0];
$rr_match=get_option('rr_poll_match_'.$rr_match_id, $rr_match_id===8985476?$rr_seed:false);
if (!$rr_match) wp_die('Kampen er ikke hentet ennå. Åpne kampoppsettet for å hente den.', '', ['response'=>404]);
$rr_match_error='';
$rr_url=add_query_arg('rr_match',$rr_match_id,home_url('/dagenskamp/'));
if (isset($_GET['wpvibe_preview']) && is_string($_GET['wpvibe_preview'])) $rr_url=add_query_arg('wpvibe_preview',sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])),$rr_url);
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['rr_select_match']) || isset($_POST['rr_auto_match']))) {
    if (!$rr_control || !$rr_admin) wp_die('Ingen tilgang til kampoppsett.', '', ['response'=>403]);
    check_admin_referer('rr_select_match','rr_match_nonce');
    $choice=isset($_POST['rr_team_choice']) && is_scalar($_POST['rr_team_choice']) ? (int)$_POST['rr_team_choice'] : 0;
    $id=isset($_POST['rr_auto_match']) ? rr_poll_find_next_home($choice) : (isset($_POST['rr_match_input']) && is_string($_POST['rr_match_input']) ? rr_poll_parse_match_id(wp_unslash($_POST['rr_match_input'])) : 0);
    if (is_wp_error($id)) $rr_match_error=$id->get_error_message();
    elseif (!$id) $rr_match_error='Oppgi FIKS-ID eller en gyldig Fotball.no-lenke til kampen.';
    else {
        $stored=get_option('rr_poll_match_'.$id,$id===8985476?$rr_seed:false);
        $clock=get_option('rr_poll_test_'.$id.'_vipps_v3_75',[]);
        $locked=!empty($clock['opened']);
        if ($locked && $stored) {
            $result=$stored;
            if (empty($stored['away_starters']) || count($stored['away_starters'])>11) {
                $fresh=rr_poll_fetch_nff($id);
                if (is_wp_error($fresh)) $result=$fresh;
                else foreach (['away_roster','away_starters','away_bench'] as $field) $result[$field]=$fresh[$field]??[];
            }
        }
        else $result=rr_poll_fetch_nff($id);
        if (!is_wp_error($result) && isset($_POST['rr_auto_match']) && (int)$result['home_id']!==$choice) $result=new WP_Error('team','Den hentede kampen tilhører ikke valgt hjemmelag.');
        if (is_wp_error($result)) $rr_match_error=$result->get_error_message();
        else {
            update_option('rr_poll_match_'.$id,$result,false);
            update_option('rr_poll_selected_match',$id,false);
            $return_section=isset($_POST['rr_return_section']) && is_string($_POST['rr_return_section']) ? sanitize_key(wp_unslash($_POST['rr_return_section'])) : 'oppsett';
            if (!in_array($return_section,['','oppsett'],true)) $return_section='oppsett';
            wp_safe_redirect(add_query_arg(['rr_match'=>$id,'rr_match_saved'=>1],rr_poll_dashboard_url($rr_url,$return_section)),303);
            exit;
        }
    }
}
// Older imports must be fetched again before voting; the known test lineup can be upgraded.
if (!isset($rr_match['starters'])) {
    $rr_match['starters']=$rr_match_id===8985476?$rr_seed['starters']:[];
    $rr_match['bench']=$rr_match_id===8985476?$rr_seed['bench']:[];
}
$rr_roster=$rr_match['roster'];
