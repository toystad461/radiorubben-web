"""Exercise real local WordPress handlers with credentials from migration-seed.php.
Usage: python3 migration-http.py /private/fixtures.json /private/results.json
Never points at a configurable remote host. Fixtures must use reserved match ID.
"""
import json,sys,urllib.request,urllib.error,urllib.parse,http.cookiejar,re
from pathlib import Path
BASE='http://127.0.0.1:8877'
f=json.loads(Path(sys.argv[1]).read_text())
assert f['match']==99999991 and f['rrlive_url'].startswith(BASE+'/')
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
clients={}
for label,user in f['users'].items():
 c=client();request(c,'/wp-login.php')
 status,body,_=request(c,'/wp-login.php',{'log':user['login'],'pwd':user['password'],'wp-submit':'Log In','redirect_to':BASE+'/min-side/','testcookie':'1'})
 check(status==200 and 'rr-member-dashboard' in body,'Local login '+label);clients[label]=c
admin=clients['admin'];member=clients['member']
match='?rr_match='+str(f['match']);api='/dagenskamp/'+match+'&rr_poll_api=1'
status,body,_=request(admin,'/dashboard/'+match)
m=re.search(r'const nonce=(["\'])(.*?)\1;',body);assert m,'Admin nonce missing'
nonce=m.group(2)
check(status==200 and 'rr-speaker' in body,'Admin speaker renders')
status,prototype,_=request(admin,'/dashboard_test/'+match)
check(status==200 and 'rr-dashboard-v1-style' in prototype,'Existing dashboard prototype preserved')
status,body,_=request(member,'/dashboard/'+match);check(status==403,'Member cannot enter speaker')
check(js(anon,api,{'action':'start'})[0]==403,'Anonymous cannot control match')
check(js(member,api,{'action':'start','nonce':nonce})[0]==403,'Member cannot use admin nonce')
check(js(admin,api,{'action':'start','nonce':'bad'})[0]==403,'Admin action requires valid nonce')
def command(action,**extra):return js(admin,api,{'action':action,'nonce':nonce,**extra})
check(command('new')[0]==200,'Create fresh synthetic match session')
check(command('start')[0]==200,'Start first half')
status,state=js(admin,api)
check(state['opened'] and state['period']==1 and state['running'],'Clock and vote open')
check(js(anon,api,{'action':'vote','player':1,'token':state['token']})[0]==403,'Anonymous vote rejected')
check(js(admin,api,{'action':'vote','player':1,'token':'bad'})[0]==403,'Invalid voting token rejected')
check(command('event',type='goal',player=2,side='home')[0]==200,'Speaker records home goal')
check(js(admin,api)[1]['score']['home']==1,'Score reflects recorded goal')
check(command('event',type='sub',player=12,out=2,side='home')[0]==200,'Speaker records substitution')
state=js(admin,api)[1];candidates=state['candidates']
check('2' in candidates and '12' in candidates,'Substituted starter and reserve remain voting candidates')
check(js(admin,api,{'action':'vote','player':2,'token':state['token']})[0]==200,'Eligible administrator test vote accepted')
check(js(admin,api,{'action':'vote','player':12,'token':state['token']})[0]==409,'Duplicate vote rejected')
check(command('half')[0]==200,'Pause at halftime')
check(command('second')[0]==200,'Start second half')
check(command('correct',seconds=5100)[0]==200,'Set synthetic clock to 85 minutes')
check(js(admin,api)[1]['closed'],'Vote closes at 85 minutes')
check(command('correct',seconds=4800)[0]==200,'Clock correction accepted')
check(js(admin,api)[1]['closed'],'Clock correction cannot reopen closed vote')
check(command('finish')[0]==200,'Finish and archive match')
status,body,_=request(anon,'/dagenskamp/'+match)
check(status==200 and 'Test Starter' in body,'Public archived match remains accessible')
check('administrator: arkivert trekning' not in body,'Private draw details absent for public')
status,body,_=request(anon,f['rrlive_url']);check(status==200 and 'Staging United' in body,'Original RRLive permalink route renders')
status,body,_=request(member,'/min-side/')
check('rr-member-dashboard' in body and 'rr_member_delete' in body,'Member dashboard, profile and deletion controls retained')
# Quiz uses the existing AJAX handler, nonce and revision, on a synthetic account.
q='/wp-admin/admin-ajax.php'
check(js(anon,q,{'action':'rrwq','op':'start'})[0]==401,'Quiz start requires login')
status,state=js(member,q,{'action':'rrwq','op':'state'});d=state['data']
check(status==200 and d['ready'] and 'questions' not in d,'Quiz ready without disclosing answers before start')
common={'action':'rrwq','nonce':d['nonce'],'week':d['week'],'revision':d['revision']}
check(js(member,q,{**common,'op':'start','nonce':'bad'})[0]==403,'Quiz rejects invalid nonce')
status,started=js(member,q,{**common,'op':'start','public':'0'})
check(status==200 and len(started['data'].get('questions',[]))==20,'Member starts 20-question quiz')
check(all('correct' not in x for x in started['data']['questions']),'Quiz answer key not sent during play')
status,result=js(member,q,{**common,'op':'submit','answers':json.dumps(f['answers'])})
check(status==200 and result['data']['result']['score']==20,'Quiz submission is scored and saved')
status,again=js(member,q,{**common,'op':'submit','answers':json.dumps([1]*20)})
check(again['data']['result']['score']==20,'Repeated quiz submission cannot overwrite result')
# Delete only the separately created disposable account using its actual account form.
dc=clients['delete'];status,body,_=request(dc,'/min-side/')
m=re.search(r'name="rr_delete_nonce" value="([^"]+)"',body);assert m,'Self-delete nonce missing'
status,_,_=request(dc,'/wp-admin/admin-post.php',{'action':'rr_member_delete','rr_delete_nonce':m.group(1),'rr_delete_confirm':'yes','rr_delete_word':'WRONG'})
check(status==400,'Account deletion requires typed confirmation')
status,body,_=request(dc,'/wp-admin/admin-post.php',{'action':'rr_member_delete','rr_delete_nonce':m.group(1),'rr_delete_confirm':'yes','rr_delete_word':'SLETT'})
check(status==200 and 'rr_member_delete' not in body,'Synthetic member self-deletion completes')
Path(sys.argv[2]).write_text(json.dumps({'checks':checks,'passed':sum(x['pass'] for x in checks),'total':len(checks)},indent=2))
print(str(sum(x['pass'] for x in checks))+'/'+str(len(checks))+' HTTP behavior checks passed.')
sys.exit(0 if all(x['pass'] for x in checks) else 1)
