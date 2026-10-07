"""Synthetic PHP template layout check with repository CSS; not live WordPress or iOS."""
from pathlib import Path
import json, os, subprocess
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
out = Path(os.environ.get('RR_NEWS_SCREEN_DIR', '/tmp/rr-news-priority-screen'))
out.mkdir(parents=True, exist_ok=True)
html = subprocess.check_output(['php', str(root/'theme-next/tests/news-priority.php'), '--render'], text=True)
results = []
with sync_playwright() as p:
    opts = {'headless': True}
    if os.environ.get('CHROMIUM_BIN'):
        opts['executable_path'] = os.environ['CHROMIUM_BIN']
    browser = p.chromium.launch(**opts)
    try:
        for width in [320, 393, 768, 1280]:
            page = browser.new_page(viewport={'width': width, 'height': 900})
            page.route('**/*', lambda r: r.abort())
            page.set_content(html, wait_until='load')
            for scale in [1, 2]:
                if scale == 2:
                    page.evaluate("""() => {
                      const sizes = [...document.querySelectorAll('body *')].map(e=>[e,parseFloat(getComputedStyle(e).fontSize)]);
                      sizes.forEach(([e,n])=>e.style.fontSize=`${n*2}px`);
                    }""")
                metric = page.evaluate("""() => ({width:innerWidth, scroll:document.documentElement.scrollWidth,
                   news:document.querySelector('#nyheter').getBoundingClientRect().top,
                   sports:document.querySelectorAll('#sport').length,
                   images:document.querySelectorAll('#nyheter img').length})""")
                assert metric['scroll'] <= width, f'Overflow at {width}px/{scale}x: {metric}'
                assert metric['sports'] == 1 and metric['images'] == 1
                results.append({'viewport':width, 'font_scale':scale, 'passed':True, **metric})
                page.screenshot(path=str(out/f'news-{width}-{scale}x.png'), full_page=False)
            page.close()
    finally:
        browser.close()
(out/'results.json').write_text(json.dumps({'method':'synthetic templates with repository CSS; not live WordPress/iPhone', 'cases':results}, indent=2))
print(f'News layout: {len(results)} viewport/font cases passed')
