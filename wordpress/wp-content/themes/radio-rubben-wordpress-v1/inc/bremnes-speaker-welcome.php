<?php
if (!defined('ABSPATH')) exit;
/** Read-only speaker preparation; never changes clock, rosters, events or votes. */
function rr_welcome_text($node) { return $node ? sanitize_text_field(trim(preg_replace('/\s+/u',' ',$node->textContent))) : ''; }
function rr_welcome_document($url) {
    $r=wp_safe_remote_get($url,['timeout'=>15,'redirection'=>0,'limit_response_size'=>2000000,'headers'=>['Accept'=>'text/html']]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) return new WP_Error('source','Kunne ikke hente opplysninger fra Fotball.no. Prøv igjen.');
    if (!class_exists('DOMDocument')) return new WP_Error('parser','Serveren mangler støtte for import.');
    $doc=new DOMDocument(); $old=libxml_use_internal_errors(true);
    $ok=$doc->loadHTML('<?xml encoding="UTF-8">'.wp_remote_retrieve_body($r),LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    return $ok ? new DOMXPath($doc) : new WP_Error('source','Kunne ikke lese kilden.');
}
function rr_welcome_match($match) {
    $xp=rr_welcome_document(rr_poll_source_url($match['id']).'&underside=dommere');
    if (is_wp_error($xp)) return ['error'=>$xp->get_error_message(),'refs'=>[],'fetched'=>time()];
    $card=$xp->query('//*['.rr_poll_class_xpath('a_matchCard').']')->item(0);
    $teams=$card ? $xp->query('.//*['.rr_poll_class_xpath('teamName').']/a',$card) : null;
    if (!$teams || $teams->length!==2 || rr_welcome_text($teams->item(0))!==$match['home'] || rr_welcome_text($teams->item(1))!==$match['away'])
        return ['error'=>'Lagene i kilden samsvarer ikke med valgt kamp. Hent kampen på nytt i oppsettet.','refs'=>[],'fetched'=>time()];
    $ids=[];
    foreach($teams as $team) { parse_str(wp_parse_url($team->getAttribute('href'),PHP_URL_QUERY)??'', $q); $ids[]=(int)($q['fiksId']??0); }
    $refs=[];
    foreach($xp->query('//*[@data-tab="dommere"]//tbody/tr') as $row) {
        $cells=$xp->query('./td',$row);
        if ($cells->length>=2 && rr_welcome_text($cells->item(1))!=='') $refs[]=['role'=>rr_welcome_text($cells->item(0)),'name'=>rr_welcome_text($cells->item(1)),'club'=>rr_welcome_text($cells->item(2))];
    }
    $competition_link=null;
    foreach($xp->query('//a[contains(@href,"/fotballdata/turnering/hjem/")]') as $link) {
        if (rr_welcome_text($link)===rr_welcome_text($xp->query('//a[contains(@href,"/fotballdata/turnering/hjem/")]')->item(0))) { $competition_link=$link; break; }
    }
    $competition_id=0;
    if ($competition_link) {
        parse_str(wp_parse_url($competition_link->getAttribute('href'),PHP_URL_QUERY)??'', $competition_query);
        $competition_id=(int)($competition_query['fiksId']??0);
    }
    $head=$xp->query('.//*['.rr_poll_class_xpath('headingElement').']',$card);
    return ['refs'=>$refs,'ids'=>$ids,'date'=>rr_welcome_text($head->item(0)),'time'=>rr_welcome_text($head->item(1)),
        'venue'=>rr_welcome_text($xp->query('.//*['.rr_poll_class_xpath('footerElement').']',$card)->item(0)),
        'competition'=>rr_welcome_text($competition_link),'competition_id'=>$competition_id,
        'fetched'=>time(),'error'=>''];
}
function rr_welcome_table($info) {
    if (empty($info['ids'][0]) || empty($info['ids'][1])) return [];
    $xp=rr_welcome_document('https://www.fotball.no/fotballdata/lag/hjem/?fiksId='.(int)$info['ids'][0].'&underside=tabeller');
    if (is_wp_error($xp)) return [];
    foreach($xp->query('//*['.rr_poll_class_xpath('a_tableStandings').']') as $wrapper) {
        $title=rr_welcome_text($xp->query('preceding::*['.rr_poll_class_xpath('sectionHeadingContent').'][1]',$wrapper)->item(0));
        if ($title!==$info['competition']) continue;
        $table=$xp->query('.//table',$wrapper)->item(0); $found=[];
        if (!$table) continue;
        foreach($xp->query('.//tbody/tr',$table) as $row) {
            $cells=$xp->query('./td',$row); if($cells->length!==9) continue;
            $a=$xp->query('./td[2]/a',$row)->item(0); if(!$a) continue;
            parse_str(wp_parse_url($a->getAttribute('href'),PHP_URL_QUERY)??'',$q);
            $id=(int)($q['fiksId']??0); if(!in_array($id,$info['ids'],true)) continue;
            $pos=rr_welcome_text($cells->item(0)); $played=rr_welcome_text($cells->item(2)); $points=rr_welcome_text($cells->item(8));
            if(!ctype_digit($pos)||!ctype_digit($played)||!preg_match('/^-?\d+$/D',$points)) continue;
            $found[$id]=rr_welcome_text($a).' ligger på '.$pos.'. plass med '.$points.' poeng etter '.$played.' kamper.';
        }
        if(count($found)===2) return array_map(static function($id) use($found){return $found[$id];},$info['ids']);
    }
    return [];
}
/** Highest goal tally for the home team in the selected competition. */
function rr_welcome_top_scorer($info) {
    $team_id=(int)($info['ids'][0]??0);
    $competition_id=(int)($info['competition_id']??0);
    if (!$team_id || !$competition_id || empty($info['competition'])) return [];
    $xp=rr_welcome_document('https://www.fotball.no/fotballdata/lag/hjem/?fiksId='.$team_id.'&underside=statistikk');
    if (is_wp_error($xp)) return [];
    $top=[]; $goals=0; $tied=false;
    foreach($xp->query('//a[@data-stattype="goal"][@data-teamid="'.$team_id.'"][@data-tournamentid="'.$competition_id.'"]') as $link) {
        $count=rr_welcome_text($link);
        $player=$xp->query('ancestor::tr[1]/td[1]/a[contains(@href,"/fotballdata/person/profil/")]',$link)->item(0);
        if (!ctype_digit($count) || !$player || (int)$count<=0) continue;
        $name=rr_welcome_text($player);
        if ($name==='') continue;
        $number=(int)$count;
        if ($number>$goals) { $top=['name'=>$name,'goals'=>$number]; $goals=$number; $tied=false; }
        elseif ($number===$goals) { $tied=true; }
    }
    return $tied ? [] : $top;
}
/**
 * Public match introduction. Wording is stable for a given match and changes
 * when the selected FIKS ID changes; factual details come from checked sources.
 */
function rr_poll_public_welcome($match) {
    $id=(int)($match['id']??0);
    $home=sanitize_text_field((string)($match['home']??'Bremnes'));
    $away=sanitize_text_field((string)($match['away']??'motstanderen'));
    $venue=sanitize_text_field((string)($match['venue']??''));
    $competition=sanitize_text_field((string)($match['competition']??''));
    $headline=$home.' møter '.$away.($venue!==''?' på '.$venue:'');
    $openers=[
        $home.' tar imot '.$away,
        'Det er klart for '.$home.' mot '.$away,
        $home.' og '.$away.' møtes til kamp',
    ];
    $lead=$openers[$id%count($openers)];
    if ($venue!=='') $lead.=' på '.$venue;
    $lead.=' '.($competition!==''?'Oppgjøret spilles i '.$competition.'. ':'');
    $date=sanitize_text_field((string)($match['date_label']??''));
    if ($date!=='') $lead.='Kampstart: '.$date.'. ';
    $lead.='Ta turen til stadion og få med deg kampen fra tribunen.';

    $cache_key='rr_poll_public_intro_'.$id;
    $snapshot=get_transient($cache_key);
    if (!is_array($snapshot)) {
        $snapshot=['table'=>[],'scorer'=>[],'fetched'=>time()];
        $info=rr_welcome_match($match);
        if (empty($info['error'])) { $snapshot['table']=rr_welcome_table($info); $snapshot['scorer']=rr_welcome_top_scorer($info); $snapshot['team_id']=(int)($info['ids'][0]??0); }
        set_transient($cache_key,$snapshot,!empty($snapshot['table'])?15*MINUTE_IN_SECONDS:5*MINUTE_IN_SECONDS);
    }
    $table=isset($snapshot['table']) && is_array($snapshot['table']) ? $snapshot['table'] : [];
    $standing=count($table)===2 ? 'Før kampen viser tabellen følgende: '.implode(' ',$table) : '';

    $history_file=__DIR__.'/bremnes-history-2026.php';
    $history=is_readable($history_file)?require $history_file:[];
    $team=((int)($match['home_id']??0)===48835)?'kvinner':'herrer';
    $kickoff=strtotime((string)($match['kickoff']??''))?:time();
    $meetings=[];
    foreach ((array)$history as $row) {
        if (!is_array($row) || empty($row['historical']) || ($row['team']??'')!==$team
            || strcasecmp(trim((string)($row['opponent']??'')),trim($away))!==0) continue;
        $when=strtotime((string)($row['kickoff']??''));
        if (!$when || $when >= $kickoff || !isset($row['result']['us'],$row['result']['them'])
            || !ctype_digit((string)$row['result']['us']) || !ctype_digit((string)$row['result']['them'])) continue;
        $meetings[]=['time'=>$when,'us'=>(int)$row['result']['us'],'them'=>(int)$row['result']['them']];
    }
    usort($meetings,static function($a,$b){return $b['time']<=>$a['time'];});
    if ($meetings) {
        $last=$meetings[0];
        $outcome=$last['us']>$last['them']?'vant':($last['us']<$last['them']?'tapte':'spilte uavgjort');
        $previous='Forrige registrerte oppgjør i 2026-oversikten var '.wp_date('d.m.Y',$last['time'],new DateTimeZone('Europe/Oslo')).'. '.$home.' '.$outcome.' '.$last['us'].'–'.$last['them'].' mot '.$away.'.';
    } else {
        $previous='Vi har ennå ikke et bekreftet tidligere oppgjør mellom lagene i Radio Rubbens 2026-oversikt.';
    }
    $invite=[
        'Når kampen er i gang, kan du også stemme på spilleren du mener fortjener tittelen Dagens Bremnesing.',
        'Ta turen til kampen, og vær med på å kåre Dagens Bremnesing når avstemningen åpner.',
        'Fra tribunen kan du følge oppgjøret og stemme fram Dagens Bremnesing når kampen er i gang.',
    ];
    return ['headline'=>$headline,'lead'=>$lead,'standing'=>$standing,'previous'=>$previous,'invite'=>$invite[$id%count($invite)],
        'fetched'=>(int)($snapshot['fetched']??time()),'verified_table'=>count($table)===2,
        'scorer'=>isset($snapshot['scorer']) && is_array($snapshot['scorer']) ? $snapshot['scorer'] : [],
        'scorer_url'=>!empty($snapshot['scorer']) && !empty($snapshot['team_id']) ? 'https://www.fotball.no/fotballdata/lag/hjem/?fiksId='.(int)$snapshot['team_id'].'&underside=statistikk' : ''];
}
$rr_welcome_info=[]; $rr_welcome_script=''; $rr_welcome_notes=[];
if ($rr_control && $rr_admin) {
    $rr_welcome_generate=($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rr_welcome_generate']));
    if($rr_welcome_generate) check_admin_referer('rr_welcome_'.$rr_match_id,'rr_welcome_nonce');
    $cache='rr_welcome_'.(int)$rr_match_id;
    $rr_welcome_info=get_transient($cache);
    if($rr_welcome_generate || !is_array($rr_welcome_info)) {
        $rr_welcome_info=rr_welcome_match($rr_match);
        set_transient($cache,$rr_welcome_info,empty($rr_welcome_info['error'])?600:60);
    }
    if($rr_welcome_generate) {
        $i=$rr_welcome_info; $paras=[];
        $paras[]='Kjære publikum! Hjertelig velkommen til '.($i['venue']??$rr_match['venue']).' og kampen mellom '.$rr_match['home'].' og '.$rr_match['away'].'! En ekstra velkomst til gjestene våre, dommerne og alle som har tatt turen hit.';
        if(empty($i['error'])) $paras[]='Vi er klare for '.$i['competition'].'. Kampstart er klokken '.$i['time'].'.';
        else $rr_welcome_notes[]=$i['error'].' Kampnavn og arena i manuset bygger på lagret kampoppsett.';
        $table=empty($i['error'])?rr_welcome_table($i):[];
        if($table) $paras[]='På tabellen nå: '.implode(' ',$table);
        else $rr_welcome_notes[]='Tabell for begge lag i riktig turnering kunne ikke bekreftes. Tabellomtale er utelatt.';
        if(!empty($i['refs'])) {
            $r=[]; foreach($i['refs'] as $ref) $r[]=$ref['role'].': '.$ref['name'].($ref['club']?' fra '.$ref['club']:'').'.';
            $paras[]='Vi ønsker også dagens dommerteam velkommen. '.implode(' ',$r);
        } else $rr_welcome_notes[]='Ingen dommere kunne bekreftes. Dommerpresentasjonen er utelatt.';
        $sponsor=trim((string)$rr_match_sponsor);
        if($sponsor!=='') $paras[]='Dagens kampsponsor er '.$sponsor.'. Tusen takk for støtten til klubben og dagens kamp!';
        else $rr_welcome_notes[]='Kampsponsor er ikke lagt inn. Legg inn navn under Oppsett og generer på nytt.';
        $paras[]='Vi vil også minne om Odd-Inges minnefond, som støtter blant annet stipend, dommerutstyr og behov i Bremnes Idrettslag. Vil du bidra, kan du vippse til 626873. Takk for støtten!';
        $paras[]='Hvem blir Dagens Bremnesing? Gå inn på radiorubben.no/dagenskamp, logg inn med Vipps og stem på spilleren du mener fortjener utmerkelsen. Det er gratis å stemme, og du kan gi én stemme. Avstemmingen åpner når kampen starter og stenger ved 75 minutter på kampklokken.';
        $paras[]='Da ønsker vi spillere, dommere og publikum en flott kamp. Heia Bremnes!';
        $rr_welcome_script=implode("\n\n",$paras);
    }
}
function rr_welcome_panel($match,$info,$script,$notes) {
    ?>
    <section class="card" id="speaker-welcome">
    <h2>Velkomstmanus og dommere</h2>
    <p class="muted">For <?php echo esc_html($match['home'].' – '.$match['away']); ?>. Henter kampinfo, dommere og tabell fra Fotball.no. Sponsor hentes fra kampoppsettet.</p>
    <h3>Dagens dommere</h3>
    <?php if(!empty($info['error'])): ?><p role="alert"><?php echo esc_html($info['error']); ?></p>
    <?php elseif(empty($info['refs'])): ?><p>Dommere er ikke publisert eller registrert hos Fotball.no ennå.</p>
    <?php else: ?><ul><?php foreach($info['refs'] as $ref): ?><li><strong><?php echo esc_html($ref['role']); ?>:</strong> <?php echo esc_html($ref['name'].($ref['club']?' · '.$ref['club']:'')); ?></li><?php endforeach; ?></ul><?php endif; ?>
    <p class="muted"><a href="<?php echo esc_url(rr_poll_source_url($match['id']).'&underside=dommere'); ?>" target="_blank" rel="noopener">Se dommere hos Fotball.no</a> · Sist henteforsøk <?php echo esc_html(wp_date('d.m.Y H:i',$info['fetched'],new DateTimeZone('Europe/Oslo'))); ?></p>
    <form method="post" action="#speaker-welcome">
    <?php wp_nonce_field('rr_welcome_'.$match['id'],'rr_welcome_nonce'); ?>
    <button type="submit" name="rr_welcome_generate" value="1">Generer velkomstmanus</button>
    <p class="muted">Oppdaterer også dommeroversikten. Manuset kan redigeres før opplesning.</p>
    </form>
    <?php if($script!==''): ?>
    <?php if($notes): ?><div role="status"><strong>Kontroller før opplesning:</strong><ul><?php foreach($notes as $note): ?><li><?php echo esc_html($note); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <label for="speaker-welcome-text">Tekst til opplesning</label>
    <textarea id="speaker-welcome-text" rows="18" style="width:100%;box-sizing:border-box;padding:18px;background:#0d1522;color:#f5f6fa;border:1px solid #586980;border-radius:12px;font-size:17px;line-height:1.65"><?php echo esc_textarea($script); ?></textarea>
    <button type="button" id="speaker-welcome-copy">Kopier manus</button><span id="speaker-welcome-copy-status" role="status"></span>
    <p class="muted">Kontroller opplysningene før opplesning. Tabell viser status ved henting. <a href="https://bremnesil.no/odd-inges-minnefond/" target="_blank" rel="noopener">Informasjon om Odd-Inges minnefond</a> (kontrollert 20.09.2026).</p>
    <script>
    document.getElementById('speaker-welcome-copy').addEventListener('click',async function(){
        const t=document.getElementById('speaker-welcome-text'),s=document.getElementById('speaker-welcome-copy-status');
        try{await navigator.clipboard.writeText(t.value);s.textContent=' Manuset er kopiert.';}
        catch(e){t.focus();t.select();s.textContent=' Marker teksten og velg Kopier.';}
    });
    </script>
    <?php endif; ?>
    </section>
    <?php
}
