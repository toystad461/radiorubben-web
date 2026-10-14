<?php
require dirname(__DIR__).'/contract.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$assets=rrni_assets();verify(count($assets)===21,'pinned manifest has 21 assets');verify(count(rrni_themes())===7,'seven editorial themes');
$formats=['article_landscape'=>[1672,941,'liggende'],'feed_square'=>[1254,1254,'kvadrat'],'story_vertical'=>[941,1672,'story']];
foreach($assets as $a){verify([$a['width_px'],$a['height_px'],$a['format']]===$formats[$a['placement']],'exact placement '.$a['asset_id']);verify($a['crop_allowed']===false&&$a['is_ai_generated']===true,'whole AI illustration '.$a['asset_id']);}
$ctx=['body'=>'Brann vant. Spilleren har tidligere spilt for Bremnes IL.'];
$a=['theme_id'=>'bremnes-fotball','reason'=>'Spilleren kommer fra Bømlo, men representerer Brann.','motif_fits'=>true,'confidence'=>0.95,'main_angle'=>'bremnes_football','primary_club'=>'Brann','bremnes_focus'=>true,'evidence'=>[$ctx['body']]];
verify(rrni_assessment($ctx,$a)['status']==='INGEN_PASSENDE_BILDE','Brann never inherits Bremnes logo from former affiliation');
$a['primary_club']='Bremnes IL';$a['evidence']=['Oppdiktet belegg i en annen sak.'];verify(rrni_assessment($ctx,$a)['status']==='INGEN_PASSENDE_BILDE','fabricated evidence rejected');
verify(rrni_assessment(['body'=>'Ny frisørsalong'],['theme_id'=>null,'reason'=>'Verkstedmotivet passer ikke en frisørsalong.'])['status']==='INGEN_PASSENDE_BILDE','no universal business fallback');
verify(!rrni_url_valid('https://www.radiorubben.no.evil.test/wp-content/uploads/a.png'),'external lookalike blocked');
verify(!rrni_selection_valid(['status'=>'selected','theme_id'=>'politikk']),'unbound media cannot be published');
