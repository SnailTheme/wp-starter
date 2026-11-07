'use strict';

// Run: node .github/tests/animation-reveal.cjs [path/to/animate.js]
// Use --stdin to exercise a historical source without changing the worktree.
// These doubles test browser API/lifecycle behavior, not CSS cascade/keyframes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sourcePath = process.argv[2] || path.resolve(__dirname,
    '../../assets/scripts/scripts-register/plugins/animatecss/animate.js');
const source = fs.readFileSync(sourcePath === '--stdin' ? 0 : sourcePath, 'utf8');
const script = new vm.Script(source, { filename: sourcePath });
const TOKEN = 'animate__fadeIn';
const REDUCE = '(prefers-reduced-motion: reduce)';
const PENDING = 'st-wp-reveal--pending';

class Events {
    constructor() { this.listeners = new Map(); }
    addEventListener(type, callback, options = {}) {
        if (!callback) return;
        const capture = typeof options === 'boolean' ? options : !!options.capture;
        const entries = this.listeners.get(type) || [];
        if (!entries.some(entry => entry.callback === callback && entry.capture === capture)) {
            entries.push({ callback, capture, once: !!options.once });
        }
        this.listeners.set(type, entries);
    }
    removeEventListener(type, callback, options = {}) {
        const capture = typeof options === 'boolean' ? options : !!options.capture;
        this.listeners.set(type, (this.listeners.get(type) || []).filter(entry =>
            entry.callback !== callback || entry.capture !== capture));
    }
    dispatchEvent(event) {
        if (!event.target) event.target = this;
        const route = [this];
        for (let parent = this.parentNode; parent; parent = parent.parentNode) route.push(parent);
        if (route.at(-1)?.defaultView) route.push(route.at(-1).defaultView);
        const invoke = (target, capture) => {
            event.currentTarget = target;
            for (const entry of [...(target.listeners.get(event.type) || [])]) {
                if (entry.capture !== capture) continue;
                if (entry.once) target.removeEventListener(event.type, entry.callback, capture);
                if (typeof entry.callback === 'function') entry.callback.call(target, event);
                else entry.callback.handleEvent(event);
            }
        };
        for (const target of [...route].reverse()) invoke(target, true);
        invoke(this, false);
        if (event.bubbles) for (const target of route.slice(1)) invoke(target, false);
        return true;
    }
    listenerCount() {
        return [...this.listeners.values()].reduce((sum, entries) => sum + entries.length, 0);
    }
}

class Tokens {
    constructor(element) { this.element = element; }
    values() { return (this.element.getAttribute('class') || '').split(/\s+/).filter(Boolean); }
    contains(token) { return this.values().includes(String(token)); }
    add(...tokens) {
        tokens = tokens.map(String);
        if (tokens.some(token => !token || /\s/.test(token))) throw new Error('Invalid DOM token');
        this.element.setAttribute('class', [...new Set([...this.values(), ...tokens])].join(' '));
    }
    remove(...tokens) {
        this.element.setAttribute('class', this.values().filter(token => !tokens.includes(token)).join(' '));
    }
    toggle(token, force = !this.contains(token)) {
        this[force ? 'add' : 'remove'](token);
        return force;
    }
    [Symbol.iterator]() { return this.values()[Symbol.iterator](); }
}

// Only the small selector vocabulary used by reveal markup is modeled. Fail
// loudly on unsupported syntax rather than silently producing false passes.
function matches(element, selector) {
    return selector.split(',').some(part => {
        part = part.trim();
        const tag = part.match(/^[A-Za-z][\w-]*|^\*/)?.[0];
        if (tag) part = part.slice(tag.length);
        let result = !tag || tag === '*' || element.tagName === tag.toUpperCase();
        while (part) {
            const token = part.match(/^\.([\w-]+)|^#([\w-]+)|^\[([\w-]+)(?:=["']?([^\]"']*)["']?)?\]/);
            if (!token) throw new Error(`Selector not modeled: ${selector}`);
            if (token[1]) result = result && element.classList.contains(token[1]);
            else if (token[2]) result = result && element.getAttribute('id') === token[2];
            else result = result && (token[4] === undefined ? element.hasAttribute(token[3]) :
                element.getAttribute(token[3]) === token[4]);
            part = part.slice(token[0].length);
        }
        return result;
    });
}

class Tree extends Events {
    constructor(type, document) {
        super();
        this.nodeType = type;
        this.ownerDocument = document;
        this.parentNode = null;
        this.children = [];
    }
    get parentElement() { return this.parentNode?.nodeType === 1 ? this.parentNode : null; }
    get childNodes() { return this.children; }
    get isConnected() {
        return this.nodeType === 9 || !!this.parentNode?.isConnected;
    }
    append(...nodes) {
        for (const node of nodes) {
            if (node.nodeType === 11) { this.append(...node.children); continue; }
            node.remove();
            node.parentNode = this;
            node.lastParent = this;
            const document = this.nodeType === 9 ? this : this.ownerDocument;
            const adopt = child => {
                child.ownerDocument = document;
                child.children.forEach(adopt);
            };
            adopt(node);
            this.children.push(node);
        }
    }
    appendChild(node) { this.append(node); return node; }
    remove() {
        if (this.parentNode) {
            this.parentNode.children = this.parentNode.children.filter(child => child !== this);
            this.parentNode = null;
        }
    }
    contains(node) { return this === node || this.children.some(child => child.contains(node)); }
    querySelectorAll(selector) {
        const result = [];
        const visit = parent => parent.children.forEach(child => {
            if (child.nodeType === 1 && child.matches(selector)) result.push(child);
            visit(child);
        });
        visit(this);
        return result;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    getRootNode() { return this.parentNode ? this.parentNode.getRootNode() : this; }
}

class Element extends Tree {
    constructor(tag, document) {
        super(1, document);
        this.tagName = tag.toUpperCase();
        this.attributes = new Map();
        this.classList = new Tokens(this);
        const dataName = key => 'data-' + key.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase());
        this.dataset = new Proxy({}, {
            get: (_, key) => this.getAttribute(dataName(key)) ?? undefined,
            set: (_, key, value) => { this.setAttribute(dataName(key), value); return true; },
            deleteProperty: (_, key) => { this.removeAttribute(dataName(key)); return true; }
        });
        this.style = {
            setProperty(name, value) { this[name] = String(value); },
            removeProperty(name) { const old = this[name] || ''; delete this[name]; return old; },
            getPropertyValue(name) { return this[name] || ''; }
        };
        this.animationConfig = {};
        this.animationReads = 0;
        this.animationInstances = new Map();
        this.rangeReads = 0;
        this.pendingTransitions = 0;
        this.selectedTransitions = 0;
        this.rect = { x: 20, y: 1200, top: 1200, bottom: 1280,
            left: 20, right: 300, width: 280, height: 80 };
    }
    get className() { return this.getAttribute('class') || ''; }
    set className(value) { this.setAttribute('class', value); }
    get hidden() { return this.hasAttribute('hidden'); }
    set hidden(value) { if (value) this.setAttribute('hidden', ''); else this.removeAttribute('hidden'); }
    setAttribute(name, value) {
        if (name === 'class') {
            const next = String(value).split(/\s+/);
            if (next.includes(PENDING) && !this.classList.contains(PENDING)) this.pendingTransitions++;
            const selected = this.getAttribute('data-animate-class');
            if (selected && next.includes(selected) && !this.classList.contains(selected)) this.selectedTransitions++;
            for (const token of this.animationInstances.keys()) {
                if (!next.includes(token)) this.animationInstances.delete(token);
            }
        }
        this.attributes.set(name, String(value));
    }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    hasAttribute(name) { return this.attributes.has(name); }
    removeAttribute(name) { this.attributes.delete(name); }
    matches(selector) { return matches(this, selector); }
    closest(selector) {
        for (let node = this; node?.nodeType === 1; node = node.parentElement) {
            if (node.matches(selector)) return node;
        }
        return null;
    }
    focus() {
        this.ownerDocument.activeElement = this;
        this.dispatchEvent({ type: 'focusin', bubbles: true });
    }
    getBoundingClientRect() { return { ...this.rect }; }
    getAnimations() {
        this.animationReads++;
        const { token, config } = animationFor(this);
        if (config.throwAnimations) throw new Error('getAnimations failed');
        if (config.animations) return config.animations;
        if (!this.isConnected || !token || config.missing || config.style?.animationName === 'none') {
            this.animationInstances.clear();
            return [];
        }
        // Repeated queries return the same active CSSAnimation, as in a browser.
        // Removing its class invalidates the instance before any later restart.
        if (!this.animationInstances.has(token)) {
            this.animationInstances.set(token, new CSSAnimation(this, config.style?.animationName || token.slice(9), config));
        }
        return [this.animationInstances.get(token)];
    }
}

class Document extends Tree {
    constructor(readyState = 'loading') {
        super(9, null);
        this.readyState = readyState;
        this.createdElements = [];
        // Two fixture stylesheet rules. Unknown class names have no keyframes.
        this.animationStyles = new Map([[TOKEN, {}], ['animate__custom-name_2', {}]]);
        this.revealStyles = true;
        this.documentElement = this.createElement('html');
        this.body = this.createElement('body');
        this.append(this.documentElement);
        this.documentElement.append(this.body);
        this.activeElement = this.body;
    }
    createElement(tag) {
        const element = new Element(tag, this);
        this.createdElements.push(element);
        return element;
    }
    createDocumentFragment() { return new Tree(11, this); }
    createRange() {
        let selected;
        return {
            selectNodeContents(element) { selected = element; },
            getBoundingClientRect() {
                assert.ok(selected, 'Range contents selected before reading geometry');
                selected.rangeReads++;
                if (selected.rangeRect) return { ...selected.rangeRect };
                const rects = [selected, ...selected.querySelectorAll('*')]
                    .map(element => element.getBoundingClientRect())
                    .filter(rect => rect.width > 0 && rect.height > 0);
                if (!rects.length) return { x: 0, y: 0, top: 0, bottom: 0, left: 0, right: 0, width: 0, height: 0 };
                const top = Math.min(...rects.map(rect => rect.top));
                const bottom = Math.max(...rects.map(rect => rect.bottom));
                const left = Math.min(...rects.map(rect => rect.left));
                const right = Math.max(...rects.map(rect => rect.right));
                return { x: left, y: top, top, bottom, left, right, width: right - left, height: bottom - top };
            },
            detach() { selected = null; }
        };
    }
}

function animationFor(element) {
    const token = [...element.classList].find(name => element.ownerDocument.animationStyles.has(name));
    const config = { ...element.ownerDocument.animationStyles.get(token || element.dataset.animateClass),
        ...element.animationConfig };
    const timing = { ...config.timing };
    for (const [property, field] of [['--animate-duration', 'duration'], ['--animate-delay', 'delay']]) {
        const value = element.style.getPropertyValue(property) || config.customProperties?.[property];
        if (value) timing[field] = Number.parseFloat(value) * (value.endsWith('ms') ? 1 : 1000);
    }
    const repeat = element.style.getPropertyValue('--animate-repeat') || config.customProperties?.['--animate-repeat'];
    if (repeat) timing.iterations = Number(repeat);
    config.timing = timing;
    config.style = { ...config.style };
    for (const [property, field] of [['animation-delay', 'animationDelay'],
        ['animation-iteration-count', 'animationIterationCount'], ['animation-play-state', 'animationPlayState']]) {
        const value = element.style.getPropertyValue(property);
        if (value) config.style[field] = value;
    }
    return { token, config };
}

class CSSAnimation extends Events {
    constructor(target, name = 'fadeIn', config = {}) {
        super();
        this.animationName = name;
        this.playState = config.style?.animationPlayState || config.playState || 'running';
        this.pending = false;
        this.currentTime = 0;
        const timing = {
            duration: 1000, delay: 0, endDelay: 0, iterations: 1,
            iterationStart: 0, playbackRate: 1, fill: 'both', ...config.timing
        };
        // A CSS override must also affect the Web Animations view of that same
        // animation; a 0s stylesheet with a live 1s effect is not a faithful mock.
        const style = config.style || {};
        const milliseconds = value => Number.parseFloat(value) * (value.endsWith('ms') ? 1 : 1000);
        if (style.animationDuration !== undefined) timing.duration = milliseconds(style.animationDuration);
        if (style.animationDelay !== undefined) timing.delay = milliseconds(style.animationDelay);
        if (style.animationIterationCount !== undefined) {
            timing.iterations = style.animationIterationCount === 'infinite' ? Infinity :
                Number(style.animationIterationCount);
        }
        this.playbackRate = config.playbackRate ?? 1;
        this.effect = {
            target,
            getTiming: () => ({ ...timing }),
            getComputedTiming: () => ({ ...timing,
                activeDuration: timing.duration * timing.iterations,
                endTime: Math.max(0, timing.delay + timing.duration * timing.iterations + timing.endDelay),
                progress: 0, currentIteration: 0, ...config.computedTiming
            })
        };
    }
    cancel() { this.playState = 'idle'; this.dispatchEvent({ type: 'cancel' }); }
}

class Timers {
    constructor() { this.now = 0; this.next = 1; this.jobs = new Map(); }
    set(callback, delay = 0, ...args) {
        const id = this.next++;
        this.jobs.set(id, { time: this.now + Math.max(0, Number(delay) || 0), callback: () => callback(...args) });
        return id;
    }
    clear(id) { this.jobs.delete(id); }
    tick(milliseconds) {
        const end = this.now + milliseconds;
        let calls = 0;
        for (;;) {
            const next = [...this.jobs].filter(([, job]) => job.time <= end)
                .sort((a, b) => a[1].time - b[1].time || a[0] - b[0])[0];
            if (!next) break;
            assert.ok(++calls < 10000, 'No endless timer polling');
            this.now = next[1].time;
            this.jobs.delete(next[0]);
            next[1].callback();
        }
        this.now = end;
    }
}

function browser(options = {}) {
    const document = new Document(options.readyState);
    const window = new Events();
    const timers = new Timers();
    const observers = [];
    const observeStates = [];
    const media = new Map();
    let observeCalls = 0;
    document.defaultView = window;
    document.revealStyles = options.revealStyles !== false;
    document.documentElement.clientHeight = 800;
    document.documentElement.clientWidth = 1280;
    class IntersectionObserver {
        constructor(callback, settings) {
            if (options.constructorThrows) throw new Error('Observer constructor failed');
            this.callback = callback;
            this.options = settings;
            this.targets = new Set();
            this.queued = [];
            observers.push(this);
        }
        observe(target) {
            observeStates.push({ target, pending: [target, ...target.querySelectorAll('*')]
                .filter(element => element.classList.contains(PENDING)) });
            if (++observeCalls === options.observeThrowsAt) throw new Error('observe failed');
            this.targets.add(target);
        }
        unobserve(target) { this.targets.delete(target); }
        disconnect() { this.targets.clear(); }
        takeRecords() { return this.queued.splice(0); }
    }
    const matchMedia = query => {
        if (!media.has(query)) {
            const list = new Events();
            list.media = query;
            list.matches = query === REDUCE ? !!options.reduced : query === 'print' && !!options.print;
            list.addListener = callback => list.addEventListener('change', callback);
            list.removeListener = callback => list.removeEventListener('change', callback);
            media.set(query, list);
        }
        return media.get(query);
    };
    const getComputedStyle = element => {
        const { token, config } = animationFor(element);
        const timing = config.timing || {};
        const style = {
            opacity: element.classList.contains('opacity-0') ||
                (document.revealStyles && element.classList.contains(PENDING)) ? '0' : '1',
            visibility: 'visible', display: 'block',
            animationName: token && !config.missing ? token.slice(9) : 'none',
            animationDuration: `${timing.duration ?? 1000}ms`, animationDelay: `${timing.delay ?? 0}ms`,
            animationIterationCount: timing.iterations === Infinity ? 'infinite' : String(timing.iterations ?? 1),
            animationPlayState: config.playState || 'running', animationFillMode: 'both',
            ...config.style, ...element.style
        };
        // Selected-class stylesheet names do not apply before that class exists.
        // Explicit baseline/inline names model deliberate pre-existing animation.
        if (!token) style.animationName = config.baselineStyle?.animationName || element.style.animationName || 'none';
        const custom = {
            '--st-wp-reveal-enabled': document.revealStyles ? '1' : '',
            '--animate-duration': `${timing.duration ?? 1000}ms`,
            '--animate-delay': `${timing.delay ?? 0}ms`,
            '--animate-repeat': String(timing.iterations ?? 1), ...config.customProperties
        };
        style.getPropertyValue = name => name.startsWith('--') ?
            element.style.getPropertyValue(name) || custom[name] || '' :
            style[name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] || '';
        return style;
    };
    Object.assign(window, {
        document, window, self: window, matchMedia,
        getComputedStyle: element => {
            if (animationFor(element).config.throwStyle) throw new Error('Style inspection failed');
            return getComputedStyle(element);
        },
        IntersectionObserver: options.noObserver ? undefined : IntersectionObserver,
        CSSAnimation, Element, HTMLElement: Element, Node: Tree, Document,
        setTimeout: timers.set.bind(timers), clearTimeout: timers.clear.bind(timers),
        requestAnimationFrame: callback => timers.set(() => callback(timers.now), 16),
        cancelAnimationFrame: timers.clear.bind(timers),
        innerHeight: 800, innerWidth: 1280,
        performance: {
            now: () => timers.now,
            getEntriesByType: type => type === 'paint' && options.painted ?
                [{ name: 'first-contentful-paint', startTime: 1 }] : []
        }, console
    });
    Object.assign(Tree, { ELEMENT_NODE: 1, DOCUMENT_NODE: 9, DOCUMENT_FRAGMENT_NODE: 11 });
    const context = vm.createContext(window);
    const evaluate = () => script.runInContext(context, { timeout: 1000 });
    const api = () => {
        assert.equal(typeof window.stWpReveal?.init, 'function', 'window.stWpReveal.init must exist');
        assert.equal(typeof window.stWpReveal?.destroy, 'function', 'window.stWpReveal.destroy must exist');
        return window.stWpReveal;
    };
    const create = (classes, parent = document.body, token) => {
        const element = document.createElement('div');
        element.className = classes;
        if (token !== undefined) element.dataset.animateClass = token;
        parent?.append(element);
        return element;
    };
    const item = (parent, token = TOKEN) => create('animate__animated opacity-0', parent, token);
    const position = (element, top) => {
        for (const node of [element, ...element.querySelectorAll('*')]) {
            node.rect = { ...node.rect, y: top, top, bottom: top + node.rect.height };
        }
    };
    const fixture = ({ onscreen = true } = {}) => {
        const wrapper = create('enter-view');
        const element = item(wrapper);
        position(wrapper, onscreen ? 200 : 1200);
        return { wrapper, element };
    };
    const intersect = (wrapper, isIntersecting = true) => {
        if (isIntersecting) position(wrapper, 200);
        for (const observer of observers) {
            if (observer.targets.has(wrapper)) observer.callback([
                { target: wrapper, isIntersecting, intersectionRatio: isIntersecting ? 1 : 0 }
            ], observer);
        }
    };
    const targets = () => new Set(observers.flatMap(observer => [...observer.targets]));
    const ready = () => {
        document.readyState = 'interactive';
        document.dispatchEvent({ type: 'DOMContentLoaded', bubbles: true });
    };
    const boot = ({ ready: fireReady = true } = {}) => {
        evaluate();
        api();
        if (fireReady && document.readyState === 'loading') ready();
    };
    const changeMedia = (query, value) => {
        const list = matchMedia(query);
        list.matches = value;
        list.dispatchEvent({ type: 'change', matches: value, media: query });
    };
    const listenerCount = () => [window, document, ...document.querySelectorAll('*'), ...media.values()]
        .reduce((sum, target) => sum + target.listenerCount(), 0);
    return { document, window, timers, observers, observeStates, media, evaluate, api, create, item, fixture, position,
        intersect, targets, ready, boot, changeMedia, listenerCount, getComputedStyle,
        flush: () => timers.tick(32) };
}

function visible(environment, element) {
    assert.equal(element.classList.contains('opacity-0'), false, 'Enrolled legacy hiding class removed');
    assert.equal(element.hidden, false, 'No hidden attribute');
    assert.notEqual(element.getAttribute('aria-hidden'), 'true', 'No accessibility hiding');
    const style = environment.getComputedStyle(element);
    assert.notEqual(style.opacity, '0', 'Content remains visible');
    assert.notEqual(style.visibility, 'hidden');
    assert.notEqual(style.display, 'none');
    assert.equal(element.classList.contains(PENDING), false, 'Owned pending hiding removed');
}

function pending(environment, element) {
    assert.equal(element.classList.contains('opacity-0'), false, 'Legacy hiding removed');
    assert.equal(element.classList.contains(PENDING), true, 'Owned pending class applied');
    assert.equal(element.getAttribute('data-st-wp-reveal'), 'pending');
    assert.equal(element.classList.contains(element.dataset.animateClass), false, 'Animation has not started');
    assert.equal(environment.getComputedStyle(element).opacity, '0', 'Mock pending stylesheet is active');
}

function settled(environment, element, token = TOKEN) {
    visible(environment, element);
    assert.equal(element.classList.contains(token), false, 'Selected animation class removed');
    assert.equal(element.hasAttribute('data-st-wp-reveal'), false, 'Transient reveal attribute removed');
}

function running(environment, element, token = TOKEN) {
    environment.flush();
    assert.equal(element.classList.contains('opacity-0'), false);
    assert.equal(element.classList.contains(PENDING), false, 'Pending removed in the start task');
    assert.equal(element.classList.contains(token), true, 'Selected animation is running');
}

function animationEvent(element, type = 'animationend', animationName = 'fadeIn') {
    element.dispatchEvent({ type, animationName, bubbles: true });
}

const tests = [];
function test(name, callback) { tests.push({ name, callback }); }

test('publishes the scoped init/destroy API', () => {
    const environment = browser();
    environment.boot();
});

test('nested overflow is visible even when ancestor content ranges are empty', () => {
    const e = browser({ readyState: 'complete' });
    const { element } = e.fixture();
    const zero = { x: 0, y: 0, width: 300, height: 0, top: 0, bottom: 0, left: 0, right: 300 };
    element.rect = { ...zero };
    element.rangeRect = { ...zero };
    const middle = e.create('zero-height', element);
    middle.rect = { ...zero };
    middle.rangeRect = { ...zero };
    const painted = e.document.createElement('img');
    painted.rect = { x: 0, y: 50, width: 80, height: 30, top: 50, bottom: 80, left: 0, right: 80 };
    middle.append(painted);
    e.boot();
    settled(e, element);
    assert.equal(element.pendingTransitions, 0, 'Painted nested overflow must never disappear');
    assert.equal(element.selectedTransitions, 0, 'Late overflow must not restart an entrance');
    assert.equal(e.targets().size, 0);
});

test('before-ready evaluation installs one document listener and waits', () => {
    const e = browser({ readyState: 'loading' });
    const { wrapper, element } = e.fixture();
    e.boot({ ready: false });
    assert.equal(e.document.listeners.get('DOMContentLoaded')?.length, 1);
    assert.equal(e.window.listeners.get('DOMContentLoaded')?.length || 0, 0);
    assert.equal(e.targets().size, 0);
    e.ready();
    pending(e, element);
    assert.ok(e.targets().has(wrapper));
    assert.equal(e.document.listeners.get('DOMContentLoaded')?.length || 0, 0, 'Ready listener is once-only');
});

for (const readyState of ['interactive', 'complete']) {
    test(`late startup skips already visible content when ${readyState}`, () => {
        const e = browser({ readyState });
        const { wrapper, element } = e.fixture();
        e.boot();
        settled(e, element);
        assert.equal(e.targets().has(wrapper), false);
        assert.equal(element.pendingTransitions, 0, 'Never briefly re-hides painted content');
        assert.equal(element.selectedTransitions, 0, 'No visible-to-start-frame flash');
        assert.equal(e.timers.jobs.size, 0);
        assert.equal(e.document.listeners.get('DOMContentLoaded')?.length || 0, 0);
    });
    test(`late startup arms offscreen content immediately when ${readyState}`, () => {
        const e = browser({ readyState });
        const { wrapper, element } = e.fixture({ onscreen: false });
        e.boot();
        pending(e, element);
        assert.ok(e.targets().has(wrapper));
        e.intersect(wrapper);
        running(e, element);
    });
}

for (const geometry of ['visible overflow descendant', 'zero-height text']) {
    for (const initialization of ['late startup', 'dynamic default init']) {
        test(`${initialization} never hides ${geometry} missed by its border box`, () => {
            const e = browser({ readyState: 'complete' });
            if (initialization === 'dynamic default init') e.boot();
            const { wrapper, element } = e.fixture({ onscreen: false });
            if (geometry === 'visible overflow descendant') {
                const child = e.create('overflow-content', element);
                e.position(child, 200);
            } else {
                element.rect = { ...element.rect, bottom: element.rect.top, height: 0 };
                // A text-only range can have painted glyphs outside a zero-height
                // element's box; no text-node/parser model is needed for this case.
                element.rangeRect = { x: 20, y: 200, top: 200, bottom: 224,
                    left: 20, right: 300, width: 280, height: 24 };
            }
            const offscreen = e.fixture({ onscreen: false });
            if (initialization === 'late startup') e.boot();
            else e.api().init();
            settled(e, element);
            assert.ok(element.rangeReads > 0, 'Checks content bounds when the border box misses');
            assert.equal(element.pendingTransitions, 0, 'No hiding of already-painted content');
            assert.equal(element.selectedTransitions, 0, 'Skip occurs before animation preflight');
            assert.equal(e.targets().has(wrapper), false);
            pending(e, offscreen.element);
            e.intersect(offscreen.wrapper);
            running(e, offscreen.element);
            animationEvent(offscreen.element);
            assert.equal(e.targets().size, 0);
            assert.equal(e.timers.jobs.size, 0);
        });
    }
}

test('loading is not before-paint permission after a paint entry exists', () => {
    const e = browser({ readyState: 'loading', painted: true });
    const onscreen = e.fixture();
    const offscreen = e.fixture({ onscreen: false });
    e.boot();
    settled(e, onscreen.element);
    assert.equal(onscreen.element.pendingTransitions, 0);
    assert.equal(onscreen.element.selectedTransitions, 0);
    pending(e, offscreen.element);
    assert.deepEqual([...e.targets()], [offscreen.wrapper]);
});

test('a paint occurring between evaluation and DOM readiness also prevents on-screen hiding', () => {
    const options = { readyState: 'loading', painted: false };
    const e = browser(options);
    const { element } = e.fixture();
    e.boot({ ready: false });
    options.painted = true;
    e.ready();
    settled(e, element);
    assert.equal(element.pendingTransitions, 0);
    assert.equal(element.selectedTransitions, 0);
});

for (const [failure, configure] of [
    ['missing performance', window => { delete window.performance; }],
    ['missing paint method', window => { delete window.performance.getEntriesByType; }],
    ['throwing paint method', window => {
        window.performance.getEntriesByType = () => { throw new Error('Paint timing unavailable'); };
    }],
    ['non-array paint result', window => { window.performance.getEntriesByType = () => ({ length: 0 }); }]
]) {
    for (const readyState of ['loading', 'complete']) {
        test(`${failure} fails open without disabling offscreen motion (${readyState})`, () => {
            const e = browser({ readyState });
            const onscreen = e.fixture();
            const offscreen = e.fixture({ onscreen: false });
            configure(e.window);
            assert.doesNotThrow(() => e.boot(), 'Neither script evaluation nor DOM readiness may throw');
            settled(e, onscreen.element);
            assert.equal(onscreen.element.pendingTransitions, 0, 'Never re-hides visible content');
            assert.equal(onscreen.element.selectedTransitions, 0, 'Never starts a visible-to-start-frame flash');
            pending(e, offscreen.element);
            assert.deepEqual([...e.targets()], [offscreen.wrapper], 'Only offscreen content is armed');
            e.intersect(offscreen.wrapper);
            running(e, offscreen.element);
            const [effect] = offscreen.element.getAnimations();
            effect.dispatchEvent({ type: 'finish' });
            settled(e, offscreen.element);
            settled(e, onscreen.element);
            assert.equal(e.targets().size, 0);
            assert.equal(e.timers.jobs.size, 0);
            assert.equal(effect.listenerCount(), 0);
        });
    }
}

for (const beforePaint of [undefined, false, true]) {
    test(`dynamic on-screen init respects beforePaint=${String(beforePaint)}`, () => {
        const e = browser();
        e.boot();
        const { wrapper, element } = e.fixture();
        if (beforePaint === undefined) e.api().init(wrapper);
        else e.api().init(wrapper, { beforePaint });
        if (beforePaint) {
            pending(e, element);
            e.intersect(wrapper);
            running(e, element);
        } else {
            settled(e, element);
            assert.equal(element.pendingTransitions, 0);
            assert.equal(element.selectedTransitions, 0);
            // A previously skipped/painted item cannot be made to flash later.
            e.api().init(wrapper, { beforePaint: true });
            e.intersect(wrapper);
            settled(e, element);
            assert.equal(e.targets().size, 0);
            assert.equal(e.timers.jobs.size, 0);
        }
    });
}

test('default dynamic init can arm offscreen content without beforePaint permission', () => {
    const e = browser({ readyState: 'complete' });
    e.boot();
    const { wrapper, element } = e.fixture({ onscreen: false });
    e.api().init(wrapper);
    pending(e, element);
    e.intersect(wrapper);
    running(e, element);
});

test('preflight validates actual target styles synchronously and leaves no selected class or helper DOM', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    element.classList.add('animate__slow', 'animate__delay-2s', 'animate__repeat-2');
    element.animationConfig = {
        customProperties: { '--animate-duration': '2s', '--animate-delay': '150ms', '--animate-repeat': '2' },
        style: { animationName: 'fadeIn', animationDelay: '0.25s', animationIterationCount: '2', animationPlayState: 'running' }
    };
    assert.equal(e.getComputedStyle(element).animationName, 'none', 'Modifiers alone do not start an animation');
    const created = e.document.createdElements.length;
    const children = [...wrapper.children];
    const attributes = new Map(element.attributes);
    const inlineStyle = { ...element.style };
    const before = e.timers.now;
    e.boot();
    pending(e, element);
    assert.equal(e.timers.now, before, 'No timer/frame advances during preflight');
    assert.equal(e.document.createdElements.length, created, 'No generic probe element is created');
    assert.deepEqual(wrapper.children, children, 'Reading/interactive DOM is untouched');
    assert.ok(element.animationReads > 0, 'Inspects actual target animation instances');
    assert.ok(element.selectedTransitions > 0, 'Selected class was synchronously inspected');
    assert.equal(element.classList.contains(TOKEN), false, 'Preflight always removes the selected class');
    assert.equal(element.getAnimations().length, 0, 'No preflight animation remains active');
    assert.equal(element.listenerCount(), 0, 'No run listeners before intersection');
    assert.equal(element.getAttribute('aria-hidden'), attributes.get('aria-hidden') ?? null);
    assert.deepEqual(element.style, inlineStyle, 'No inline-style workaround changes actual styling');
    assert.ok(element.classList.contains('animate__slow'), 'Existing animation modifiers are preserved');
    e.intersect(wrapper);
    running(e, element);
    animationEvent(element);
    settled(e, element);
});

for (const token of ['animate__NotInstalled', TOKEN]) {
    test(`pre-existing project animation skips ${token} without probing or restarting it`, () => {
        const e = browser();
        e.document.animationStyles.set('project-animation', { style: { animationName: 'projectPulse' } });
        const { wrapper, element } = e.fixture();
        element.dataset.animateClass = token;
        element.classList.add('project-animation');
        const [projectEffect] = element.getAnimations();
        let projectFinishes = 0;
        projectEffect.addEventListener('finish', () => projectFinishes++);
        assert.equal(e.getComputedStyle(element).animationName, 'projectPulse');
        const neighbor = e.fixture();
        e.boot();

        settled(e, element, token);
        assert.equal(element.pendingTransitions, 0, 'Existing animation is never used as reveal proof');
        assert.equal(element.selectedTransitions, 0, 'Does not probe a token that might interrupt the project animation');
        assert.ok(element.classList.contains('project-animation'), 'Project-owned class is preserved');
        assert.equal(element.getAnimations()[0], projectEffect, 'Existing animation identity is preserved');
        assert.equal(projectEffect.playState, 'running');
        assert.equal(projectEffect.listenerCount(), 1, 'Project listeners remain untouched; no reveal listeners added');
        assert.equal(e.targets().has(wrapper), false);
        pending(e, neighbor.element);
        e.intersect(neighbor.wrapper);
        running(e, neighbor.element);
        animationEvent(neighbor.element);

        e.api().init(wrapper, { beforePaint: true });
        assert.equal(element.selectedTransitions, 0);
        assert.equal(element.getAnimations()[0], projectEffect);
        projectEffect.dispatchEvent({ type: 'finish' });
        assert.equal(projectFinishes, 1, 'Existing project event handling still works');
        settled(e, element, token);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

for (const missing of ['animation stylesheet', 'pending stylesheet sentinel']) {
    test(`missing ${missing} never hides or schedules actual content`, () => {
        const e = browser({ revealStyles: missing !== 'pending stylesheet sentinel' });
        if (missing === 'animation stylesheet') e.document.animationStyles.clear();
        const { element } = e.fixture();
        e.boot();
        settled(e, element);
        assert.equal(element.pendingTransitions, 0);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
        assert.equal(e.document.querySelectorAll('span').length, 0);
    });
}

for (const readyState of ['loading', 'complete']) {
    test(`repeat evaluation and init reuse singleton/listeners/observer (${readyState})`, () => {
        const e = browser({ readyState });
        const { wrapper, element } = e.fixture({ onscreen: false });
        e.boot({ ready: false });
        const singleton = e.api();
        const listenersBefore = e.listenerCount();
        e.evaluate();
        e.evaluate();
        assert.equal(e.api(), singleton);
        assert.equal(e.listenerCount(), listenersBefore);
        if (readyState === 'loading') e.ready();
        const listenersAfter = e.listenerCount();
        const observerCount = e.observers.length;
        const timerCount = e.timers.jobs.size;
        for (let index = 0; index < 3; index++) e.api().init();
        assert.equal(e.observers.length, observerCount);
        assert.equal(e.listenerCount(), listenersAfter);
        assert.equal(e.timers.jobs.size, timerCount);
        e.intersect(wrapper);
        running(e, element);
        animationEvent(element);
        e.flush();
        settled(e, element);
        e.api().init(e.document, { beforePaint: true });
        e.evaluate();
        e.intersect(wrapper);
        e.flush();
        settled(e, element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.observers.length, observerCount);
    });
}

test('observes wrappers in its own document at threshold zero without a dead zone', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    assert.equal(e.observers.length, 1);
    assert.equal(e.observers[0].options.threshold, 0);
    assert.equal(e.observers[0].options.rootMargin, '0px');
    assert.deepEqual([...e.targets()], [wrapper]);
    pending(e, element);
    assert.equal(e.observeStates[0].pending.length, 0, 'Observe succeeds before actual content is hidden');
    assert.equal(element.classList.contains(TOKEN), false);
    e.intersect(wrapper, false);
    assert.equal(wrapper.classList.contains('entered'), false);
    assert.equal(element.classList.contains(TOKEN), false);
    e.intersect(wrapper);
    assert.equal(wrapper.classList.contains('entered'), true);
    running(e, element);
    assert.ok(element.animationReads > 0, 'Checks actual animation API on run');
    const reads = element.animationReads;
    e.intersect(wrapper);
    e.flush();
    assert.equal(element.animationReads, reads, 'Repeated intersection does not restart a running item');
});

test('wrapper itself and descendants enroll; unrelated opacity classes stay untouched', () => {
    const e = browser();
    const wrapper = e.create('enter-view animate__animated opacity-0', e.document.body, TOKEN);
    const child = e.item(wrapper);
    const unrelated = e.create('opacity-0', wrapper);
    const outside = e.item(e.document.body);
    e.boot();
    pending(e, wrapper);
    pending(e, child);
    assert.ok(unrelated.classList.contains('opacity-0'));
    assert.ok(outside.classList.contains('opacity-0'));
    e.intersect(wrapper);
    running(e, wrapper);
    running(e, child);
    animationEvent(child);
    assert.ok(wrapper.classList.contains(TOKEN), 'Bubbled child event does not finish wrapper');
    assert.ok(e.targets().has(wrapper), 'Group stays observed while one item remains active');
    animationEvent(wrapper);
    settled(e, wrapper);
    settled(e, child);
    assert.equal(e.targets().size, 0);
});

test('only the nearest enter-view wrapper owns each item', () => {
    const e = browser();
    const outer = e.fixture();
    const inner = e.create('enter-view', outer.wrapper);
    const element = e.item(inner);
    e.boot();
    e.intersect(outer.wrapper);
    running(e, outer.element);
    assert.equal(element.classList.contains(TOKEN), false);
    assert.equal(inner.classList.contains('entered'), false);
    e.intersect(inner);
    running(e, element);
});

for (const token of [undefined, '', ' ', 'two tokens', 'animate__fadeIn animate__zoomIn',
    'fadeIn', 'animate__', 'animate__2bad', 'animate__fadeIn!', 'animate__fadeIn\n']) {
    test(`invalid token ${JSON.stringify(token)} settles independently`, () => {
        const e = browser();
        const wrapper = e.create('enter-view');
        const bad = e.item(wrapper, token);
        if (token === undefined) bad.removeAttribute('data-animate-class');
        const good = e.item(wrapper);
        e.boot();
        visible(e, bad);
        e.intersect(wrapper);
        running(e, good);
        settled(e, bad);
        animationEvent(good);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

test('unknown but well-formed animation token settles without failing its neighbor', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    element.dataset.animateClass = 'animate__NotInstalled';
    element.animationConfig.missing = true;
    const good = e.item(wrapper);
    e.boot();
    settled(e, element, 'animate__NotInstalled');
    assert.equal(element.pendingTransitions, 0, 'Unknown CSS never becomes pending');
    e.intersect(wrapper);
    e.flush();
    settled(e, element, 'animate__NotInstalled');
    running(e, good);
});

for (const token of ['animate__fadeIn', 'animate__custom-name_2']) {
    test(`accepts one well-formed selected token ${token}`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        element.dataset.animateClass = token;
        e.boot();
        e.intersect(wrapper);
        running(e, element, token);
        animationEvent(element, 'animationend', token.slice(9));
        settled(e, element, token);
    });
}

test('scoped init accepts a later wrapper, item self, and arbitrary subtree', () => {
    const e = browser();
    e.boot();
    const wrapper = e.create('enter-view');
    const first = e.item(wrapper);
    e.api().init(wrapper, { beforePaint: true });
    pending(e, first);
    const subtree = e.create('content', wrapper);
    const second = e.item(subtree);
    e.api().init(subtree, { beforePaint: true });
    pending(e, second);
    const third = e.item(wrapper);
    e.api().init(third, { beforePaint: true });
    pending(e, third);
    assert.equal(e.targets().size, 1);
    e.intersect(wrapper);
    for (const element of [first, second, third]) running(e, element);
});

test('document fragment init/destroy safely handles detached markup and completed root self', () => {
    const e = browser();
    e.boot();
    const fragment = e.document.createDocumentFragment();
    const wrapper = e.create('enter-view animate__animated opacity-0', fragment, TOKEN);
    const element = e.item(wrapper);
    e.api().init(fragment, { beforePaint: true });
    e.api().destroy(fragment);
    visible(e, wrapper);
    visible(e, element);
    e.document.body.append(fragment);
    e.api().init(wrapper, { beforePaint: true });
    e.intersect(wrapper);
    settled(e, wrapper);
    settled(e, element);
});

test('new descendants in an entered wrapper run once without replaying completed siblings', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    e.intersect(wrapper);
    running(e, element);
    animationEvent(element);
    const later = e.item(wrapper);
    e.api().init(wrapper, { beforePaint: true });
    e.intersect(wrapper);
    running(e, later);
    settled(e, element);
    animationEvent(later);
    e.api().init(e.document, { beforePaint: true });
    settled(e, later);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

for (const rootSelf of [false, true]) {
    test(`historical entered wrapper waits for fresh intersection for all new siblings; rootSelf=${rootSelf}`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        if (rootSelf) {
            wrapper.classList.add('animate__animated', 'opacity-0');
            wrapper.dataset.animateClass = TOKEN;
        }
        e.boot();
        e.intersect(wrapper);
        running(e, element);
        animationEvent(element);
        if (rootSelf) animationEvent(wrapper);
        const previousStarts = element.selectedTransitions;
        const later = [e.item(wrapper), e.item(wrapper), e.item(wrapper)];
        e.position(wrapper, 1200);
        const observations = e.observeStates.length;
        e.api().init(wrapper);
        for (const child of later) pending(e, child);
        assert.equal(e.observeStates.length, observations + 1, 'One fresh observation per wrapper/batch');
        e.api().init(wrapper);
        e.api().init(later[0]);
        assert.equal(e.observeStates.length, observations + 1, 'Repeated/scoped initialization does not reobserve');
        e.intersect(wrapper, false);
        for (const child of later) pending(e, child);
        e.intersect(wrapper);
        for (const child of later) running(e, child);
        assert.equal(element.selectedTransitions, previousStarts, 'Completed sibling never replays');
        for (const child of later) animationEvent(child);
        e.api().init(wrapper, {beforePaint: true});
        for (const child of later) settled(e, child);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

for (const onscreen of [false, true]) {
    test(`new children of a still-observed wrapper request a current result; onscreen=${onscreen}`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        e.boot();
        e.intersect(wrapper);
        running(e, element);
        const previousStarts = element.selectedTransitions;
        const later = [e.item(wrapper), e.item(wrapper)];
        e.position(wrapper, onscreen ? 200 : 1200);
        const observations = e.observeStates.length;
        e.api().init(wrapper, {beforePaint: true});
        for (const child of later) pending(e, child);
        assert.equal(e.observeStates.length, observations + 1, 'Existing observation is refreshed once for new content');
        assert.equal(e.observers.length, 1, 'Reuse the observer, not additional observers');
        e.intersect(wrapper, false);
        for (const child of later) pending(e, child);
        e.intersect(wrapper);
        for (const child of later) running(e, child);
        assert.equal(element.selectedTransitions, previousStarts, 'Running sibling never restarts');
        for (const child of [element, ...later]) animationEvent(child);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

test('nested entered wrappers enroll new children only with their nearest wrapper', () => {
    const e = browser();
    const outer = e.fixture();
    const inner = e.create('enter-view', outer.wrapper);
    const original = e.item(inner);
    e.boot();
    e.intersect(outer.wrapper);
    animationEvent(outer.element);
    pending(e, original);
    e.intersect(inner);
    animationEvent(original);
    const previousStarts = [outer.element.selectedTransitions, original.selectedTransitions];
    const later = [e.item(inner), e.item(inner)];
    e.position(inner, 1200);
    e.api().init(outer.wrapper);
    for (const child of later) pending(e, child);
    assert.deepEqual([...e.targets()], [inner]);
    e.intersect(outer.wrapper);
    for (const child of later) pending(e, child);
    e.intersect(inner);
    for (const child of later) running(e, child);
    assert.deepEqual([outer.element.selectedTransitions, original.selectedTransitions], previousStarts);
    for (const child of later) animationEvent(child);
    e.api().init(outer.wrapper, {beforePaint: true});
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

for (const otherWrapper of [false, true]) {
    test(`queued intersections belong to existing participants, not later enrollments; otherWrapper=${otherWrapper}`, () => {
        const e = browser();
        const original = e.fixture();
        const neighbor = otherWrapper ? e.fixture() : null;
        e.boot();
        const observer = e.observers[0];
        observer.queued.push({target: original.wrapper, isIntersecting: true});
        if (neighbor) observer.queued.push({target: neighbor.wrapper, isIntersecting: true});
        e.position(original.wrapper, 1200);
        const later = [e.item(original.wrapper), e.item(original.wrapper)];
        e.api().init(original.wrapper);
        // Native unobserve does not invalidate entries already queued earlier.
        observer.callback(observer.takeRecords(), observer);
        for (const child of later) pending(e, child);
        running(e, original.element);
        if (neighbor) running(e, neighbor.element);
        e.intersect(original.wrapper, false);
        for (const child of later) pending(e, child);
        e.intersect(original.wrapper);
        for (const child of later) running(e, child);
        for (const child of [original.element, ...later, ...(neighbor ? [neighbor.element] : [])]) animationEvent(child);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

test('settling a queued original does not lose the later enrollment or its observation', () => {
    const e = browser();
    const {wrapper, element} = e.fixture();
    e.boot();
    const observer = e.observers[0];
    observer.queued.push({target: wrapper, isIntersecting: true});
    element.getAnimations = () => [];
    e.position(wrapper, 1200);
    const later = e.item(wrapper);
    e.api().init(later);
    observer.callback(observer.takeRecords(), observer);
    settled(e, element);
    pending(e, later);
    assert.equal(e.targets().has(wrapper), true);
    e.intersect(wrapper);
    running(e, later);
    animationEvent(later);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

for (const neighboringWrapper of [false, true]) {
    test(`queued delivery snapshots participants before synchronous reentry; neighboringWrapper=${neighboringWrapper}`, () => {
        const e = browser();
        const original = e.fixture();
        const neighbor = neighboringWrapper ? e.fixture() : null;
        e.boot();
        const observer = e.observers[0];
        observer.queued.push({target: original.wrapper, isIntersecting: true});
        if (neighbor) observer.queued.push({target: neighbor.wrapper, isIntersecting: true});
        e.position(original.wrapper, 1200);
        if (neighbor) e.position(neighbor.wrapper, 1200);
        const later = e.item((neighbor || original).wrapper);
        const trigger = neighbor ? e.item(original.wrapper) : later;
        const setAttribute = original.wrapper.setAttribute.bind(original.wrapper);
        let reentered = false;
        original.wrapper.setAttribute = (name, value) => {
            setAttribute(name, value);
            if (!reentered && name === 'class' && original.wrapper.classList.contains('entered')) {
                reentered = true;
                e.api().init(later);
            }
        };
        e.api().init(trigger);
        assert.equal(reentered, true, 'Synchronous custom-element reaction was exercised');
        pending(e, later);
        if (neighbor) pending(e, trigger);
        e.api().destroy();
        for (const element of [original.element, later, trigger, ...(neighbor ? [neighbor.element] : [])]) {
            settled(e, element);
            assert.equal(element.listenerCount(), 0, 'Destroy removed every animation listener');
        }
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0, 'Reentry must not orphan a record timer');
        e.timers.tick(31000);
        assert.equal(later.listenerCount(), 0, 'Expired timers must not leave handlers behind');
    });
}

for (const mutation of ['detach', 'reduce', 'focus', 'token', 'style', 'class']) {
    test(`enrollment revalidates after a synchronous ${mutation} reaction during queue delivery`, () => {
        const e = browser();
        const {wrapper, element} = e.fixture();
        e.boot();
        const observer = e.observers[0];
        observer.queued.push({target: wrapper, isIntersecting: true});
        e.position(wrapper, 1200);
        const later = e.item(wrapper);
        const control = mutation === 'focus' ? e.create('control', later) : null;
        const setAttribute = wrapper.setAttribute.bind(wrapper);
        let reacted = false;
        wrapper.setAttribute = (name, value) => {
            setAttribute(name, value);
            if (!reacted && name === 'class' && wrapper.classList.contains('entered')) {
                reacted = true;
                if (mutation === 'detach') later.remove();
                else if (mutation === 'reduce') e.changeMedia(REDUCE, true);
                else if (mutation === 'focus') control.focus();
                else if (mutation === 'token') later.dataset.animateClass = 'animate__Missing';
                else if (mutation === 'style') later.animationConfig.missing = true;
                else later.classList.remove('animate__animated');
            }
        };
        e.api().init(later);
        assert.equal(reacted, true);
        settled(e, later);
        e.api().destroy();
        settled(e, element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
        assert.equal(later.listenerCount(), 0);
    });
}

test('failed queue inspection leaves old, new and neighboring content readable', () => {
    const e = browser();
    const original = e.fixture();
    const neighbor = e.fixture();
    e.boot();
    e.observers[0].takeRecords = () => { throw new Error('Queue inspection failed'); };
    e.position(original.wrapper, 1200);
    const later = [e.item(original.wrapper), e.item(original.wrapper)];
    e.api().init(original.wrapper);
    for (const child of [original.element, neighbor.element, ...later]) settled(e, child);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

test('queued entries for destroyed wrappers cannot revive their participants', () => {
    const e = browser();
    const original = e.fixture();
    e.boot();
    const observer = e.observers[0];
    observer.queued.push({target: original.wrapper, isIntersecting: true});
    e.api().destroy(original.wrapper);
    const later = e.fixture({onscreen: false});
    e.api().init(later.wrapper);
    observer.callback(observer.takeRecords(), observer);
    settled(e, original.element);
    pending(e, later.element);
    e.intersect(later.wrapper);
    running(e, later.element);
    animationEvent(later.element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

test('refresh observation failure releases the original and all newly enrolled content', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    e.intersect(wrapper);
    running(e, element);
    const later = [e.item(wrapper), e.item(wrapper)];
    e.position(wrapper, 1200);
    e.observers[0].unobserve = () => { throw new Error('Refresh failed'); };
    e.api().init(wrapper);
    for (const child of [element, ...later]) settled(e, child);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

for (const options of [{ noObserver: true }, { constructorThrows: true },
    { observeThrowsAt: 1 }, { observeThrowsAt: 2 }]) {
    test(`observer failure is visible and cleans partial enrollment: ${JSON.stringify(options)}`, () => {
        const e = browser(options);
        const fixtures = [e.fixture(), e.fixture(), e.fixture()];
        e.boot();
        e.flush();
        for (const { element } of fixtures) settled(e, element);
        assert.equal(e.targets().size, 0, 'Partial setup is released immediately');
        assert.equal(e.timers.jobs.size, 0, 'No lease left behind by failed setup');
        for (const { wrapper } of fixtures) e.intersect(wrapper);
        e.timers.tick(30000);
        for (const { element } of fixtures) settled(e, element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

for (const options of [{ reduced: true }, { print: true }]) {
    test(`initial motion preference skips motion: ${JSON.stringify(options)}`, () => {
        const e = browser(options);
        const { wrapper, element } = e.fixture();
        e.boot();
        e.intersect(wrapper);
        e.flush();
        settled(e, element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

for (const trigger of ['reduced-change', 'print-change', 'beforeprint']) {
    test(`${trigger} settles waiting and running items, skips newly initialized motion`, () => {
        const e = browser();
        const pending = e.fixture();
        const active = e.fixture();
        e.boot();
        e.intersect(active.wrapper);
        running(e, active.element);
        if (trigger === 'beforeprint') e.window.dispatchEvent({ type: 'beforeprint' });
        else e.changeMedia(trigger === 'reduced-change' ? REDUCE : 'print', true);
        settled(e, pending.element);
        settled(e, active.element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
        const later = e.fixture();
        e.api().init(later.wrapper, { beforePaint: true });
        e.intersect(later.wrapper);
        e.flush();
        settled(e, later.element);
    });
}

test('afterprint allows new items but never replays completed or print-settled content', () => {
    const e = browser();
    const completed = e.fixture();
    const waiting = e.fixture();
    const active = e.fixture();
    e.boot();
    e.intersect(completed.wrapper);
    running(e, completed.element);
    animationEvent(completed.element);
    e.intersect(active.wrapper);
    running(e, active.element);
    e.window.dispatchEvent({ type: 'beforeprint' });
    const duringPrint = e.fixture();
    e.api().init(duringPrint.wrapper, { beforePaint: true });
    const oldItems = [completed, waiting, active, duringPrint];
    const transitions = oldItems.map(({ element }) => ({
        pending: element.pendingTransitions, selected: element.selectedTransitions
    }));
    for (const { element } of oldItems) settled(e, element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);

    e.window.dispatchEvent({ type: 'afterprint' });
    e.api().init(e.document, { beforePaint: true });
    e.evaluate();
    for (const { wrapper, element } of oldItems) {
        e.intersect(wrapper);
        settled(e, element);
    }
    assert.deepEqual(oldItems.map(({ element }) => ({
        pending: element.pendingTransitions, selected: element.selectedTransitions
    })), transitions, 'Afterprint and re-init never hide or restart completed content');

    const onscreen = e.fixture();
    const offscreen = e.fixture({ onscreen: false });
    e.api().init(onscreen.wrapper, { beforePaint: true });
    e.api().init(offscreen.wrapper);
    for (const { wrapper, element } of [onscreen, offscreen]) {
        pending(e, element);
        e.intersect(wrapper);
        running(e, element);
        animationEvent(element);
        settled(e, element);
    }
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

test('focusin settles enclosing pending items without touching another group', () => {
    const e = browser();
    const first = e.fixture();
    const nested = e.item(first.element);
    const control = e.create('control', nested);
    const other = e.fixture();
    e.boot();
    control.focus();
    settled(e, first.element);
    settled(e, nested);
    assert.equal(e.targets().has(first.wrapper), false);
    assert.ok(e.targets().has(other.wrapper));
    e.intersect(other.wrapper);
    running(e, other.element);
});

test('already focused content skips motion on entry; focus during animation settles visibly', () => {
    const e = browser();
    const first = e.fixture();
    e.create('control', first.element).focus();
    const second = e.fixture();
    const control = e.create('control', second.element);
    e.boot();
    e.intersect(first.wrapper);
    settled(e, first.element);
    e.intersect(second.wrapper);
    running(e, second.element);
    control.focus();
    settled(e, second.element);
    assert.equal(e.timers.jobs.size, 0);
});

for (const type of ['animationend', 'animationcancel']) {
    test(`${type} filters animationName and target, then clears the drained group`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        const child = e.create('decoration', element);
        e.boot();
        e.intersect(wrapper);
        running(e, element);
        const effects = element.getAnimations();
        animationEvent(child, type);
        animationEvent(element, type, 'unrelated');
        assert.ok(element.classList.contains(TOKEN));
        animationEvent(element, type);
        settled(e, element);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
        assert.equal(element.listenerCount(), 0);
        for (const effect of effects) assert.equal(effect.listenerCount(), 0, 'Native listeners released too');
    });
}

for (const type of ['cancel', 'finish']) {
    test(`native CSSAnimation ${type} settles synchronously without a bubbling CSS event`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        e.boot();
        e.intersect(wrapper);
        assert.ok(element.classList.contains(TOKEN));
        const [effect] = element.getAnimations();
        assert.ok(effect instanceof CSSAnimation);
        assert.equal(element.getAnimations()[0], effect, 'Animation identity is stable across API queries');
        assert.equal(effect.listeners.get('finish')?.length, 1);
        assert.equal(effect.listeners.get('cancel')?.length, 1);
        const before = e.timers.now;
        assert.ok(e.timers.jobs.size > 0, 'A live watchdog exists before the native event');

        const remove = element.classList.remove.bind(element.classList);
        let checkedCleanupOrder = false;
        element.classList.remove = (...tokens) => {
            if (tokens.includes(TOKEN)) {
                assert.equal(effect.listenerCount(), 0, 'Native listeners removed before selected class cleanup');
                checkedCleanupOrder = true;
            }
            return remove(...tokens);
        };
        // No animationstart/end/cancel DOM event and no timer/microtask advance.
        effect.dispatchEvent({ type });
        settled(e, element);
        assert.equal(e.timers.now, before, 'Recovery did not wait for the watchdog');
        assert.ok(checkedCleanupOrder);
        assert.equal(effect.listenerCount(), 0);
        assert.equal(element.listenerCount(), 0);
        assert.equal(element.getAnimations().length, 0, 'Class removal releases the cached active animation');
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);

        e.api().init(wrapper, { beforePaint: true });
        e.intersect(wrapper);
        effect.dispatchEvent({ type: type === 'cancel' ? 'finish' : 'cancel' });
        settled(e, element);
        assert.equal(effect.listenerCount(), 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

test('native cancel() before animationstart releases its item without affecting a running sibling', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    const sibling = e.item(wrapper);
    e.boot();
    e.intersect(wrapper);
    const [effect] = element.getAnimations();
    const [siblingEffect] = sibling.getAnimations();
    assert.ok(effect.listenerCount() > 0);
    assert.ok(siblingEffect.listenerCount() > 0);
    const before = e.timers.now;
    effect.cancel();
    settled(e, element);
    assert.equal(e.timers.now, before);
    assert.equal(effect.listenerCount(), 0);
    assert.ok(sibling.classList.contains(TOKEN), 'Sibling keeps running');
    assert.ok(e.targets().has(wrapper), 'Group remains observed until its sibling drains');
    assert.ok(siblingEffect.listenerCount() > 0);
    siblingEffect.dispatchEvent({ type: 'finish' });
    settled(e, sibling);
    assert.equal(siblingEffect.listenerCount(), 0);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

const invalidAnimations = [
    ['missing CSS', { missing: true }],
    ['computed animation disabled', { style: { animationName: 'none' } }],
    ['computed duration zero', { style: { animationDuration: '0s' } }],
    ['computed animation paused', { style: { animationPlayState: 'paused' } }],
    ['computed animation infinite', { style: { animationIterationCount: 'infinite' } }],
    ['computed duration excessive', { style: { animationDuration: '11s' } }],
    ['actual 20s CSS duration', { style: { animationDuration: '20s' } }],
    ['computed delay excessive', { style: { animationDelay: '11s' } }],
    ['animation paused', { playState: 'paused' }],
    ['animation infinite', { timing: { iterations: Infinity } }],
    ['animation duration excessive', { timing: { duration: 10001 } }],
    ['animation delay plus duration excessive', { timing: { duration: 1000, delay: 9500 } }],
    ['animation timing nonfinite', { timing: { duration: NaN } }],
    ['style inspection throws', { throwStyle: true }],
    ['animation inspection throws', { throwAnimations: true }]
];
for (const [name, config] of invalidAnimations) {
    test(`${name} settles immediately without waiting for a watchdog`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        element.animationConfig = config;
        const good = e.item(wrapper);
        const created = e.document.createdElements.length;
        e.boot();
        settled(e, element);
        assert.equal(element.pendingTransitions, 0, 'Invalid actual-target CSS never arms hiding');
        assert.equal(e.document.createdElements.length, created, 'No generic probe DOM');
        pending(e, good);
        e.intersect(wrapper);
        e.flush();
        settled(e, element);
        running(e, good);
        animationEvent(good);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

for (const [name, config] of invalidAnimations.filter(([, config]) => !config.throwStyle && !config.throwAnimations)) {
    test(`preflight rejects ${name} before adding any actual pending state`, () => {
        const e = browser();
        e.document.animationStyles.set(TOKEN, config);
        const { element } = e.fixture();
        e.boot();
        settled(e, element);
        assert.equal(element.pendingTransitions, 0);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
        assert.equal(element.getAnimations().length, 0, 'Rejected target has no active preflight animation');
    });
}

test('computed CSS alone is not proof of an installed keyframe animation', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    element.animationConfig.animations = [];
    e.boot();
    e.intersect(wrapper);
    e.flush();
    settled(e, element);
    assert.ok(element.animationReads > 0);
});

for (const kind of ['wrong-name', 'wrong-target', 'not-css-animation']) {
    test(`getAnimations ignores ${kind} as proof of selected animation`, () => {
        const e = browser();
        const { wrapper, element } = e.fixture();
        const animation = new CSSAnimation(kind === 'wrong-target' ? wrapper : element,
            kind === 'wrong-name' ? 'unrelated' : 'fadeIn');
        element.animationConfig.animations = [kind === 'not-css-animation' ? {
            animationName: animation.animationName, playState: animation.playState, effect: animation.effect
        } : animation];
        e.boot();
        e.intersect(wrapper);
        e.flush();
        settled(e, element);
    });
}

test('unrelated invalid animation does not invalidate a matching finite CSSAnimation', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    element.animationConfig.animations = [
        new CSSAnimation(element, 'unrelated', { timing: { iterations: Infinity } }),
        new CSSAnimation(wrapper, 'fadeIn', { playState: 'paused' }),
        new CSSAnimation(element)
    ];
    e.boot();
    e.intersect(wrapper);
    running(e, element);
});

test('waiting lease expires at 30000ms, clears observations and prevents replay', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    e.timers.tick(29999);
    pending(e, element);
    assert.ok(e.targets().has(wrapper));
    e.timers.tick(1);
    settled(e, element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
    e.api().init(wrapper, { beforePaint: true });
    e.intersect(wrapper);
    e.flush();
    settled(e, element);
});

test('each new waiting record gets its own lease, not the wrapper age', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    e.timers.tick(20000);
    const later = e.item(wrapper);
    e.api().init(later, { beforePaint: true });
    e.timers.tick(10000);
    settled(e, element);
    assert.ok(e.targets().has(wrapper));
    e.intersect(wrapper);
    running(e, later);
    settled(e, element);
    animationEvent(later);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

test('run watchdog bounds a lost animationend including its delay to 10000ms', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    element.animationConfig = { timing: { duration: 1000, delay: 8000 }, style: { animationDelay: '8s' } };
    e.boot();
    e.intersect(wrapper);
    running(e, element);
    e.timers.tick(10000 - e.timers.now);
    settled(e, element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
});

test('normal finite 1s animations remain active before finish and do not require timer completion', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    e.intersect(wrapper);
    running(e, element);
    e.timers.tick(400);
    assert.ok(element.classList.contains(TOKEN));
    animationEvent(element);
    settled(e, element);
    assert.equal(e.timers.jobs.size, 0);
});

test('detached waiting and running records release by their bounded deadlines', () => {
    const e = browser();
    const waiting = e.fixture();
    const active = e.fixture();
    e.boot();
    e.intersect(active.wrapper);
    running(e, active.element);
    waiting.wrapper.remove();
    active.wrapper.remove();
    e.timers.tick(30000);
    settled(e, waiting.element);
    settled(e, active.element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
    assert.equal(active.element.listenerCount(), 0);
});

test('destroy(subtree) releases only its waiting/running records, including root self', () => {
    const e = browser();
    const container = e.create('content');
    const wrapper = e.create('enter-view animate__animated opacity-0', container, TOKEN);
    const child = e.item(wrapper);
    const waiting = e.fixture();
    container.append(waiting.wrapper);
    const untouched = e.fixture();
    e.boot();
    e.intersect(wrapper);
    running(e, wrapper);
    running(e, child);
    e.api().destroy(container);
    for (const element of [wrapper, child, waiting.element]) settled(e, element);
    assert.deepEqual([...e.targets()], [untouched.wrapper]);
    assert.equal(child.listenerCount(), 0);
    e.api().init(container);
    e.intersect(wrapper);
    e.intersect(waiting.wrapper);
    e.flush();
    for (const element of [wrapper, child, waiting.element]) settled(e, element);
    e.intersect(untouched.wrapper);
    running(e, untouched.element);
    animationEvent(untouched.element);
    assert.equal(e.timers.jobs.size, 0);
});

test('destroy(item) leaves its sibling usable and destroy() drains the document', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    const sibling = e.item(wrapper);
    e.boot();
    e.api().destroy(element);
    settled(e, element);
    assert.ok(e.targets().has(wrapper));
    e.intersect(wrapper);
    running(e, sibling);
    settled(e, element);
    e.api().destroy();
    settled(e, sibling);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
    e.api().destroy();
    e.api().init();
    e.flush();
    settled(e, sibling);
});

test('destroy can clean a detached subtree without depending on a document query', () => {
    const e = browser();
    const { wrapper, element } = e.fixture();
    e.boot();
    wrapper.remove();
    e.api().destroy(wrapper);
    settled(e, element);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
    e.document.body.append(wrapper);
    e.api().init(wrapper);
    e.intersect(wrapper);
    e.flush();
    settled(e, element);
});

for (const marker of ['editor-styles-wrapper', 'block-editor-iframe__body']) {
    test(`editor boundary ${marker} is skipped without disabling frontend neighbors`, () => {
        const e = browser();
        const editor = e.create(marker);
        const wrapper = e.create('enter-view', editor);
        const element = e.item(wrapper);
        const frontend = e.fixture();
        e.boot();
        assert.equal(e.targets().has(wrapper), false);
        assert.equal(element.classList.contains(TOKEN), false);
        e.api().init(wrapper);
        e.intersect(wrapper);
        assert.equal(element.classList.contains(TOKEN), false);
        e.intersect(frontend.wrapper);
        running(e, frontend.element);
    });
    test(`whole editor document ${marker} creates no reveal work`, () => {
        const e = browser();
        e.document.body.classList.add(marker);
        const { wrapper, element } = e.fixture();
        e.boot();
        e.intersect(wrapper);
        assert.equal(element.classList.contains(TOKEN), false);
        assert.equal(e.targets().size, 0);
        assert.equal(e.timers.jobs.size, 0);
    });
}

test('foreign document/subtree is skipped and owns no observers or timers here', () => {
    const e = browser();
    const other = browser();
    const foreign = other.fixture();
    e.boot();
    for (const root of [other.document, foreign.wrapper, foreign.element]) e.api().init(root);
    assert.equal(e.targets().size, 0);
    assert.equal(e.timers.jobs.size, 0);
    assert.ok(foreign.element.classList.contains('opacity-0'), 'Does not mutate another document');
    other.boot();
    other.intersect(foreign.wrapper);
    running(other, foreign.element);
    e.api().destroy(other.document);
    assert.ok(foreign.element.classList.contains(TOKEN), 'Foreign destroy cannot touch another runtime');
});

let failures = 0;
for (const { name, callback } of tests) {
    try {
        callback();
        console.log(`PASS ${name}`);
    } catch (error) {
        failures++;
        console.error(`FAIL ${name}\n${error.stack}`);
    }
}
console.log(`\n${tests.length - failures}/${tests.length} animation reveal tests passed; ${failures} failed.`);
process.exitCode = failures ? 1 : 0;
