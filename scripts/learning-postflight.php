<?php
/** Read-only runtime checks: no model calls, editorial writes or cron changes. */
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\Writer;
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('WP-CLI required');
if (get_option('home')!=='https://www.radiorubben.no') throw new RuntimeException('Wrong site');
$m=['id'=>0,'home'=>['id'=>1,'name'=>'Test A'],'away'=>['id'=>2,'name'=>'Test B'],'score'=>[3,0],'events'=>[]];
foreach([10,20,30] as $i=>$minute)$m['events'][]=['id'=>'test-'.$i,'side'=>'home','minute'=>(string)$minute,'type'=>'Spillemål','name'=>'Testspiller'];
$a=Facts::scoringAngles($m);
if (($a[0]['id']??'')!=='hattricks' || count($a[0]['scorers'][0]['event_ids'])!==3) throw new RuntimeException('Hattrick readback failed');
if (Facts::publicName('Personinfo ikke tilgjengelig')!=='' || Facts::duration(Facts::dom('<p>Spilletid: 70 min</p>'))!==70) throw new RuntimeException('Source rules failed');
if (Writer::WRITING_PROMPT_VERSION!=='2026-10-22.2' || !str_contains(Writer::prompt(),Writer::matchLearningRules())) throw new RuntimeException('Writer revision failed');
echo "LEARNING_RUNTIME_OK\n";
