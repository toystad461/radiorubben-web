"""Exercise real local WordPress handlers with credentials from migration-seed.php.
Usage: python3 migration-http.py /private/fixtures.json /private/results.json
Never points at a configurable remote host. Fixtures must use reserved match ID.
"""
import json,sys,urllib.request,urllib.error,urllib.parse,http.cookiejar,re
from pathlib import Path
BASE='http://127.0.0.1:8877'
f=json.loads(Path(sys.argv[1]).read_text())
assert f['match']==99999992 and f['url']==BASE+'/fotball-pilot/'
checks=[]
def check(ok,label):
 checks.append({'check':label,'pass':bool(ok)})
 if not ok:print('FAIL:',label)
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
anon=client()
def request(c,path,data=None):
 url=path if path.startswith(BASE) else BASE+path
 assert urllib.parse.urlparse(url).netloc=='127.0.0.1:8877'
 req=urllib.request.Request(url,urllib.parse.urlencode(data).encode() if data is not None else None)
 try:r=c.open(req,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read().decode(),dict(r.headers)
def js(c,path,data=None):
 status,body,headers=request(c,path,data)
 try:payload=json.loads(body)
 except ValueError:raise RuntimeError('Expected JSON: '+path+' status '+str(status))
 return status,payload

pilot='/fotball-pilot/'
status,body,headers=request(anon,pilot)
check(status==200 and 'Staging United' in body,'Pilot contains synthetic match')
check(len(re.findall(r'<h1(?: |>)',body))==1,'One page heading')
check(len(re.findall(r'<main(?: |>)',body))==1,'One main landmark')
check(body.count('id="poll-live"')==1,'One voting module')
check('[rr_' not in body,'No unresolved shortcodes')
check('no-store' in headers.get('Cache-Control','') and 'Cookie' in headers.get('Vary',''),'Personalized view cannot be cached')
status,abuse,_=request(anon,pilot+'?rr_poll_control=1&rr_poll_api=1')
check(status==200 and '<!doctype' in abuse.lower() and 'id="poll-live"' in abuse and 'id="speaker-nff-status"' not in abuse,'Query cannot turn content into API or speakerboard')
status,posted,_=request(anon,pilot,{'action':'start'})
check('id="poll-live"' not in posted,'POST to content page does not execute embedded motor')
clients={}
for label,user in f['users'].items():
 c=client();request(c,'/wp-login.php')
 status,html,_=request(c,'/wp-login.php',{'log':user['login'],'pwd':user['password'],'wp-submit':'Log In','redirect_to':BASE+pilot,'testcookie':'1'})
 status,html,_=request(c,pilot)
 check(status==200 and 'id="poll-live"' in html,'Local login '+label);clients[label]=c
admin=clients['admin'];member=clients['member']
api='/dagenskamp/?rr_match='+str(f['match'])+'&rr_poll_api=1'
status,html,_=request(admin,'/dashboard/?rr_match='+str(f['match']))
m=re.search(r'const nonce=(["\'])(.*?)\1;',html);assert m,'Admin nonce missing'
nonce=m.group(2)
check(js(anon,api,{'action':'start'})[0]==403,'Anonymous control rejected')
check(js(member,api,{'action':'start','nonce':nonce})[0]==403,'Member control rejected')
check(js(admin,api,{'action':'start','nonce':'bad'})[0]==403,'Invalid admin nonce rejected')
def command(action,**extra):return js(admin,api,{'action':action,'nonce':nonce,**extra})
check(command('start')[0]==200,'Synthetic match starts')
state=js(admin,api)[1]
check(state.get('authenticated') is True,'Latest session payload retained')
check(js(anon,api,{'action':'vote','player':1,'token':state['token']})[0]==403,'Anonymous vote rejected')
check(js(admin,api,{'action':'vote','player':1,'token':'bad'})[0]==403,'Invalid vote token rejected')
check(js(admin,api,{'action':'vote','player':2,'token':state['token']})[0]==200,'Synthetic admin vote accepted')
check(js(admin,api,{'action':'vote','player':1,'token':state['token']})[0]==409,'Duplicate vote rejected')
check(command('half')[0]==200,'Halftime retained')
check(command('second')[0]==200,'Second half retained')
check(command('correct',seconds=5100)[0]==200,'Synthetic 85 minute clock')
check(js(admin,api)[1]['closed'],'Voting closes at 85 minutes')
check(command('correct',seconds=4800)[0]==200 and js(admin,api)[1]['closed'],'Clock correction cannot reopen vote')
status,html,_=request(admin,pilot)
check('id="poll-live"' in html and 'id="speaker-nff-status"' not in html,'Logged-in pilot stays public view')
Path(sys.argv[2]).write_text(json.dumps({'checks':checks,'passed':sum(x['pass'] for x in checks),'total':len(checks)},indent=2))
print(str(sum(x['pass'] for x in checks))+'/'+str(len(checks))+' pilot HTTP checks passed.')
sys.exit(0 if all(x['pass'] for x in checks) else 1)
