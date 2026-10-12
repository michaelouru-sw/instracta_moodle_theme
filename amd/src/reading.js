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
 * Reading tools: a small preferences panel for text size, spacing, line length, font, contrast,
 * motion, focus mode and (when the site allows it) course layouts.
 *
 * Choices are applied as data-i-* attributes on <html>, kept in localStorage and, for logged in
 * users, saved as a user preference so they follow the person between devices.
 *
 * @module     theme_instracta/reading
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core_user/repository'], function(UserRepo) {
    'use strict';

    const KEY = 'instracta-reading';
    const SIZES = ['90', '100', '110', '120', '135', '150'];
    const root = document.documentElement;
    const DEFAULTS = {size: '100', lh: 'default', measure: 'default', font: 'default', contrast: '0', motion: '0', focus: '0'};
    const LAYOUT_KEYS = ['cl', 'ch', 'cs', 'ci'];

    let cfg = null;
    let prefs = {};
    let panel = null;
    let button = null;
    let saveTimer = null;

    const s = (key, fallback) => (cfg.strings && cfg.strings[key]) || fallback || key;

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

    const load = () => {
        try {
            const stored = JSON.parse(window.localStorage.getItem(KEY) || '{}');
            prefs = stored && typeof stored === 'object' ? stored : {};
        } catch (e) {
            prefs = {};
        }
    };

    const effective = key => {
        if (prefs[key] !== undefined) {
            return prefs[key];
        }
        if (cfg.site && cfg.site[key] !== undefined) {
            return cfg.site[key];
        }
        return DEFAULTS[key];
    };

    const apply = key => {
        const v = effective(key);
        const isDefault = DEFAULTS[key] !== undefined && v === DEFAULTS[key];
        if (v === undefined || v === '' || isDefault) {
            root.removeAttribute('data-i-' + key);
        } else {
            root.setAttribute('data-i-' + key, v);
        }
    };

    const save = () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            const json = JSON.stringify(prefs);
            try {
                window.localStorage.setItem(KEY, json);
            } catch (e) {
                // Storage can be unavailable; the choice then lasts for this page only.
            }
            if (cfg.loggedin && UserRepo && UserRepo.setUserPreference) {
                UserRepo.setUserPreference(KEY, json).catch(() => null);
            }
        }, 500);
    };

    const set = (key, value) => {
        prefs[key] = String(value);
        apply(key);
        save();
        document.dispatchEvent(new CustomEvent('instracta:prefs', {detail: {key: key, value: String(value)}}));
        render();
    };

    const reset = () => {
        prefs = {};
        Object.keys(DEFAULTS).concat(LAYOUT_KEYS).forEach(apply);
        save();
        document.dispatchEvent(new CustomEvent('instracta:prefs', {detail: {key: 'all'}}));
        render();
    };

    const seg = (key, options) => h('div', {class: 'instracta-rt__seg', role: 'group', 'aria-label': s('rt_' + key)},
        options.map(o => h('button', {type: 'button', text: s('rt_' + key + '_' + o),
            'aria-pressed': String(effective(key)) === o ? 'true' : 'false', on: {click: () => set(key, o)}})));

    const sw = (key, label) => h('div', {class: 'instracta-rt__switch'}, [
        h('span', {id: 'instracta-rt-' + key, text: label}),
        h('button', {type: 'button', role: 'switch', 'aria-labelledby': 'instracta-rt-' + key,
            'aria-checked': effective(key) === '1' ? 'true' : 'false',
            on: {click: () => set(key, effective(key) === '1' ? '0' : '1')}}),
    ]);

    const select = (key, prefix, labelKey) => {
        const id = 'instracta-rt-sel-' + key;
        return h('div', {class: 'instracta-rt__group'}, [
            h('label', {class: 'instracta-rt__label', for: id, text: s(labelKey)}),
            h('select', {id: id, class: 'form-select', on: {change: e => set(key, e.target.value)}},
                (cfg.options[key] || []).map(o => h('option', {value: o, text: s(prefix + o, o),
                    selected: effective(key) === o ? 'selected' : null}))),
        ]);
    };

    const group = (label, control) => h('div', {class: 'instracta-rt__group'}, [
        h('span', {class: 'instracta-rt__label', text: label}),
        control,
    ]);

    const stepSize = delta => {
        const i = Math.max(0, Math.min(SIZES.length - 1, SIZES.indexOf(String(effective('size'))) + delta));
        set('size', SIZES[i]);
    };

    const render = () => {
        if (!panel) {
            return;
        }
        const hadFocus = panel.contains(document.activeElement) ? document.activeElement.id : '';
        while (panel.firstChild) {
            panel.removeChild(panel.firstChild);
        }
        const size = String(effective('size'));
        const idx = SIZES.indexOf(size);
        panel.appendChild(h('div', {class: 'instracta-rt__head'}, [
            h('h2', {id: 'instracta-rt-title', text: s('rt_title', 'Reading tools')}),
            h('button', {type: 'button', class: 'instracta-rt__close', 'aria-label': s('rt_close', 'Close'),
                on: {click: () => toggle(false)}}, [h('i', {class: 'fa-solid fa-xmark', 'aria-hidden': 'true'})]),
        ]));
        panel.appendChild(h('p', {class: 'instracta-rt__preview', text: s('rt_sample')}));
        panel.appendChild(h('div', {class: 'instracta-rt__group'}, [
            h('span', {class: 'instracta-rt__label', text: s('rt_size')}),
            h('div', {class: 'instracta-rt__size'}, [
                h('button', {type: 'button', 'aria-label': s('rt_smaller'), disabled: idx <= 0 ? 'disabled' : null,
                    on: {click: () => stepSize(-1)}}, [h('span', {'aria-hidden': 'true', text: 'A−'})]),
                h('output', {'aria-live': 'polite', text: size + '%'}),
                h('button', {type: 'button', 'aria-label': s('rt_larger'),
                    disabled: idx >= SIZES.length - 1 ? 'disabled' : null, on: {click: () => stepSize(1)}},
                [h('span', {'aria-hidden': 'true', text: 'A+'})]),
            ]),
        ]));
        panel.appendChild(group(s('rt_lh'), seg('lh', ['default', 'relaxed', 'loose'])));
        panel.appendChild(group(s('rt_measure'), seg('measure', ['narrow', 'default', 'wide', 'full'])));
        panel.appendChild(group(s('rt_font'), seg('font', ['default', 'easy', 'serif'])));
        panel.appendChild(h('div', {class: 'instracta-rt__group'}, [
            sw('contrast', s('rt_contrast')),
            sw('motion', s('rt_motion')),
            sw('focus', s('rt_focus')),
        ]));
        if (cfg.layouts) {
            panel.appendChild(select('cl', 'cl_', 'rt_courselayout'));
            panel.appendChild(select('ch', 'ch_', 'rt_courseheader'));
            panel.appendChild(select('cs', 'cs_', 'rt_cardstyle'));
            panel.appendChild(select('ci', 'ci_', 'rt_courseindex'));
        }
        panel.appendChild(h('div', {class: 'instracta-rt__foot'}, [
            h('button', {type: 'button', class: 'btn btn-link p-0', text: s('rt_reset', 'Reset to defaults'),
                on: {click: reset}}),
            h('button', {type: 'button', class: 'btn btn-primary btn-sm', text: s('rt_done', 'Done'),
                on: {click: () => toggle(false)}}),
        ]));
        if (hadFocus) {
            const again = panel.querySelector('#' + hadFocus);
            if (again) {
                again.focus();
            }
        }
    };

    const onDocClick = e => {
        if (panel && !panel.hidden && !panel.contains(e.target) && !button.contains(e.target)) {
            toggle(false);
        }
    };

    const onKey = e => {
        if (e.key === 'Escape' && panel && !panel.hidden) {
            toggle(false);
            button.focus();
        }
    };

    const toggle = open => {
        const show = open === undefined ? panel.hidden : open;
        panel.hidden = !show;
        button.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) {
            render();
            const title = panel.querySelector('#instracta-rt-title');
            if (title) {
                title.setAttribute('tabindex', '-1');
                title.focus();
            }
        }
    };

    return {
        /**
         * Add the reading tools button and panel to the page.
         *
         * @param {Object} config theme configuration from the init call
         * @param {Element} host the element the button is inserted into
         */
        init: function(config, host) {
            cfg = config;
            if (!host || document.querySelector('.instracta-rt-toggle')) {
                return;
            }
            load();
            button = h('button', {type: 'button', class: 'instracta-rt-toggle', 'aria-haspopup': 'dialog',
                'aria-expanded': 'false', 'aria-controls': 'instracta-rt-panel', title: s('rt_title', 'Reading tools'),
                on: {click: () => toggle()}}, [
                h('span', {'aria-hidden': 'true'}, ['A', h('small', {text: 'a'})]),
                h('span', {class: 'visually-hidden', text: s('rt_title', 'Reading tools')}),
            ]);
            panel = h('div', {id: 'instracta-rt-panel', class: 'instracta-rt', role: 'dialog',
                'aria-labelledby': 'instracta-rt-title', hidden: 'hidden'});
            host.insertBefore(button, host.firstChild);
            document.body.appendChild(panel);
            document.addEventListener('click', onDocClick);
            document.addEventListener('keydown', onKey);
        },
    };
});
