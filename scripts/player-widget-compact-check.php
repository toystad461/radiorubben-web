<?php
use RadioRubben\PlayerWidget\Service;
use RadioRubben\PlayerWidget\Sources;
use RadioRubben\PlayerWidget\View;
if (RadioRubben\PlayerWidget\VERSION !== '1.1.0') throw new RuntimeException('Wrong version');
$before=hash('sha256',serialize(Service::settings()));
Service::tick();
$html=View::shortcode();
if (substr_count($html,'class="rrpw-mini"')!==5) throw new RuntimeException('Expected five selected players');
if (!str_contains($html,'Bømlo-spillere ute')) throw new RuntimeException('Missing compact title');
$cache=Service::cache();$match=$cache['teams'][20705]['matches'][8992098]??null;
if (!$match) throw new RuntimeException('Missing verified Fana–Åsane 2 fixture');
$page=Sources::mygame(Sources::fetch('stream',8992098),$match);
if ($page['source']!==Sources::url('stream',8992098)) throw new RuntimeException('Wrong MyGame destination');
if ($before!==hash('sha256',serialize(Service::settings()))) throw new RuntimeException('Settings changed');
echo wp_json_encode(['version'=>RadioRubben\PlayerWidget\VERSION,'players'=>5,'mygame_page'=>$page['source'],'broadcast_confirmed'=>!empty($page['url']),'next_refresh'=>wp_next_scheduled('rrpw_refresh')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
