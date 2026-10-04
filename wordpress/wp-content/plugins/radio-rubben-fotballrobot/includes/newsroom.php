<?php
namespace RadioRubben\Fotballrobot;

/** Authenticated bridge to the existing approval methods; GET never changes a decision. */
final class Newsroom {
    const VERSION='newsroom-1';
    public static function card(int $id): array {
        $m=ReviewDesk::model($id);$p=$m['post'];$s=$m['state'];$reasons=[];
        if($m['status']==='rejected'||$m['test'])return [];
        $status=$m['status']==='published'?'published':($m['can_approve']?'ready':'attention');
        if($status==='attention'){
            if(!empty($s['error']))$reasons[]=$s['error'];
            if(!$m['quality'])$reasons[]='Fakta- og språkkontrollen må fullføres før publisering.';
            if($m['status']==='publishing')$reasons[]='Publiseringsstatus må avklares.';
        }
        $links=[];
        if(preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',$p->post_content,$matches,PREG_SET_ORDER))foreach($matches as $a){
            $url=html_entity_decode($a[1],ENT_QUOTES,'UTF-8');$parts=parse_url($url);
            if(($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))continue;
            $links[$url]=['url'=>$url,'label'=>trim(wp_strip_all_tags($a[2]))?:$parts['host']];
        }
        $body=trim(html_entity_decode(wp_strip_all_tags(preg_replace('~</(?:p|h[1-6]|li)>|<br\s*/?>~i',"\n",$p->post_content)),ENT_QUOTES,'UTF-8'));
        return ['id'=>$id,'type'=>'wordpress','token'=>ReviewDesk::token($id,$m),'title'=>$p->post_title,'intro'=>$p->post_excerpt,
            'body'=>$body,'status'=>$status,'reasons'=>$reasons,'sourceName'=>$m['player']?'Fotball · Spillersak':'Fotball · Kampomtale',
            'sourceUrl'=>'','links'=>array_values($links),'canApprove'=>$m['can_approve'],'canRevise'=>$m['player']&&$m['pending'],
            'publishedUrl'=>$status==='published'?get_permalink($id):null];
    }
    public static function response(): array {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $items=[];$query=['post_type'=>'post','post_status'=>['draft','pending'],'numberposts'=>150,'orderby'=>'date','order'=>'DESC',
            'meta_query'=>['relation'=>'OR',['key'=>PlayerReview::META,'compare'=>'EXISTS'],['key'=>'_rrfr_ai_match','compare'=>'EXISTS'],['key'=>'_rrfr_trial_match','compare'=>'EXISTS']]];
        $posts=array_merge(get_posts($query),get_posts(array_replace($query,['post_status'=>['publish'],'numberposts'=>30])));
        foreach($posts as $p){if(!current_user_can('edit_post',$p->ID))continue;$card=self::card((int)$p->ID);if($card)$items[]=$card;}
        return ['version'=>self::VERSION,'items'=>$items,'notification'=>ReviewDigest::status()];
    }
    public static function decide(array $input): array {
        if(!Robot::allowed()||!current_user_can('publish_posts'))throw new \RuntimeException('Ingen publiseringstilgang.');
        $op=$input['operation']??'';
        if(!in_array($op,['approve','reject','revise'],true)||!is_int($input['id']??null)||!is_string($input['token']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['token']))throw new \RuntimeException('Ugyldig avgjørelse.');
        foreach(['comment','editorial_facts','actor'] as $field)if(isset($input[$field])&&(!is_string($input[$field])||strlen($input[$field])>8000))throw new \RuntimeException('Ugyldig tekst.');
        ReviewDesk::decide($input['id'],$input['token'],$op,$input['comment']??'',$input['editorial_facts']??'');
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
