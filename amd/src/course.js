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
 * Course page "focus" layout: one section at a time, with a section bar and previous/next buttons.
 *
 * The layout itself is chosen with the data-i-cl attribute on <html>. This module only builds the
 * navigation and tells the page which section is current; the CSS does the showing and hiding.
 *
 * @module     theme_instracta/course
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const root = document.documentElement;
    let strings = {};
    let current = 0;
    let nav = null;
    let pager = null;
    let observer = null;
    let busy = false;

    const s = (key, fallback) => strings[key] || fallback || key;

    const h = (tag, attrs, kids) => {
        const el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(k => {
            const v = attrs[k];
            if (v === null || v === undefined || v === false) {
                return;
            }
            if (k === 'class') {
                el.className = v;
            } else if (k === 'text') {
                el.textContent = v;
            } else if (k === 'on') {
                Object.keys(v).forEach(ev => el.addEventListener(ev, v[ev]));
            } else {
                el.setAttribute(k, v === true ? '' : v);
            }
        });
        [].concat(kids === undefined ? [] : kids).forEach(c => {
            if (c !== null && c !== undefined && c !== false) {
                el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            }
        });
        return el;
    };

    const isActive = () => root.getAttribute('data-i-cl') === 'focus';
    const content = () => document.querySelector('.course-content');
    const list = () => {
        const c = content();
        return c ? c.querySelector('ul.section-list, ul.topics, ul.weeks') : null;
    };
    const sections = () => {
        const l = list();
        return l ? Array.prototype.slice.call(l.children).filter(li => li.matches('li.course-section')) : [];
    };

    const storeKey = () => 'instracta-focus-' + ((window.M && window.M.cfg && window.M.cfg.courseId) || '0');

    const sectionName = (li, i) => {
        const el = li.querySelector('.sectionname');
        const text = el ? el.textContent.trim() : '';
        return text || (i === 0 ? s('fn_intro', 'Intro') : s('fn_section', 'Section') + ' ' + i);
    };

    const isDone = li => {
        const buttons = li.querySelectorAll('[data-region="completion-info"] button, .completion-dropdown button');
        if (!buttons.length) {
            return false;
        }
        return Array.prototype.every.call(buttons, b => b.classList.contains('btn-success'));
    };

    const initialIndex = secs => {
        const m = /section-(\d+)/.exec(window.location.hash || '');
        if (m) {
            const i = secs.findIndex(li => li.id === 'section-' + m[1]);
            if (i >= 0) {
                return i;
            }
        }
        try {
            const saved = parseInt(window.sessionStorage.getItem(storeKey()), 10);
            if (!isNaN(saved) && saved >= 0 && saved < secs.length) {
                return saved;
            }
        } catch (e) {
            // No storage: start from the beginning.
        }
        return 0;
    };

    const go = i => {
        const secs = sections();
        if (i < 0 || i >= secs.length) {
            return;
        }
        current = i;
        try {
            window.sessionStorage.setItem(storeKey(), String(i));
        } catch (e) {
            // Ignore.
        }
        update();
        const top = content();
        if (top && top.getBoundingClientRect().top < 0) {
            top.scrollIntoView({behavior: 'auto', block: 'start'});
        }
    };

    const buildNav = secs => {
        nav = h('nav', {class: 'instracta-focusnav', 'aria-label': s('fn_label', 'Course sections')}, [
            h('ul', {class: 'instracta-focusnav__list'}, secs.map((li, i) => h('li', {}, [
                h('button', {type: 'button', class: 'instracta-focusnav__item', title: sectionName(li, i),
                    'data-index': String(i), text: i === 0 ? s('fn_intro', 'Intro') : String(i),
                    on: {click: () => go(i)}}),
            ]))),
        ]);
        pager = h('div', {class: 'instracta-focusnav__pager'}, [
            h('button', {type: 'button', class: 'btn btn-secondary', 'data-dir': 'prev', on: {click: () => go(current - 1)}}),
            h('button', {type: 'button', class: 'btn btn-primary', 'data-dir': 'next', on: {click: () => go(current + 1)}}),
        ]);
    };

    const update = () => {
        const secs = sections();
        if (!secs.length || !nav) {
            return;
        }
        busy = true;
        secs.forEach((li, i) => li.classList.toggle('is-current', i === current));
        Array.prototype.forEach.call(nav.querySelectorAll('.instracta-focusnav__item'), (b, i) => {
            b.classList.toggle('is-current', i === current);
            b.classList.toggle('is-done', !!secs[i] && isDone(secs[i]));
            if (i === current) {
                b.setAttribute('aria-current', 'step');
                if (b.scrollIntoView) {
                    const wrap = b.closest('.instracta-focusnav__list');
                    wrap.scrollLeft = b.offsetLeft - wrap.clientWidth / 2 + b.clientWidth / 2;
                }
            } else {
                b.removeAttribute('aria-current');
            }
        });
        const prev = pager.querySelector('[data-dir=prev]');
        const next = pager.querySelector('[data-dir=next]');
        prev.hidden = current <= 0;
        next.hidden = current >= secs.length - 1;
        if (current > 0) {
            prev.textContent = '← ' + sectionName(secs[current - 1], current - 1);
        }
        if (current < secs.length - 1) {
            next.textContent = sectionName(secs[current + 1], current + 1) + ' →';
        }
        setTimeout(() => {
            busy = false;
        }, 0);
    };

    const enable = () => {
        const c = content();
        const l = list();
        const secs = sections();
        if (!c || !l || !secs.length) {
            return;
        }
        if (!nav || !nav.isConnected || nav.querySelectorAll('.instracta-focusnav__item').length !== secs.length) {
            if (nav) {
                nav.remove();
                pager.remove();
            }
            buildNav(secs);
            l.parentNode.insertBefore(nav, l);
            l.parentNode.insertBefore(pager, l.nextSibling);
            current = initialIndex(secs);
        }
        c.classList.add('instracta-focus');
        update();
    };

    const disable = () => {
        const c = content();
        if (c) {
            c.classList.remove('instracta-focus');
        }
        sections().forEach(li => li.classList.remove('is-current'));
        if (nav) {
            nav.remove();
            pager.remove();
            nav = null;
            pager = null;
        }
    };

    // Course map: a sticky strip of section chips with progress and scroll tracking (all layouts except focus).
    let map = null;
    let spy = null;

    const counts = li => {
        const acts = li.querySelectorAll('li.activity:not(.modtype_label)');
        const btns = li.querySelectorAll('li.activity [data-region="completion-info"] button, li.activity .completion-dropdown button');
        const done = Array.prototype.filter.call(btns, b => b.classList.contains('btn-success')).length;
        return {total: acts.length, done: done, tracked: btns.length};
    };

    const buildMap = () => {
        const secs = sections();
        const l = list();
        if (!l || secs.length < 2) {
            return;
        }
        const sig = secs.map(li => li.id + ':' + counts(li).done + '/' + counts(li).total).join('|');
        if (map && map.isConnected && map.getAttribute('data-sig') === sig) {
            return;
        }
        if (map) {
            map.remove();
        }
        if (spy) {
            spy.disconnect();
        }
        map = h('nav', {class: 'instracta-map', 'aria-label': s('fn_label', 'Course sections'), 'data-sig': sig},
            h('ul', {class: 'instracta-map__list'}, secs.map((li, i) => {
                const c = counts(li);
                const full = c.tracked > 0 && c.done >= c.tracked;
                return h('li', {}, h('a', {
                    class: 'instracta-map__chip' + (full ? ' is-done' : ''), href: '#' + li.id, 'data-target': li.id,
                    on: {click: ev => {
                        ev.preventDefault();
                        const body = li.querySelector('.course-content-item-content');
                        if (body && !body.classList.contains('show')) {
                            const t = li.querySelector('.icons-collapse-expand');
                            if (t) {
                                t.click();
                            }
                        }
                        li.scrollIntoView({behavior: 'smooth', block: 'start'});
                    }},
                }, [
                    h('span', {class: 'instracta-map__name', text: sectionName(li, i)}),
                    c.total ? h('span', {class: 'instracta-map__count',
                        text: c.tracked ? c.done + '/' + c.total : String(c.total)}) : null,
                ]));
            })));
        l.parentNode.insertBefore(map, l);
        if (window.IntersectionObserver) {
            spy = new IntersectionObserver(entries => {
                entries.forEach(en => {
                    if (en.isIntersecting) {
                        Array.prototype.forEach.call(map.querySelectorAll('.instracta-map__chip'), a => {
                            const on = a.getAttribute('data-target') === en.target.id;
                            a.classList.toggle('is-current', on);
                            if (on && map.scrollWidth > map.clientWidth) {
                                const ul = map.firstChild;
                                ul.scrollLeft = a.offsetLeft - ul.clientWidth / 2 + a.clientWidth / 2;
                            }
                        });
                    }
                });
            }, {rootMargin: '-25% 0px -65% 0px'});
            secs.forEach(li => spy.observe(li));
        }
    };

    const dropMap = () => {
        if (map) {
            map.remove();
            map = null;
        }
        if (spy) {
            spy.disconnect();
            spy = null;
        }
    };

    const sync = () => {
        if (isActive()) {
            dropMap();
            enable();
        } else {
            disable();
            buildMap();
        }
    };

    return {
        /**
         * Start watching the course page.
         *
         * @param {Object} localStrings language strings (fn_*)
         */
        init: function(localStrings) {
            strings = localStrings || {};
            if (!document.body.classList.contains('path-course-view') && !document.querySelector('.course-content')) {
                return;
            }
            sync();
            document.addEventListener('instracta:prefs', sync);
            // The course page re-renders its sections after edits and completion changes.
            const c = content();
            if (c && window.MutationObserver) {
                observer = new MutationObserver(() => {
                    if (busy) {
                        return;
                    }
                    if (isActive()) {
                        enable();
                    } else {
                        buildMap();
                    }
                });
                observer.observe(c, {childList: true, subtree: true, attributes: true, attributeFilter: ['class']});
            }
        },
    };
});
