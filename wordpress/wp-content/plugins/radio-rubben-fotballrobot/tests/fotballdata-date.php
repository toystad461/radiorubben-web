<?php
require __DIR__.'/../includes/fotballdata.php';
use RadioRubben\Fotballrobot\Fotballdata as F;
$count=0;
function checkDate($raw,$expected){global $count;if(F::date($raw)!==$expected)throw new RuntimeException($raw);$count++;}
// Captured public dates; never include API credentials or full responses.
checkDate('/Date(1791574200000-0000)/','2026-10-09T19:30:00+02:00');
checkDate('/Date(1792179000000-0000)/','2026-10-16T19:30:00+02:00');
foreach(['2026-01-09 19:30:00'=>'+01:00','2026-07-09 19:30:00'=>'+02:00','2026-10-25 19:30:00'=>'+01:00','2026-03-29 19:30:00'=>'+02:00'] as $wall=>$offset){
    $ms=(new DateTimeImmutable($wall,new DateTimeZone('UTC')))->getTimestamp()*1000;
    checkDate('/Date('.$ms.'-0000)/',str_replace(' ','T',$wall).$offset);
}
// UTC/offset-bearing epoch dates keep their established instant semantics.
foreach(['','+0000','+0200'] as $offset)checkDate('/Date(1791567000000'.$offset.')/','2026-10-09T19:30:00+02:00');
foreach(['garbage','/Date(123)/','/Date(1774751400000-0000)/'] as $bad){
    try{F::date($bad);throw new LogicException('Invalid date accepted');}catch(RuntimeException $e){$count++;}
}
echo "OK: $count Fotballdata date controls\n";
