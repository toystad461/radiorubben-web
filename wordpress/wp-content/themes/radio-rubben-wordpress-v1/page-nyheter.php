<?php get_header(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">AKTUELT</p><h1>Fra Radio Rubben.</h1><p>Artikler, ledertekster og lokale saker fra Radio Rubben.</p></div></section>
<section class="rr-section rr-wrap">
<?php
$paged = max(1, get_query_var('paged'));
$q = new WP_Query(['post_type'=>'post','post_status'=>'publish','paged'=>$paged]);
if($q->have_posts()): ?><div class="rr-post-grid"><?php while($q->have_posts()): $q->the_post(); ?>
<article class="rr-post-card">
<?php if(has_post_thumbnail()): ?><a class="rr-post-thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium_large', ['style'=>'display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:contain;object-position:center;background:#08131f;']); ?></a><?php endif; ?>
<?php rr_one_post_meta(); ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="rr-card-excerpt"><?php the_excerpt(); ?></div><a class="rr-text-link" href="<?php the_permalink(); ?>">Les videre →</a>
</article>
<?php endwhile; ?></div><?php
$GLOBALS['wp_query']->max_num_pages = $q->max_num_pages; rr_one_pagination(); wp_reset_postdata();
else: ?><div class="rr-empty"><h2>Ingen innlegg ennå.</h2><p>Her kommer artikler og oppdateringer fra Radio Rubben.</p></div><?php endif; ?>
</section>
<?php get_footer(); ?>
