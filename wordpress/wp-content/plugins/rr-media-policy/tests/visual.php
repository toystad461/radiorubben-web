<?php
// Visual fixture only. Real WordPress behavior is covered separately by wordpress.php.
function add_filter(...$args){} function add_action(...$args){}
function get_post_meta(...$args){return ['origin'=>'ai_generated'];}
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_html($s){return esc_attr($s);}
require dirname(__DIR__).'/policy.php';require dirname(__DIR__).'/wordpress.php';
$root=dirname(__DIR__,5);
$svg='<svg xmlns="http://www.w3.org/2000/svg" width="640" height="960"><rect width="640" height="960" fill="#497f88"/><circle cx="320" cy="300" r="140" fill="#efc375"/><path d="M0 900L300 500L640 900Z" fill="#153747"/></svg>';
$img=rrmp_wrap('<img src="data:image/svg+xml;base64,'.base64_encode($svg).'" width="640" height="960" alt="Syntetisk testfigur">',1);
?>
<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bildemerking – test</title><style>
<?php foreach(['theme','components','home-universes']as$file)echo file_get_contents($root.'/theme-next/radio-rubben-next/assets/css/'.$file.'.css'); ?>
body{margin:0;padding:20px;background:#eee;color:#111;font:16px system-ui}main{max-width:760px;margin:auto}section{margin-bottom:32px}h2{font-size:20px}img{max-width:100%}.rr-u-story-image{width:100%}.rr-compact-grid{display:block!important}.rr-post-card{max-width:320px}.rr-post-thumb{color:#fff}
</style><main><h1>AI-bildemerking</h1><section><h2>Hovedsak med beskåret bilde</h2><div class="rr-u-story-image" data-viewport><?=$img?></div></section><section><h2>Kompakt nyhetskort</h2><div class="rr-compact-grid"><article class="rr-post-card"><a class="rr-post-thumb" data-viewport href="#"><?=$img?></a><h3>Eksempelsak</h3></article></div></section><section><h2>Artikkelbilde</h2><div class="rr-single-image" data-viewport><?=$img?></div></section></main></html>
