<?php
// WP core APIs only; a private, reversible page/menu change. Never touches articles.
function rrSportNavContent(string $content,array $plan): string {
    if(hash('sha256',$content)!==$plan['page_content_sha256'])throw new RuntimeException('Sport page changed since approval');
    [$start,$end]=$plan['replace_block_between'];
    if(substr_count($content,$start)!==1||substr_count($content,$end)!==1)throw new RuntimeException('Ambiguous weekly block');
    $a=strpos($content,$start);$b=strpos($content,$end)+strlen($end);
    if($b<=$a||hash('sha256',substr($content,$a,$b-$a))!==$plan['legacy_weekly_block_sha256'])throw new RuntimeException('Legacy block changed');
    $new=substr($content,0,$a).$plan['replacement'].substr($content,$b);
    if(substr_count($new,$plan['shortcut']['old'])!==1)throw new RuntimeException('Shortcut changed');
    return str_replace($plan['shortcut']['old'],$plan['shortcut']['new'],$new);
}
function rrSportMenu(int $id):array {
    $items=wp_get_nav_menu_items($id,['post_status'=>'any']);
    if($items===false)throw new RuntimeException('Menu missing');
    return array_map(static fn($p)=>['ID'=>(int)$p->ID,'title'=>$p->title,'url'=>$p->url,'type'=>$p->type,'object'=>$p->object,'object_id'=>(int)$p->object_id,'parent'=>(int)$p->menu_item_parent,'order'=>(int)$p->menu_order,'status'=>$p->post_status,'classes'=>$p->classes,'target'=>$p->target,'xfn'=>$p->xfn],$items);
}
function rrSportNavSave(string $file,array $state):void {
    $bytes=json_encode($state,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    $tmp=tempnam(dirname($file),'.nav-');
    if($tmp===false||file_put_contents($tmp,$bytes)!==strlen($bytes)||!chmod($tmp,0600)||!rename($tmp,$file))throw new RuntimeException('Private navigation receipt failed');
}
function rrSportNavigation(array $plan,string $file,string $action):array {
    $id=(int)$plan['page_id'];$mid=(int)$plan['menu']['id'];
    if((get_nav_menu_locations()[$plan['menu']['location']]??null)!==$mid)throw new RuntimeException('Active menu changed');
    $p=get_post($id);if(!$p||$p->post_type!=='page'||$p->post_status!=='publish')throw new RuntimeException('Sport page missing');
    if(in_array($action,['check','apply'],true)){
        $new=rrSportNavContent($p->post_content,$plan);$menu=rrSportMenu($mid);
        $existing=array_values(array_filter($menu,static fn($i)=>($i['object']==='page'&&$i['object_id']===$id)||untrailingslashit($i['url'])===untrailingslashit(get_permalink($id))));
        if($action==='check')return ['page'=>$id,'menu'=>$mid,'existing_sport_items'=>count($existing),'before_hash'=>hash('sha256',$p->post_content),'after_hash'=>hash('sha256',$new)];
        if(file_exists($file))throw new RuntimeException('Navigation backup already exists');
        $s=['page_before'=>(array)$p,'page_meta_before'=>get_post_meta($id),'menu_before'=>$menu,'after_hash'=>hash('sha256',$new),'created_menu_id'=>null,'page_written'=>false];
        rrSportNavSave($file,$s);
        if(get_post($id)->post_content!==$p->post_content||rrSportMenu($mid)!==$menu)throw new RuntimeException('Concurrent navigation edit');
        $r=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$new]),true);
        if(is_wp_error($r)||get_post_field('post_content',$id)!==$new)throw new RuntimeException('Sport page update not confirmed');
        $s['page_written']=true;rrSportNavSave($file,$s);
        if(!$existing){
            $r=wp_update_nav_menu_item($mid,0,['menu-item-title'=>$plan['menu']['title'],'menu-item-object'=>'page','menu-item-object-id'=>$id,'menu-item-type'=>'post_type','menu-item-status'=>'publish']);
            if(is_wp_error($r))throw new RuntimeException($r->get_error_message());
            $s['created_menu_id']=(int)$r;$s['created_menu_record']=array_values(array_filter(rrSportMenu($mid),static fn($i)=>$i['ID']===(int)$r))[0]??null;
            rrSportNavSave($file,$s);
        }
        $s['menu_after']=rrSportMenu($mid);rrSportNavSave($file,$s);
        return rrSportNavigation($plan,$file,'after');
    }
    $s=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    if($action==='rollback'){
        $current=hash('sha256',$p->post_content);
        if(!in_array($current,[$plan['page_content_sha256'],$s['after_hash']],true))throw new RuntimeException('New Sport content blocks rollback');
        $menu=rrSportMenu($mid);$created=$s['created_menu_id'];
        $others=array_values(array_filter($menu,static fn($i)=>$i['ID']!==$created));
        if($others!==$s['menu_before'])throw new RuntimeException('New menu changes block rollback');
        $item=array_values(array_filter($menu,static fn($i)=>$i['ID']===$created));
        if($item&&$item[0]!==($s['created_menu_record']??null))throw new RuntimeException('Changed Sport item blocks rollback');
        if($current!==$plan['page_content_sha256']){
            $r=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$s['page_before']['post_content']]),true);
            if(is_wp_error($r)||hash('sha256',get_post_field('post_content',$id))!==$plan['page_content_sha256'])throw new RuntimeException('Sport restore failed');
        }
        if($item&&!wp_delete_post($created,true))throw new RuntimeException('Owned menu item restore failed');
        return ['restored'=>true,'page'=>$id];
    }
    if($action!=='after'||hash('sha256',$p->post_content)!==$s['after_hash']||rrSportMenu($mid)!==$s['menu_after'])throw new RuntimeException('Navigation aftercheck failed');
    // Nothing else on the page may change, aside from normal WordPress modification times.
    foreach(['post_title','post_status','post_name','post_excerpt','post_parent','post_author']as$key)if($p->$key!=$s['page_before'][$key])throw new RuntimeException('Unexpected page field change: '.$key);
    if(get_post_meta($id)!==$s['page_meta_before'])throw new RuntimeException('Unexpected page metadata change');
    return ['page'=>$id,'menu'=>$mid,'created_menu_item'=>$s['created_menu_id'],'content_sha256'=>$s['after_hash'],'verified'=>true];
}
if(defined('WP_CLI')&&WP_CLI&&getenv('RR_SPORT_NAV_ACTION')){
    if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
    $stage=getenv('RR_SPORT_STAGE');$backup=getenv('RR_SPORT_BACKUP');
    $plan=json_decode(file_get_contents($stage.'/navigation.json'),true,512,JSON_THROW_ON_ERROR);
    echo json_encode(rrSportNavigation($plan,$backup.'/navigation.json',getenv('RR_SPORT_NAV_ACTION')),JSON_UNESCAPED_SLASHES).PHP_EOL;
}
