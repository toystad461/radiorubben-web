<?php require __DIR__ . '/page-layout-fixture.php'; ?>
<!doctype html><html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Radio Rubben sidemaler — eksempelinnhold</title>
<?php foreach ( array( 'theme','design-v13','member-hub','mobile-shell','components','brand-profile','page-layouts' ) as $style ) : ?><link rel="stylesheet" href="/theme-next/radio-rubben-next/assets/css/<?php echo $style; ?>.css"><?php endforeach; ?>
</head><body class="rr-next"><aside style="padding:1rem;background:#fff;color:#000">LOKAL TESTVISNING · Eksempelinnhold · Ingen aktive tjenester</aside><header class="rr-header"><div class="rr-wrap"><img width="225" src="/theme-next/radio-rubben-next/assets/brand/2026-09-30/SVG/07-Uten-verdilinje-hvit.svg" alt="Radio Rubben"></div></header>
<?php get_template_part( 'templates/' . ( 'standard' === $fixture['layout'] ? 'content' : $fixture['layout'] ) ); ?>
<footer class="rr-footer"><div class="rr-wrap">Radio Rubben</div></footer></body></html>
