<?php defined('ABSPATH') || exit; ?>
<section class="rr-section rr-home-rss" aria-labelledby="rr-home-rss-title">
  <div class="rr-wrap">
    <div class="rr-section-head"><div><p class="rr-eyebrow">LOKALT NÅ</p><h2 id="rr-home-rss-title">Siste fra Bømlo kommune.</h2><p>Aktuelle saker hentet fra kommunens RSS-feed.</p></div><a class="rr-btn-outline" href="<?php echo esc_url(home_url('/aktuelt-og-kunngjoringer-fra-bomlo-kommune/')); ?>">Se alle saker →</a></div>
    <?php if (shortcode_exists('rubben_rss_cards')) echo do_shortcode('[rubben_rss_cards urls="https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk" count="3"]'); ?>
    <p class="rr-home-rss-credit">Foto: <a href="https://commons.wikimedia.org/wiki/File:Mosterhamn.jpg" target="_blank" rel="noopener">Jan-Tore Egge / Wikimedia Commons</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC BY-SA 4.0</a> · beskåret i visning</p>
  </div>
</section>
<style>
.rr-home-rss{position:relative;isolation:isolate;padding-block:48px;background:#15171e}
.rr-home-rss::before{position:absolute;z-index:0;inset:0;content:"";background:url("<?php echo esc_url(wp_get_attachment_url(923)); ?>") center 58% / cover no-repeat;opacity:.2;pointer-events:none}
.rr-home-rss>.rr-wrap{position:relative;z-index:1}
.rr-home-rss-credit{margin:14px 0 0;color:#b9bec9;font-size:11px;text-align:right}
.rr-home-rss-credit a{color:inherit;text-decoration:underline}
.rr-home-rss .rubben-rss-cards__head{display:none}
.rr-home-rss .rubben-rss-cards__list{grid-template-columns:repeat(3,minmax(0,1fr));gap:15px!important}
.rr-home-rss .rubben-rss-cards__item{background:#20232b!important;border:1px solid #393d48!important;padding:20px!important;min-height:130px;color:#eef0f5}
.rr-home-rss .rubben-rss-cards__item a{color:#fff;text-decoration:none}
.rr-home-rss .rubben-rss-cards__item a:hover{text-decoration:underline;text-decoration-color:#f5cb45}
@media(max-width:760px){.rr-home-rss .rubben-rss-cards__list{grid-template-columns:1fr}.rr-home-rss{padding-block:32px}}
</style>