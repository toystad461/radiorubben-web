<?php
namespace RadioRubben\Fotballrobot;
if(!Robot::allowed())wp_die('Ingen tilgang.');
$id=absint($_GET['post_id']??0);$postUrl=admin_url('admin-post.php');
$notice=get_transient('rrfr_review_notice_'.get_current_user_id());delete_transient('rrfr_review_notice_'.get_current_user_id());
$labels=['writing'=>'Skriving pågår / avbrutt jobb må kontrolleres','pending'=>'Venter på din godkjenning','failed'=>'Skriving feilet','rejected'=>'Avvist','published'=>'Publisert','publishing'=>'Publisering må kontrolleres','test_approved'=>'Test godkjent – ikke publisert'];
echo '<div class="wrap rrfr"><header><p class="rrfr-eyebrow">FOTBALLROBOTEN · REDAKSJON</p><h1>Artikler til godkjenning</h1><p>Les forslaget, si ja eller nei, eller be om endringer.</p></header>';
if($notice)echo '<p class="rrfr-notice" role="status">'.esc_html($notice).'</p>';
echo '<section class="rrfr-card"><h2>E-postvarsler</h2><p>Til: '.esc_html(self::TO).'<br>Fra: Fotballroboten &lt;'.esc_html(self::FROM).'&gt;</p><p>Svar gis på denne innloggede siden. Svar på selve e-posten leses ikke automatisk.</p><p>Automatiske forslag: <strong>'.(get_option('rrfr_player_review_enabled_at',0)?'Aktivert':'Ikke aktivert').'</strong>. Én samlet artikkel per spillerkontroll, høyst ett forslag per time. Nye forslag og omskriving bruker eksisterende AI-tilkobling med separat faktakontroll.</p><form method="post" action="'.esc_url($postUrl).'">';
self::fields(get_option('rrfr_player_review_enabled_at',0)?'disable':'enable');echo '<button>'.(get_option('rrfr_player_review_enabled_at',0)?'Stopp automatiske forslag':'Aktiver for nye hendelser').'</button></form><h3>Test med en spiller</h3><form method="post" action="'.esc_url($postUrl).'">';self::fields('test');echo '<label>Spiller <select name="player_id">';foreach(Players::ids() as $pid){$pstate=Players::state((int)$pid);echo '<option value="'.esc_attr($pid).'">'.esc_html($pstate['name']).'</option>';}echo '</select></label> <button>Lag og send testforslag</button><p>Testen kan godkjennes og avvises uten publisering. Samme test gjenbrukes ved gjentatte klikk.</p></form></section>';
if($id)try{
    if(!current_user_can('edit_post',$id))throw new \RuntimeException('Ingen tilgang.');$s=self::state($id);$p=get_post($id);
    echo '<section class="rrfr-card"><h2>'.esc_html($p->post_title).'</h2><p><strong>'.esc_html($labels[$s['status']]??$s['status']).'</strong> · Versjon '.esc_html($s['version']).'</p>';
    if($s['test'])echo '<p class="rrfr-notice">TEST: Dette innlegget kan ikke publiseres. «Ja» tester bare godkjenningen.</p>';
    $mailLabels=['none'=>'Ikke sendt','sending'=>'Uavklart utsending – kontroller innboksen før nytt forsøk','accepted'=>'Levert til e-postsystemet; mottak i innboksen er ikke bekreftet','failed'=>'Sending feilet'];
    echo '<p>E-post: '.esc_html($mailLabels[$s['mail']]??$s['mail']).'</p>';
    if(!empty($s['error']))echo '<p class="rrfr-notice">'.esc_html($s['error']).'</p>';
    // Stored prose only: do not execute shortcodes, embeds or content filters on the review page.
    echo '<div class="rrfr-example">'.wp_kses_post($p->post_content).'</div><p><a href="'.esc_url(get_edit_post_link($id,'raw')).'">Rediger teksten i WordPress</a> · <a href="'.esc_url(self::url($id)).'">Hent siste lagrede tekst</a></p><p>Kommentaren lagres som en intern redaksjonell merknad. Ved «Be om endringer» brukes den til ny AI-tekst; den er ikke en ny faktakilde. Ja publiserer teksten som vises, uten å flette inn kommentaren.</p>';
    echo '<form method="post" action="'.esc_url($postUrl).'">';wp_nonce_field('rrfr_player_review');
    foreach(['action'=>'rrfr_player_review','post_id'=>$id,'version'=>$s['version'],'hash'=>self::hash($p)] as $k=>$v)echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    echo '<label>Din kommentar<textarea name="comment" maxlength="2000" rows="4"></textarea></label><div class="rrfr-row">';
    $ops=$s['status']==='pending'?['approve'=>$s['test']?'Ja – test godkjenning':'Ja – godkjenn og publiser','reject'=>'Nei – avvis','revise'=>'Be om endringer','mail'=>'Send e-post på nytt']:['resubmit'=>'Send lagret tekst til ny godkjenning'];
    if($s['status']==='failed')$ops=['retry'=>'Prøv AI-skriving på nytt'];
    if(in_array($s['status'],['published','publishing','writing'],true)||$p->post_status!=='draft')$ops=[];
    foreach($ops as $op=>$label)echo '<button name="operation" value="'.esc_attr($op).'">'.esc_html($label).'</button>';
    echo '</div></form><details><summary>Kommentarer og historikk</summary>';foreach($s['history'] as $h)echo '<p>'.esc_html($h['at'].' · '.$h['action'].' · '.$h['comment']).'</p>';echo '</details></section>';
}catch(\Throwable $e){echo '<p class="rrfr-notice">'.esc_html($e->getMessage()).'</p>';}
echo '<section class="rrfr-card"><h2>Forslag</h2><ul>';foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future'],'numberposts'=>50,'meta_key'=>self::META]) as $p){$s=self::state($p->ID);echo '<li><a href="'.esc_url(self::url($p->ID)).'">'.esc_html($p->post_title).'</a> · '.esc_html($labels[$s['status']]??$s['status']).'</li>';}echo '</ul></section></div>';
