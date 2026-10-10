<?php
defined('ABSPATH') || exit;

// Server-only provider. Persist only the normalized fields used by the dashboard.
function rr_poll_fd_request($path) {
    if (!preg_match('~^(?:matches/[1-9][0-9]{0,8}/people|teams/(?:30365|48835)/matches)$~D',$path)) return new WP_Error('fd_path','Ugyldig API-forespørsel.');
    $settings=[];
    foreach (['CID'=>'rr_fd_cid','CWD'=>'rr_fd_cwd'] as $key=>$option) {
        $constant='RRFR_FOTBALLDATA_'.$key;
        $value=defined($constant)?constant($constant):getenv($constant);
        if ($value===false || $value==='') $value=get_option($option,'');
        $settings[strtolower($key)]=is_scalar($value)?trim((string)$value):'';
    }
    if (!preg_match('/^[1-9][0-9]*$/D',$settings['cid']) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$settings['cwd'])) return new WP_Error('fd_config','Fotballdata-tilgangen er ikke konfigurert. Tidligere kampdata er beholdt.');
    $url='https://api.fotballdata.no/v1/'.$path.'?'.http_build_query($settings+['clubid'=>827,'format'=>'json']);
    try { $response=wp_safe_remote_get($url,['timeout'=>10,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'application/json']]); }
    catch (Throwable $e) { return new WP_Error('fd_fetch','Fotballdata kunne ikke kontaktes. Tidligere kampdata er beholdt.'); }
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) return new WP_Error('fd_fetch','Fotballdata kunne ikke besvare forespørselen. Tidligere kampdata er beholdt.');
    $body=wp_remote_retrieve_body($response);
    if (!is_string($body) || $body==='' || strlen($body)>=2500000) return new WP_Error('fd_format','Fotballdata returnerte tomme eller avkortede data.');
    $data=json_decode($body,true,64);
    if (!is_array($data) || json_last_error()!==JSON_ERROR_NONE || isset($data['ResponseStatus'])) return new WP_Error('fd_format','Fotballdata returnerte et ukjent svarformat. Tidligere kampdata er beholdt.');
    return $data;
}
function rr_poll_fd_int($value,$min=1,$max=999999999) {
    if (!is_int($value) || $value<$min || $value>$max) throw new RuntimeException('Fotballdata: ugyldig tall eller ID.');
    return $value;
}
function rr_poll_fd_text($value) {
    if (!is_string($value) || trim($value)==='') throw new RuntimeException('Fotballdata: påkrevd tekst mangler.');
    $text=trim(preg_replace('/\s+/u',' ',sanitize_text_field($value)));
    if ($text==='') throw new RuntimeException('Fotballdata: påkrevd tekst mangler.');
    return $text;
}
function rr_poll_fd_date($value) {
    if (!is_string($value) || !preg_match('~^/Date\(([0-9]{12,13})(-0000)?\)/$~D',$value,$m)) throw new RuntimeException('Fotballdata: ukjent datoformat.');
    $utc=new DateTimeImmutable('@'.intdiv((int)$m[1],1000));
    // v1 marks unspecified Norwegian wall time as -0000. Verified against FIKS kickoff.
    // An unmarked Microsoft JSON date remains a UTC instant.
    return !empty($m[2]) ? new DateTimeImmutable($utc->format('Y-m-d H:i:s'),new DateTimeZone('Europe/Oslo'))
        : $utc->setTimezone(new DateTimeZone('Europe/Oslo'));
}
function rr_poll_fd_roster($players) {
    if (!is_array($players) || !array_is_list($players)) throw new RuntimeException('Fotballdata: kamptropp mangler.');
    $roster=[]; $starters=[]; $bench=[]; $people=[];
    foreach ($players as $player) {
        if (!is_array($player) || ($player['PersonInfoHidden']??null)!==false) throw new RuntimeException('Fotballdata: spilleropplysningene kan ikke bekreftes. Tidligere tropp beholdes.');
        $number=rr_poll_fd_int($player['PlayerShirtNumber']??null,1,999);
        $person=rr_poll_fd_int($player['PersonId']??null);
        if (isset($roster[$number]) || in_array($person,$people,true)) throw new RuntimeException('Fotballdata: like draktnumre eller gjentatte spillere.');
        $name=rr_poll_fd_text($player['FirstName']??null).' '.rr_poll_fd_text($player['SurName']??null);
        $position=rr_poll_fd_text($player['Position']??null);
        if (in_array($position,['Reserve','Reserve (keeper)'],true)) $bench[]=$number;
        elseif (in_array($position,['Keeper','Forsvar','Midtbane','Angrep'],true)) $starters[]=$number;
        else throw new RuntimeException('Fotballdata: ukjent oppstillingsrolle. Tidligere tropp beholdes.');
        $roster[$number]=$name; $people[$number]=$person;
    }
    if (count($starters)>11) throw new RuntimeException('Fotballdata: for mange startspillere.');
    ksort($roster); ksort($people);
    return ['roster'=>$roster,'starters'=>$starters,'bench'=>$bench,'person_ids'=>$people];
}
function rr_poll_fd_parse_match($data,$id) {
    try {
        if (rr_poll_fd_int($data['MatchId']??null)!==$id) throw new RuntimeException('Fotballdata: svaret tilhører en annen kamp.');
        $home=rr_poll_fd_int($data['HomeTeamId']??null); $away=rr_poll_fd_int($data['AwayTeamId']??null);
        if (!in_array($home,[30365,48835],true) || ($data['HomeTeamClubId']??null)!==827 || $away===$home) throw new RuntimeException('Velg en hjemmekamp for Bremnes Menn A eller Kvinner A.');
        foreach (['Cancelled','Postponed','Interrupted'] as $field) if (isset($data[$field]) && $data[$field]!==false) throw new RuntimeException('Fotballdata: kampen er avlyst, utsatt eller avbrutt.');
        $date=rr_poll_fd_date($data['MatchStartDate']??null);
        $out=['id'=>$id,'home'=>rr_poll_fd_text($data['HomeTeamName']??null),'away'=>rr_poll_fd_text($data['AwayTeamName']??null),
            'home_id'=>$home,'away_id'=>$away,'team'=>$home===30365?'Menn A':'Kvinner A',
            'home_logo'=>'https://logo.fotballdata.no/logos/827.jpg?w=200',
            'away_logo'=>'https://logo.fotballdata.no/logos/'.rr_poll_fd_int($data['AwayTeamClubId']??null).'.jpg?w=200',
            'kickoff'=>$date->format(DATE_ATOM),'date_label'=>$date->format('d.m.Y').' · kl. '.$date->format('H.i'),
            'venue'=>rr_poll_fd_text($data['StadiumName']??null),'competition'=>rr_poll_fd_text($data['TournamentName']??null),
            'provider'=>'Fotballdata','fetched'=>time()];
        foreach (['HomeTeamPlayers'=>'','AwayTeamPlayers'=>'away_'] as $field=>$prefix) foreach (rr_poll_fd_roster($data[$field]??null) as $key=>$value) $out[$prefix.$key]=$value;
        return $out;
    } catch (RuntimeException $e) { return new WP_Error('fd_match',$e->getMessage()); }
}
function rr_poll_fd_fetch_match($id,$cache=true) {
    if (!is_int($id) || $id<1 || $id>999999999) return new WP_Error('fd_id','Ugyldig kamp-ID.');
    $key='rr_poll_fd_match_v1_'.$id;
    if ($cache && is_array($saved=get_transient($key))) return $saved;
    $data=rr_poll_fd_request('matches/'.$id.'/people');
    if (is_wp_error($data)) return $data;
    $match=rr_poll_fd_parse_match($data,$id);
    if ($cache && !is_wp_error($match)) set_transient($key,$match,30);
    return $match;
}
function rr_poll_fd_pick_home($data,$team,$today) {
    try {
        if (($data['TeamId']??null)!==$team || !is_array($data['Matches']??null) || !array_is_list($data['Matches'])) throw new RuntimeException('Fotballdata: terminlisten tilhører ikke valgt lag.');
        $candidates=[];
        foreach ($data['Matches'] as $match) {
            if (!is_array($match)) throw new RuntimeException('Fotballdata: ugyldig kamprad.');
            if (!in_array($team,[$match['HomeTeamId']??null,$match['AwayTeamId']??null],true)) throw new RuntimeException('Fotballdata: kamp tilhører et annet lag.');
            if (($match['HomeTeamId']??null)!==$team) continue;
            foreach (['Cancelled','Postponed','Interrupted'] as $flag) if (!is_bool($match[$flag]??null)) throw new RuntimeException('Fotballdata: ukjent kampstatus.');
            if ($match['Cancelled'] || $match['Postponed'] || $match['Interrupted']) continue;
            $date=rr_poll_fd_date($match['MatchStartDate']??null);
            if ($date->format('Y-m-d')<$today) continue;
            $id=rr_poll_fd_int($match['MatchId']??null);
            if (isset($candidates[$id]) && $candidates[$id]!==$date->getTimestamp()) throw new RuntimeException('Fotballdata: motstridende kampkopier.');
            $candidates[$id]=$date->getTimestamp();
        }
        if (!$candidates) return new WP_Error('empty','Fant ingen hjemmekamp i dag eller fremover for valgt lag. Du kan legge inn kampens FIKS-ID manuelt.');
        asort($candidates,SORT_NUMERIC);
        return (int)array_key_first($candidates);
    } catch (RuntimeException $e) { return new WP_Error('fd_fixtures',$e->getMessage()); }
}
function rr_poll_fd_find_next_home($team) {
    if (!in_array($team,[30365,48835],true)) return new WP_Error('team','Velg Herrer A eller Damer A.');
    $data=rr_poll_fd_request('teams/'.$team.'/matches');
    if (is_wp_error($data)) return $data;
    return rr_poll_fd_pick_home($data,$team,wp_date('Y-m-d',null,new DateTimeZone('Europe/Oslo')));
}
