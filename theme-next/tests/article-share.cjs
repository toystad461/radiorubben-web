const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const template = fs.readFileSync(path.join(__dirname, '../radio-rubben-next/template-parts/article-share.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
const article = {title: 'Bømlo – mål & musikk', url: 'https://www.radiorubben.no/test-artikkel/'};

function element(extra = {}) {
  return Object.assign({hidden: true, textContent: '', listeners: {}, attrs: {},
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); },
    setAttribute(key, value) { this.attrs[key] = value; },
    focus() { this.focused = true; }, select() { this.selected = true; },
    setSelectionRange(start, end) { this.selection = [start, end]; },
    click(overrides = {}) {
      const event = {button: 0, preventDefault() { this.prevented = true; }, ...overrides};
      for (const fn of this.listeners.click || []) fn(event);
      return event;
    }
  }, extra);
}
function setup(nav = {}, secure = true, count = 1) {
  const boxes = Array.from({length: count}, (_, index) => {
    const elements = {};
    for (const name of ['fallback', 'status', 'copy', 'native', 'facebook', 'help', 'web']) {
      elements[name] = element();
    }
    elements.fallback.value = article.url + (index ? '?second=1' : '');
    return {dataset: {}, elements,
      querySelector(selector) { return elements[selector.replace('.rr-share-', '')]; },
      getAttribute(name) { assert.equal(name, 'data-share-title'); return article.title; }
    };
  });
  const context = vm.createContext({document: {querySelectorAll() { return boxes; }}, navigator: nav, window: {isSecureContext: secure}});
  const run = () => vm.runInContext(script, context);
  run();
  return {boxes, el: boxes[0].elements, run};
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('Facebook click passes the article directly to the chooser before returning', async () => {
  const calls = [];
  const {el} = setup({share: data => { calls.push({...data}); return Promise.resolve(); }});
  assert.equal(el.facebook.click().prevented, true);
  assert.deepEqual(calls, [article]);
  assert.equal(el.native.disabled, true);
  await flush();
  assert.equal(el.native.disabled, false);
  assert.equal(el.status.textContent, ''); // never falsely claim publication
  assert.match(el.facebook.attrs['aria-label'], /velg Facebook/);
  assert.equal(el.web.hidden, false);
});
test('General share uses exactly the same canonical article payload', () => {
  let received;
  const {el} = setup({share: data => { received = {...data}; return Promise.resolve(); }});
  assert.equal(el.native.hidden, false);
  el.native.click();
  assert.deepEqual(received, article);
});
test('Unsupported browsers preserve Facebook web navigation and expose copy', () => {
  const {el} = setup();
  assert.equal(el.native.hidden, true);
  assert.equal(el.facebook.click().prevented, undefined);
  assert.equal(el.copy.hidden, false);
});
for (const [name, nav, secure] of [
  ['insecure context', {share() { throw Error('must not run'); }}, false],
  ['canShare false', {share() { throw Error('must not run'); }, canShare: () => false}, true],
  ['canShare throws', {share() { throw Error('must not run'); }, canShare() { throw Error('blocked'); }}, true],
]) test(name + ' keeps web fallback', () => {
  const {el} = setup(nav, secure);
  assert.equal(el.native.hidden, true);
  assert.equal(el.facebook.click().prevented, undefined);
});
test('canShare receives the same URL and title as share', () => {
  const calls = [];
  const {el} = setup({canShare: data => { calls.push({...data}); return true; }, share: data => { calls.push({...data}); return Promise.resolve(); }});
  el.native.click();
  assert.deepEqual(calls, [article, article]);
});
test('Cancel does not copy, navigate, or show a failure', async () => {
  const {el} = setup({share: () => Promise.reject({name: 'AbortError'}), clipboard: {writeText() { assert.fail('unexpected copy'); }}});
  el.facebook.click();
  await flush();
  assert.equal(el.fallback.hidden, true);
  assert.equal(el.status.textContent, '');
  assert.equal(el.native.disabled, false);
});
for (const sync of [false, true]) test((sync ? 'Thrown' : 'Rejected') + ' share failure exposes a selected URL', async () => {
  const {el} = setup({share() { if (sync) throw Error('blocked'); return Promise.reject({name: 'NotAllowedError'}); }});
  el.facebook.click();
  await flush();
  assert.equal(el.fallback.hidden, false);
  assert.equal(el.fallback.value, article.url);
  assert.deepEqual(el.fallback.selection, [0, article.url.length]);
  assert.match(el.status.textContent, /Kopier lenken/);
  assert.equal(el.native.disabled, false);
});
test('Repeated clicks do not open a second chooser; new click works after completion', async () => {
  let finish, calls = 0;
  const {el} = setup({share() { calls++; return new Promise(resolve => { finish = resolve; }); }});
  el.facebook.click(); el.native.click(); el.facebook.click();
  assert.equal(calls, 1);
  finish(); await flush();
  el.facebook.click(); assert.equal(calls, 2);
  finish();
});
test('Modified/new-tab gestures keep the original web share link', () => {
  const {el} = setup({share() { assert.fail('unexpected native share'); }});
  for (const options of [{ctrlKey: true}, {metaKey: true}, {shiftKey: true}, {altKey: true}, {button: 1}]) {
    assert.equal(el.facebook.click(options).prevented, undefined);
  }
});
test('Clipboard receives the canonical URL and success waits for completion', async () => {
  let finish, received;
  const {el} = setup({clipboard: {writeText(url) { received = url; return new Promise(resolve => { finish = resolve; }); }}});
  el.copy.click();
  assert.equal(received, article.url);
  assert.equal(el.status.textContent, '');
  finish(); await flush();
  assert.equal(el.status.textContent, 'Lenken er kopiert!');
});
for (const mode of ['absent', 'reject', 'throw', 'insecure']) test('Clipboard ' + mode + ' preserves manual copy', async () => {
  const clipboard = mode === 'absent' ? undefined : {writeText() { if (mode === 'throw') throw Error('denied'); return Promise.reject(Error('denied')); }};
  const {el} = setup({clipboard}, mode !== 'insecure');
  el.copy.click(); await flush();
  assert.equal(el.fallback.hidden, false);
  assert.equal(el.fallback.selected, true);
  assert.equal(el.fallback.value, article.url);
  assert.doesNotMatch(el.status.textContent, /er kopiert/);
});
test('Repeated partials initialize once and share their own article', () => {
  const calls = [];
  const {boxes, run} = setup({share: data => { calls.push({...data}); return Promise.resolve(); }}, true, 2);
  run();
  boxes[1].elements.facebook.click();
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, article.url + '?second=1');
});

// Render with PHP first in CI so these assertions check actual output/encoding.
if (process.env.RR_SHARE_RENDER) {
  const fixtures = JSON.parse(fs.readFileSync(process.env.RR_SHARE_RENDER, 'utf8'));
  const decode = text => text.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#0*39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
  for (const [index, fixture] of fixtures.entries()) test('Rendered destinations preserve article URL and Unicode: ' + index, () => {
    const html = fixture.html;
    const links = [...html.matchAll(/<a\b[^>]*href="([^"]+)"/g)].map(match => decode(match[1]));
    const title = decode(fixture.title.replace(/<[^>]+>/g, ''));
    const facebook = links.filter(link => link.startsWith('https://www.facebook.com/'));
    assert.equal(facebook.length, 2);
    facebook.forEach(link => assert.equal(new URL(link).searchParams.get('u'), fixture.url));
    const whatsapp = new URL(links.find(link => link.startsWith('https://wa.me/')));
    assert.equal(whatsapp.searchParams.get('text'), title + ' ' + fixture.url);
    const email = new URL(links.find(link => link.startsWith('mailto:')));
    assert.equal(email.searchParams.get('subject'), title);
    assert.equal(email.searchParams.get('body'), title + '\n\n' + fixture.url);
    assert.equal(decode(html.match(/data-share-title="([^"]*)"/)[1]), title);
    assert.match(html, /<span>Del artikkelen<\/span>/);
  });
}
