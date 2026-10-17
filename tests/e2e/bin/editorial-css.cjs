/* Ownership gates for migrated contracts; unrelated legacy CSS remains outside this ratchet. */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const postcss = require('postcss');
const dir = 'themes/industriesalon/assets/css/';
const read = file => fs.readFileSync(dir + file, 'utf8');

test('Migrated quote skins contain role values, without resurrecting legacy quote renderers', () => {
  for (const file of ['single-ausstellung.css', ...fs.readdirSync(dir + 'skins').filter(f => f.startsWith('ausstellung-')).map(f => 'skins/' + f)]) {
    postcss.parse(read(file)).walkRules(rule => {
      assert.doesNotMatch(rule.selector, /iss-ausstellung-(?:editorial__quote|section__quote)|--layout-(?:quote|source-focus)/, file);
    });
  }
  const css = read('editorial-landing.css');
  assert.doesNotMatch(css, /iss-landing-quote|iss-landing-section--layout-(?:grid|sequence|wall|viewport)/);
  assert.doesNotMatch(read('single-tour.css'), /iss-tour-section(?:__quote|--layout-quote)/);
  assert.doesNotMatch(read('single-event.css'), /iss-event-structured__quote/);
  postcss.parse(css).walkRules(rule => {
    if (!rule.selector.includes('--treatment-zitat-')) return;
    assert.ok(rule.selectors.every(selector => selector.includes('.iss-landing-section--treatment-zitat-')), 'Quote treatment remains scoped to its section');
    assert.doesNotMatch(rule.selector, /\.postid-|\.page-id-|\.single-projekt|\.single-ausstellung/);
  });
});

test('Timeline bridge has one conditional theme owner', () => {
  postcss.parse(read('patterns.css')).walkRules(rule => {
    // The legacy promo slot is stored block composition, not plugin timeline markup.
    assert.doesNotMatch(rule.selector, /\.iss-timeline(?!-promo-slot)[\s._:>-]/);
  });
  assert.match(read('timeline-skin.css'), /\.iss-timeline--scheme-scarlet-red/);
});

test('Shared palette and spacing aliases read registered WordPress presets', () => {
  const settings = JSON.parse(fs.readFileSync('themes/industriesalon/theme.json', 'utf8')).settings;
  const palette = new Set(settings.color.palette.map(item => item.slug));
  const spacing = new Set(settings.spacing.spacingSizes.map(item => item.slug));
  let count = 0;
  postcss.parse(read('tokens.css')).walkDecls(decl => {
    if (!/^--iss-(?:red|white|black|grey|green|blue|yellow|brown|space-(?:2xs|xs|sm|md|lg|xl))$/.test(decl.prop)) return;
    const match = decl.value.match(/^var\(--wp--preset--(color|spacing)--([a-z0-9-]+)\)$/);
    assert.ok(match, decl.prop + ' must alias its preset');
    assert.ok((match[1] === 'color' ? palette : spacing).has(match[2]), decl.prop + ' must reference a declared preset');
    count++;
  });
  assert.equal(count, 14);
  assert.doesNotMatch(read('single-event.css'), /var\(--iss-event-(?:ink|paper)\)/);
});
