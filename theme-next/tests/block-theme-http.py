"""Read-only smoke checks of the isolated local WordPress fixture pages."""
import urllib.request,re,subprocess,tempfile,os,json
base='http://127.0.0.1:8877'
for kind in ['content','application','editorial','football']:
    with urllib.request.urlopen(base+'/rr-block-preview-'+kind+'/',timeout=30) as r:
        s=r.read().decode(); headers=r.headers
    assert len(re.findall(r'<main(?: |>)',s))==1,kind+' main'
    assert len(re.findall(r'<h1(?: |>)',s))==1,kind+' title'
    assert '[rr_' not in s,kind+' unresolved shortcode'
    assert 'rr-block-main--'+('standard' if kind=='content' else kind) in s,kind+' native template'
    footer=re.search(r'<footer\b.*?</footer>',s,re.S).group()
    assert 'Ingen valg' not in footer
    assert s.count('id="rr-audio"')==1,kind+' single audio'
    if kind=='football':
        assert s.count('id="poll-live"')==1,'voting module missing or duplicated'
        for attrs,code in re.findall(r'<script\b([^>]*)>(.*?)</script>',s,re.S):
            if not code.strip(): continue
            if 'importmap' in attrs or 'application/ld+json' in attrs:
                json.loads(code); continue
            if 'application/' in attrs: continue
            with tempfile.NamedTemporaryFile(suffix='.mjs' if 'module' in attrs else '.js',mode='w') as f:
                f.write(code); f.flush()
                subprocess.run([os.environ.get('RR_NODE','node'),'--check',f.name],check=True,capture_output=True)
        assert 'no-store' in headers.get('Cache-Control','') and 'Cookie' in headers.get('Vary',''),'personalized cache policy'
    print('PASS:',kind)
with urllib.request.urlopen(base+'/',timeout=30) as r:s=r.read().decode()
assert 'rr-block-main--home' in s and s.count('id="rr-audio"')==1
nav=re.search(r'<nav\b[^>]*aria-label="Hovedmeny".*?</nav>',s,re.S).group()
assert 'wp-block-navigation-item' in nav and 'wp-block-page-list' not in nav,'navigation must not expose all pages'
print('PASS: native homepage shell and controlled navigation')
