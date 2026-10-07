"""Public, unauthenticated verification; no publishing, login or playback."""
import json, os, urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright

out=Path(os.environ.get('RR_PUBLIC_CHECK_DIR','/tmp/rr-public-news-check'))
out.mkdir(parents=True,exist_ok=True)
base='https://www.radiorubben.no/'
report={'url':base,'source_commit':'332e443349a79d225f7b659ee22e798a831ef7df','views':[]}
request=urllib.request.Request(base,headers={'User-Agent':'RadioRubben-Release-Verification/1.0'})
with urllib.request.urlopen(request,timeout=30) as r:
    body=r.read().decode('utf-8')
    report['http_status']=r.status
    report['cache_headers']={k:v for k,v in r.headers.items() if any(x in k.lower() for x in ['cache','age','date'])}
assert 'class="rr-news-first"' in body, 'Public homepage without cache parameter is stale'
assert body.index('id="nyheter"') < body.index('rr-member-invite') < body.index('id="sport"')
report['section_order']='radio, news, member, sport, player-widget'
expected_sport={303:305,770:812,774:812,777:813,788:812,814:819,830:812,983:773,993:812,1029:773,1038:813,1065:773,1073:812,1088:813,1091:813}
with urllib.request.urlopen(base+'wp-json/wp/v2/posts?per_page=100&_fields=id,featured_media,categories',timeout=30) as r:
    posts=json.load(r)
by_id={p['id']:p for p in posts}
assert all(by_id[i]['featured_media']==media for i,media in expected_sport.items()), 'A protected sports image changed'
report['sports_featured_images_unchanged']=len(expected_sport)
with sync_playwright() as pw:
    browser=pw.chromium.launch()
    try:
        for label,width,height,touch in [('mobile-320',320,720,True),('iphone15pro',393,852,True),('landscape',852,393,True),('desktop',1280,900,False)]:
            context=browser.new_context(viewport={'width':width,'height':height},is_mobile=touch,has_touch=touch,device_scale_factor=1)
            page=context.new_page();errors=[]
            page.on('pageerror',lambda e:errors.append(str(e)))
            page.route('**/*',lambda route:route.continue_() if route.request.method in ['GET','HEAD'] else route.abort())
            response=page.goto(base,wait_until='domcontentloaded',timeout=45000)
            assert response and response.status==200
            page.wait_for_function("getComputedStyle(document.querySelector('#nyheter .rr-priority-grid')).display==='grid'",timeout=15000)
            page.wait_for_timeout(750)
            metrics=page.evaluate('''() => {
              const news=document.querySelector('#nyheter'), sport=document.querySelector('#sport');
              return {width:innerWidth, documentWidth:document.documentElement.scrollWidth,
                newsWidth:news.getBoundingClientRect().width,
                newsTop:Math.round(news.getBoundingClientRect().top+scrollY),
                firstHeadlineTop:Math.round(news.querySelector('h3').getBoundingClientRect().top+scrollY),
                newsCount:news.querySelectorAll('article').length,sportCount:sport.querySelectorAll('article').length,
                grid:getComputedStyle(news.querySelector('.rr-priority-grid')).gridTemplateColumns,
                compactRadio:getComputedStyle(document.querySelector('.rr-radio-cover')).display==='none',
                playerWidget:!!document.querySelector('.rrpw-home'),
                titleBeforeImage:news.querySelector('h3').getBoundingClientRect().top<news.querySelector('img').getBoundingClientRect().top,
                newsTitles:[...news.querySelectorAll('h3')].map(x=>x.textContent.trim())};
            }''')
            assert metrics['documentWidth']<=width+1, metrics
            assert metrics['newsCount']==3 and metrics['sportCount']==3, metrics
            assert metrics['compactRadio'] and metrics['playerWidget'] and metrics['titleBeforeImage'], metrics
            page.screenshot(path=str(out/(label+'.png')),full_page=False)
            page.locator('#nyheter').screenshot(path=str(out/(label+'-news.png')))
            report['views'].append({'profile':label,'metrics':metrics,'javascript_errors':errors})
            context.close()
            print('PASS',label,json.dumps(metrics,ensure_ascii=False))
    finally:
        browser.close()
        (out/'public-check.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print('PUBLIC_NEWS_CHECK=PASS; 4 live browser sizes; no physical iPhone or live-audio test.')
