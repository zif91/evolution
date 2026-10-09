#!/usr/bin/env python3
"""Exercise real manager processors, cookies, CSRF and restricted role over HTTP."""
from pathlib import Path
from html.parser import HTMLParser
import http.cookiejar, json, secrets, socket, subprocess, time, urllib.request, urllib.parse
root=Path(__file__).resolve().parents[1]
class Inputs(HTMLParser):
    def __init__(self,html):
        super().__init__(); self.values={}; self.feed(html)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='input' and a.get('name'): self.values[a['name']]=a.get('value','')
def fixture(command='state', data=None):
    r=subprocess.run(['php','tests/manager-fixture.php',command],cwd=root,input=json.dumps(data or {}),text=True,capture_output=True)
    marker=[line[13:] for line in r.stdout.splitlines() if line.startswith('FIXTURE_JSON ')]
    if r.returncode or not marker: raise RuntimeError('Fixture failed: '+r.stdout+r.stderr)
    return json.loads(marker[-1])
results=[]
def check(name,condition):
    if not condition: raise AssertionError(name)
    results.append({'test':name,'status':'PASS'});print('PASS',name)
with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
base=f'http://127.0.0.1:{port}'
class Redirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        with open(root/'reports/manager-redirects.log','a') as out: out.write(req.full_url+' -> '+newurl+'\n')
        return super().redirect_request(req,fp,code,msg,headers,newurl)
class Client:
    def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),Redirects())
    def request(self,path,data=None):
        request=urllib.request.Request(base+path, None if data is None else urllib.parse.urlencode(data).encode(),headers={'Referer':base+'/manager/','User-Agent':'Mozilla/5.0 EvoCompatibilityTest','Accept-Language':'en-US,en;q=0.9'})
        try:
            with self.opener.open(request,timeout=20) as r: return r.geturl(),r.read().decode()
        except urllib.error.HTTPError as e:
            (root/'reports/manager-http-error.html').write_bytes(e.read());raise
    def login(self,user,password):
        _,html=self.request('/manager/');token=Inputs(html).values['_token']
        _,login_html=self.request('/manager/?a=0',{'_token':token,'username':user,'password':password,'ajax':'1'})
        _,html=self.request('/manager/?a=4');assert 'name="pagetitle"' in html, 'Login failed: '+login_html[:1200]+' / '+html[-1200:]
    def save(self,title, id=0, form_id=None, **changes):
        form_id = id if form_id is None else form_id
        _,html=self.request(f'/manager/?a={27 if form_id else 4}&id={form_id}')
        token=Inputs(html).values['_token']
        data=dict(a=5,id=id,mode=27 if id else 4,_token=token,pagetitle=title,introtext='',ta='HTTP processor verified',longtitle='',type='document',description='',alias='',link_attributes='',isfolder=0,richtext=0,published=1,parent=0,template=0,menuindex=0,searchable=1,cacheable=1,pub_date='',unpub_date='',contentType='text/html',content_dispo=0,hide_from_tree=0,menutitle='',hidemenu=0,alias_visible=1,refresh_preview=0,stay=2,syncsite=1,dir='',sort='createdon',page='')
        data.update(changes);return self.request('/manager/index.php',data)
log=open(root/'reports/manager-http-server.log','w')
server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','tools/dev-router.php'],cwd=root,stdout=log,stderr=log)
initialized=False
try:
    for _ in range(50):
        try: Client().request('/');break
        except (OSError,urllib.error.URLError): time.sleep(.1)
    password=secrets.token_urlsafe(22);f=fixture('init',{'password':password});initialized=True
    admin=Client();credentials=json.loads((root/'.local/credentials.json').read_text());admin.login(credentials['admin'],credentials['admin_password'])
    url,html=admin.save('PR543 HTTP resource',template=f['template'],**{'tv'+str(f['tv']):'private-value'})
    docid=int(urllib.parse.parse_qs(urllib.parse.urlparse(url).query)['id'][0])
    def state(id=docid): return next(d for d in fixture() if d['id']==id)
    check('Manager creates published resource and TV',state()['published']==1 and state()['tvs'][str(f['tv'])]=='private-value')
    admin.save('PR543 HTTP edited',id=docid,stay=0,template=f['template'],**{'tv'+str(f['tv']):'new-value'})
    check('Manager edits resource and TV',state()['pagetitle']=='PR543 HTTP edited' and state()['tvs'][str(f['tv'])]=='new-value')
    public=Client()
    url,html=public.request(f'/index.php?id={docid}')
    check('Numeric URL redirects to the correct friendly resource using template A',
          'data-test-template="A"' in html and 'PR543 HTTP edited: HTTP processor verified' in html and
          'compatibility lab' not in html and '/manager/' not in url)
    friendly_path=urllib.parse.urlparse(url).path
    _,html=public.request(friendly_path)
    check('Direct friendly URL renders resource template with warm cache', 'data-test-template="A"' in html)
    admin.save('PR543 HTTP edited',id=docid,stay=0,template=f['template_b'])
    _,html=public.request(friendly_path)
    check('Switch to template B changes persisted template and public HTML',
          state()['template']==f['template_b'] and 'data-test-template="B"' in html and 'data-test-template="A"' not in html)
    admin.save('PR543 HTTP edited',id=docid,stay=0,template=0)
    _,html=public.request(friendly_path)
    check('Blank template renders only resource content', html.strip()=='HTTP processor verified' and state()['template']==0)
    admin.save('PR543 HTTP edited',id=docid,stay=0,template=f['template'],**{'tv'+str(f['tv']):'new-value'})
    _,html=public.request(f'/index.php?q={urllib.parse.quote(friendly_path.lstrip("/"))}')
    check('Explicit q query preserves alias routing', 'data-test-template="A"' in html)
    try:
        public.request('/pr543-http-no-such-resource.html')
        raise AssertionError('Unknown friendly URL returned success')
    except urllib.error.HTTPError as error:
        check('Unknown friendly URL returns HTTP 404', error.code==404)
    editor=Client();editor.login('pr543_http_editor',password)
    _,html=editor.request(f'/manager/?a=27&id={docid}')
    check('Restricted TV is absent from editor form',f'name="tv{f["tv"]}"' not in html)
    editor.save('PR543 HTTP edited',id=docid,stay=0,template=f['template'],**{'tv'+str(f['tv']):'forged-value'})
    check('Forged restricted TV POST cannot alter saved value',state()['tvs'][str(f['tv'])]=='new-value')
    _,html=editor.save('PR543 HTTP forbidden edit',id=f['parent'],form_id=0)
    check('Restricted resource rejects forged edit POST',state(f['parent'])['pagetitle']=='PR543 HTTP parent' and '__alertQuit' in html)
    before=len(fixture());_,html=editor.save('PR543 HTTP forbidden child',parent=f['parent'])
    check('Restricted parent rejects forged create POST',len(fixture())==before and '__alertQuit' in html)
    _,html=editor.request(f'/manager/?a=62&id={docid}')
    check('Role without publish permission cannot unpublish',state()['published']==1 and '__alertQuit' in html)
    admin.request(f'/manager/?a=62&id={docid}');check('Manager unpublishes resource',state()['published']==0)
    admin.request(f'/manager/?a=61&id={docid}');check('Manager publishes resource',state()['published']==1)
    admin.request(f'/manager/?a=94&id={docid}');check('Manager duplicates resource',len(fixture())==before+1)
    admin.request(f'/manager/?a=6&id={docid}');check('Manager soft deletes resource',state()['deleted']==1)
    admin.request(f'/manager/?a=63&id={docid}');check('Manager restores resource',state()['deleted']==0)
    url,html=admin.save('PR543 HTTP preview',id=docid,refresh_preview=1)
    (root/'reports/manager-preview.html').write_text(url+'\n'+html)
    check('Save and preview redirects to actual site URL','{MODX_SITE_URL}' not in url and '/manager/' not in url and 'HTTP processor verified' in html)
    _,html=Client().request(f'/manager/?a=6&id={docid}')
    check('Guest cannot invoke destructive processor','name="password"' in html and state()['deleted']==0)
    print(f'MANAGER_HTTP_COMPLETE {len(results)} checks, 0 failed')
finally:
    if initialized: fixture('cleanup')
    server.terminate();server.wait(timeout=10);log.close()
    (root/'reports/manager-http.json').write_text(json.dumps(results,indent=2))
