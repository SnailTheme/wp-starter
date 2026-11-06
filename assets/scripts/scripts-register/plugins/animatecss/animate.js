/**
 * Optional, fail-open AnimateCSS reveal integration.
 *
 * Enqueue both plugins.animatecss.animate handles. Hide only after usable CSS
 * and a working observer are confirmed, then reveal once on intersection.
 * After inserting content call window.stWpReveal.init(subtree). Before removing
 * it call window.stWpReveal.destroy(subtree) to release pending work immediately.
 *
 * @package ST_WP_Starter
 */

(() => {
    if (window.stWpReveal && typeof window.stWpReveal.init === 'function') {
        if (document.readyState !== 'loading') {
            window.stWpReveal.init(document);
        }
        return;
    }

    const completed = new WeakSet();
    const records = new Map();
    const wrappers = new Map();
    const waitingLimit = 30000;
    const animationLimit = 10000;
    const loadedEarly = document.readyState === 'loading';
    let observer;
    let unavailable = false;
    let reducedMotion;
    let printing;
    let printEventActive = false;

    /** Restore the normal project appearance and release this item's resources. */
    function settle(element) {
        const record = records.get(element);
        completed.add(element);
        records.delete(element);
        if (record) {
            window.clearTimeout(record.timer);
            element.removeEventListener('animationend', record.onEnd);
            element.removeEventListener('animationcancel', record.onCancel);
            for (const effect of record.effects || []) {
                effect.removeEventListener('finish', record.onEffectFinish);
                effect.removeEventListener('cancel', record.onEffectCancel);
            }
            const group = wrappers.get(record.wrapper);
            group?.delete(element);
            if (group && group.size === 0) {
                wrappers.delete(record.wrapper);
                try {
                    observer?.unobserve(record.wrapper);
                } catch {
                    // Cleanup errors must not prevent the visible fallback.
                }
            }
        }
        const token = record?.token || element.getAttribute('data-animate-class') || '';
        if (/^animate__[A-Za-z][A-Za-z0-9_-]*$/.test(token)) {
            element.classList.remove(token);
        }
        element.classList.remove('opacity-0');
        element.classList.remove('st-wp-reveal--pending');
        element.removeAttribute('data-st-wp-reveal');
        if (wrappers.size === 0) {
            try {
                observer?.disconnect();
            } catch {
                // The registry is empty even if platform cleanup fails.
            }
        }
    }

    function settleAll() {
        for (const element of [...records.keys()]) {
            settle(element);
        }
    }

    function motionDisabled(element) {
        return unavailable || reducedMotion?.matches || printing?.matches || printEventActive
            || document.documentElement.classList.contains('block-editor-iframe__html')
            || Boolean(element.closest('.editor-styles-wrapper, .block-editor-iframe__body'));
    }

    /** Inspect real CSS animations, not just a possibly undefined keyframe name. */
    function usableAnimations(element) {
        if (typeof window.CSSAnimation !== 'function') {
            return [];
        }
        const names = window.getComputedStyle(element).animationName.split(',').map(name => name.trim());
        const animations = element.getAnimations().filter(animation =>
            animation instanceof window.CSSAnimation && animation.effect?.target === element
                && names.includes(animation.animationName)
        );
        if (animations.length === 0 || animations.some(animation => {
            const endTime = animation.effect.getComputedTiming().endTime;
            return animation.playState === 'paused' || !Number.isFinite(endTime)
                || endTime <= 0 || endTime > animationLimit;
        })) {
            return [];
        }
        return animations;
    }

    /**
     * Validate the actual element, including tag/ID selectors and inline CSS.
     * The class is added/read/removed in one task, before an animation frame or
     * paint can happen. A detached clone cannot reproduce this CSS context.
     */
    function canAnimate(element, token) {
        const baseline = window.getComputedStyle(element);
        if (baseline.getPropertyValue('--st-wp-reveal-enabled').trim() !== '1'
            || baseline.animationName.split(',').some(name => name.trim() !== 'none')) {
            // Do not interrupt an existing project animation or mistake it for
            // proof that an unknown data-animate-class token is available.
            return false;
        }
        try {
            element.classList.add(token);
            return usableAnimations(element).length > 0;
        } finally {
            element.classList.remove(token);
        }
    }

    function inViewport(element) {
        const intersects = rect => rect.bottom > 0 && rect.right > 0
            && rect.top < window.innerHeight && rect.left < window.innerWidth;
        if (intersects(element.getBoundingClientRect())) {
            return true;
        }
        // A range can stop at an entirely selected zero-height child, missing
        // nested positioned/replaced content. Inspect descendant boxes as well
        // as their text ranges before declaring anything safely off-screen.
        const range = document.createRange();
        return [element, ...element.querySelectorAll('*')].some(node => {
            if (intersects(node.getBoundingClientRect())) {
                return true;
            }
            range.selectNodeContents(node);
            return intersects(range.getBoundingClientRect());
        });
    }

    function start(element) {
        const record = records.get(element);
        if (!record || record.running) {
            return;
        }
        if (motionDisabled(element) || !element.isConnected || element.contains(document.activeElement)) {
            settle(element);
            return;
        }
        try {
            record.running = true;
            window.clearTimeout(record.timer);
            record.names = new Set();
            record.onEnd = (event) => {
                if (event.target === element && record.names.delete(event.animationName) && record.names.size === 0) {
                    settle(element);
                }
            };
            record.onCancel = (event) => {
                if (event.target === element && record.names.has(event.animationName)) {
                    settle(element);
                }
            };
            element.addEventListener('animationend', record.onEnd);
            element.addEventListener('animationcancel', record.onCancel);
            // Install recovery before the class can hide or transform content.
            record.timer = window.setTimeout(() => settle(element), animationLimit);
            element.setAttribute('data-st-wp-reveal', 'running');
            element.classList.remove('st-wp-reveal--pending');
            element.classList.add(record.token);
            const animations = usableAnimations(element);
            if (animations.length === 0) {
                settle(element);
                return;
            }
            record.names = new Set(animations.map(animation => animation.animationName));
            record.effects = animations;
            record.onEffectFinish = (event) => record.onEnd({target: element, animationName: event.target.animationName});
            record.onEffectCancel = () => {
                if (records.get(element) === record) {
                    settle(element);
                }
            };
            // Web Animations cancellation also covers cancellation before the
            // CSS animationstart event, when no animationcancel event may bubble.
            for (const effect of animations) {
                effect.addEventListener('finish', record.onEffectFinish);
                effect.addEventListener('cancel', record.onEffectCancel);
            }
        } catch {
            settle(element);
        }
    }

    function ensureObserver() {
        if (observer) {
            return observer;
        }
        observer = new window.IntersectionObserver(entries => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }
                const group = wrappers.get(entry.target);
                if (!group) {
                    continue;
                }
                entry.target.classList.add('entered');
                for (const element of [...group]) {
                    start(element);
                }
            }
        }, {threshold: 0, rootMargin: '0px'});
        return observer;
    }

    /** Enroll only this document's reveal nodes, including a matching root. */
    function init(root = document, options = {}) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }
        const elements = [...root.querySelectorAll('.animate__animated')];
        if (typeof root.matches === 'function' && root.matches('.animate__animated')) {
            elements.unshift(root);
        }
        const refreshedWrappers = new Set();
        for (const element of elements) {
            if (element.ownerDocument !== document || completed.has(element) || records.has(element)) {
                continue;
            }
            const wrapper = element.closest('.enter-view');
            if (!wrapper) {
                continue;
            }
            const token = element.getAttribute('data-animate-class') || '';
            element.classList.remove('opacity-0');
            if (!/^animate__[A-Za-z][A-Za-z0-9_-]*$/.test(token) || motionDisabled(element)) {
                settle(element);
                continue;
            }
            try {
                // Never hide already-painted, visible content for a late reveal.
                if ((!options?.beforePaint && inViewport(element))
                    || element.contains(document.activeElement) || !canAnimate(element, token)) {
                    settle(element);
                    continue;
                }
            } catch {
                // A bad item's CSS must not disable valid neighboring items.
                settle(element);
                continue;
            }
            try {
                const activeObserver = ensureObserver();
                const record = {wrapper, token, running: false};
                records.set(element, record);
                // An undelivered observation cannot retain content indefinitely.
                record.timer = window.setTimeout(() => settle(element), waitingLimit);
                const group = wrappers.get(wrapper) || new Set();
                const alreadyObserved = wrappers.has(wrapper);
                group.add(element);
                wrappers.set(wrapper, group);
                if (!refreshedWrappers.has(wrapper)) {
                    // `entered` records history, not current intersection. A
                    // fresh subscription also delivers a result for new content
                    // in an already-visible group whose threshold did not change.
                    if (alreadyObserved) {
                        activeObserver.unobserve(wrapper);
                    }
                    activeObserver.observe(wrapper);
                    refreshedWrappers.add(wrapper);
                }
                if (records.has(element) && !record.running) {
                    element.setAttribute('data-st-wp-reveal', 'pending');
                    element.classList.add('st-wp-reveal--pending');
                }
            } catch {
                // Partial observer setup must release earlier registrations too.
                unavailable = true;
                settleAll();
                settle(element);
            }
        }
    }

    function destroy(root = document) {
        for (const element of [...records.keys()]) {
            if (root === document || root === element || root?.contains(element)) {
                settle(element);
            }
        }
    }

    window.stWpReveal = {init, destroy};
    try {
        reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        printing = window.matchMedia('print');
        for (const query of [reducedMotion, printing]) {
            query.addEventListener('change', () => {
                if (query.matches) {
                    settleAll();
                }
            });
        }
        window.addEventListener('beforeprint', () => {
            printEventActive = true;
            settleAll();
        });
        window.addEventListener('afterprint', () => {
            printEventActive = false;
        });
        document.addEventListener('focusin', event => {
            for (const element of [...records.keys()]) {
                if (element.contains(event.target)) {
                    settle(element);
                }
            }
        });
    } catch {
        unavailable = true;
    }

    function boot() {
        let beforePaint = false;
        try {
            const paints = window.performance?.getEntriesByType?.('paint');
            beforePaint = loadedEarly && Array.isArray(paints) && paints.length === 0;
        } catch {
            // Without paint timing, do not risk hiding already-visible content.
        }
        init(document, {beforePaint});
    }

    if (loadedEarly) {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
