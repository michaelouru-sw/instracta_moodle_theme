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
 * Theme behaviour: colour mode toggle and navbar scroll state.
 *
 * @module     theme_instracta/theme
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['theme_instracta/home', 'theme_instracta/reading', 'theme_instracta/course', 'theme_instracta/features',
    'core_user/repository'], function(Home, Reading, Course, Features, UserRepo) {
    'use strict';

    let strs = {};

    const root = document.documentElement;

    const applyMode = mode => {
        const dark = mode === 'dark' ||
            (mode === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.setAttribute('data-instracta-theme', dark ? 'dark' : 'light');
        root.setAttribute('data-instracta-mode', mode);
    };

    const icon = name => {
        const i = document.createElement('i');
        i.className = 'fa-solid fa-' + name;
        i.setAttribute('aria-hidden', 'true');
        return i;
    };

    const addToggle = cfg => {
        if (document.querySelector('.instracta-mode-toggle')) {
            return;
        }
        const host = document.getElementById('usernavigation') || document.querySelector('.navbar .navbar-nav:last-of-type');
        if (!host) {
            return;
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'instracta-mode-toggle';
        const light = icon('moon');
        light.classList.add('instracta-icon-light');
        const dark = icon('sun');
        dark.classList.add('instracta-icon-dark');
        const label = document.createElement('span');
        label.className = 'visually-hidden';
        btn.append(light, dark, label);

        const sync = () => {
            const isDark = root.getAttribute('data-instracta-theme') === 'dark';
            label.textContent = isDark ? strs.colormode_light : strs.colormode_dark;
            btn.title = strs.colormode_toggle;
            btn.setAttribute('aria-label', strs.colormode_toggle + ': ' + label.textContent);
        };
        btn.addEventListener('click', () => {
            const next = root.getAttribute('data-instracta-theme') === 'dark' ? 'light' : 'dark';
            applyMode(next);
            sync();
            try {
                window.localStorage.setItem('instracta-color-mode', next);
            } catch (e) {
                // Storage can be unavailable; the choice then lasts for this page only.
            }
            if (cfg.loggedin && UserRepo && UserRepo.setUserPreference) {
                UserRepo.setUserPreference('instracta-color-mode', next).catch(() => null);
            }
        });
        host.insertBefore(btn, host.firstChild);
        sync();
    };

    const watchScroll = () => {
        const page = document.getElementById('page');
        const update = () => {
            const y = Math.max(window.scrollY || 0, page ? page.scrollTop : 0);
            document.body.classList.toggle('instracta-scrolled', y > 8);
        };
        window.addEventListener('scroll', update, {passive: true});
        if (page) {
            page.addEventListener('scroll', update, {passive: true});
        }
        update();
    };

    return {
        /**
         * Initialise the theme behaviour.
         *
         * @param {Object} cfg toggle, sticky, mode, loggedin, strings
         */
        init: function(cfg) {
            strs = (window.M && window.M.str && window.M.str.theme_instracta) || {};
            cfg.strings = strs;
            root.classList.add('instracta-js');
            if (cfg.toggle) {
                addToggle(cfg);
                if (window.matchMedia) {
                    const mq = window.matchMedia('(prefers-color-scheme: dark)');
                    const onChange = () => {
                        if (root.getAttribute('data-instracta-mode') === 'auto') {
                            applyMode('auto');
                        }
                    };
                    if (mq.addEventListener) {
                        mq.addEventListener('change', onChange);
                    }
                }
            }
            watchScroll();
            Home.init();
            if (cfg.tools) {
                const host = document.getElementById('usernavigation') || document.querySelector('.navbar .navbar-nav:last-of-type');
                Reading.init(cfg, host);
            }
            Course.init(strs);
            Features.init(cfg.f, strs);
        },
    };
});
