const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { test } = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../../wordpress/wp-content/themes/radio-rubben-wordpress-v1/assets/js/weekly-quiz.js'), 'utf8');

function setup(returnTo, vippsAvailable = true) {
  let click;
  const calls = [], operations = [];
  const link = {};
  const play = { addEventListener(type, fn) { if (type === 'click') click = fn; }, contains(node) { return node === link; } };
  const root = { querySelector(selector) { return selector === '.rrq-play' ? play : {}; } };
  const context = {
    document: { querySelector() { return root; } },
    rrwqConfig: { ajax: 'https://www.radiorubben.no/wp-admin/admin-ajax.php', returnTo },
    window: {}, URLSearchParams,
    fetch(url, options) { operations.push(options.body.get('op')); return new Promise(() => {}); },
  };
  if (vippsAvailable) context.window.login_with_vipps = (...args) => calls.push(args);
  vm.runInNewContext(source, context);
  function activate(vippsLink = true) {
    let prevented = false;
    click({ target: { closest() { return vippsLink ? link : null; } }, preventDefault() { prevented = true; } });
    return prevented;
  }
  return { calls, operations, activate };
}

test('Quiz sends its return URL even with no document.referrer or HTTP header', () => {
  const target = 'https://www.radiorubben.no/quiz/#rr-weekly';
  const s = setup(target);
  assert.equal(s.activate(), true);
  assert.equal(s.calls.length, 1);
  assert.equal(s.calls[0][0], 'wordpress');
  assert.equal(s.calls[0][1]._wp_http_referer, target);
  assert.deepEqual(s.operations, ['state'], 'Login must not start a timed attempt');
});
test('A preview returns to the same quiz preview', () => {
  const target = 'https://www.radiorubben.no/quiz/?wpvibe_preview=test-token#rr-weekly';
  const s = setup(target);
  s.activate();
  assert.equal(s.calls[0][1]._wp_http_referer, target);
});
test('Password links retain their normal browser navigation', () => {
  const s = setup('https://www.radiorubben.no/quiz/#rr-weekly');
  assert.equal(s.activate(false), false);
  assert.equal(s.calls.length, 0);
});
test('An older cached config or unavailable Vipps script keeps the original link', () => {
  for (const s of [setup(undefined), setup('https://www.radiorubben.no/quiz/', false)]) {
    assert.equal(s.activate(), false);
    assert.equal(s.calls.length, 0);
  }
});
