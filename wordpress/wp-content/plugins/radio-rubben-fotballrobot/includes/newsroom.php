<?php
namespace RadioRubben\Fotballrobot;

/** Authenticated bridge to the existing approval methods; GET never changes a decision. */
final class Newsroom {
    const VERSION='newsroom-1';
    public static function card(int $id): array {
        $m=ReviewDesk::model($id);$p=$m['post'];$s=$m['state'];$reasons=[];
        if($m['status']==='rejected'||$m['test'])return [];
        $status=$m['status']==='published'?'published':($m['can_approve']?'ready':'attention');
        $token=ReviewDesk::token($id,$m);$correction=null;
        if($m['status']==='published'&&class_exists(PlayerCorrections::class)){
            $r=get_post_meta($id,PlayerCorrections::META,true);
            if(is_array($r)&&($r['status']??'')==='pending'){
                $correction=$r;$p=clone $p;foreach($r['post'] as $key=>$value)$p->$key=$value;
                $token=$r['token'];$m['can_approve']=current_user_can('publish_posts')&&hash_equals($r['base'],PlayerCorrections::base($id))
                    &&($r['quality']['publishable']??false)===true&&($r['quality']['rulesVersion']??'')===EditorialQuality::RULES_VERSION;
                $status=$m['can_approve']?'ready':'attention';
                $reasons[]=$m['can_approve']?'Godkjenning oppdaterer den eksisterende saken. Nettadresse og bilde beholdes.':'Saken er endret etter kontrollen av dette endringsforslaget. Ny kontroll kreves.';
            }
        }
        if($status==='attention'){
            if(!empty($s['error']))$reasons[]=$s['error'];
            if(!$m['quality'])$reasons[]='Fakta- og språkkontrollen må fullføres før publisering.';
            if($m['status']==='publishing')$reasons[]='Publiseringsstatus må avklares.';
        }
        $links=[];
        if(preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',$p->post_content,$matches,PREG_SET_ORDER))foreach($matches as $a){
            $url=html_entity_decode($a[1],ENT_QUOTES,'UTF-8');$parts=parse_url($url);
            if(($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))continue;
            if(!isset($links[$url]))$links[$url]=['url'=>$url,'label'=>trim(wp_strip_all_tags($a[2]))?:$parts['host']];
        }
        // Presentation only: retain the stored article and its quality hash verbatim.
        $html=EditorialNotice::reviewContent($p->post_content);
        $notice=$html!==$p->post_content?'KI-generert artikkel · Redaksjonelt ansvar: Radio Rubben.':'';
        $html=preg_replace('~<small\b[^>]*>Kilder:.*?</small>~is','',$html);
        $body=trim(html_entity_decode(wp_strip_all_tags(preg_replace('~</(?:p|h[1-6]|li)>|<br\s*/?>~i',"\n",$html)),ENT_QUOTES,'UTF-8'));
        $paragraphs=preg_split('/\R+/u',$body);
        $normalize=static fn($text)=>preg_replace('/\s+/u',' ',trim(html_entity_decode(wp_strip_all_tags($text),ENT_QUOTES,'UTF-8')));
        if($paragraphs&&$normalize($paragraphs[0])===$normalize($p->post_excerpt))array_shift($paragraphs);
        $body=implode("\n",$paragraphs);
        $media=(int)get_post_meta($id,'_thumbnail_id',true);$imageUrl=$media?wp_get_attachment_image_url($media,'large'):false;
        $image=$imageUrl?['id'=>$media,'url'=>$imageUrl,'alt'=>(string)get_post_meta($media,'_wp_attachment_image_alt',true),'caption'=>(string)wp_get_attachment_caption($media)]:null;
        return ['id'=>$id,'type'=>'wordpress','token'=>$token,'title'=>$p->post_title,'intro'=>$p->post_excerpt,
            'body'=>$body,'notice'=>$notice,'image'=>$image,'isCorrection'=>(bool)$correction,'canAddFacts'=>!$correction,'status'=>$status,'reasons'=>$reasons,'sourceName'=>$correction?'Fotball · Oppdatering av publisert sak':($m['player']?'Fotball · Spillersak':'Fotball · Kampomtale'),
            'sourceUrl'=>'','links'=>array_values($links),'canApprove'=>$m['can_approve'],'canRevise'=>$correction?$m['can_approve']:($m['player']&&$m['pending']),
            'publishedUrl'=>$status==='published'?get_permalink($id):null];
    }
    public static function response(): array {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $items=[];$query=['post_type'=>'post','post_status'=>['draft','pending'],'numberposts'=>150,'orderby'=>'date','order'=>'DESC',
            'meta_query'=>['relation'=>'OR',['key'=>PlayerReview::META,'compare'=>'EXISTS'],['key'=>'_rrfr_ai_match','compare'=>'EXISTS'],['key'=>'_rrfr_trial_match','compare'=>'EXISTS']]];
        $posts=array_merge(get_posts($query),get_posts(array_replace($query,['post_status'=>['publish'],'numberposts'=>30])));
        foreach($posts as $p){if(!current_user_can('edit_post',$p->ID))continue;$card=self::card((int)$p->ID);if($card)$items[]=$card;}
        try{$studioReady=count(ReviewDigest::worker('queue')['items']);}catch(\Throwable $e){$studioReady=null;}
        return ['version'=>self::VERSION,'items'=>$items,'notification'=>ReviewDigest::status(),'studioQueueReady'=>$studioReady];
    }
    public static function decide(array $input): array {
        if(!Robot::allowed()||!current_user_can('publish_posts'))throw new \RuntimeException('Ingen publiseringstilgang.');
        $op=$input['operation']??'';
        if(!in_array($op,['approve','reject','revise'],true)||!is_int($input['id']??null)||!is_string($input['token']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['token']))throw new \RuntimeException('Ugyldig avgjørelse.');
        foreach(['comment','editorial_facts','actor'] as $field)if(isset($input[$field])&&(!is_string($input[$field])||strlen($input[$field])>8000))throw new \RuntimeException('Ugyldig tekst.');
        $correction=class_exists(PlayerCorrections::class)?get_post_meta($input['id'],PlayerCorrections::META,true):null;
        if(is_array($correction)&&($correction['status']??'')==='pending'){
            if(!empty($input['editorial_facts']))throw new \RuntimeException('Nye opplysninger krever et nytt kontrollert forslag.');
            if($op==='approve')PlayerCorrections::approve($input['id'],$input['token']);
            elseif($op==='reject')PlayerCorrections::discard($input['id'],$input['token']);
            else PlayerCorrections::revise($input['id'],$input['token'],$input['comment']??'');
        }else ReviewDesk::decide($input['id'],$input['token'],$op,$input['comment']??'',$input['editorial_facts']??'');
        add_post_meta($input['id'],'_rrfr_studio_decision',['operation'=>$op,'actor'=>sanitize_text_field($input['actor']??'Studio'),'at'=>gmdate(DATE_ATOM),'wordpress_user'=>get_current_user_id()]);
        return self::response();
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/newsroom',[
            ['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn()=>Robot::response(static fn()=>self::response())],
            ['methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn($r)=>Robot::response(static fn()=>self::decide((array)$r->get_json_params()))]
        ]);
    }
}
