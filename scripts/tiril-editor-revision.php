<?php
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\ReviewDesk;
use RadioRubben\Fotballrobot\PublicationGate;
try {
 $id=1091;$model=ReviewDesk::model($id);$s=$model['state'];$p=$model['post'];
 if($p->post_status!=='draft'||($s['facts']['fiks_id']??0)!==3942773||($s['notify']??true)!==false)throw new RuntimeException('Utkastidentitet eller varslingsvalg avviker.');
 if(isset($s['facts']['editorial_facts']))throw new RuntimeException('Egne opplysninger finnes allerede; les tilbake før nytt forsøk.');
 if($s['version']!==5 || PlayerReview::hash($p)!=='48504bb44e4c84227b516a9739dc7aa6f247b4b1adbde4e63f0b8eaec5745a94') throw new RuntimeException('Redaktøren har endret utkastet. Stoppet uten overskriving.');
 $history=end($s['history']);
 if(($history['user']??0)!==get_current_user_id() || hash('sha256',$history['comment']??'')!=='f0ce00badeb816db79a90a36a49bdc9c53a249206fe04b2578e6364b8849fe07')throw new RuntimeException('Den bekreftede redaktørkommentaren er endret.');
 // Use only the editor's already stored, explicitly confirmed statement; no private prose in Git/logs.
 $facts=preg_replace('/^Ta med i artikkelennat /u','',$history['comment']);
 $comment='Ta med et kort, naturlig avsnitt om veien tilbake og betydningen av debuten, med støtte i facts.editorial_facts. Behold bekreftede kampopplysninger. Bruk bare datoer og detaljer som finnes i kildene. Behold relevante kildelenker i brødteksten, men ingen lenke i ingressen. La profil-lenken støtte en egen setning om debutstatistikken, og kamp-lenken en egen setning om innhoppet. Ikke knytt redaksjonens opplysninger til NFF-lenker.';
 ReviewDesk::decide($id,ReviewDesk::token($id,$model),'revise',$comment,$facts);
 $s=PlayerReview::state($id);$quality=PublicationGate::current($id,get_post($id));
 echo wp_json_encode(['id'=>$id,'status'=>get_post($id)->post_status,'review_status'=>$s['status'],'quality'=>$quality,'version'=>$s['version'],'editorial_sources'=>count($s['facts']['editorial_facts']??[]),'mail'=>$s['mail']],JSON_UNESCAPED_UNICODE)."\n";
 if(!$quality||$s['status']!=='pending'||get_post($id)->post_status!=='draft')throw new RuntimeException('Omskrevet utkast trenger oppfølging. Les kvalitetstilstanden i WordPress.');
 echo "TIRIL_REVISION_OK\n";
} catch(Throwable $e){WP_CLI::error($e->getMessage());}
