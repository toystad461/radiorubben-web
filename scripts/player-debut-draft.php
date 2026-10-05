<?php
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\PlayerFacts;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\PublicationGate;
try {
    $key='verified-debut:3942773:8989882:2-div-kvinner';
    $existing=\RadioRubben\Fotballrobot\PlayerMonitor::review($key);
    if($existing){echo wp_json_encode($existing)."\nDEBUT_DRAFT_EXISTS\n";return;}
    $read=static function($url){$r=wp_safe_remote_get($url,['timeout'=>15,'redirection'=>0,'limit_response_size'=>2500000]);if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)throw new RuntimeException('Fersk NFF-kilde utilgjengelig.');return wp_remote_retrieve_body($r);};
    $matchUrl='https://www.fotball.no/fotballdata/kamp/?fiksId=8989882';$profileUrl=PlayerFacts::url(3942773);
    $html=$read($matchUrl);$profileHtml=$read($profileUrl);
    $m=Facts::match($html,8989882);$p=PlayerFacts::participation($html,8989882,3942773);$profile=PlayerFacts::profile($profileHtml,3942773);
    if($m['score']!==[0,3] || !str_contains($html,'Kampen er slutt') || ($p['events'][7417931]['type']??'')!=='Innbytte' || ($p['events'][7417931]['minute']??'')!=='90' || ($profile['stats']['2026:35897']['appearances']??0)!==1) throw new RuntimeException('Kamp- eller debutgrunnlaget har endret seg.');
    $x=Facts::dom($profileHtml);$a=$x->query('//a[@data-fiksid="3942773"][@data-tournament-category-name="2. div kvinner"][@data-stat-type="any"]')->item(0);
    if(Facts::text($a)!=='1') throw new RuntimeException('Første kamp i divisjonen er ikke bekreftet.');
    $at=gmdate(DATE_ATOM);
    $facts=['type'=>'public_news','player_id'=>1011,'fiks_id'=>3942773,'name'=>$profile['name'],'source'=>$matchUrl,'fetched_at'=>$at,'news'=>[
        'url'=>$matchUrl,'title'=>'Tiril debuterte i 2. divisjon kvinner for Brann 2','published_at'=>'2026-10-04','checked_at'=>$at,'event_date'=>'2026-10-04',
        'facts'=>[
            'Sogndal Fotballklubb–Brann 2 ble spilt 4. oktober 2026 klokken 14.00 på Fosshaugane Campus, i 2. div kvinner avdeling 1. Kampen er slutt. Brann 2 vant 3–0; det sto 0–0 til pause.',
            'Tiril Elisabeth Sellevold-Øystad (FIKS 3942773, Brann 2 draktnummer 7) kom inn for Stine Isaksen Solberg i det 90. minutt. Byttet er registrert som NFF-hendelse 7417931.',
            'Stine Isaksen Solberg scoret i det 53. minutt. Elise Lyssand scoret i det 64. og 84. minutt.',
            'Vinkel: Debuten til Tiril, med lokal tilknytning til Bremnes. Ikke kall dette seniordebut. Ikke oppgi nøyaktig antall spilte minutter eller vurder prestasjonen uten dokumentasjon. Ingen sitater foreligger.'
        ],
        'supporting_sources'=>[['url'=>$profileUrl,'title'=>'Spillerhistorikken til Tiril Elisabeth Sellevold-Øystad','checked_at'=>$at,'facts'=>[
            'NFFs samlede turneringsstatistikk viser én kamp i 2. divisjon kvinner, for Brann 2. Sesongstatistikken viser én kamp for Brann 2 i 2026 og ingen tidligere Brann 2-sesonger. Dette bekrefter hennes første registrerte kamp i divisjonen.',
            'Spillerhistorikken inneholder ungdomskamper for Bremnes fra 2022 til 2024 og seniorkamper for Bremnes i 3. divisjon kvinner. Hun har også registrerte kamper for Åsane 2 i 4. divisjon kvinner.'
        ]]]
    ]];
    $id=PlayerReview::create($key,$facts,false,false); // No email; existing quality and approval gates remain.
    $state=PlayerReview::state($id);
    if(get_post($id)->post_status!=='draft' || $state['status']!=='pending' || !PublicationGate::current($id,get_post($id))) throw new RuntimeException('Utkast '.$id.' er lagret, men kvalitetskontrollen ble ikke fullført: '.($state['error']??''));
    wp_set_post_categories($id,[16,17,62]);
    echo wp_json_encode(['id'=>$id,'title'=>get_post($id)->post_title,'status'=>'draft','quality'=>true,'mail'=>$state['mail'],'review_url'=>PlayerReview::url($id),'edit_url'=>get_edit_post_link($id,'raw')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\nDEBUT_DRAFT_OK\n";
} catch(Throwable $e) { WP_CLI::error($e->getMessage()); }
