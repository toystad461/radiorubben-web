<?php
/**
 * Title: RR · Felles blokktopp
 * Slug: radio-rubben-next/block-header
 * Categories: radio-rubben
 * Inserter: no
 */
?>
<!-- wp:group {"className":"rr-block-header","layout":{"type":"constrained"}} --><div class="wp-block-group rr-block-header">
<!-- wp:group {"align":"wide","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} --><div class="wp-block-group alignwide">
<!-- wp:image {"width":"210px","sizeSlug":"full","linkDestination":"custom"} --><figure class="wp-block-image size-full is-resized"><a href="<?php echo esc_url( home_url('/') ); ?>"><img src="<?php echo esc_url( rr_brand_logo_url() ); ?>" alt="Radio Rubben – hjem" style="width:210px"/></a></figure><!-- /wp:image -->
<!-- wp:navigation {"overlayMenu":"mobile","ariaLabel":"Hovedmeny","layout":{"type":"flex","justifyContent":"right"}} --><?php echo rr_block_navigation(); ?><!-- /wp:navigation -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->
