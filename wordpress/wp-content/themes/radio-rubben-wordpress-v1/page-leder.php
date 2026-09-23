<?php get_header(); $cat = get_category_by_slug('leder'); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">LEDER</p><h1>Tanker med lokal nerve.</h1><p>Personlige ledertekster og refleksjoner fra Radio Rubben.</p></div></section>
<section class="rr-section rr-wrap">
<?php
$paged=max(1,get_query_var('paged'));
$args=['post_type'=>'post','post_status'=>'publish','paged'=>$paged]; if($cat) $args['cat']=$cat->term_id;
$q=new WP_Query($args);
if($q->have_posts()): ?><div class="rr-post-grid"><?php while($q->have_posts()):$q->the_post(); ?><article class="rr-post-card"><?php if(has_post_thumbnail()):?><a class="rr-post-thumb" href="<?php the_permalink();?>"><?php the_post_thumbnail('rr-card');?></a><?php endif;?><?php rr_one_post_meta();?><h2><a href="<?php the_permalink();?>"><?php the_title();?></a></h2><?php the_excerpt();?><a class="rr-text-link" href="<?php the_permalink();?>">Les videre →</a></article><?php endwhile;?></div><?php $GLOBALS['wp_query']->max_num_pages=$q->max_num_pages; rr_one_pagination(); wp_reset_postdata(); else:?><p>Ingen lederartikler funnet.</p><?php endif;?>
</section><?php get_footer(); ?>
