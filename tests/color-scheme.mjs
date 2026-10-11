import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../components/02-components/color-scheme/color-scheme.js', import.meta.url), 'utf8');

function scenario(systemDark, savedPreference, storageAvailable = true) {
  const attrs = new Map();
  const handlers = new Map();
  const stored = new Map(savedPreference ? [['jisaba-color-scheme', savedPreference]] : []);
  const button = {
    dataset: {},
    setAttribute: (name, value) => attrs.set(`button:${name}`, value),
    addEventListener: (name, handler) => handlers.set(name, handler),
  };
  const label = {};
  const control = {
    dataset: { label: '表示', systemLabel: '自動', lightLabel: 'ライト', darkLabel: 'ダーク' },
    querySelector: (selector) => selector === '[data-color-scheme-button]' ? button : label,
    hidden: true,
  };
  const media = { matches: systemDark, addEventListener: (name, handler) => handlers.set(`media:${name}`, handler) };
  const context = {
    document: {
      documentElement: { setAttribute: (name, value) => attrs.set(name, value), removeAttribute: (name) => attrs.delete(name) },
      querySelectorAll: () => [control],
    },
    window: {
      localStorage: {
        getItem: (key) => { if (!storageAvailable) throw new Error('Unavailable'); return stored.get(key) ?? null; },
        setItem: (key, value) => { if (!storageAvailable) throw new Error('Unavailable'); stored.set(key, value); },
        removeItem: (key) => { if (!storageAvailable) throw new Error('Unavailable'); stored.delete(key); },
      },
      matchMedia: () => media,
      addEventListener: (name, handler) => handlers.set(`window:${name}`, handler),
    },
    Drupal: { behaviors: {}, t: (text, replacements) => Object.entries(replacements).reduce((result, [key, value]) => result.replace(key, value), text) },
    once: () => [control],
  };
  vm.runInNewContext(source, context);
  context.Drupal.behaviors.clear_layersColorScheme.attach(context.document);
  return { attrs, handlers, button, control, stored, media };
}

for (const dark of [false, true]) {
  for (const storageAvailable of [true, false]) {
    const state = scenario(dark, null, storageAvailable);
    assert.equal(state.control.hidden, false);
    assert.equal(state.button.dataset.mode, 'system');
    assert.equal(state.button.dataset.effectiveScheme, dark ? 'dark' : 'light');
    assert.equal(state.attrs.has('data-color-scheme'), false);
    state.handlers.get('click')();
    assert.equal(state.button.dataset.mode, dark ? 'light' : 'dark');
    state.handlers.get('click')();
    assert.equal(state.button.dataset.mode, dark ? 'dark' : 'light');
    state.handlers.get('click')();
    assert.equal(state.button.dataset.mode, 'system');
    assert.equal(state.stored.has('jisaba-color-scheme'), false);
    state.media.matches = !dark;
    state.handlers.get('media:change')();
    assert.equal(state.button.dataset.effectiveScheme, dark ? 'light' : 'dark');
  }
  for (const saved of ['light', 'dark']) {
    const state = scenario(dark, saved);
    assert.equal(state.button.dataset.mode, saved);
    assert.equal(state.attrs.get('data-color-scheme'), saved);
    assert.ok(state.attrs.get('button:aria-label').includes('クリック'));
  }
}
console.log('PASS: system appearance, click cycle, saved preference, OS changes, and unavailable storage.');
