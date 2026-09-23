<?php get_header(); ?>
<?php while(have_posts()): the_post(); ?>
<style>
.rr-single-article .rr-article-content > p{margin:0 0 1.4em;line-height:1.75}
.rr-single-article .rr-article-content > h2{margin:2em 0 .7em;line-height:1.3}
.rr-single-article .rr-article-content > p:has(> small){line-height:1.5}
.rr-single-article:has(.rr-single-image figcaption) .rr-article-content > p:has(> small > a[href="https://commons.wikimedia.org/wiki/File:Heidi_Halbmayr.jpg"]){display:none}
.rr-single-article .rr-related-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:22px}
.rr-single-article .rr-related-grid .rr-post-card{display:grid;grid-template-columns:88px minmax(0,1fr);grid-template-rows:auto auto;gap:6px 14px;padding:14px;border:1px solid #343740;border-radius:12px;background:#17191f;align-content:center;min-width:0}
.rr-single-article .rr-related-grid .rr-post-thumb{grid-column:1;grid-row:1/3;display:block;width:88px;height:72px;margin:0;align-self:center;aspect-ratio:auto;overflow:hidden}
.rr-single-article .rr-related-grid .rr-post-thumb img{display:block;width:88px;height:72px;object-fit:contain;object-position:center;border-radius:8px}
.rr-single-article .rr-related-grid h3{grid-column:2;grid-row:1;margin:0;font-size:1rem;line-height:1.4;overflow-wrap:anywhere}
.rr-single-article .rr-related-grid time{grid-column:2;grid-row:2;font-size:.78rem;color:#b7bac3}
.rr-single-article .rr-related-grid .rr-post-card:not(:has(.rr-post-thumb)){grid-template-columns:minmax(0,1fr)}
.rr-single-article .rr-related-grid .rr-post-card:not(:has(.rr-post-thumb)) h3,.rr-single-article .rr-related-grid .rr-post-card:not(:has(.rr-post-thumb)) time{grid-column:1}
@media(max-width:650px){.rr-single-article .rr-related-grid{grid-template-columns:1fr}.rr-single-article .rr-related-grid .rr-post-card{padding:12px}}
</style>
<article class="rr-single-article">
<section class="rr-subhero rr-article-hero"><div class="rr-wrap"><p class="rr-eyebrow"><?php $cats=get_the_category(); echo esc_html($cats ? $cats[0]->name : 'FRA RADIO RUBBEN'); ?></p><h1><?php the_title(); ?></h1><?php rr_one_post_meta(); ?><?php get_template_part('template-parts/article-share'); ?></div></section>
<?php if(has_post_thumbnail()): ?><div class="rr-wrap rr-single-image"><figure style="margin:0;"><?php the_post_thumbnail('full', ['style' => 'display:block;width:auto;max-width:100%;height:auto;max-height:650px;object-fit:contain;margin:0 auto;']); ?><?php $rr_caption = wp_get_attachment_caption(get_post_thumbnail_id()); if ($rr_caption): ?><figcaption style="font-size:13px;line-height:1.5;color:#b7bac3;max-width:900px;margin:10px auto 0;"><?php echo wp_kses_post($rr_caption); ?></figcaption><?php endif; ?></figure></div><?php endif; ?>
<section class="rr-section rr-wrap"><div class="rr-content rr-article-content"><?php the_content(); ?><?php wp_link_pages(['before'=>'<div class="rr-page-links">Sider: ','after'=>'</div>']); ?></div>
<?php $tags=get_the_tags(); if($tags): ?><div class="rr-tags"><strong>Emner:</strong><?php foreach($tags as $tag): ?><a href="<?php echo esc_url(get_tag_link($tag)); ?>"><?php echo esc_html($tag->name); ?></a><?php endforeach; ?></div><?php endif; ?>
<nav class="rr-post-nav" aria-label="Innleggsnavigasjon"><div><?php previous_post_link('%link','← %title'); ?></div><div><?php next_post_link('%link','%title →'); ?></div></nav>
</section>
<?php
$related_args=['post_type'=>'post','posts_per_page'=>3,'post__not_in'=>[get_the_ID()],'ignore_sticky_posts'=>true];
if(!empty($cats)) $related_args['category__in']=wp_list_pluck($cats,'term_id');
$related=new WP_Query($related_args);
if($related->have_posts()): ?>
<section class="rr-section rr-alt"><div class="rr-wrap"><p class="rr-eyebrow">LES OGSÅ</p><h2>Mer fra Radio Rubben</h2><div class="rr-post-grid rr-related-grid"><?php while($related->have_posts()):$related->the_post();?><article class="rr-post-card"><?php if(has_post_thumbnail()):?><a class="rr-post-thumb" href="<?php the_permalink();?>"><?php the_post_thumbnail('medium_large');?></a><?php endif;?><h3><a href="<?php the_permalink();?>"><?php the_title();?></a></h3><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></article><?php endwhile;?></div></div></section>
<?php wp_reset_postdata(); endif; ?>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
