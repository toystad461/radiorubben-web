<?php
use RadioRubben\Fotballrobot\Players;
use RadioRubben\PlayerWidget\Service;
try {
    Players::register(); Service::init();
    $state=Players::refresh(1083);
    if(!isset($state['snapshot']['clubs'][1730],$state['snapshot']['stats']['2026:171'])) throw new RuntimeException('Hødd-tilknytningen kunne ikke bekreftes.');
    Service::tick();
    $selected=Service::selected(Service::settings(),Service::profiles());
    if(count($selected)!==12 || !in_array(171,$selected[1083]['selected_teams'],true)) throw new RuntimeException('Godkjent spillerliste avviker.');
    $cache=Service::cache();
    if(($cache['teams'][171]['club_id']??0)!==1730) throw new RuntimeException('Hødds terminliste mangler.');
    $event=wp_get_scheduled_event('rrpw_refresh');
    if(!$event || $event->interval!==60) throw new RuntimeException('Minuttintervallet mangler.');
    $profile=wp_get_scheduled_event('rrfr_profiles_tick');
    if(!$profile || $profile->interval!==300) throw new RuntimeException('Profilkøen mangler.');
    $html=\RadioRubben\PlayerWidget\Compact::render();
    if(substr_count($html,'class="rrpw-mini"')!==12 || str_contains($html,'Tropp uavklart') || str_contains($html,'Ingen kommende kamper')) throw new RuntimeException('Widgetkontroll feilet.');
    echo wp_json_encode(['players'=>count($selected),'torbjorn_team'=>171,'club'=>1730,'widget_interval'=>$event->interval,'profile_interval'=>$profile->interval,'last_run'=>$cache['last_run'],'due_matches'=>$cache['due_matches'],'match_requests'=>$cache['match_requests']],JSON_UNESCAPED_UNICODE)."\n";
    do_action('litespeed_purge_url',home_url('/'));do_action('litespeed_purge_url',home_url('/sport/'));
    echo "FOLLOWUP_RELEASE_OK\n";
} catch(Throwable $e) {WP_CLI::error($e->getMessage());}
