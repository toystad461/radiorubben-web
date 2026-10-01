import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let chromium;
try { ({ chromium } = require('playwright')); }
catch (error) {
  if (!process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES) throw error;
  ({ chromium } = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES + '/playwright'));
}
const base = new URL('../wordpress/wp-content/themes/radio-rubben-wordpress-v1/', import.meta.url);
const js = await readFile(new URL('assets/js/weekly-quiz.js', base), 'utf8');
const css = await readFile(new URL('assets/css/weekly-quiz.css', base), 'utf8');
const questions = Array.from({length:20}, (_, i) => ({q:'Testspørsmål ' + (i + 1), a:['A','B','C','D'], correct:0, why:'Testkilde', url:'https://example.org/kilde'}));
const result = {score:17, seconds:143, public:false, answers:Array(20).fill(0)};
const initial = {
  week:'2026-09-28', label:'28.09.2026', revision:1, now:1790812800, ends:1791151200,
  ready:true, loggedIn:true, verified:true, board:[], nonce:'private-nonce', storageKey:'private-user-key'
};
let browser;
before(async () => { browser = await chromium.launch({headless:true, executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined}); });
after(async () => { await browser?.close(); });

async function fixture(t, {state={}, share='success', clipboard='success', query='', width=390} = {}) {
  const page = await browser.newPage({viewport:{width, height:844}});
  t.after(() => page.close());
  let current = {...initial, ...state};
  const requests = [];
  await page.addInitScript(({share, clipboard}) => {
    window.shared = []; window.copied = [];
    Object.defineProperty(navigator, 'share', {configurable:true, value:share === 'absent' ? undefined : async data => {
      window.shared.push({...data, activated:navigator.userActivation.isActive});
      if (share !== 'success') throw new DOMException('Test sharing rejection', share);
    }});
    Object.defineProperty(navigator, 'clipboard', {configurable:true, value:clipboard === 'absent' ? undefined : {writeText:async text => {
      if (clipboard === 'denied') throw new DOMException('Test clipboard rejection','NotAllowedError');
      window.copied.push(text);
    }}});
  }, {share, clipboard});
  await page.route('https://quiz.test/**', async route => {
    const req = route.request();
    if (req.url().endsWith('/ajax')) {
      const op = new URLSearchParams(req.postData()).get('op'); requests.push(op);
      if (op === 'start') current = {...current, started:initial.now, questions};
      if (op === 'submit') current = {...current, result, review:questions};
      if (op === 'hide') current = {...current, result:{...current.result,public:false}};
      return route.fulfill({json:{success:true, data:current}});
    }
    return route.fulfill({contentType:'text/html; charset=utf-8', body:`<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#090b10;color:white;font:16px Arial}main{max-width:900px;margin:24px auto;padding:0 12px}${css}</style><main><section id="rr-weekly" class="rrq"><h2>Ukens Rubben-quiz</h2><div class="rrq-status" role="status"></div><div class="rrq-play"></div><template id="rrq-login-template"><p>Logg inn med Vipps for å spille.</p><a href="/login">Logg inn</a></template><aside class="rrq-board"></aside></section></main><script>window.rrwqConfig={ajax:'/ajax',login:'/login',quiz:'https://quiz.test/quiz/'};</script><script>${js}</script></html>`});
  });
  await page.goto('https://quiz.test/quiz/' + query);
  await page.locator('.rrq-status').filter({hasText:'Uken fra'}).waitFor();
  return {page, requests};
}
const challenge = page => page.getByRole('button', {name:'Jeg utfordrer deg!', exact:true});

test('guest can share an invitation; sharing never starts a round', async t => {
  const {page, requests} = await fixture(t, {state:{loggedIn:false,verified:false}});
  await challenge(page).click();
  const [data] = await page.evaluate(() => window.shared);
  assert.match(data.text, /Jeg utfordrer deg/);
  assert.doesNotMatch(data.text, /Jeg fikk/);
  assert.equal(data.activated, true);
  assert.deepEqual(requests, ['state']);
});
test('private result shares score and time without account data or answers', async t => {
  const {page, requests} = await fixture(t, {state:{result,review:questions},query:'?wpvibe_preview=secret&nonce=secret&name=Thomas#private'});
  await challenge(page).click();
  const [data] = await page.evaluate(() => window.shared);
  assert.match(data.text, /17 av 20 riktige på 2:23/);
  assert.match(data.text, /28\.09\.2026/);
  assert.equal(data.url, 'https://quiz.test/quiz/?rrq_week=2026-09-28&rrq_revision=1#rr-weekly');
  assert.doesNotMatch(JSON.stringify(data), /private|secret|Thomas|Testspørsmål|Testkilde/);
  assert.deepEqual(requests, ['state']);
  assert.match(await page.locator('.rrq-play').textContent(), /vises ikke på den offentlige topplisten/);
});
test('zero score and a new revision are shared correctly', async t => {
  const {page} = await fixture(t, {state:{revision:2,result:{...result,score:0,seconds:0},review:questions}});
  await challenge(page).click();
  const [data] = await page.evaluate(() => window.shared);
  assert.match(data.text, /0 av 20 riktige på 0:00/);
  assert.match(data.text, /utgave 2/);
  assert.match(data.url, /rrq_revision=2/);
});
test('cancel does not copy, report success or submit anything', async t => {
  const {page, requests} = await fixture(t, {share:'AbortError'});
  await challenge(page).click();
  assert.equal(await page.locator('.rrq-challenge [role=status]').textContent(), '');
  assert.deepEqual(await page.evaluate(() => window.copied), []);
  assert.deepEqual(requests, ['state']);
  assert.equal(await challenge(page).isEnabled(), true);
});
test('share failure offers selected manual text without silently copying', async t => {
  const {page} = await fixture(t, {share:'NotAllowedError'});
  await challenge(page).click();
  assert.equal(await page.locator('textarea').isVisible(), true);
  assert.match(await page.locator('.rrq-challenge [role=status]').textContent(), /kunne ikke åpnes/);
  assert.deepEqual(await page.evaluate(() => window.copied), []);
  assert.equal(await page.locator('textarea').evaluate(n => n.selectionEnd - n.selectionStart), await page.locator('textarea').evaluate(n => n.value.length));
});
test('desktop without Web Share copies the full invitation', async t => {
  const {page} = await fixture(t, {share:'absent',width:1200});
  await challenge(page).click();
  const [text] = await page.evaluate(() => window.copied);
  assert.match(text, /Jeg utfordrer deg/);
  assert.match(text, /\nhttps:\/\/quiz.test\/quiz\//);
  assert.match(await page.locator('.rrq-challenge [role=status]').textContent(), /er kopiert/);
});
for (const clipboard of ['denied','absent']) {
  test('manual fallback when clipboard is ' + clipboard, async t => {
    const {page} = await fixture(t, {share:'absent', clipboard});
    await challenge(page).click();
    assert.equal(await page.locator('textarea').isVisible(), true);
    assert.match(await page.locator('textarea').inputValue(), /rrq_week=2026-09-28/);
    assert.match(await page.locator('.rrq-challenge [role=status]').textContent(), /tekstfeltet/);
  });
}
test('copy button works when native share also exists', async t => {
  const {page} = await fixture(t);
  await page.getByRole('button',{name:'Kopier utfordringen',exact:true}).click();
  assert.equal((await page.evaluate(() => window.copied)).length, 1);
  assert.equal((await page.evaluate(() => window.shared)).length, 0);
});
test('matching invitation shows welcome without starting the clock', async t => {
  const {page, requests} = await fixture(t, {query:'?rrq_week=2026-09-28&rrq_revision=1'});
  assert.match(await page.locator('.rrq-challenge-notice').textContent(), /Du er utfordret!/);
  assert.deepEqual(requests, ['state']);
});
for (const query of ['?rrq_week=2026-09-21&rrq_revision=1','?rrq_week=2026-09-28&rrq_revision=2']) {
  test('old week or revision is explained: ' + query, async t => {
    const {page} = await fixture(t, {query});
    assert.match(await page.locator('.rrq-challenge-notice').textContent(), /kan ikke sammenlignes direkte/);
  });
}
test('malformed invitation is ignored and URL scores do not override a result', async t => {
  const {page} = await fixture(t, {query:'?rrq_week=%3Cscript%3E&rrq_revision=1&score=20',state:{result,review:questions}});
  assert.equal(await page.locator('.rrq-challenge-notice').count(), 0);
  await challenge(page).click();
  assert.match((await page.evaluate(() => window.shared))[0].text, /17 av 20/);
});
test('no sharing controls while a round is active or a pack is unavailable', async t => {
  const active = await fixture(t,{state:{started:initial.now,questions}});
  assert.equal(await challenge(active.page).count(), 0);
  const unavailable = await fixture(t,{state:{ready:false}});
  assert.equal(await challenge(unavailable.page).count(), 0);
});
test('play through, result sharing and hiding a public result keep existing actions', async t => {
  const {page, requests} = await fixture(t);
  await page.getByRole('button',{name:'Start quizen',exact:true}).click();
  for (let i=0; i<20; i++) {
    await page.locator('input[type=radio]').first().check();
    await page.getByRole('button',{name:i===19?'Lever svarene':'Neste',exact:true}).click();
  }
  await challenge(page).click();
  assert.deepEqual(requests, ['state','start','submit']);
  assert.match((await page.evaluate(() => window.shared))[0].text, /17 av 20/);
  const pub = await fixture(t, {state:{result:{...result,public:true},review:questions}});
  await pub.page.getByRole('button',{name:'Skjul meg fra topplisten',exact:true}).click();
  await challenge(pub.page).click();
  assert.deepEqual(pub.requests, ['state','hide']);
  assert.match((await pub.page.evaluate(() => window.shared))[0].text, /17 av 20/);
});
test('result and manual fallback fit a 320px mobile screen', async t => {
  const {page} = await fixture(t, {width:320,share:'absent',clipboard:'denied',state:{result,review:questions}});
  await challenge(page).click();
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  if (process.env.RR_QUIZ_SCREENSHOT) await page.screenshot({path:process.env.RR_QUIZ_SCREENSHOT,fullPage:true});
});
