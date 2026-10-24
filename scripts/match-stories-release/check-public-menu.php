<?php
$html=file_get_contents($argv[1]);$d=new DOMDocument();libxml_use_internal_errors(true);$d->loadHTML($html);$x=new DOMXPath($d);
$nodes=$x->query('//nav[@id="rr-primary-nav"]//a[@href="https://www.radiorubben.no/sport/"]');
if($nodes->length!==1||trim($nodes->item(0)->textContent)!=='Sport')throw new RuntimeException('Sport absent from actual main navigation');
echo "PUBLIC_PRIMARY_SPORT_MENU_OK\n";
