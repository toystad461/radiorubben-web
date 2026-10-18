<?php
require __DIR__.'/../includes/review-digest.php';
use RadioRubben\Fotballrobot\ReviewDigest as D;
$options=[];$calls=[];$count=0;
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,...$a){$GLOBALS['options'][$k]=$v;return true;}
function check($ok,$label){$GLOBALS['count']++;if(!$ok)throw new RuntimeException($label);}
$now=100000;$a='studio:aaaaaaaaaaaaaaaa:'.str_repeat('a',64);$b='wp:45:'.str_repeat('b',64);
$sender=function($subject,$body)use(&$calls){check(get_option(D::OPTION)['status']==='sending','reservation precedes transport');$calls[]=[$subject,$body];return true;};
D::flush([$a],[],$now,$sender);check(!$calls,'first item is batched, not immediately mailed');
D::flush([$a,$b],[],$now+599,$sender);check(!$calls,'second item joins waiting digest');
D::flush([$a,$b,$a],[],$now+600,$sender);check(count($calls)===1&&str_contains($calls[0][0],'2 saker'),'one digest counts unique ready stories');
check(str_contains($calls[0][1],D::URL)&&!str_contains($calls[0][1],'operation=')&&!str_contains($calls[0][1],'post_id='),'mail has only read-only desk link');
D::flush([$a,$b],[],$now+20000,$sender);check(count($calls)===1,'unchanged pending stories never re-notify');
$c='wp:45:'.str_repeat('c',64);
D::flush([$a,$c],[],$now+700,$sender);D::flush([$a,$c],[],$now+1300,$sender);check(count($calls)===1,'hourly rate cap holds after changed story');
D::flush([$a,$c],[],$now+4200,$sender);check(count($calls)===2,'new revision may notify after interval');
$options=[];D::flush([$a],[],$now,$sender);D::flush([],[],$now+500,$sender);D::flush([$b],[],$now+600,$sender);check(count($calls)===2,'empty queue resets waiting window');
D::flush([$b],[],$now+1200,static function(){throw new RuntimeException('ambiguous transport');});
check(get_option(D::OPTION)['status']==='uncertain','ambiguous delivery retained');
D::flush([$b],[],$now+20000,$sender);check(count($calls)===2,'uncertain sending never retries automatically');
$options=[D::OPTION=>['status'=>'sending','pending_since'=>$now,'attempt_at'=>$now]];
D::flush([$a],[],$now+20000,$sender);check(count($calls)===2,'interrupted sending never retries automatically');
echo "$count digest checks passed; no real email sent\n";
