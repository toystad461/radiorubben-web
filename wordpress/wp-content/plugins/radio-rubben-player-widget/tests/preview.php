<?php
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Sources;
use RadioRubben\PlayerWidget\View;
$team=Sources::team(file_get_contents(__DIR__.'/fixtures/team.html'),35897);
$m=$team['matches'][8989882];
$stream=Sources::stream(file_get_contents(__DIR__.'/fixtures/mygame.html'),$m);
// Layout-only fixture: keep the source example readable whenever CI runs.
$m['kickoff']=gmdate(DATE_ATOM,(int)(ceil(time()/3600)*3600));
$card=['match'=>$m,'stream'=>$stream,'team_checked_at'=>time(),'expires'=>time()+900,'lineup_expires'=>0,'players'=>[['name'=>'Tiril Elisabeth Sellevold-Øystad','role'=>null]]];
echo '<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kampwidget – layoutprøve</title><link rel="stylesheet" href="../assets/widget.css"><style>body{margin:0;padding:16px;background:#ececef}*{box-sizing:border-box}</style><body>';
echo View::card($card);
echo '<script src="../assets/widget.js"></script></body></html>';
