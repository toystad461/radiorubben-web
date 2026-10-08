<?php
function add_filter(...$a){} function add_action(...$a){}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require __DIR__.'/../includes/writer.php';
require __DIR__.'/../includes/player-review.php';
use RadioRubben\Fotballrobot\InlineSources as S;
use RadioRubben\Fotballrobot\EditorialQuality as Q;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\PublicationGate;
$n=0;
function check($v,$why){global $n;$n++;if(!$v)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(RuntimeException $e){check(true,$why);return;}check(false,$why);}
$url='https://www.fotball.no/fotballdata/kamp/?fiksId=9007464';
$profile='https://www.fotball.no/fotballdata/person/profil/?fiksId=3909887';
$f=['type'=>'events','source'=>$profile,'events'=>[['source'=>$url,'after'=>['type'=>'Advarsel','minute'=>'59','name'=>'Lasse Nathaniel Høgmo Breivik']]]];
$a=['title'=>'Breivik scoret for Åsane','lead'=>'Lasse Nathaniel Høgmo Breivik scoret for Åsane.','paragraphs'=>['Breivik fikk også gult kort i det 59. minutt.'],'checks'=>[['claim'=>'Gult kort','support'=>'events.0.after']],'inline_sources'=>[['paragraph'=>0,'text'=>'gult kort i det 59. minutt','source_url'=>$url]]];
$plain=$a;unset($plain['inline_sources']);
check(Writer::validate($plain)===$plain,'Legacy articles stay valid without link metadata');
$html=PlayerReview::body($a,$f);
$anchor='<a href="'.$url.'">gult kort i det 59. minutt</a>';
check(str_contains($html,'Breivik fikk også '.$anchor.'.'),'Natural phrase is linked in the player article');
check(str_contains($html,'Kilder:')&&substr_count($html,$url)===2,'Source footer is preserved');
check($a['lead']==='Lasse Nathaniel Høgmo Breivik scoret for Åsane.','Plain excerpt is unchanged');
check(str_contains(Writer::body($a,$f),$anchor),'Match renderer supports the same citation');
check(!str_contains(PlayerReview::body($plain,$f),$anchor),'Legacy article is not retroactively linked');
check(str_contains(Writer::prompt(),S::prompt())&&str_contains(Writer::playerPrompt(),S::prompt()),'Both writing prompts request source annotations');
check(in_array('inline_sources',Writer::schema()['required'],true),'New generated responses include the link list');
$b=$a;$b['inline_sources'][0]['paragraph']=-1;$b['inline_sources'][0]['text']='Lasse Nathaniel Høgmo Breivik';
check(str_contains(S::paragraphs($b,$f)[0],'<a href="'.$url.'">Lasse Nathaniel Høgmo Breivik</a>'),'Ingress link uses the correct paragraph');
foreach([
    ['source_url'=>'javascript:alert(1)'],
    ['source_url'=>'http://www.fotball.no/fotballdata/kamp/?fiksId=9007464'],
    ['source_url'=>'https://www.fotball.no@evil.test/fotballdata/kamp/?fiksId=9007464'],
    ['source_url'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=9007464#invented'],
    ['source_url'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=9999999'],
    ['source_url'=>'https://www.fotball.no:443/fotballdata/kamp/?fiksId=9007464'],
    ['source_url'=>"https://www.fotball.no/\nunsafe"],
    ['text'=>'Gult kort i det 59. minutt'],
    ['text'=>'kort'],
    ['paragraph'=>2],
    ['paragraph'=>'0']
] as $change){$b=$a;$b['inline_sources'][0]=array_replace($b['inline_sources'][0],$change);rejects(fn()=>S::paragraphs($b,$f),'Reject unsafe URL, fabricated source, invalid index or missing span');}
$b=$a;$b['paragraphs'][0].=' Breivik fikk også gult kort i det 59. minutt.';
rejects(fn()=>S::paragraphs($b,$f),'Repeated span is ambiguous');
$b=$a;$b['inline_sources'][]=['paragraph'=>0,'text'=>'det 59. minutt','source_url'=>$url];
rejects(fn()=>S::paragraphs($b,$f),'Overlapping source spans rejected');
$b=$a;$b['paragraphs'][0]='Åsane & Breivik: gult kort i det 59. minutt. Et mål ble også registrert.';
$b['inline_sources'][]=['paragraph'=>0,'text'=>'Et mål ble også registrert','source_url'=>$url];
check(substr_count(S::paragraphs($b,$f)[1],'<a href=')===2&&str_contains(S::paragraphs($b,$f)[1],'Åsane &amp; Breivik'),'Multiple non-overlapping Unicode spans and surrounding escaping');
$newsUrl='https://club.example/news?id=12&view=full';
$news=['type'=>'public_news','source'=>$newsUrl,'news'=>['url'=>$newsUrl,'facts'=>['Et offentlig faktum']]];
$b=$a;$b['inline_sources'][0]['source_url']=$newsUrl;
check(str_contains(S::paragraphs($b,$news)[1],'id=12&amp;view=full'),'Verified club source query is attribute-escaped');
$untrusted=$f;$untrusted['news']['facts']=['https://invented.example/news'];$b['inline_sources'][0]['source_url']='https://invented.example/news';
rejects(fn()=>S::paragraphs($b,$untrusted),'A URL inside source prose is not a verified source field');
// A link-only rewrite is a material editorial change, even with identical prose.
$yes=static fn()=>['approved'=>true,'issues'=>[]];
$changed=$a;$changed['inline_sources'][0]['source_url']=$profile;$calls=0;
$reviewer=function()use(&$calls){$calls++;return $calls===1?['approved'=>true,'issues'=>[]]:['approved'=>false,'issues'=>['Profilen støtter ikke det konkrete kortet.']];};
$r=Q::review($a,$f,$reviewer,fn()=>$changed);
check($calls===2&&!$r['publishable'],'Link-only change receives independent factual recheck');
$r=Q::advance(Q::begin($a,$f),$f,$yes,fn()=>$changed);
$r=Q::advance($r,$f,$yes,fn()=>$changed);
check($r['phase']==='recheck'&&!$r['publishable'],'Staged match review also detects link-only change');
$r=Q::advance($r,$f,fn()=>['approved'=>false,'issues'=>['Feil kildelenke']],fn()=>$changed);
check(!$r['publishable'],'Rejected link cannot inherit prior approval');
$b=$a;$b['inline_sources'][0]['source_url']='https://invented.example/news';$calls=0;
$r=Q::review($b,$f,function()use(&$calls){$calls++;return ['approved'=>true,'issues'=>[]];},fn($v)=>$v);
check(!$r['publishable']&&$calls===0,'Unknown source stops before paid review');
$b=$a;$b['paragraphs'][0]='Breivik fikk gult kort etter 59 minutter.';
check(!Q::review($a,$f,$yes,fn()=>$b)['publishable'],'Copyedit cannot leave a broken source span');
$r=Q::review($a,$f,$yes,function($v,$facts,$instructions){check(str_contains($instructions,S::prompt()),'Language reviewer preserves source links');return $v;});
check($r['publishable'],'Unchanged verified citation passes normal quality review');
// Human-edited HTML retains targets during recheck; the footer does not become body citations.
$saved='<p>Breivik fikk også '.$anchor.'.</p><p>Se <a href="'.$profile.'">sesongstatistikken</a>.</p><p><small>Kilder: <a href="'.$url.'">fotball.no</a></small></p>';
$refs=S::fromHtml($saved);
check(count($refs)===2&&$refs[0]['source_url']===$url&&$refs[1]['source_url']===$profile,'Extract body link targets and omit source footer');
$reviewCopy=$plain;$reviewCopy['paragraphs']=[strip_tags($saved)];$reviewCopy['inline_sources']=$refs;
S::validate($reviewCopy,$f);check(true,'Multiple saved links validate in combined review paragraph');
$post=['post_title'=>$a['title'],'post_excerpt'=>$a['lead'],'post_content'=>$html];
$edited=$post;$edited['post_content']=str_replace($url,$profile,$html);
check(PublicationGate::hash($post)!==PublicationGate::hash($edited),'Link edits invalidate the exact saved-post approval');
echo "OK: $n inline citation rendering, provenance and review controls; no network or paid AI\n";
