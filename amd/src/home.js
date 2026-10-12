// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Public home page behaviour: scroll reveal, counters, carousels, deferred video and the live
 * preview bridge used by the page builder.
 *
 * @module     theme_instracta/home
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const reduced = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const all = (sel, scope) => Array.prototype.slice.call((scope || document).querySelectorAll(sel));

    let observer = null;

    const countUp = el => {
        const target = parseFloat(el.getAttribute('data-instracta-count'));
        if (isNaN(target) || el.dataset.instractaDone) {
            return;
        }
        el.dataset.instractaDone = '1';
        const final = el.textContent;
        if (reduced()) {
            return;
        }
        const start = performance.now();
        const duration = 1400;
        const tick = now => {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString();
            if (p < 1) {
                requestAnimationFrame(tick);
            } else {
                el.textContent = final;
            }
        };
        requestAnimationFrame(tick);
    };

    const reveal = el => {
        el.classList.add('is-in');
        all('[data-instracta-count]', el).forEach(countUp);
        if (el.matches('[data-instracta-animate]')) {
            all('[data-instracta-count]', el).forEach(countUp);
        }
    };

    const watch = scope => {
        const targets = all('.instracta-anim-fade, .instracta-anim-up, .instracta-anim-zoom, [data-instracta-animate]', scope)
            .filter(el => !el.classList.contains('is-in'));
        if (!('IntersectionObserver' in window)) {
            targets.forEach(reveal);
            return;
        }
        if (!observer) {
            observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        reveal(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {threshold: 0.12, rootMargin: '0px 0px -6% 0px'});
        }
        targets.forEach(el => observer.observe(el));
        // Never leave content hidden if the observer does not fire.
        setTimeout(() => targets.forEach(reveal), 4000);
    };

    const carousels = scope => {
        all('[data-instracta-carousel]', scope).forEach(box => {
            if (box.dataset.instractaBound) {
                return;
            }
            box.dataset.instractaBound = '1';
            const track = box.querySelector('.instracta-carousel__track');
            all('[data-instracta-dir]', box).forEach(btn => btn.addEventListener('click', () => {
                const card = track.firstElementChild;
                const step = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
                track.scrollBy({left: Number(btn.dataset.instractaDir) * step, behavior: reduced() ? 'auto' : 'smooth'});
            }));
        });
    };

    const embeds = scope => {
        all('[data-instracta-embed]', scope).forEach(btn => {
            if (btn.dataset.instractaBound) {
                return;
            }
            btn.dataset.instractaBound = '1';
            btn.addEventListener('click', () => {
                const url = btn.getAttribute('data-instracta-embed');
                if (!/^https:\/\//i.test(url)) {
                    return;
                }
                const frame = document.createElement('iframe');
                frame.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'autoplay=1';
                frame.title = btn.getAttribute('data-instracta-title') || '';
                frame.allow = 'autoplay; accelerometer; encrypted-media; picture-in-picture; fullscreen';
                frame.setAttribute('allowfullscreen', '');
                btn.replaceWith(frame);
            });
        });
    };

    const enhance = scope => {
        watch(scope);
        carousels(scope);
        embeds(scope);
    };

    // Builder preview bridge.

    const inPreview = () => document.body.classList.contains('instracta-preview');

    const markSelected = (id, scroll) => {
        all('.instracta-sec.is-selected').forEach(el => el.classList.remove('is-selected'));
        if (!id) {
            return;
        }
        const el = document.querySelector('[data-instracta-section="' + id + '"]');
        if (el) {
            el.classList.add('is-selected');
            if (scroll) {
                el.scrollIntoView({behavior: reduced() ? 'auto' : 'smooth', block: 'center'});
            }
        }
    };

    const setHtml = (region, html) => {
        const el = document.querySelector('[data-region=' + region + ']');
        if (!el) {
            return;
        }
        const range = document.createRange();
        range.selectNodeContents(el);
        el.replaceChildren(range.createContextualFragment(html || ''));
        enhance(el);
        all('.instracta-anim-fade, .instracta-anim-up, .instracta-anim-zoom', el).forEach(n => n.classList.add('is-in'));
    };

    const previewBridge = () => {
        let selected = null;
        window.addEventListener('message', e => {
            const d = e.data;
            if (e.origin !== window.location.origin || !d || d.source !== 'instracta-builder') {
                return;
            }
            if (d.type === 'instracta:render') {
                const y = window.scrollY;
                setHtml('instracta-before', d.before);
                setHtml('instracta-after', d.after);
                const main = document.querySelector('[data-region=instracta-main]');
                if (main) {
                    main.classList.toggle('d-none', !d.hasmain);
                }
                markSelected(selected, false);
                window.scrollTo(0, y);
            } else if (d.type === 'instracta:select') {
                selected = d.id;
                markSelected(d.id, d.scroll);
            }
        });
        document.addEventListener('click', e => {
            const sec = e.target.closest('[data-instracta-section]');
            if (sec) {
                e.preventDefault();
                selected = sec.getAttribute('data-instracta-section');
                markSelected(selected, false);
                window.parent.postMessage({type: 'instracta:select', id: selected}, window.location.origin);
            }
        }, true);
        window.parent.postMessage({type: 'instracta:ready'}, window.location.origin);
    };

    return {
        /**
         * Initialise the home page behaviour.
         */
        init: function() {
            enhance(document);
            if (inPreview() && window.parent !== window) {
                previewBridge();
            }
        },
    };
});
