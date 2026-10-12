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
 * Small page helpers: notices, back to top, reading progress and time, external link marks and the cookie notice.
 *
 * @module     theme_instracta/features
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const store = {
        get: key => {
            try {
                return window.localStorage.getItem(key);
            } catch (e) {
                return null;
            }
        },
        set: (key, value) => {
            try {
                window.localStorage.setItem(key, value);
            } catch (e) {
                // Storage can be blocked; the choice then lasts for this page only.
            }
        },
    };

    const make = (tag, attrs, text) => {
        const el = document.createElement(tag);
        Object.keys(attrs || {}).forEach(k => el.setAttribute(k, attrs[k]));
        if (text) {
            el.textContent = text;
        }
        return el;
    };

    const notices = () => {
        const box = document.querySelector('[data-instracta-notices]');
        if (!box) {
            return;
        }
        let gone = [];
        try {
            gone = JSON.parse(store.get('instracta-dismissed') || '[]') || [];
        } catch (e) {
            gone = [];
        }
        box.querySelectorAll('[data-notice]').forEach(n => {
            if (gone.indexOf(n.getAttribute('data-notice')) !== -1) {
                n.remove();
            }
        });
        if (!box.children.length) {
            box.remove();
            return;
        }
        box.addEventListener('click', e => {
            const btn = e.target.closest('[data-action="dismiss"]');
            if (!btn) {
                return;
            }
            const n = btn.closest('[data-notice]');
            gone.push(n.getAttribute('data-notice'));
            store.set('instracta-dismissed', JSON.stringify(gone.slice(-30)));
            n.remove();
            if (!box.children.length) {
                box.remove();
            }
        });
        const host = document.getElementById('topofscroll') || document.getElementById('region-main-box') ||
            document.querySelector('[role="main"]');
        if (host) {
            host.insertBefore(box, host.firstChild);
        }
        box.hidden = false;
    };

    const backToTop = label => {
        const btn = make('button', {type: 'button', class: 'instracta-top', 'aria-label': label, hidden: 'hidden'});
        btn.appendChild(make('i', {class: 'fa-solid fa-arrow-up', 'aria-hidden': 'true'}));
        btn.addEventListener('click', () => {
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({top: 0, behavior: reduce ? 'auto' : 'smooth'});
        });
        document.body.appendChild(btn);
        return () => {
            btn.hidden = window.scrollY < 700;
        };
    };

    const progressBar = () => {
        const bar = make('div', {class: 'instracta-readbar', 'aria-hidden': 'true'});
        const fill = make('div', {class: 'instracta-readbar__fill'});
        bar.appendChild(fill);
        document.body.appendChild(bar);
        return () => {
            const max = document.documentElement.scrollHeight - window.innerHeight;
            const long = max > window.innerHeight * 0.6;
            bar.hidden = !long;
            fill.style.width = long ? Math.min(100, Math.max(0, window.scrollY / max * 100)) + '%' : '0';
        };
    };

    const readingTime = strings => {
        if (!/path-mod-(page|book|lesson|wiki)/.test(document.body.className)) {
            return;
        }
        const area = document.querySelector('#region-main .box.generalbox, #region-main .book_content, ' +
            '#region-main .no-overflow');
        const main = document.querySelector('#region-main [role="main"]') || document.getElementById('region-main');
        if (!area || !main) {
            return;
        }
        const words = (area.textContent || '').trim().split(/\s+/).filter(Boolean).length;
        if (words < 120) {
            return;
        }
        const minutes = Math.max(1, Math.round(words / 220));
        const text = (strings.ft_readtime || '{$a} min read').replace('{$a}', String(minutes));
        const pill = make('p', {class: 'instracta-readtime'});
        pill.appendChild(make('i', {class: 'fa-regular fa-clock', 'aria-hidden': 'true'}));
        pill.appendChild(document.createTextNode(' ' + text));
        main.insertBefore(pill, main.firstChild);
    };

    const externalLinks = strings => {
        document.querySelectorAll('#region-main .no-overflow a[href^="http"], #region-main .text_to_html a[href^="http"]')
            .forEach(a => {
                if (a.hostname && a.hostname !== window.location.hostname && !a.querySelector('img')) {
                    a.classList.add('instracta-ext');
                    a.setAttribute('rel', 'noopener');
                    a.setAttribute('title', strings.ft_external || 'Opens another website');
                }
            });
    };

    const cookies = () => {
        const box = document.querySelector('[data-instracta-cookie]');
        if (!box) {
            return;
        }
        if (store.get('instracta-cookie') === '1') {
            box.remove();
            return;
        }
        box.hidden = false;
        box.addEventListener('click', e => {
            if (e.target.closest('[data-action="accept"]')) {
                store.set('instracta-cookie', '1');
                box.remove();
            }
        });
    };

    return {
        /**
         * Start the helpers.
         *
         * @param {Array} flags back to top, reading progress, reading time, external links (0 or 1 each)
         * @param {Object} strings language strings
         */
        init: function(flags, strings) {
            if (document.body.classList.contains('instracta-preview')) {
                return;
            }
            const f = flags || [];
            const s = strings || {};
            const handlers = [];
            notices();
            cookies();
            if (f[0]) {
                handlers.push(backToTop(s.ft_top || 'Back to top'));
            }
            if (f[1]) {
                handlers.push(progressBar());
            }
            if (f[2]) {
                readingTime(s);
            }
            if (f[3]) {
                externalLinks(s);
            }
            if (handlers.length) {
                let waiting = false;
                const run = () => {
                    waiting = false;
                    handlers.forEach(h => h());
                };
                const onScroll = () => {
                    if (!waiting) {
                        waiting = true;
                        window.requestAnimationFrame(run);
                    }
                };
                window.addEventListener('scroll', onScroll, {passive: true});
                window.addEventListener('resize', onScroll);
                run();
            }
        },
    };
});
