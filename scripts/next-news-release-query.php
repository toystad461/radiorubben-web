<?php
// Invoked by authenticated WP-CLI with plugins/themes skipped. Read-only.
$ids = static function(array $slugs): array {
    $terms = get_terms(['taxonomy'=>'category','slug'=>$slugs,'hide_empty'=>false]);
    if (is_wp_error($terms)) throw new RuntimeException('Category lookup failed.');
    $result = [];
    foreach ($terms as $term) {
        $children = get_term_children($term->term_id, 'category');
        if (is_wp_error($children)) throw new RuntimeException('Category descendants failed.');
        $result = array_merge($result, [$term->term_id], $children);
    }
    return array_unique(array_map('intval', $result));
};
$newsIds=$ids(['latest-updates','lokale_nyheter']);
$sportIds=$ids(['sport','fotball']);
if (!$newsIds || !$sportIds) throw new RuntimeException('Expected news/sport categories missing.');
foreach ([false,true] as $sport) {
    $tax=['relation'=>'AND',['taxonomy'=>'category','field'=>'slug','terms'=>$sport?['sport','fotball']:['latest-updates','lokale_nyheter'],'include_children'=>true,'operator'=>'IN']];
    if (!$sport) $tax[]=['taxonomy'=>'category','field'=>'slug','terms'=>['sport','fotball'],'include_children'=>true,'operator'=>'NOT IN'];
    $q=new WP_Query(['post_type'=>'post','post_status'=>'publish','has_password'=>false,'posts_per_page'=>3,'ignore_sticky_posts'=>true,'no_found_rows'=>true,'orderby'=>['date'=>'DESC','ID'=>'DESC'],'tax_query'=>$tax]);
    foreach ($q->posts as $post) {
        if ($post->post_password || $post->post_status!=='publish') throw new RuntimeException('Non-public post in query.');
        if ($sport && !has_category($sportIds,$post)) throw new RuntimeException('Non-sport post in sport query.');
        if (!$sport && (!has_category($newsIds,$post) || has_category($sportIds,$post))) throw new RuntimeException('Wrong category in news query.');
    }
    echo ($sport?'SPORT':'NEWS').'_POST_IDS='.implode(',',wp_list_pluck($q->posts,'ID'))."\n";
}
$double = new WP_Query(['post_type'=>'post','post_status'=>'publish','has_password'=>false,'posts_per_page'=>-1,'fields'=>'ids','tax_query'=>['relation'=>'AND',['taxonomy'=>'category','field'=>'term_id','terms'=>$newsIds],['taxonomy'=>'category','field'=>'term_id','terms'=>$sportIds]]]);
echo 'DOUBLE_CATEGORY_EXCLUDED_FROM_NEWS='.implode(',',$double->posts)."\n";
echo "REAL_WORDPRESS_CATEGORY_CHECK=PASS\n";
