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
 * Home page builder: the visual editor.
 *
 * Builds the whole interface with DOM calls (never innerHTML with data), keeps the layout in one
 * object, autosaves a draft and streams unsaved layouts to the preview iframe through postMessage.
 *
 * @module     theme_instracta/builder
 * @copyright  2026 Instracta eLearning Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const RENDER_DELAY = 200;
    const SAVE_DELAY = 1200;
    const COALESCE_MS = 900;
    const MAX_UNDO = 80;

    let cfg = null;
    let root = null;
    let frame = null;
    const S = {
        layout: {v: 1, sections: []},
        selected: null,
        tab: 'sections',
        insTab: 'content',
        dirty: false,
        haschanges: false,
        saving: false,
        resave: false,
        frameReady: false,
        undo: [],
        redo: [],
        lastKey: '',
        lastTime: 0,
        snap: '',
        revisions: [],
        open: new WeakSet(),
        names: {},
    };
    let renderTimer = null;
    let saveTimer = null;
    let renderSeq = 0;

    // Helpers.

    const str = (key, fallback) => (cfg.strings && cfg.strings[key]) || fallback || key;

    /**
     * Create an element.
     *
     * @param {string} tag
     * @param {Object} [attrs] class, text, data, on, or any attribute
     * @param {Array|Node|string} [kids]
     * @return {HTMLElement}
     */
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
            } else if (k === 'data') {
                Object.keys(v).forEach(d => {
                    el.dataset[d] = v[d];
                });
            } else if (k === 'on') {
                Object.keys(v).forEach(ev => el.addEventListener(ev, v[ev]));
            } else if (k === 'value') {
                el.value = v;
            } else {
                el.setAttribute(k, v === true ? '' : v);
            }
        });
        [].concat(kids === undefined ? [] : kids).forEach(c => {
            if (c === null || c === undefined || c === false) {
                return;
            }
            el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return el;
    };

    const icon = (name, extra) => h('i', {class: 'fa-solid fa-' + name + (extra ? ' ' + extra : ''), 'aria-hidden': 'true'});
    const clone = o => JSON.parse(JSON.stringify(o));
    const qs = (sel, scope) => (scope || root).querySelector(sel);
    const qsa = (sel, scope) => Array.prototype.slice.call((scope || root).querySelectorAll(sel));
    const clear = el => {
        while (el.firstChild) {
            el.removeChild(el.firstChild);
        }
        return el;
    };
    const newId = () => 's' + Array.prototype.map.call(crypto.getRandomValues(new Uint8Array(5)),
        b => ('0' + b.toString(16)).slice(-2)).join('');
    const defOf = type => cfg.schema.types[type];
    const current = () => S.layout.sections.find(s => s.id === S.selected) || null;
    const indexOf = id => S.layout.sections.findIndex(s => s.id === id);

    const debounce = (fn, ms) => {
        let t = null;
        const wrapped = function() {
            const args = arguments;
            clearTimeout(t);
            t = setTimeout(() => fn.apply(null, args), ms);
        };
        wrapped.cancel = () => clearTimeout(t);
        return wrapped;
    };

    // Network.

    /**
     * Call the AJAX endpoint.
     *
     * @param {string} action
     * @param {Object} [params]
     * @param {File} [file]
     * @return {Promise<Object>}
     */
    const api = (action, params, file) => {
        const body = new FormData();
        body.append('sesskey', cfg.sesskey);
        body.append('action', action);
        Object.keys(params || {}).forEach(k => {
            body.append(k, typeof params[k] === 'object' ? JSON.stringify(params[k]) : params[k]);
        });
        if (file) {
            body.append('file', file);
        }
        return fetch(cfg.ajaxurl, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(r => r.json().catch(() => ({error: str('b_err_generic')})).then(j => {
                if (!r.ok || j.error) {
                    throw new Error(j.error || str('b_err_generic'));
                }
                return j;
            }));
    };

    // Status, toasts, modal.

    const setStatus = (kind, text) => {
        const el = qs('[data-region=status]');
        el.className = 'lb-status is-' + kind;
        el.textContent = text;
    };

    const refreshStatus = () => {
        if (S.saving) {
            setStatus('saving', str('b_saving', 'Saving…'));
        } else if (S.dirty) {
            setStatus('dirty', str('b_unsaved', 'Unsaved changes'));
        } else if (S.haschanges) {
            setStatus('saved', str('b_draftsaved', 'Draft saved · not published'));
        } else {
            setStatus('saved', str('b_published_state', 'All changes published'));
        }
        const pub = qs('[data-action=publish]');
        pub.disabled = !(S.dirty || S.haschanges);
    };

    const toast = (text, error) => {
        const t = h('div', {class: 'lb-toast' + (error ? ' lb-toast--error' : ''), role: error ? 'alert' : 'status', text: text});
        qs('[data-region=toasts]').appendChild(t);
        setTimeout(() => t.remove(), error ? 6000 : 3200);
    };

    let closeModal = null;

    /**
     * Open a modal dialog.
     *
     * @param {Object} o title, body (Node), foot (Node[]), wide
     * @return {Function} close
     */
    const openModal = o => {
        if (closeModal) {
            closeModal();
        }
        const modal = qs('[data-region=modal]');
        const previous = document.activeElement;
        const close = () => {
            modal.hidden = true;
            clear(modal);
            closeModal = null;
            if (previous && previous.focus) {
                previous.focus();
            }
        };
        const dialog = h('div', {class: 'lb-dialog' + (o.wide ? ' lb-dialog--wide' : ''), role: 'dialog', 'aria-modal': 'true',
            'aria-label': o.title}, [
            h('div', {class: 'lb-dialog__head'}, [
                h('h2', {text: o.title}),
                h('button', {type: 'button', class: 'lb-icon-btn', 'aria-label': str('b_close', 'Close'), on: {click: close}},
                    [icon('xmark')]),
            ]),
            h('div', {class: 'lb-dialog__body'}, o.body),
            o.foot ? h('div', {class: 'lb-dialog__foot'}, o.foot) : null,
        ]);
        modal.addEventListener('mousedown', e => {
            if (e.target === modal) {
                close();
            }
        }, {once: true});
        clear(modal).appendChild(dialog);
        modal.hidden = false;
        closeModal = close;
        const first = dialog.querySelector('input,textarea,button.lb-btn--primary,button.lb-btn');
        if (first) {
            first.focus();
        }
        return close;
    };

    const confirmDialog = (message, okLabel, danger) => new Promise(resolve => {
        let done = false;
        const finish = v => {
            if (!done) {
                done = true;
                resolve(v);
            }
        };
        const close = openModal({
            title: str('b_confirm', 'Please confirm'),
            body: h('p', {text: message}),
            foot: [
                h('button', {type: 'button', class: 'lb-btn', text: str('b_cancel', 'Cancel'),
                    on: {click: () => {
                        close();
                        finish(false);
                    }}}),
                h('button', {type: 'button', class: 'lb-btn ' + (danger ? 'lb-btn--danger' : 'lb-btn--primary'), text: okLabel,
                    on: {click: () => {
                        close();
                        finish(true);
                    }}}),
            ],
        });
    });

    // Preview bridge.

    const post = msg => {
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage(Object.assign({source: 'instracta-builder'}, msg), window.location.origin);
        }
    };

    const doRender = () => {
        const seq = ++renderSeq;
        api('render', {layout: S.layout}).then(res => {
            if (seq !== renderSeq) {
                return;
            }
            post({type: 'instracta:render', before: res.before, after: res.after, hasmain: res.hasmain});
            if (S.selected) {
                post({type: 'instracta:select', id: S.selected, scroll: false});
            }
        }).catch(e => setStatus('error', e.message));
    };

    const scheduleRender = () => {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(doRender, RENDER_DELAY);
    };

    // Saving.

    const saveNow = () => {
        clearTimeout(saveTimer);
        if (S.saving) {
            S.resave = true;
            return Promise.resolve();
        }
        if (!S.dirty) {
            return Promise.resolve();
        }
        S.saving = true;
        S.dirty = false;
        refreshStatus();
        return api('save', {layout: S.layout}).then(res => {
            S.haschanges = !!res.haschanges;
        }).catch(e => {
            S.dirty = true;
            setStatus('error', e.message);
            toast(e.message, true);
        }).then(() => {
            S.saving = false;
            if (S.resave || S.dirty) {
                S.resave = false;
                return saveNow();
            }
            refreshStatus();
            return null;
        });
    };

    const scheduleSave = () => {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNow, SAVE_DELAY);
    };

    // Change tracking and undo.

    /**
     * Apply a change to the layout.
     *
     * @param {Function} fn mutates S.layout
     * @param {string} [key] consecutive changes with the same key are merged into one undo step
     * @param {Object} [opts] {lists: bool} refresh the section list
     */
    const mutate = (fn, key) => {
        const before = JSON.stringify(S.layout);
        fn();
        const after = JSON.stringify(S.layout);
        if (before === after) {
            return;
        }
        const now = Date.now();
        if (!(key && key === S.lastKey && now - S.lastTime < COALESCE_MS)) {
            S.undo.push(before);
            if (S.undo.length > MAX_UNDO) {
                S.undo.shift();
            }
        }
        S.lastKey = key || '';
        S.lastTime = now;
        S.redo = [];
        changed();
    };

    /**
     * Record an edit made directly on the layout object by an inspector field.
     *
     * @param {string} key coalescing key
     */
    const edited = key => {
        const now = Date.now();
        if (!(key === S.lastKey && now - S.lastTime < COALESCE_MS)) {
            S.undo.push(S.snap);
            if (S.undo.length > MAX_UNDO) {
                S.undo.shift();
            }
        }
        S.lastKey = key;
        S.lastTime = now;
        S.redo = [];
        changed();
    };

    const changed = () => {
        S.snap = JSON.stringify(S.layout);
        S.dirty = true;
        refreshStatus();
        refreshUndo();
        renderSections();
        scheduleRender();
        scheduleSave();
    };

    const refreshUndo = () => {
        qs('[data-action=undo]').disabled = !S.undo.length;
        qs('[data-action=redo]').disabled = !S.redo.length;
    };

    const applySnapshot = json => {
        S.layout = JSON.parse(json);
        if (S.selected && indexOf(S.selected) < 0) {
            S.selected = null;
        }
        S.lastKey = '';
        renderInspector();
        changed();
    };

    const undo = () => {
        if (!S.undo.length) {
            return;
        }
        S.redo.push(JSON.stringify(S.layout));
        applySnapshot(S.undo.pop());
    };

    const redo = () => {
        if (!S.redo.length) {
            return;
        }
        S.undo.push(JSON.stringify(S.layout));
        applySnapshot(S.redo.pop());
    };

    // Section operations.

    const select = (id, scroll) => {
        S.selected = id;
        renderSections();
        renderInspector();
        post({type: 'instracta:select', id: id, scroll: scroll !== false});
    };

    const addSection = type => {
        const def = defOf(type);
        const section = {
            id: newId(),
            type: type,
            label: '',
            hidden: 0,
            props: clone(def.defaults),
            style: clone(def.styledefaults),
        };
        mutate(() => {
            const at = S.selected ? indexOf(S.selected) + 1 : S.layout.sections.length;
            S.layout.sections.splice(at, 0, section);
        });
        setTab('sections');
        select(section.id);
    };

    const moveSection = (id, delta) => {
        const from = indexOf(id);
        const to = from + delta;
        if (from < 0 || to < 0 || to >= S.layout.sections.length) {
            return;
        }
        mutate(() => {
            const item = S.layout.sections.splice(from, 1)[0];
            S.layout.sections.splice(to, 0, item);
        });
    };

    const duplicateSection = id => {
        const src = S.layout.sections.find(s => s.id === id);
        if (!src) {
            return;
        }
        const copy = clone(src);
        copy.id = newId();
        mutate(() => S.layout.sections.splice(indexOf(id) + 1, 0, copy));
        select(copy.id);
    };

    const toggleHidden = id => {
        mutate(() => {
            const s = S.layout.sections.find(x => x.id === id);
            s.hidden = s.hidden ? 0 : 1;
        });
    };

    const deleteSection = id => {
        confirmDialog(str('b_confirm_delete', 'Delete this section?'), str('b_delete', 'Delete'), true).then(ok => {
            if (!ok) {
                return;
            }
            mutate(() => {
                S.layout.sections.splice(indexOf(id), 1);
            });
            if (S.selected === id) {
                S.selected = null;
                renderInspector();
            }
        });
    };

    const moveTo = (id, targetId, after) => {
        if (id === targetId) {
            return;
        }
        mutate(() => {
            const item = S.layout.sections.splice(indexOf(id), 1)[0];
            let at = indexOf(targetId);
            if (after) {
                at++;
            }
            S.layout.sections.splice(at, 0, item);
        });
    };

    // Left panel.

    const setTab = tab => {
        S.tab = tab;
        qsa('[data-action=tab]').forEach(b => {
            const on = b.dataset.tab === tab;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        qsa('[data-panel]').forEach(p => {
            p.hidden = p.dataset.panel !== tab;
        });
        if (tab === 'add') {
            const search = qs('.lb-search');
            if (search) {
                search.focus();
            }
        }
    };

    const sectionSummary = s => {
        const p = s.props || {};
        const text = p.title || p.eyebrow || p.text || '';
        return String(text).replace(/<[^>]*>/g, '').slice(0, 60);
    };

    const toolButton = (iconName, label, fn, extra) => h('button', {type: 'button',
        class: 'lb-tool' + (extra ? ' ' + extra : ''), title: label, 'aria-label': label,
        on: {click: e => {
            e.stopPropagation();
            fn();
        }}}, [icon(iconName)]);

    let dragId = null;

    const renderSections = () => {
        const panel = qs('[data-panel=sections]');
        const keepScroll = panel.scrollTop;
        clear(panel);
        const list = S.layout.sections;
        if (!list.length) {
            panel.appendChild(h('div', {class: 'lb-empty'}, [
                icon('layer-group'),
                h('p', {text: str('b_nosections', 'This page has no sections yet.')}),
                h('button', {type: 'button', class: 'lb-btn lb-btn--primary', text: str('b_addsection', 'Add a section'),
                    on: {click: () => setTab('add')}}),
            ]));
            return;
        }
        const ul = h('div', {class: 'lb-sections', role: 'list'});
        list.forEach((s, i) => {
            const def = defOf(s.type);
            if (!def) {
                return;
            }
            const row = h('div', {
                class: 'lb-section' + (s.id === S.selected ? ' is-selected' : '') + (s.hidden ? ' is-hidden' : ''),
                role: 'listitem', tabindex: '0', draggable: 'true', data: {id: s.id},
                'aria-current': s.id === S.selected ? 'true' : null,
                on: {
                    click: () => select(s.id),
                    keydown: e => {
                        if (e.target !== row) {
                            return;
                        }
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            select(s.id);
                        } else if (e.key === 'ArrowUp' && e.altKey) {
                            e.preventDefault();
                            moveSection(s.id, -1);
                            focusRow(s.id);
                        } else if (e.key === 'ArrowDown' && e.altKey) {
                            e.preventDefault();
                            moveSection(s.id, 1);
                            focusRow(s.id);
                        } else if (e.key === 'ArrowUp' && row.previousElementSibling) {
                            e.preventDefault();
                            row.previousElementSibling.focus();
                        } else if (e.key === 'ArrowDown' && row.nextElementSibling) {
                            e.preventDefault();
                            row.nextElementSibling.focus();
                        } else if (e.key === 'Delete') {
                            deleteSection(s.id);
                        }
                    },
                    dragstart: e => {
                        dragId = s.id;
                        row.classList.add('is-dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', s.id);
                    },
                    dragend: () => {
                        dragId = null;
                        qsa('.lb-section', ul).forEach(r => r.classList.remove('is-dragging', 'drop-before', 'drop-after'));
                    },
                    dragover: e => {
                        if (!dragId || dragId === s.id) {
                            return;
                        }
                        e.preventDefault();
                        const rect = row.getBoundingClientRect();
                        const after = e.clientY > rect.top + rect.height / 2;
                        row.classList.toggle('drop-after', after);
                        row.classList.toggle('drop-before', !after);
                    },
                    dragleave: () => row.classList.remove('drop-before', 'drop-after'),
                    drop: e => {
                        e.preventDefault();
                        const after = row.classList.contains('drop-after');
                        row.classList.remove('drop-before', 'drop-after');
                        if (dragId) {
                            moveTo(dragId, s.id, after);
                        }
                    },
                },
            }, [
                h('span', {class: 'lb-handle', 'aria-hidden': 'true', title: str('b_drag', 'Drag to reorder')}, [icon('grip-vertical')]),
                h('span', {class: 'lb-section__icon'}, [icon(def.icon)]),
                h('span', {class: 'lb-section__text'}, [
                    h('span', {class: 'lb-section__name', text: s.label || def.label}),
                    h('span', {class: 'lb-section__type', text: sectionSummary(s) || def.label}),
                ]),
                h('span', {class: 'lb-section__tools'}, [
                    toolButton('arrow-up', str('b_moveup', 'Move up'), () => moveSection(s.id, -1)),
                    toolButton('arrow-down', str('b_movedown', 'Move down'), () => moveSection(s.id, 1)),
                    toolButton('clone', str('b_duplicate', 'Duplicate'), () => duplicateSection(s.id)),
                    toolButton(s.hidden ? 'eye' : 'eye-slash', s.hidden ? str('b_show', 'Show') : str('b_hide', 'Hide'),
                        () => toggleHidden(s.id)),
                    toolButton('trash', str('b_delete', 'Delete'), () => deleteSection(s.id), 'lb-tool--danger'),
                ]),
            ]);
            row.dataset.index = i;
            ul.appendChild(row);
        });
        panel.appendChild(ul);
        panel.appendChild(h('button', {type: 'button', class: 'lb-add-btn', on: {click: () => setTab('add')}},
            [icon('plus'), ' ' + str('b_addsection', 'Add a section')]));
        panel.scrollTop = keepScroll;
    };

    const focusRow = id => {
        const row = qs('.lb-section[data-id="' + id + '"]');
        if (row) {
            row.focus();
        }
    };

    const renderLibrary = filter => {
        const box = qs('[data-region=library]');
        clear(box);
        const q = (filter || '').toLowerCase();
        let any = false;
        cfg.schema.categories.forEach(cat => {
            const types = Object.keys(cfg.schema.types).map(k => cfg.schema.types[k]).filter(t => t.category === cat.key &&
                (!q || (t.label + ' ' + t.description).toLowerCase().indexOf(q) >= 0));
            if (!types.length) {
                return;
            }
            any = true;
            box.appendChild(h('h3', {class: 'lb-cat', text: cat.label}));
            box.appendChild(h('div', {class: 'lb-library'}, types.map(t => h('button', {type: 'button', class: 'lb-card',
                title: t.description, on: {click: () => addSection(t.type)}}, [
                icon(t.icon),
                h('strong', {text: t.label}),
                h('span', {text: t.description}),
            ]))));
        });
        if (!any) {
            box.appendChild(h('p', {class: 'lb-hint', text: str('b_nomatch', 'No sections match your search.')}));
        }
    };

    const renderAddPanel = () => {
        const panel = qs('[data-panel=add]');
        clear(panel);
        panel.appendChild(h('input', {type: 'search', class: 'lb-search', placeholder: str('b_search', 'Search sections…'),
            'aria-label': str('b_search', 'Search sections…'), on: {input: e => renderLibrary(e.target.value)}}));
        panel.appendChild(h('div', {data: {region: 'library'}}));
        renderLibrary('');
    };

    const renderHistory = () => {
        const panel = qs('[data-panel=history]');
        clear(panel);
        if (!S.revisions.length) {
            panel.appendChild(h('p', {class: 'lb-hint', text: str('b_nohistory', 'Nothing has been published yet.')}));
            return;
        }
        panel.appendChild(h('div', {class: 'lb-revisions'}, S.revisions.map(r => h('div', {class: 'lb-revision'}, [
            h('div', {class: 'lb-revision__meta'}, [
                h('strong', {text: r.timeformatted}),
                r.status === 'published' ? h('span', {class: 'lb-badge', text: str('b_live', 'Live')}) : null,
                h('small', {text: [r.user, r.note].filter(Boolean).join(' · ') || ' '}),
                h('small', {text: String(r.sections) + ' ' + str('b_sections_count', 'sections')}),
            ]),
            h('button', {type: 'button', class: 'lb-btn lb-btn--sm', text: str('b_restore', 'Restore'),
                on: {click: () => restoreRevision(r)}}),
        ]))));
    };

    const restoreRevision = r => {
        confirmDialog(str('b_confirm_restore', 'Replace the draft with this version? Your current draft will be overwritten.'),
            str('b_restore', 'Restore'), false).then(ok => {
            if (!ok) {
                return;
            }
            clearTimeout(saveTimer);
            api('restore', {id: r.id}).then(res => replaceLayout(res, str('b_restored', 'Version restored to the draft.')))
                .catch(e => toast(e.message, true));
        });
    };

    const replaceLayout = (res, message) => {
        S.undo.push(JSON.stringify(S.layout));
        S.redo = [];
        S.layout = res.layout;
        S.snap = JSON.stringify(S.layout);
        S.haschanges = !!res.haschanges;
        S.dirty = false;
        if (S.selected && indexOf(S.selected) < 0) {
            S.selected = null;
        }
        refreshStatus();
        refreshUndo();
        renderSections();
        renderInspector();
        doRender();
        if (message) {
            toast(message);
        }
    };

    // Inspector fields.

    const visible = (field, obj) => {
        const rules = field.show;
        if (!rules || Array.isArray(rules) || !Object.keys(rules).length) {
            return true;
        }
        return Object.keys(rules).every(k => rules[k].map(String).indexOf(String(obj[k])) >= 0);
    };

    const labelFor = (field, id) => h('label', {for: id, text: field.label});

    let uid = 0;
    const nextId = () => 'lbf' + (++uid);

    /**
     * Build the control for one field.
     *
     * @param {Object} field schema field
     * @param {Object} obj object holding the value
     * @param {Function} commit called with (rerender:boolean, key) after each change
     * @return {HTMLElement}
     */
    const fieldEl = (field, obj, commit) => {
        const id = nextId();
        const key = field.key;
        const set = (v, rerender) => {
            obj[key] = v;
            commit(rerender, key);
        };
        const wrap = (control, inline) => h('div', {class: 'lb-field' + (inline ? ' lb-field--inline' : ''),
            data: {field: key}}, inline ? [labelFor(field, id), control] : [labelFor(field, id), control]);
        const value = obj[key] === undefined ? field.default : obj[key];

        switch (field.type) {
            case 'text':
            case 'url':
                return wrap(h('input', {type: 'text', id: id, value: value || '', autocomplete: 'off',
                    placeholder: field.type === 'url' ? '/path  or  https://…' : null,
                    on: {input: e => set(e.target.value, false)}}));
            case 'textarea':
                return wrap(h('textarea', {id: id, rows: '3', value: value || '', on: {input: e => set(e.target.value, false)}}));
            case 'html':
                return htmlField(field, id, value || '', set);
            case 'number':
                return wrap(h('input', {type: 'number', id: id, value: value, min: field.min, max: field.max, step: field.step,
                    on: {input: e => set(e.target.value === '' ? field.default : Number(e.target.value), false)}}));
            case 'range': {
                const out = h('output', {text: String(value)});
                return wrap(h('div', {class: 'lb-range'}, [
                    h('input', {type: 'range', id: id, value: value, min: field.min, max: field.max, step: field.step,
                        on: {input: e => {
                            out.textContent = e.target.value;
                            set(Number(e.target.value), false);
                        }}}),
                    out,
                ]));
            }
            case 'toggle': {
                const sw = h('button', {type: 'button', id: id, class: 'lb-switch', role: 'switch',
                    'aria-checked': Number(value) ? 'true' : 'false', on: {click: () => set(Number(value) ? 0 : 1, true)}});
                return wrap(sw, true);
            }
            case 'select':
                if (field.options.length <= 4) {
                    return wrap(h('div', {class: 'lb-seg', role: 'group', 'aria-label': field.label},
                        field.options.map(o => h('button', {type: 'button', text: o.label,
                            class: String(o.value) === String(value) ? 'is-active' : '',
                            'aria-pressed': String(o.value) === String(value) ? 'true' : 'false',
                            on: {click: () => set(o.value, true)}}))));
                }
                return wrap(h('select', {id: id, on: {change: e => set(e.target.value, true)}},
                    field.options.map(o => h('option', {value: o.value, text: o.label,
                        selected: String(o.value) === String(value)}))));
            case 'color':
                return colorField(field, id, value || '', set);
            case 'icon':
                return iconField(field, id, value || '', set);
            case 'image':
                return imageField(field, id, value || '', set);
            case 'courses':
                return coursesField(field, id, Array.isArray(value) ? value : [], set);
            case 'category':
                return wrap(h('select', {id: id, on: {change: e => set(Number(e.target.value), false)}},
                    cfg.categories.map(c => h('option', {value: c.id, text: c.name, selected: Number(value) === c.id}))));
            case 'list':
                return listField(field, Array.isArray(value) ? value : [], set, obj);
            default:
                return h('div');
        }
    };

    const htmlField = (field, id, value, set) => {
        const area = h('textarea', {id: id, rows: '6', value: value, on: {input: e => set(e.target.value, false)}});
        const wrapSel = (open, close) => {
            const a = area.selectionStart;
            const b = area.selectionEnd;
            const sel = area.value.slice(a, b);
            area.value = area.value.slice(0, a) + open + sel + close + area.value.slice(b);
            area.focus();
            area.setSelectionRange(a + open.length, a + open.length + sel.length);
            set(area.value, false);
        };
        const tool = (label, title, open, close) => h('button', {type: 'button', title: title, 'aria-label': title,
            text: label, on: {click: () => wrapSel(open, close)}});
        return h('div', {class: 'lb-field', data: {field: field.key}}, [
            labelFor(field, id),
            h('div', {class: 'lb-toolbar'}, [
                tool('B', str('b_bold', 'Bold'), '<strong>', '</strong>'),
                tool('I', str('b_italic', 'Italic'), '<em>', '</em>'),
                tool('H', str('b_heading', 'Heading'), '<h3>', '</h3>'),
                tool('¶', str('b_paragraph', 'Paragraph'), '<p>', '</p>'),
                tool('•', str('b_bullets', 'Bulleted list'), '<ul>\n<li>', '</li>\n</ul>'),
                tool('1.', str('b_numbers', 'Numbered list'), '<ol>\n<li>', '</li>\n</ol>'),
                tool('🔗', str('b_link', 'Link'), '<a href="https://">', '</a>'),
            ]),
            area,
        ]);
    };

    const colorField = (field, id, value, set) => {
        const text = h('input', {type: 'text', id: id, value: value, placeholder: '#RRGGBB', maxlength: '9', autocomplete: 'off'});
        const picker = h('input', {type: 'color', value: /^#[0-9a-f]{6}$/i.test(value) ? value : '#4f46e5',
            'aria-label': field.label});
        picker.addEventListener('input', () => {
            text.value = picker.value;
            set(picker.value, false);
        });
        text.addEventListener('input', () => {
            const v = text.value.trim();
            if (v === '' || /^#[0-9a-f]{3,8}$/i.test(v)) {
                if (/^#[0-9a-f]{6}$/i.test(v)) {
                    picker.value = v;
                }
                set(v, false);
            }
        });
        return h('div', {class: 'lb-field', data: {field: field.key}}, [
            labelFor(field, id),
            h('div', {class: 'lb-color'}, [picker, text]),
        ]);
    };

    const iconField = (field, id, value, set) => {
        const holder = h('div', {class: 'lb-iconpick'});
        let pop = null;
        const btn = h('button', {type: 'button', id: id, class: 'lb-iconpick__btn', 'aria-haspopup': 'true',
            'aria-expanded': 'false'}, [icon(value || 'star'), h('span', {text: value || 'star'})]);
        const closePop = () => {
            if (pop) {
                pop.remove();
                pop = null;
                btn.setAttribute('aria-expanded', 'false');
            }
        };
        btn.addEventListener('click', () => {
            if (pop) {
                closePop();
                return;
            }
            const grid = h('div', {class: 'lb-iconpick__grid'});
            const fill = q => {
                clear(grid);
                cfg.schema.icons.filter(n => n.indexOf(q) >= 0).forEach(n => grid.appendChild(h('button', {type: 'button',
                    title: n, 'aria-label': n, class: n === value ? 'is-active' : '', on: {click: () => {
                        closePop();
                        set(n, true);
                    }}}, [icon(n)])));
            };
            fill('');
            const search = h('input', {type: 'search', placeholder: str('b_search', 'Search…'),
                'aria-label': str('b_search', 'Search…'), on: {input: e => fill(e.target.value.toLowerCase())}});
            pop = h('div', {class: 'lb-iconpick__pop'}, [search, grid]);
            holder.appendChild(pop);
            btn.setAttribute('aria-expanded', 'true');
            search.focus();
            const away = e => {
                if (!holder.contains(e.target)) {
                    closePop();
                    document.removeEventListener('mousedown', away, true);
                }
            };
            document.addEventListener('mousedown', away, true);
        });
        holder.appendChild(btn);
        return h('div', {class: 'lb-field', data: {field: field.key}}, [labelFor(field, id), holder]);
    };

    const imageField = (field, id, value, set) => {
        const preview = h('div', {class: 'lb-image__preview'});
        if (value) {
            preview.style.backgroundImage = 'url("' + value.replace(/["\\]/g, '') + '")';
        } else {
            preview.appendChild(icon('image'));
        }
        return h('div', {class: 'lb-field', data: {field: field.key}}, [
            labelFor(field, id),
            h('div', {class: 'lb-image'}, [
                preview,
                h('div', {class: 'lb-image__row'}, [
                    h('button', {type: 'button', id: id, class: 'lb-btn lb-btn--sm',
                        text: value ? str('b_change_image', 'Change') : str('b_choose_image', 'Choose image'),
                        on: {click: () => openMedia(url => set(url, true))}}),
                    value ? h('button', {type: 'button', class: 'lb-btn lb-btn--sm', text: str('b_remove', 'Remove'),
                        on: {click: () => set('', true)}}) : null,
                ]),
            ]),
        ]);
    };

    const coursesField = (field, id, value, set) => {
        const chips = h('div', {class: 'lb-chips'});
        const results = h('div', {class: 'lb-results', hidden: true});
        const draw = () => {
            clear(chips);
            value.forEach(cid => chips.appendChild(h('span', {class: 'lb-chip'}, [
                h('span', {text: S.names[cid] || ('#' + cid)}),
                h('button', {type: 'button', 'aria-label': str('b_remove', 'Remove'), on: {click: () => {
                    value = value.filter(x => x !== cid);
                    set(value.slice(), false);
                    draw();
                }}}, [icon('xmark')]),
            ])));
        };
        const missing = value.filter(cid => !S.names[cid]);
        if (missing.length) {
            api('coursenames', {ids: missing.join(',')}).then(res => {
                Object.keys(res.names).forEach(k => {
                    S.names[k] = res.names[k];
                });
                draw();
            }).catch(() => null);
        }
        const search = debounce(q => {
            api('courses', {query: q}).then(res => {
                clear(results);
                results.hidden = false;
                if (!res.courses.length) {
                    results.appendChild(h('small', {text: str('b_courses_none', 'No courses found.')}));
                }
                res.courses.forEach(c => results.appendChild(h('button', {type: 'button', on: {click: () => {
                    S.names[c.id] = c.name;
                    if (value.indexOf(c.id) < 0) {
                        value = value.concat([c.id]);
                        set(value.slice(), false);
                    }
                    results.hidden = true;
                    input.value = '';
                    draw();
                }}}, [c.name, h('small', {text: c.category})])));
            }).catch(e => toast(e.message, true));
        }, 250);
        const input = h('input', {type: 'search', id: id, placeholder: str('b_courses_search', 'Search courses…'),
            autocomplete: 'off', on: {input: e => search(e.target.value.trim()), focus: e => search(e.target.value.trim())}});
        draw();
        return h('div', {class: 'lb-field', data: {field: field.key}}, [labelFor(field, id), chips, input, results]);
    };

    const listField = (field, items, set, obj) => {
        const box = h('div', {class: 'lb-list'});
        const titleKey = (field.fields.find(f => f.type === 'text') || field.fields[0] || {}).key;
        const sync = rerender => {
            obj[field.key] = items;
            set(items, !!rerender);
        };
        const draw = () => {
            clear(box);
            items.forEach((item, i) => {
                const open = S.open.has(item);
                const title = h('span', {class: 'lb-item__title', text: String(item[titleKey] || '') || (str('b_item', 'Item') + ' ' + (i + 1))});
                const body = h('div', {class: 'lb-item__body', hidden: !open});
                const head = h('div', {class: 'lb-item__head'}, [
                    h('button', {type: 'button', class: 'lb-item__title', 'aria-expanded': open ? 'true' : 'false',
                        on: {click: () => {
                            if (S.open.has(item)) {
                                S.open.delete(item);
                            } else {
                                S.open.add(item);
                            }
                            draw();
                        }}}, [icon(open ? 'chevron-down' : 'chevron-right'), title]),
                    toolButton('arrow-up', str('b_moveup', 'Move up'), () => move(i, -1)),
                    toolButton('arrow-down', str('b_movedown', 'Move down'), () => move(i, 1)),
                    toolButton('trash', str('b_delete', 'Delete'), () => {
                        items.splice(i, 1);
                        sync(true);
                        draw();
                    }, 'lb-tool--danger'),
                ]);
                field.fields.forEach(sub => {
                    if (visible(sub, item)) {
                        body.appendChild(fieldEl(sub, item, (rerender, k) => {
                            if (k === titleKey) {
                                title.textContent = String(item[titleKey] || '') || (str('b_item', 'Item') + ' ' + (i + 1));
                            }
                            sync(false);
                            if (rerender) {
                                draw();
                            }
                        }));
                    }
                });
                box.appendChild(h('div', {class: 'lb-item'}, [head, body]));
            });
            if (items.length < cfg.schema.maxitems) {
                box.appendChild(h('button', {type: 'button', class: 'lb-btn lb-btn--sm', on: {click: () => {
                    const fresh = {};
                    field.fields.forEach(f => {
                        fresh[f.key] = clone(f.default);
                    });
                    items.push(fresh);
                    S.open.add(fresh);
                    sync(true);
                    draw();
                }}}, [icon('plus'), ' ' + str('b_additem', 'Add item')]));
            }
        };
        const move = (i, d) => {
            const j = i + d;
            if (j < 0 || j >= items.length) {
                return;
            }
            items.splice(j, 0, items.splice(i, 1)[0]);
            sync(true);
            draw();
        };
        draw();
        return h('div', {class: 'lb-field', data: {field: field.key}}, [h('label', {text: field.label}), box]);
    };

    // Media library.

    const openMedia = onPick => {
        const grid = h('div', {class: 'lb-media-grid'});
        const note = h('p', {class: 'lb-hint', text: ''});
        let close = null;
        const draw = files => {
            clear(grid);
            if (!files.length) {
                grid.appendChild(h('p', {class: 'lb-hint', text: str('b_nomedia', 'No images uploaded yet.')}));
            }
            files.forEach(f => grid.appendChild(h('div', {class: 'lb-media'}, [
                h('button', {type: 'button', title: f.name, 'aria-label': f.name, on: {click: () => {
                    close();
                    onPick(f.url);
                }}}, [h('img', {src: f.url, alt: '', loading: 'lazy'})]),
                h('button', {type: 'button', class: 'lb-media__del', title: str('b_delete', 'Delete'),
                    'aria-label': str('b_delete', 'Delete'), on: {click: () => {
                        api('deletemedia', {name: f.name}).then(load).catch(e => toast(e.message, true));
                    }}}, [icon('trash')]),
            ])));
        };
        const load = () => api('media').then(res => draw(res.files)).catch(e => toast(e.message, true));
        const upload = list => {
            const files = Array.prototype.slice.call(list);
            if (!files.length) {
                return;
            }
            note.textContent = str('b_uploading', 'Uploading…');
            files.reduce((p, file) => p.then(() => {
                if (file.size > cfg.maxupload) {
                    throw new Error(str('b_err_toolarge_js', 'File is too large.'));
                }
                return api('upload', {}, file);
            }), Promise.resolve()).then(() => {
                note.textContent = '';
                return load();
            }).catch(e => {
                note.textContent = e.message;
            });
        };
        const input = h('input', {type: 'file', accept: 'image/png,image/jpeg,image/gif,image/webp,image/avif', multiple: true,
            class: 'visually-hidden', on: {change: e => upload(e.target.files)}});
        const drop = h('div', {class: 'lb-drop', tabindex: '0', on: {
            click: () => input.click(),
            keydown: e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            },
            dragover: e => {
                e.preventDefault();
                drop.classList.add('is-over');
            },
            dragleave: () => drop.classList.remove('is-over'),
            drop: e => {
                e.preventDefault();
                drop.classList.remove('is-over');
                upload(e.dataTransfer.files);
            },
        }}, [icon('cloud-arrow-up'), h('span', {text: str('b_dropimages', 'Drop images here or click to upload')}), input]);
        close = openModal({title: str('b_library', 'Media library'), wide: true, body: h('div', {}, [drop, note, grid])});
        load();
    };

    // Inspector.

    const renderInspector = () => {
        const box = qs('[data-region=inspector]');
        const s = current();
        const scroll = box.querySelector('.lb-ins-body') ? box.querySelector('.lb-ins-body').scrollTop : 0;
        clear(box);
        if (!s) {
            box.appendChild(h('div', {class: 'lb-ins-empty'}, [
                icon('hand-pointer'),
                h('p', {text: str('b_selectsection', 'Select a section to edit it.')}),
            ]));
            return;
        }
        const def = defOf(s.type);
        const groups = {content: [], style: [], advanced: []};
        def.fields.forEach(f => groups[f.group in groups ? f.group : 'content'].push({field: f, obj: s.props}));
        cfg.schema.stylefields.forEach(f => groups[f.group in groups ? f.group : 'style'].push({field: f, obj: s.style}));

        const tabs = ['content', 'style', 'advanced'].filter(t => groups[t].length || t === 'advanced');
        if (tabs.indexOf(S.insTab) < 0) {
            S.insTab = 'content';
        }
        const body = h('div', {class: 'lb-ins-body'});

        const draw = () => {
            clear(body);
            if (S.insTab === 'advanced') {
                body.appendChild(fieldEl({key: 'label', type: 'text', label: str('b_sectionlabel', 'Section name'), default: ''},
                    s, () => edited(s.id + ':label')));
            }
            groups[S.insTab].forEach(item => {
                if (!visible(item.field, item.obj)) {
                    return;
                }
                body.appendChild(fieldEl(item.field, item.obj, (rerender, key) => {
                    edited(s.id + (item.obj === s.props ? ':p:' : ':s:') + key);
                    if (rerender) {
                        draw();
                    }
                }));
            });
        };

        box.appendChild(h('div', {class: 'lb-ins-head'}, [
            h('h2', {text: s.label || def.label}),
            h('p', {text: def.description}),
        ]));
        box.appendChild(h('div', {class: 'lb-ins-tabs', role: 'tablist'}, tabs.map(t => h('button', {type: 'button',
            role: 'tab', class: t === S.insTab ? 'is-active' : '', 'aria-selected': t === S.insTab ? 'true' : 'false',
            text: str('b_tab_' + t, t.charAt(0).toUpperCase() + t.slice(1)),
            on: {click: () => {
                S.insTab = t;
                renderInspector();
            }}}))));
        box.appendChild(body);
        draw();
        body.scrollTop = scroll;
    };

    // Top bar actions.

    const publish = () => {
        const note = h('input', {type: 'text', maxlength: '200', placeholder: str('b_publish_note', 'What changed? (optional)'),
            'aria-label': str('b_publish_note', 'What changed? (optional)')});
        const go = h('button', {type: 'button', class: 'lb-btn lb-btn--primary', text: str('b_publish', 'Publish')});
        const close = openModal({
            title: str('b_publish_title', 'Publish to your live site'),
            body: h('div', {}, [h('p', {text: str('b_publish_text', 'Visitors will see this version of the home page straight away.')}),
                h('div', {class: 'lb-field'}, [note])]),
            foot: [h('button', {type: 'button', class: 'lb-btn', text: str('b_cancel', 'Cancel'), on: {click: () => close()}}), go],
        });
        const run = () => {
            go.disabled = true;
            clearTimeout(saveTimer);
            api('publish', {layout: S.layout, note: note.value}).then(res => {
                S.dirty = false;
                S.haschanges = false;
                S.revisions = res.revisions;
                renderHistory();
                refreshStatus();
                close();
                toast(str('b_published', 'Published. Your home page is live.'));
            }).catch(e => {
                go.disabled = false;
                toast(e.message, true);
            });
        };
        go.addEventListener('click', run);
        note.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                run();
            }
        });
    };

    const serverReplace = (action, message, confirmKey, confirmDefault, label) => {
        confirmDialog(str(confirmKey, confirmDefault), label, true).then(ok => {
            if (!ok) {
                return;
            }
            clearTimeout(saveTimer);
            api(action).then(res => replaceLayout(res, message)).catch(e => toast(e.message, true));
        });
    };

    const toggleMenu = open => {
        const menu = qs('[data-region=more-menu]');
        const btn = qs('[data-action=more]');
        const show = open === undefined ? menu.hidden : open;
        menu.hidden = !show;
        btn.setAttribute('aria-expanded', show ? 'true' : 'false');
    };

    const setDevice = d => {
        qs('[data-region=framewrap]').dataset.device = d;
        qsa('[data-action=device]').forEach(b => {
            const on = b.dataset.device === d;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    };

    const onClick = e => {
        const target = e.target.closest('[data-action]');
        if (!target || !root.contains(target)) {
            if (!e.target.closest('.lb-menu-wrap')) {
                toggleMenu(false);
            }
            return;
        }
        const action = target.dataset.action;
        switch (action) {
            case 'exit':
                if (S.dirty || S.saving) {
                    e.preventDefault();
                    saveNow().then(() => {
                        window.location.href = target.href;
                    });
                }
                break;
            case 'device':
                setDevice(target.dataset.device);
                break;
            case 'undo':
                undo();
                break;
            case 'redo':
                redo();
                break;
            case 'more':
                toggleMenu();
                break;
            case 'discard':
                toggleMenu(false);
                serverReplace('discard', str('b_discarded', 'Draft reset to the published page.'), 'b_confirm_discard',
                    'Discard all unpublished changes?', str('b_discard_ok', 'Discard changes'));
                break;
            case 'reset':
                toggleMenu(false);
                serverReplace('reset', str('b_resetdone', 'Starter layout loaded into the draft.'), 'b_confirm_reset',
                    'Replace the draft with the starter layout?', str('b_reset_ok', 'Load starter layout'));
                break;
            case 'publish':
                publish();
                break;
            case 'tab':
                setTab(target.dataset.tab);
                break;
            default:
                break;
        }
    };

    const onKey = e => {
        const mod = e.ctrlKey || e.metaKey;
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
        if (e.key === 'Escape') {
            if (closeModal) {
                closeModal();
            } else {
                toggleMenu(false);
            }
            return;
        }
        if (mod && e.key.toLowerCase() === 's') {
            e.preventDefault();
            saveNow();
        } else if (mod && !typing && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (e.shiftKey) {
                redo();
            } else {
                undo();
            }
        } else if (mod && !typing && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            redo();
        }
    };

    const onMessage = e => {
        if (e.origin !== window.location.origin || !frame || e.source !== frame.contentWindow || !e.data) {
            return;
        }
        if (e.data.type === 'instracta:ready') {
            S.frameReady = true;
            if (S.dirty || S.undo.length) {
                doRender();
            } else if (S.selected) {
                post({type: 'instracta:select', id: S.selected, scroll: false});
            }
        } else if (e.data.type === 'instracta:select' && e.data.id && indexOf(e.data.id) >= 0) {
            setTab('sections');
            select(e.data.id, false);
        }
    };

    return {
        /**
         * Start the builder.
         *
         * @param {Object} config values prepared by builder/index.php
         */
        init: function(config) {
            cfg = config;
            root = document.querySelector('[data-region=instracta-builder]');
            if (!root) {
                return;
            }
            frame = qs('[data-region=preview]');
            S.layout = cfg.layout && Array.isArray(cfg.layout.sections) ? cfg.layout : {v: 1, sections: []};
            S.snap = JSON.stringify(S.layout);
            S.haschanges = !!cfg.haschanges;
            S.revisions = cfg.revisions || [];

            root.addEventListener('click', onClick);
            document.addEventListener('keydown', onKey);
            window.addEventListener('message', onMessage);
            window.addEventListener('beforeunload', e => {
                if (S.dirty || S.saving) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            renderSections();
            renderAddPanel();
            renderHistory();
            renderInspector();
            refreshStatus();
            refreshUndo();
        },
    };
});
