<?php
// Layout fixtures only, never installed in production.
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Sources as S;
use RadioRubben\PlayerWidget\Service as App;
use RadioRubben\PlayerWidget\View;
$now=time();$posts=[1011,1012,1013,1014,1037];
$names=['Tiril Elisabeth Sellevold-Øystad','Lasse Nathaniel Høgmo Breivik','Sander Håvik Innvær','Troy Engseth Nyhammer','Anna Engeseth Lie'];
$teamIds=[35897,20705,2,2,210681];$clubIds=[781,1633,1509,1509,711];$teamNames=['Brann 2','Åsane 2','Tromsø','Tromsø','Haugesund 2'];
$options[App::SETTINGS]=['revision'=>1,'enabled'=>true,'players'=>[]];
$cache=['teams'=>[],'matches'=>[]];
foreach($posts as $i=>$id) {
    $options['rrfr_player_'.$id]=['name'=>$names[$i],'fiks_id'=>$id,'enabled'=>true,'snapshot'=>['clubs'=>[$clubIds[$i]=>[]],'stats'=>[]]];
    $options[App::SETTINGS]['players'][$id]=['fiks_id'=>$id,'teams'=>[$teamIds[$i]]];
    $cache['teams'][$teamIds[$i]]=['id'=>$teamIds[$i],'club_id'=>$clubIds[$i],'name'=>$teamNames[$i],'checked_at'=>$now,'error'=>null,'matches'=>[]];
}
$team=S::team(file_get_contents(__DIR__.'/fixtures/team.html'),35897);$m=$team['matches'][8989882];
$stream=S::stream(file_get_contents(__DIR__.'/fixtures/mygame.html'),$m);$m['kickoff']=gmdate(DATE_ATOM,$now+3600);
$cache['teams'][35897]['matches']=[8989882=>$m];
$cache['matches'][8989882]=['match'=>$m,'stream'=>$stream,'stream_checked_at'=>$now,'lineup_checked_at'=>$now,'roles'=>[1011=>'bench']];
$m2=$m;$m2['id']=8992098;$m2['home']=['id'=>415,'name'=>'Fana'];$m2['away']=['id'=>20705,'name'=>'Åsane 2'];$m2['kickoff']=gmdate(DATE_ATOM,$now+86400);
$cache['teams'][20705]['matches']=[8992098=>$m2];$cache['matches'][8992098]=['match'=>$m2,'stream'=>['source'=>S::url('stream',8992098),'url'=>null,'logos'=>[]],'stream_checked_at'=>$now,'lineup_checked_at'=>$now,'roles'=>[]];
$options[App::CACHE]=$cache;
echo '<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kompakt spillerwidget – layoutprøve</title><link rel="stylesheet" href="../assets/widget.css"><style>body{margin:0;padding:20px;background:#0b0d11}*{box-sizing:border-box}</style><body>';
echo View::shortcode();
echo '<script src="../assets/widget.js"></script></body></html>';
