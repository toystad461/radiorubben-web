<?php defined('ABSPATH') || exit; ?>
<?php get_header(); ?>
<?php while(have_posts()): the_post(); ?>
<?php if (is_page(736)): ?><style id="rr-member-compact-live">
/* Compact Min Rubben: the main actions come first, with quieter supporting cards. */
.page-id-736 .rr-subhero{padding:20px 0 15px}
.page-id-736 .rr-subhero h1{font-size:clamp(25px,3.2vw,32px)}
.page-id-736 .rr-section{padding:18px 0 38px}
.page-id-736 .rr-page-content{max-width:1040px}
.page-id-736 .rr-content h2{line-height:1.22}
.page-id-736 .rr-member-dashboard:not(.rr-member-support){padding:22px;margin-bottom:18px}
.page-id-736 .rr-member-dashboard:not(.rr-member-support) h2{font-size:clamp(23px,3vw,29px);margin:5px 0}
.page-id-736 .rr-member-dashboard:not(.rr-member-support)>p{font-size:14px;margin:4px 0 10px}
.page-id-736 .rr-member-shortcuts{margin:14px 0 16px;gap:10px}
.page-id-736 .rr-member-shortcuts a:first-child{background:#e43843;border-color:#e43843;font-weight:750}
.page-id-736 .rr-member-shortcuts a{padding:10px 12px;min-height:46px}
.page-id-736 .rr-member-grid{grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:12px;margin-top:12px}
.page-id-736 .rr-member-grid article{padding:17px}
.page-id-736 .rr-member-quiz .rr-member-score{font-size:38px}
.page-id-736 .rr-member-badges{padding:0!important}
.page-id-736 .rr-member-badges>summary{padding:17px;min-height:48px;font-size:15px}
.page-id-736 .rr-member-badges>ul,.page-id-736 .rr-member-badges>p{margin-left:17px;margin-right:17px}
.page-id-736 .rr-quiz-challenge{margin:18px 0}
.page-id-736 .rr-quiz-challenge h2{font-size:clamp(19px,2.4vw,24px)}
.page-id-736 .rr-vipps-entry{padding:22px!important;margin:0 0 18px}
.page-id-736 .rr-vipps-entry h2{font-size:clamp(21px,2.5vw,26px);margin:0 0 8px}
.page-id-736 .rr-vipps-entry p{font-size:14px;line-height:1.55}
.page-id-736 .rr-member-support{margin-top:18px;padding:20px}
.page-id-736 .rr-member-support h2{font-size:21px}
@media(max-width:700px){
.page-id-736 .rr-member-grid{grid-template-columns:1fr}
.page-id-736 .rr-member-dashboard:not(.rr-member-support){padding:17px}
.page-id-736 .rr-member-shortcuts{grid-template-columns:1fr}
.page-id-736 .rr-vipps-entry{padding:18px!important}
}

</style><?php endif; ?>
<section class="rr-subhero rr-generic-hero"><div class="rr-wrap"><p class="rr-eyebrow">RADIO RUBBEN</p><h1><?php if (is_page(736)) echo 'Min Rubben'; else the_title(); ?></h1><?php if(has_excerpt()): ?><p><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?></div></section>
<section class="rr-section rr-wrap"><div class="rr-content rr-page-content"><?php if (is_page(736)) rr_member_dashboard(); ?><?php the_content(); ?><?php if (is_page(736)) { rr_site_template('member-profile'); rr_site_template('member-support'); rr_site_template('member-account-actions'); } ?></div></section>
<?php endwhile; ?>
<?php get_footer(); ?>
