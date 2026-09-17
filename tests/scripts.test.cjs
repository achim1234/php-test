const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const vm = require('node:vm');

const script = readFileSync(require.resolve('../public/js/scripts.js'), 'utf8');

// A small DOM/transport fixture lets slow responses and rapid input be tested
// deterministically without adding a browser or JavaScript dependencies.
class Element extends EventTarget {
    constructor(id = '', type = '') {
        super();
        Object.assign(this, { id, type, value: '', style: {}, children: [], files: [], disabled: false });
        this.attributes = new Map();
        const classes = new Set();
        this.classList = {
            add: value => classes.add(value),
            remove: value => classes.delete(value),
            toggle: (value, enabled) => enabled ? classes.add(value) : classes.delete(value),
            contains: value => classes.has(value)
        };
    }
    setAttribute(name, value) { this.attributes.set(name, value); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    set src(value) { this.setAttribute('src', value); }
    get src() { return new URL(this.getAttribute('src') || '', 'https://example.test/').href; }
    set alt(value) { this.setAttribute('alt', value); }
    append(...children) { this.children.push(...children); }
    prepend(child) { this.children.unshift(child); }
    querySelector() { return null; }
    remove() { this.removed = true; }
    requestSubmit() { this.dispatchEvent(new Event('submit', { cancelable: true })); }
}

function editor(source = 'original.png') {
    const ids = ['lightbox', 'lightbox-img', 'lightbox-caption', 'library_image_input', 'glitchForm',
        'select-lib-btn', 'glitched-preview', 'download-link', 'source_file', 'photo', 'submit',
        'save-output-btn', 'morph-btn', 'editor-status', 'result-container', 'placeholder',
        'random-glitch-btn', 'output-grid', 'library-grid', 'val_rgb_shift', 'preset_filter',
        'duotone-palette', 'duotone-palette-status', 'duotone_shadow', 'duotone_highlight'];
    const elements = Object.fromEntries(ids.map(id => [id, new Element(id)]));
    const range = elements.rgb_shift = new Element('rgb_shift', 'range');
    Object.assign(range, { min: '0', max: '50', step: '1', value: '10' });
    Object.assign(elements.preset_filter, { tagName: 'SELECT', value: 'none', options: ['none', 'duotone'] });
    Object.assign(elements.duotone_shadow, { type: 'color', value: '#24105e' });
    Object.assign(elements.duotone_highlight, { type: 'color', value: '#ffef5c' });
    elements.source_file.value = source;
    elements.photo.type = 'file';
    elements['glitched-preview'].src = source ? 'uploads/previous.png' : '';
    const form = elements.glitchForm;
    form.action = 'https://example.test/index.php';
    form.querySelectorAll = () => [range, elements.preset_filter, elements.duotone_shadow, elements.duotone_highlight];
    form.querySelector = () => elements.submit;
    const document = new EventTarget();
    document.getElementById = id => elements[id];
    document.createElement = tag => Object.assign(new Element(), { tagName: tag.toUpperCase() });
    document.querySelectorAll = selector => selector === '.library-item img'
        ? [...elements['output-grid'].children, ...elements['library-grid'].children]
            .flatMap(item => item.children.filter(child => child.tagName === 'IMG'))
        : [];
    document.querySelector = () => ({ value: 'select' });
    class EditorFormData extends FormData {
        constructor(form) {
            super();
            if (form) {
                for (const id of ['source_file', 'library_image_input', 'rgb_shift', 'photo', 'preset_filter', 'duotone_shadow', 'duotone_highlight']) {
                    const element = elements[id];
                    if (!element.disabled) this.append(id === 'library_image_input' ? 'library_image' : id, element.value);
                }
            }
        }
    }
    const requests = [];
    const timers = new Map();
    let timerId = 0;
    const context = vm.createContext({
        document, window: {}, Event, URL, FormData: EditorFormData,
        Image: class { decode() { return Promise.resolve(); } },
        setTimeout: callback => { timers.set(++timerId, callback); return timerId; },
        clearTimeout: id => timers.delete(id),
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ ...options, resolve, reject }))
    });
    vm.runInContext(script, context);
    document.dispatchEvent(new Event('DOMContentLoaded'));
    return {
        elements, requests, context,
        input(value) { range.value = String(value); range.dispatchEvent(new Event('input')); },
        click(id) { elements[id].dispatchEvent(new Event('click')); },
        tick() { for (const [id, callback] of [...timers]) { timers.delete(id); callback(); } },
        async reply(index, data, ok = true) {
            requests[index].resolve({ ok, status: ok ? 200 : 500, json: async () => data });
            await new Promise(setImmediate);
        }
    };
}

const result = (image, source = 'original.png') => ({ success: true, sourceFile: source, glitchedImage: image });

test('rapid slider edits use one request at a time and never display a stale response', async () => {
    const app = editor();
    app.input(11);
    app.input(12);
    assert.equal(app.elements.val_rgb_shift.textContent, '12');
    assert.equal(app.requests.length, 0);
    app.tick();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].body.get('rgb_shift'), '12');
    app.input(20);
    app.tick();
    app.input(30);
    app.tick();
    assert.equal(app.requests.length, 1);
    assert.equal(app.elements['save-output-btn'].disabled, true);
    await app.reply(0, result('uploads/stale.png'));
    assert.equal(app.elements['glitched-preview'].getAttribute('src'), 'uploads/previous.png');
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[1].body.get('rgb_shift'), '30');
    await app.reply(1, result('uploads/latest.png'));
    assert.equal(app.elements['glitched-preview'].getAttribute('src'), 'uploads/latest.png');
    assert.equal(app.elements['download-link'].href, 'uploads/latest.png');
    assert.equal(app.elements['save-output-btn'].disabled, false);
});

test('upload preserves controls and queued edits use the returned original without reuploading', async () => {
    const app = editor('');
    app.elements.photo.files = [{}];
    app.elements.glitchForm.requestSubmit();
    app.input(35);
    app.tick();
    await app.reply(0, { ...result('uploads/first.png', 'uploaded.png'), libraryImage: 'lib/uploaded.png' });
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[1].body.get('source_file'), 'uploaded.png');
    assert.equal(app.requests[1].body.get('rgb_shift'), '35');
    assert.equal(app.requests[1].body.has('photo'), false);
    assert.equal(app.requests[1].body.has('library_image'), false);
    await app.reply(1, result('uploads/current.png', 'uploaded.png'));
    assert.equal(app.elements.rgb_shift.value, '35');
    assert.equal(app.elements['library-grid'].children.length, 1);
    assert.equal(app.elements['result-container'].style.display, 'block');
});

test('randomizing effects replaces a pending slider update', async () => {
    const app = editor();
    app.input(1);
    app.click('random-glitch-btn');
    app.tick();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].body.get('rgb_shift'), String(app.elements.rgb_shift.value));
    await app.reply(0, result('uploads/random.png'));
    assert.equal(app.elements['save-output-btn'].disabled, false);
});

test('changing a duotone color activates the filter and sends both palette colors', async () => {
    const app = editor();
    app.elements.duotone_shadow.value = '#102030';
    app.elements.duotone_shadow.dispatchEvent(new Event('input'));

    assert.equal(app.elements.preset_filter.value, 'duotone');
    assert.equal(app.elements['duotone-palette'].classList.contains('is-active'), true);
    assert.match(app.elements['duotone-palette-status'].textContent, /Active/);

    app.tick();
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].body.get('preset_filter'), 'duotone');
    assert.equal(app.requests[0].body.get('duotone_shadow'), '#102030');
    assert.equal(app.requests[0].body.get('duotone_highlight'), '#ffef5c');
    await app.reply(0, result('uploads/duotone.png'));
});

test('failed requests unlock the editor and can be retried', async () => {
    const app = editor();
    app.input(20);
    app.tick();
    await app.reply(0, {}, false);
    assert.match(app.elements['editor-status'].textContent, /HTTP 500/);
    assert.equal(app.elements.submit.disabled, false);
    assert.equal(app.elements['save-output-btn'].disabled, true);
    app.elements.glitchForm.requestSubmit();
    await app.reply(1, result('uploads/retry.png'));
    assert.equal(app.elements['save-output-btn'].disabled, false);
});

test('saving adds to the collection without losing controls and queues edits until the copy finishes', async () => {
    const app = editor();
    app.click('save-output-btn');
    assert.equal(app.requests[0].body.get('filename'), 'previous.png');
    app.input(25);
    app.tick();
    assert.equal(app.requests.length, 1);
    await app.reply(0, { success: true, outputImage: 'output/saved.png' });
    assert.equal(app.elements['output-grid'].children.length, 1);
    assert.equal(app.elements.rgb_shift.value, '25');
    assert.equal(app.requests.length, 2);
    await app.reply(1, result('uploads/edited.png'));
    assert.equal(app.elements.source_file.value, 'original.png');
});

test('morph selects full library paths and subsequent controls edit the composite', async () => {
    const app = editor('');
    vm.runInContext("selectedImages.add('lib/same.png'); selectedImages.add('output/same.png'); morphSelected();", app.context);
    assert.deepEqual(app.requests[0].body.getAll('images[]'), ['lib/same.png', 'output/same.png']);
    await app.reply(0, result('uploads/morph.png', 'morph.png'));
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[1].body.get('source_file'), 'morph.png');
    await app.reply(1, result('uploads/glitched_morph.png', 'morph.png'));
    assert.equal(app.elements['glitched-preview'].getAttribute('src'), 'uploads/glitched_morph.png');
    assert.equal(vm.runInContext('selectedImages.size', app.context), 0);
});
