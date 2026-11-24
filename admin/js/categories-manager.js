/**
 * Categories manager (admin/categories-manager.php).
 *
 * Renders the editable category list from medbeafgalleryCategoriesManager and
 * talks to the wp_ajax_medbeafgallery_categories_manager handler. Every
 * response carries the whole list, so the screen re-renders from the server's
 * truth after each change and puts focus back where the user was.
 *
 * Plain ES2017, no dependencies (wp.media is used when present).
 */
(function () {
    'use strict';

    var cfg = window.medbeafgalleryCategoriesManager;
    var root = document.getElementById('medbeafgallery-cm');
    if (!cfg || !root) {
        return;
    }

    var t = cfg.i18n || {};
    var can = cfg.can || {};

    var els = {
        list: document.getElementById('medbeafgallery-cm-list'),
        all: document.getElementById('medbeafgallery-cm-all'),
        empty: document.getElementById('medbeafgallery-cm-empty'),
        live: document.getElementById('medbeafgallery-cm-live'),
        notice: document.getElementById('medbeafgallery-cm-notice'),
        usage: document.getElementById('medbeafgallery-cm-usage'),
        addForm: document.getElementById('medbeafgallery-cm-add'),
        addName: document.getElementById('medbeafgallery-cm-new-name'),
        addParent: document.getElementById('medbeafgallery-cm-new-parent'),
        addButton: document.getElementById('medbeafgallery-cm-add-button'),
        limit: document.getElementById('medbeafgallery-cm-limit')
    };

    var state = {
        rows: Array.isArray(cfg.rows) ? cfg.rows : [],
        all: cfg.all || null,
        usage: cfg.usage || { limited: false, at_limit: false },
        usageText: cfg.usageText || '',
        // Suggestions stay up after the first chip is used, so several can be added.
        suggestions: !(Array.isArray(cfg.rows) && cfg.rows.length)
    };

    var queue = Promise.resolve();
    var pending = 0;
    var drag = null;

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    // sprintf subset: %s, %d, %1$s, %2$d.
    function fmt(str) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return String(str || '').replace(/%(?:(\d+)\$)?([sd])/g, function (m, n, type) {
            var value = n ? args[parseInt(n, 10) - 1] : args[i++];
            if (type === 'd') {
                return String(parseInt(value, 10) || 0);
            }
            return value === undefined || value === null ? '' : String(value);
        });
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                var value = attrs[key];
                if (value === null || value === undefined || value === false) {
                    return;
                }
                if (key === 'text') {
                    node.textContent = value;
                } else if (key === 'className') {
                    node.className = value;
                } else if (key === 'on') {
                    Object.keys(value).forEach(function (evt) {
                        node.addEventListener(evt, value[evt]);
                    });
                } else if (value === true) {
                    node.setAttribute(key, '');
                } else {
                    node.setAttribute(key, String(value));
                }
            });
        }
        (children || []).forEach(function (child) {
            if (child === null || child === undefined || child === false) {
                return;
            }
            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        });
        return node;
    }

    function icon(name) {
        return el('span', { className: 'dashicons dashicons-' + name, 'aria-hidden': 'true' });
    }

    function srText(text) {
        return el('span', { className: 'screen-reader-text', text: text });
    }

    function rowById(id) {
        id = Number(id);
        for (var i = 0; i < state.rows.length; i++) {
            if (Number(state.rows[i].id) === id) {
                return state.rows[i];
            }
        }
        return null;
    }

    function childrenOf(parentId) {
        parentId = Number(parentId);
        return state.rows.filter(function (row) {
            return Number(row.parent) === parentId;
        });
    }

    function subtreeCount(row) {
        var total = Number(row.count) || 0;
        childrenOf(row.id).forEach(function (child) {
            total += subtreeCount(child);
        });
        return total;
    }

    function descendantCount(row) {
        var kids = childrenOf(row.id);
        var total = kids.length;
        kids.forEach(function (child) {
            total += descendantCount(child);
        });
        return total;
    }

    function topLevelRows() {
        return childrenOf(0);
    }

    function casesText(count) {
        return fmt(count === 1 ? t.casesOne : t.casesMany, count);
    }

    var liveTimer = null;
    function announce(message) {
        if (!els.live || !message) {
            return;
        }
        // Clear first so the same message twice is still read out.
        els.live.textContent = '';
        window.clearTimeout(liveTimer);
        liveTimer = window.setTimeout(function () {
            els.live.textContent = message;
        }, 60);
    }

    function showError(message) {
        if (!els.notice) {
            return;
        }
        els.notice.textContent = '';
        if (message) {
            els.notice.appendChild(el('p', { text: message }));
            els.notice.hidden = false;
        } else {
            els.notice.hidden = true;
        }
    }

    function sameRows(a, b) {
        try {
            return JSON.stringify(a) === JSON.stringify(b);
        } catch (e) {
            return false;
        }
    }

    function focusPart(spec) {
        if (!spec) {
            return;
        }
        var target = null;
        if (spec.selector) {
            target = root.querySelector(spec.selector);
        } else if (spec.id === 'all') {
            target = els.all ? els.all.querySelector('[data-part="' + spec.part + '"]') : null;
        } else if (spec.id) {
            var item = els.list.querySelector('li[data-id="' + Number(spec.id) + '"]');
            if (item) {
                var row = item.querySelector(':scope > .medbeafgallery-cm-row');
                target = row ? row.querySelector('[data-part="' + (spec.part || 'name') + '"]') : null;
                if (!target || target.disabled) {
                    target = row ? (row.querySelector('[data-part="name"]') || row.querySelector('[data-part="handle"]')) : null;
                }
            }
        }
        if (target && !target.disabled) {
            target.focus();
        }
    }

    function flash(id) {
        var item = els.list.querySelector('li[data-id="' + Number(id) + '"] > .medbeafgallery-cm-row');
        if (!item) {
            return;
        }
        item.classList.remove('is-flash');
        // Restart the animation.
        void item.offsetWidth;
        item.classList.add('is-flash');
    }

    /* ------------------------------------------------------------------ */
    /* Server                                                              */
    /* ------------------------------------------------------------------ */

    function applyState(data) {
        if (!data) {
            return false;
        }
        var changed = false;
        if (Array.isArray(data.rows)) {
            changed = !sameRows(state.rows, data.rows);
            state.rows = data.rows;
        }
        if ('all' in data) {
            if (!sameRows(state.all, data.all)) {
                changed = true;
            }
            state.all = data.all;
        }
        if (data.usage) {
            state.usage = data.usage;
            state.usageText = data.usageText || '';
        }
        return changed;
    }

    /**
     * Queue one request. Resolves with { ok, data, message }.
     */
    function request(op, params) {
        var body = new URLSearchParams();
        body.append('action', cfg.action);
        body.append('nonce', cfg.nonce);
        body.append('op', op);
        Object.keys(params || {}).forEach(function (key) {
            var value = params[key];
            if (Array.isArray(value)) {
                value.forEach(function (v) {
                    body.append(key + '[]', String(v));
                });
            } else {
                body.append(key, String(value));
            }
        });

        var run = function () {
            pending++;
            root.setAttribute('aria-busy', 'true');
            return fetch(cfg.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (response) {
                return response.text();
            }).then(function (text) {
                var json = null;
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    json = null;
                }
                if (!json || typeof json !== 'object') {
                    return { ok: false, data: null, message: t.networkError };
                }
                var data = json.data || {};
                return {
                    ok: !!json.success,
                    data: data,
                    message: json.success ? '' : (data.message || t.networkError)
                };
            }).catch(function () {
                return { ok: false, data: null, message: t.networkError };
            }).then(function (result) {
                pending--;
                if (!pending) {
                    root.removeAttribute('aria-busy');
                }
                return result;
            });
        };

        var next = queue.then(run, run);
        queue = next.catch(function () {});
        return next;
    }

    /**
     * Send a write; on success re-render and focus; on failure show the
     * message, re-render only if the list changed elsewhere, and run onError.
     */
    function write(op, params, done) {
        return request(op, params).then(function (result) {
            var changed = applyState(result.data);
            if (result.ok) {
                showError('');
                render();
                if (done && done.success) {
                    done.success(result.data || {});
                }
            } else {
                showError(result.message);
                announce(result.message);
                if (changed) {
                    render();
                }
                if (done && done.error) {
                    done.error(result, changed);
                }
            }
            return result;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Rendering                                                           */
    /* ------------------------------------------------------------------ */

    function render() {
        renderUsage();
        renderAddForm();
        renderAll();
        renderList();
        renderEmpty();
    }

    function renderUsage() {
        if (!els.usage) {
            return;
        }
        var usage = state.usage || {};
        els.usage.hidden = !usage.limited;
        var text = els.usage.querySelector('.medbeafgallery-cm-usage-text');
        if (text) {
            text.textContent = state.usageText || '';
        }
        var bar = els.usage.querySelector('.medbeafgallery-cm-usage-meter > span');
        if (bar && usage.max > 0) {
            bar.style.width = Math.min(100, Math.round(usage.used / usage.max * 100)) + '%';
        }
        els.usage.classList.toggle('is-full', !!usage.at_limit);
    }

    function atLimit() {
        return !!(state.usage && state.usage.at_limit);
    }

    function renderAddForm() {
        if (!els.addForm) {
            return;
        }
        if (!can.add) {
            els.addForm.hidden = true;
            return;
        }
        els.addForm.hidden = false;

        var keep = els.addParent.value;
        els.addParent.textContent = '';
        els.addParent.appendChild(el('option', { value: '0', text: t.topLevel }));
        topLevelRows().forEach(function (row) {
            els.addParent.appendChild(el('option', { value: String(row.id), text: row.name }));
        });
        els.addParent.value = rowById(keep) && Number(rowById(keep).parent) === 0 ? keep : '0';

        var full = atLimit();
        els.addName.disabled = full;
        els.addParent.disabled = full;
        els.addButton.disabled = full;
        renderLimit(els.limit, full);
    }

    function renderLimit(container, show) {
        if (!container) {
            return;
        }
        container.textContent = '';
        container.hidden = !show;
        if (!show) {
            return;
        }
        container.appendChild(document.createTextNode(t.limitReached + ' '));
        container.appendChild(el('a', {
            href: cfg.upgradeUrl,
            target: '_blank',
            rel: 'noopener noreferrer',
            text: t.upgrade
        }));
    }

    function thumbContent(imageUrl, placeholderIcon) {
        if (imageUrl) {
            return el('img', { src: imageUrl, alt: '' });
        }
        return icon(placeholderIcon || 'format-image');
    }

    function renderAll() {
        if (!els.all) {
            return;
        }
        var all = state.all;
        els.all.textContent = '';
        if (!all) {
            els.all.hidden = true;
            return;
        }
        els.all.hidden = false;

        var thumb;
        if (can.edit && hasMedia()) {
            thumb = el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-thumb' + (all.image_url ? '' : ' is-empty'),
                'data-part': 'thumb',
                'aria-label': all.image_url ? t.allImageChange : t.allImageChoose,
                title: all.image_url ? t.allImageChange : t.allImageChoose,
                on: { click: function () { pickImage('all'); } }
            }, [thumbContent(all.image_url, 'format-gallery')]);
        } else {
            thumb = el('span', { className: 'medbeafgallery-cm-thumb medbeafgallery-cm-thumb-static' + (all.image_url ? '' : ' is-empty') }, [thumbContent(all.image_url, 'format-gallery')]);
        }

        var actions = el('div', { className: 'medbeafgallery-cm-actions' });
        els.all.classList.remove('is-editing');
        if (can.edit && fieldsFor('all').length) {
            // Only the add-on fields offered for "All" (Pro: its icon).
            actions.appendChild(linkButton(t.allEdit || t.edit, 'edit', t.allEditLabel, openAllPanel));
        }
        if (can.edit && all.image_url) {
            actions.appendChild(el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-link',
                'data-part': 'remove-image',
                'aria-label': t.allImageRemove,
                text: t.removeImage,
                on: { click: function () { setImage('all', 0); } }
            }));
        }

        els.all.appendChild(thumb);
        els.all.appendChild(el('div', { className: 'medbeafgallery-cm-main' }, [
            el('div', { className: 'medbeafgallery-cm-line' }, [
                el('span', { className: 'medbeafgallery-cm-name-static', text: t.allTitle }),
                el('span', { className: 'medbeafgallery-cm-badge', text: t.allNote })
            ]),
            actions.childNodes.length ? actions : null,
            el('div', { className: 'medbeafgallery-cm-panel', hidden: true })
        ]));
    }

    function renderList() {
        els.list.textContent = '';
        appendItems(els.list, 0, 0);
        els.list.hidden = state.rows.length === 0;
        // The no-JS "empty" paragraph is replaced by the richer empty state.
        var stale = root.querySelector('.medbeafgallery-cm-empty-static');
        if (stale) {
            stale.parentNode.removeChild(stale);
        }
    }

    function appendItems(list, parentId, depth) {
        var siblings = childrenOf(parentId);
        siblings.forEach(function (row, index) {
            list.appendChild(buildItem(row, index, siblings.length, depth));
        });
    }

    function buildItem(row, index, total, depth) {
        var item = el('li', {
            className: 'medbeafgallery-cm-item' + (depth > 0 ? ' is-child' : ''),
            'data-id': row.id,
            'data-parent': row.parent
        });
        var line = el('div', { className: 'medbeafgallery-cm-row' });

        // Drag handle (also takes arrow keys).
        if (can.edit && total > 1) {
            line.appendChild(el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-handle',
                'data-part': 'handle',
                'aria-label': fmt(t.reorder, row.name),
                'aria-describedby': 'medbeafgallery-cm-reorder-hint',
                title: t.reorderHint,
                on: {
                    keydown: function (e) { onHandleKey(e, row); },
                    pointerdown: function (e) { startDrag(e, item, row); }
                }
            }, [el('span', { className: 'medbeafgallery-cm-grip', 'aria-hidden': 'true' })]));
        } else {
            line.appendChild(el('span', { className: 'medbeafgallery-cm-handle-spacer', 'aria-hidden': 'true' }));
        }

        // Thumbnail.
        if (can.edit && hasMedia()) {
            line.appendChild(el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-thumb' + (row.image_url ? '' : ' is-empty'),
                'data-part': 'thumb',
                'aria-label': fmt(row.image_url ? t.changeImage : t.chooseImage, row.name),
                title: fmt(row.image_url ? t.changeImage : t.chooseImage, row.name),
                on: { click: function () { pickImage(row.id); } }
            }, [thumbContent(row.image_url)]));
        } else {
            line.appendChild(el('span', { className: 'medbeafgallery-cm-thumb medbeafgallery-cm-thumb-static' + (row.image_url ? '' : ' is-empty') }, [thumbContent(row.image_url)]));
        }

        // Name + count.
        var nameNode;
        if (can.edit) {
            nameNode = el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-name',
                'data-part': 'name',
                'aria-label': fmt(t.renameLabel, row.name),
                on: { click: function () { openPanel(item, row, 'rename'); } }
            }, [el('span', { className: 'medbeafgallery-cm-name-text', text: row.name }), icon('edit')]);
        } else {
            nameNode = el('span', { className: 'medbeafgallery-cm-name-static', 'data-part': 'name', tabindex: '-1', text: row.name });
        }

        var count = Number(row.count) || 0;
        var countNode;
        if (count > 0) {
            countNode = el('a', {
                className: 'medbeafgallery-cm-count',
                href: row.cases_url,
                'data-part': 'count'
            }, [casesText(count), srText(' — ' + fmt(t.casesLinkLabel, row.name))]);
        } else {
            countNode = el('span', { className: 'medbeafgallery-cm-count is-zero', text: t.noCases });
        }
        var tree = subtreeCount(row);
        var treeNode = tree > count ? el('span', { className: 'medbeafgallery-cm-count-tree', text: fmt(t.casesTree, tree) }) : null;

        var main = el('div', { className: 'medbeafgallery-cm-main' }, [
            el('div', { className: 'medbeafgallery-cm-line' }, [nameNode, countNode, treeNode]),
            buildActions(item, row, depth),
            el('div', { className: 'medbeafgallery-cm-panel', hidden: true })
        ]);
        line.appendChild(main);

        // Move up / down.
        if (can.edit && total > 1) {
            line.appendChild(el('div', { className: 'medbeafgallery-cm-order' }, [
                el('button', {
                    type: 'button',
                    className: 'medbeafgallery-cm-icon-button',
                    'data-part': 'up',
                    'aria-label': fmt(t.moveUp, row.name),
                    title: fmt(t.moveUp, row.name),
                    disabled: index === 0,
                    on: { click: function () { moveBy(row, -1, 'up'); } }
                }, [icon('arrow-up-alt2')]),
                el('button', {
                    type: 'button',
                    className: 'medbeafgallery-cm-icon-button',
                    'data-part': 'down',
                    'aria-label': fmt(t.moveDown, row.name),
                    title: fmt(t.moveDown, row.name),
                    disabled: index === total - 1,
                    on: { click: function () { moveBy(row, 1, 'down'); } }
                }, [icon('arrow-down-alt2')])
            ]));
        }

        item.appendChild(line);

        if (childrenOf(row.id).length) {
            var sub = el('ul', { className: 'medbeafgallery-cm-list medbeafgallery-cm-children' });
            appendItems(sub, row.id, depth + 1);
            item.appendChild(sub);
        }
        return item;
    }

    function moveDestinations(row) {
        var hasKids = childrenOf(row.id).length > 0;
        var out = [];
        if (Number(row.parent) !== 0) {
            out.push({ id: 0, name: t.topLevel });
        }
        if (hasKids) {
            return out;
        }
        topLevelRows().forEach(function (top) {
            if (Number(top.id) !== Number(row.id) && Number(top.id) !== Number(row.parent)) {
                out.push({ id: Number(top.id), name: top.name });
            }
        });
        return out;
    }

    function linkButton(label, part, ariaLabel, handler, extraClass) {
        return el('button', {
            type: 'button',
            className: 'medbeafgallery-cm-link' + (extraClass ? ' ' + extraClass : ''),
            'data-part': part,
            'aria-label': ariaLabel || null,
            text: label,
            on: { click: handler }
        });
    }

    function buildActions(item, row, depth) {
        var actions = el('div', { className: 'medbeafgallery-cm-actions' });

        if (can.edit) {
            // One Edit panel: name, description and any add-on fields (Pro: icon).
            actions.appendChild(linkButton(t.edit || t.rename, 'rename', fmt(t.editLabel || t.renameLabel, row.name), function () {
                openPanel(item, row, 'rename');
            }));
        }
        if (can.add && depth === 0) {
            var addSub = linkButton(t.addSub, 'add-sub', fmt(t.addSubLabel, row.name), function () {
                openPanel(item, row, 'add-sub');
            });
            if (atLimit()) {
                addSub.disabled = true;
                addSub.title = t.limitReached;
            }
            actions.appendChild(addSub);
        }
        if (can.edit && moveDestinations(row).length) {
            actions.appendChild(linkButton(t.moveTo, 'move', fmt(t.moveToLabel, row.name), function () {
                openPanel(item, row, 'move');
            }));
        }
        if (can.edit && row.image_url) {
            actions.appendChild(linkButton(t.removeImage, 'remove-image', fmt(t.removeImageLabel, row.name), function () {
                setImage(row.id, 0);
            }));
        }
        if (can.delete) {
            actions.appendChild(linkButton(t.delete, 'delete', fmt(t.deleteLabel, row.name), function () {
                openPanel(item, row, 'delete');
            }, 'is-danger'));
        }
        return actions;
    }

    function renderEmpty() {
        if (!els.empty) {
            return;
        }
        els.empty.textContent = '';
        var none = state.rows.length === 0;
        var existing = {};
        state.rows.forEach(function (row) {
            existing[String(row.name).toLocaleLowerCase()] = true;
        });
        var chips = (cfg.suggestions || []).filter(function (name) {
            return !existing[String(name).toLocaleLowerCase()];
        });
        var showChips = can.add && state.suggestions && chips.length > 0;

        if (!none && !showChips) {
            els.empty.hidden = true;
            return;
        }
        els.empty.hidden = false;
        els.empty.classList.toggle('is-empty-list', none);

        if (none) {
            els.empty.appendChild(icon('category'));
            els.empty.appendChild(el('h3', { className: 'medbeafgallery-cm-empty-title', text: t.emptyTitle }));
        }
        if (!showChips) {
            return;
        }
        els.empty.appendChild(el('p', { id: 'medbeafgallery-cm-suggest-label', text: none ? t.emptyText : t.suggestTitle }));

        if (atLimit()) {
            var limit = el('p', { className: 'medbeafgallery-cm-limit' });
            els.empty.appendChild(limit);
            renderLimit(limit, true);
            return;
        }

        var group = el('div', { className: 'medbeafgallery-cm-chips', role: 'group', 'aria-labelledby': 'medbeafgallery-cm-suggest-label' });
        chips.forEach(function (name) {
            group.appendChild(el('button', {
                type: 'button',
                className: 'button medbeafgallery-cm-chip',
                'data-chip': name,
                'aria-label': fmt(t.suggestAdd, name),
                on: { click: function (e) { addSuggestion(name, e.currentTarget); } }
            }, [icon('plus-alt2'), name]));
        });
        els.empty.appendChild(group);

        if (!none) {
            els.empty.appendChild(el('button', {
                type: 'button',
                className: 'medbeafgallery-cm-link medbeafgallery-cm-dismiss',
                text: t.suggestDismiss,
                on: {
                    click: function () {
                        state.suggestions = false;
                        renderEmpty();
                        if (els.addName && !els.addName.disabled) {
                            els.addName.focus();
                        }
                    }
                }
            }));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Inline panels (rename, add subcategory, move, delete)               */
    /* ------------------------------------------------------------------ */

    function closePanels(except) {
        Array.prototype.forEach.call(els.list.querySelectorAll('.medbeafgallery-cm-panel'), function (panel) {
            if (panel === except) {
                return;
            }
            panel.hidden = true;
            panel.textContent = '';
            var row = panel.closest('.medbeafgallery-cm-row');
            if (row) {
                row.classList.remove('is-editing', 'is-confirming');
                var line = row.querySelector('.medbeafgallery-cm-line');
                if (line) {
                    line.hidden = false;
                }
            }
        });
    }

    function openPanel(item, row, type) {
        var rowEl = item.querySelector(':scope > .medbeafgallery-cm-row');
        var panel = rowEl.querySelector('.medbeafgallery-cm-panel');
        showError('');
        closePanels(panel);
        closeAllPanel();
        panel.textContent = '';
        panel.hidden = false;
        rowEl.classList.remove('is-editing', 'is-confirming');
        rowEl.querySelector('.medbeafgallery-cm-line').hidden = false;

        var cancel = function (focusPartName) {
            showError('');
            panel.hidden = true;
            panel.textContent = '';
            rowEl.classList.remove('is-editing', 'is-confirming');
            rowEl.querySelector('.medbeafgallery-cm-line').hidden = false;
            var target = rowEl.querySelector('[data-part="' + focusPartName + '"]') || rowEl.querySelector('[data-part="name"]');
            if (target) {
                target.focus();
            }
        };

        panel.onkeydown = function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                cancel(type === 'rename' ? 'name' : type);
            }
        };

        if (type === 'rename') {
            buildRenamePanel(panel, rowEl, row, cancel);
        } else if (type === 'add-sub') {
            buildAddSubPanel(panel, row, cancel);
        } else if (type === 'move') {
            buildMovePanel(panel, row, cancel);
        } else if (type === 'delete') {
            buildDeletePanel(panel, rowEl, item, row, cancel);
        }
    }

    function inputId(prefix, id) {
        return 'medbeafgallery-cm-' + prefix + '-' + id;
    }

    /* ------------------------------------------------------------------ */
    /* Add-on fields (cfg.fields, medbeafgallery_cm_extra_fields())        */
    /* ------------------------------------------------------------------ */

    // Types this screen draws; unknown ones (from a newer add-on) are skipped.
    var FIELD_TYPES = ['choice', 'image', 'toggle'];

    // The add-on fields for a category row, or for "All cases" ('all').
    function fieldsFor(scope) {
        return (cfg.fields || []).filter(function (field) {
            if (!field || FIELD_TYPES.indexOf(field.type) === -1) {
                return false;
            }
            return scope !== 'all' || !!field.all;
        });
    }

    // A url("…") value for CSS: characters that would end the string are encoded.
    function cssUrl(url) {
        return 'url("' + String(url).replace(/["'()\\\s]/g, function (c) {
            return '%' + ('0' + c.charCodeAt(0).toString(16).toUpperCase()).slice(-2);
        }) + '")';
    }

    // A small preview of an image: tinted (a mask in the text colour) or as it is.
    function imagePreview(url, tint) {
        if (!url) {
            return null;
        }
        if (tint) {
            var span = el('span', { className: 'medbeafgallery-cm-img is-tint', 'aria-hidden': 'true' });
            span.style.setProperty('--medbeafgallery-cm-img', cssUrl(url));
            return span;
        }
        return el('img', { className: 'medbeafgallery-cm-img', src: url, alt: '' });
    }

    function makeEmitter() {
        var list = [];
        return {
            on: function (fn) { list.push(fn); },
            emit: function () {
                var args = arguments;
                list.forEach(function (fn) { fn.apply(null, args); });
            }
        };
    }

    // type "choice": a row of labelled options, or (display "grid") a grouped,
    // searchable grid of icon-only tiles with the pinned options first.
    function choiceField(field, values, idBase) {
        var current = values[field.key] !== undefined && values[field.key] !== null ? String(values[field.key]) : '';
        var name = idBase + '-' + field.key;
        var grid = field.display === 'grid';
        var changed = makeEmitter();
        var picked = makeEmitter();
        var tiles = [];
        var pointerValue = null;
        var previous = current;
        var justChanged = null; // { from, to } for the click that caused the change

        var choices = field.choices || [];
        var byValue = {};
        choices.forEach(function (choice) { byValue[String(choice.value)] = choice; });

        var set = el('fieldset', { className: 'medbeafgallery-cm-choice-field' + (grid ? ' is-grid' : ''), 'data-field': field.key });
        var chosenNote = el('span', { className: 'medbeafgallery-cm-chosen', 'aria-hidden': 'true' });
        set.appendChild(el('legend', {}, [el('span', { text: field.label }), grid ? chosenNote : null]));

        function artFor(choice) {
            var art = el('span', { className: grid ? 'medbeafgallery-cm-tile-art' : 'medbeafgallery-cm-choice-icon', 'aria-hidden': 'true' });
            var svg = choice.svg;
            // The empty choice ("Automatic") previews the option it stands for.
            if (choice.value === '' && field.auto_from && values[field.auto_from] !== undefined && byValue[String(values[field.auto_from])]) {
                svg = byValue[String(values[field.auto_from])].svg || svg;
            }
            if (svg) {
                art.innerHTML = svg; // SVG allow-listed on the server (medbeafgallery_cm_extra_fields()).
            }
            return art;
        }

        function makeOption(choice, named) {
            var input = el('input', { type: 'radio', name: name, value: choice.value });
            input.checked = String(choice.value) === current;
            var label;
            if (!grid) {
                label = el('label', { className: 'medbeafgallery-cm-choice' }, [input]);
                if (choice.svg) {
                    label.appendChild(artFor(choice));
                }
                label.appendChild(el('span', { text: choice.label }));
            } else {
                label = el('label', {
                    className: 'medbeafgallery-cm-tile' + (named ? ' is-named' : ''),
                    title: named ? null : choice.label,
                    'data-value': choice.value
                }, [
                    input,
                    artFor(choice),
                    named ? el('span', { className: 'medbeafgallery-cm-tile-name', text: choice.label }) : el('span', { className: 'screen-reader-text', text: choice.label })
                ]);
            }
            var pointerAt = 0;
            label.addEventListener('pointerdown', function () {
                pointerValue = String(choice.value);
                pointerAt = Date.now();
            });
            // A mouse or touch click on an option that opens something (e.g. the
            // Media Library) opens it, also when it is already chosen. The radio
            // gets a click however it is chosen; only one that follows a pointer
            // press here counts. Keyboard users get the field's own button.
            input.addEventListener('click', function () {
                if (!choice.opens || Date.now() - pointerAt > 1500) {
                    return;
                }
                pointerAt = 0;
                window.setTimeout(function () {
                    var value = String(choice.value);
                    var from = justChanged && justChanged.to === value ? justChanged.from : null;
                    if (input.checked) {
                        picked.emit(value, true, from);
                    }
                }, 0);
            });
            input.addEventListener('change', function () {
                if (!input.checked) {
                    return;
                }
                var before = previous;
                previous = String(choice.value);
                justChanged = { from: before, to: previous };
                window.setTimeout(function () { justChanged = null; }, 0);
                syncChosen();
                changed.emit(previous);
                if (!choice.opens) {
                    picked.emit(previous, pointerValue === previous, before);
                }
                pointerValue = null;
            });
            tiles.push({ label: label, input: input, choice: choice, named: named, text: '' });
            return label;
        }

        function syncChosen() {
            var choice = byValue[previous];
            chosenNote.textContent = choice ? fmt(t.chosen || '%s', choice.label) : '';
        }

        var status = null;
        var search = null;
        var filter = null;
        if (!grid) {
            var row = el('div', { className: 'medbeafgallery-cm-choices' });
            choices.forEach(function (choice) { row.appendChild(makeOption(choice, false)); });
            set.appendChild(row);
        } else {
            var gridId = name + '-grid';
            if (field.search) {
                search = el('input', {
                    type: 'search',
                    className: 'medbeafgallery-cm-grid-search',
                    placeholder: field.search,
                    'aria-label': field.search,
                    'aria-controls': gridId,
                    autocomplete: 'off',
                    spellcheck: 'false'
                });
                status = el('span', { className: 'medbeafgallery-cm-grid-status', role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
                set.appendChild(el('div', { className: 'medbeafgallery-cm-grid-tools' }, [search, status]));
            }
            var pinned = choices.filter(function (choice) { return choice.pinned; });
            if (pinned.length) {
                var pinRow = el('div', { className: 'medbeafgallery-cm-grid-pinned' });
                pinned.forEach(function (choice) { pinRow.appendChild(makeOption(choice, true)); });
                set.appendChild(pinRow);
            }
            var scroller = el('div', { className: 'medbeafgallery-cm-grid-scroll', id: gridId });
            var groups = (field.groups || []).slice();
            var known = {};
            groups.forEach(function (group) { known[group.key] = true; });
            groups.push({ key: '', label: '' }); // anything without a known group
            groups.forEach(function (group, gi) {
                var members = choices.filter(function (choice) {
                    return !choice.pinned && (group.key ? choice.group === group.key : !known[choice.group]);
                });
                if (!members.length) {
                    return;
                }
                var titleId = name + '-g' + gi;
                var box = el('div', { className: 'medbeafgallery-cm-grid-group', role: 'group', 'aria-labelledby': group.label ? titleId : null });
                if (group.label) {
                    box.appendChild(el('p', { className: 'medbeafgallery-cm-grid-title', id: titleId, text: group.label }));
                }
                var cells = el('div', { className: 'medbeafgallery-cm-grid' });
                members.forEach(function (choice) {
                    var label = makeOption(choice, false);
                    tiles[tiles.length - 1].text = [choice.label, choice.keywords || '', group.label].join(' ').toLowerCase();
                    cells.appendChild(label);
                });
                box.appendChild(cells);
                scroller.appendChild(box);
            });
            set.appendChild(scroller);

            filter = function () {
                var words = search ? search.value.trim().toLowerCase().split(/\s+/).filter(Boolean) : [];
                var shown = 0;
                tiles.forEach(function (tile) {
                    if (tile.named) {
                        return;
                    }
                    var hit = words.every(function (w) { return tile.text.indexOf(w) !== -1; });
                    // The chosen tile stays: it is the radio group's Tab stop.
                    tile.label.hidden = !hit && !tile.input.checked;
                    if (hit) {
                        shown++;
                    }
                });
                Array.prototype.forEach.call(scroller.querySelectorAll('.medbeafgallery-cm-grid-group'), function (box) {
                    box.hidden = !box.querySelector('.medbeafgallery-cm-tile:not([hidden])');
                });
                scroller.hidden = !scroller.querySelector('.medbeafgallery-cm-tile:not([hidden])');
                if (status) {
                    status.textContent = words.length ? (shown ? fmt(shown === 1 ? t.matchesOne : t.matchesMany, shown) : t.noMatch) : '';
                }
            };
            if (search) {
                search.addEventListener('input', filter);
                search.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        // Enter filters; it does not save the panel.
                        e.preventDefault();
                    } else if (e.key === 'Escape' && search.value) {
                        e.preventDefault();
                        e.stopPropagation();
                        search.value = '';
                        filter();
                    }
                });
            }
            syncChosen();
        }
        if (field.help) {
            set.appendChild(el('p', { className: 'description', text: field.help }));
        }

        return {
            type: 'choice',
            node: set,
            get: function () {
                var input = set.querySelector('input[type="radio"]:checked');
                return input ? input.value : null;
            },
            set: function (value) {
                value = value === null || value === undefined ? '' : String(value);
                tiles.forEach(function (tile) { tile.input.checked = String(tile.choice.value) === value; });
                previous = value;
                syncChosen();
                if (filter) {
                    filter(); // the tile now chosen may not match the search
                }
                changed.emit(value);
            },
            setArt: function (value, node) {
                tiles.forEach(function (tile) {
                    if (String(tile.choice.value) !== String(value)) {
                        return;
                    }
                    var art = tile.label.querySelector('.medbeafgallery-cm-tile-art, .medbeafgallery-cm-choice-icon');
                    if (art && node) {
                        art.textContent = '';
                        art.appendChild(node);
                    } else if (art) {
                        art.parentNode.replaceChild(artFor(tile.choice), art);
                    }
                });
            },
            onChange: changed.on,
            onPick: picked.on
        };
    }

    // type "image": an attachment from the Media Library.
    function imageField(field, values, idBase) {
        var start = values[field.key] && typeof values[field.key] === 'object' ? values[field.key] : null;
        var value = { id: start && start.id ? Number(start.id) : 0, url: start && start.url ? String(start.url) : '' };
        var tint = false;
        var changed = makeEmitter();
        var labelId = idBase + '-' + field.key + '-label';
        var preview = el('span', { className: 'medbeafgallery-cm-image-preview' });
        var button = el('button', { type: 'button', className: 'button', 'aria-describedby': labelId });
        var none = el('span', { className: 'medbeafgallery-cm-image-none', text: t.imageNone });
        var node = el('div', { className: 'medbeafgallery-cm-image-field', 'data-field': field.key }, [
            el('span', { className: 'medbeafgallery-cm-field-label', id: labelId, text: field.label }),
            el('div', { className: 'medbeafgallery-cm-image-row' }, [preview, none, hasMedia() ? button : null]),
            field.help ? el('p', { className: 'description', text: field.help }) : null
        ]);

        function paint() {
            preview.textContent = '';
            var img = imagePreview(value.url, tint);
            if (img) {
                preview.appendChild(img);
            }
            preview.hidden = !img;
            none.hidden = !!img;
            button.textContent = value.id ? t.imageChange : t.imageChoose;
        }

        function open(onCancel) {
            if (!hasMedia()) {
                return;
            }
            var frame = window.wp.media({
                title: field.label || t.imageMediaTitle,
                button: { text: t.mediaButton },
                multiple: false,
                library: { type: field.mimes && field.mimes.length ? field.mimes : 'image' }
            });
            var chose = false;
            frame.on('open', function () {
                if (value.id) {
                    var attachment = window.wp.media.attachment(value.id);
                    attachment.fetch();
                    frame.state().get('selection').reset([attachment]);
                }
            });
            frame.on('select', function () {
                var picked = frame.state().get('selection').first();
                if (!picked) {
                    return;
                }
                var data = picked.toJSON();
                if (field.mimes && field.mimes.length && data.mime && field.mimes.indexOf(data.mime) === -1) {
                    showError(field.help || t.imageNone);
                    announce(field.help || t.imageNone);
                    return;
                }
                var sizes = data.sizes || {};
                var best = sizes.medium || sizes.thumbnail || sizes.full;
                chose = true;
                value = { id: Number(data.id), url: (best && best.url) || data.url || '' };
                paint();
                changed.emit(value.id);
            });
            frame.on('close', function () {
                window.setTimeout(function () {
                    if (!chose && onCancel) {
                        onCancel();
                    }
                    if (document.body.contains(button)) {
                        button.focus();
                    }
                }, 0);
            });
            frame.open();
        }

        button.addEventListener('click', function () { open(null); });
        paint();

        return {
            type: 'image',
            node: node,
            get: function () { return value.id || 0; },
            has: function () { return !!value.id; },
            url: function () { return value.url; },
            open: open,
            setTint: function (on) { tint = !!on; paint(); },
            preview: function () { return imagePreview(value.url, tint); },
            onChange: changed.on
        };
    }

    // type "toggle": a checkbox.
    function toggleField(field, values, idBase) {
        var id = idBase + '-' + field.key;
        var helpId = id + '-help';
        var on = values[field.key] === undefined || values[field.key] === null ? field.default !== false : !!values[field.key];
        var changed = makeEmitter();
        var input = el('input', { type: 'checkbox', id: id, 'aria-describedby': field.help ? helpId : null });
        input.checked = on;
        input.addEventListener('change', function () { changed.emit(input.checked); });
        var node = el('div', { className: 'medbeafgallery-cm-toggle-field', 'data-field': field.key }, [
            el('label', { 'for': id }, [input, el('span', { text: field.label })]),
            field.help ? el('p', { className: 'description', id: helpId, text: field.help }) : null
        ]);
        return {
            type: 'toggle',
            node: node,
            get: function () { return input.checked; },
            onChange: changed.on
        };
    }

    /**
     * Builds a set of add-on fields and wires them together: show_if, an image
     * previewed tinted while its tint toggle is on, and a choice that opens an
     * image field (e.g. Pro's "Custom image").
     */
    function buildExtraFields(fields, values, idBase) {
        values = values || {};
        var apis = {};
        var nodes = [];
        fields.forEach(function (field) {
            var api;
            if (field.type === 'image') {
                api = imageField(field, values, idBase);
            } else if (field.type === 'toggle') {
                api = toggleField(field, values, idBase);
            } else {
                api = choiceField(field, values, idBase);
            }
            apis[field.key] = api;
            nodes.push(api.node);
        });

        fields.forEach(function (field) {
            if (field.show_if && apis[field.show_if.field]) {
                var ctrl = apis[field.show_if.field];
                var sync = function () {
                    apis[field.key].node.hidden = String(ctrl.get()) !== String(field.show_if.value);
                };
                ctrl.onChange(sync);
                sync();
            }
            if (field.type === 'image' && field.tint_field && apis[field.tint_field] && apis[field.tint_field].type === 'toggle') {
                var toggle = apis[field.tint_field];
                apis[field.key].setTint(toggle.get());
                toggle.onChange(function (on) { apis[field.key].setTint(on); });
            }
        });

        fields.forEach(function (field) {
            if (field.type !== 'choice') {
                return;
            }
            var api = apis[field.key];
            (field.choices || []).forEach(function (choice) {
                var image = choice.opens ? apis[choice.opens] : null;
                if (!image || image.type !== 'image') {
                    return;
                }
                var paintTile = function () { api.setArt(choice.value, image.preview()); };
                image.onChange(paintTile);
                var tintField = fields.filter(function (f) { return f.key === choice.opens; })[0];
                if (tintField && tintField.tint_field && apis[tintField.tint_field]) {
                    apis[tintField.tint_field].onChange(paintTile);
                }
                paintTile();
                api.onPick(function (value, byPointer, previous) {
                    if (value !== String(choice.value) || !byPointer) {
                        return;
                    }
                    image.open(function () {
                        // Closed without an image: go back to the earlier choice.
                        if (!image.has() && previous !== null && previous !== undefined) {
                            api.set(previous);
                        }
                    });
                });
            });
        });

        return {
            nodes: nodes,
            collect: function () {
                var extra = {};
                fields.forEach(function (field) {
                    var value = apis[field.key].get();
                    if (value !== null && value !== undefined) {
                        extra[field.key] = value;
                    }
                });
                return extra;
            }
        };
    }

    // The Edit panel: name, description and the add-on fields.
    function buildRenamePanel(panel, rowEl, row, cancel) {
        rowEl.classList.add('is-editing');
        rowEl.querySelector('.medbeafgallery-cm-line').hidden = true;

        var id = inputId('rename', row.id);
        var descId = inputId('description', row.id);
        var helpId = descId + '-help';
        var input = el('input', { type: 'text', id: id, className: 'regular-text', value: row.name, maxlength: '200', autocomplete: 'off' });
        var description = el('textarea', { id: descId, rows: '2', maxlength: '1000', 'aria-describedby': helpId });
        description.value = row.description || '';
        var extraFields = buildExtraFields(fieldsFor('row'), row.extra, inputId('field', row.id));
        var body = [
            el('div', { className: 'medbeafgallery-cm-edit-field' }, [
                el('label', { 'for': id, text: t.nameLabel || t.newName }),
                input
            ]),
            el('div', { className: 'medbeafgallery-cm-edit-field' }, [
                el('label', { 'for': descId, text: t.descriptionLabel }),
                description,
                el('p', { className: 'description', id: helpId, text: t.descriptionHelp })
            ])
        ].concat(extraFields.nodes);
        body.push(el('div', { className: 'medbeafgallery-cm-edit-buttons' }, [
            el('button', { type: 'submit', className: 'button button-primary', text: t.save }),
            el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: function () { cancel('rename'); } } })
        ]));
        var form = el('form', { className: 'medbeafgallery-cm-edit-form', novalidate: true, 'aria-label': fmt(t.editLabel || t.renameLabel, row.name) }, body);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = input.value.trim();
            if (!name) {
                showError(t.nameRequired);
                announce(t.nameRequired);
                input.focus();
                return;
            }
            var extra = extraFields.collect();
            setFormBusy(form, true);
            write('edit', { id: row.id, name: name, description: description.value, extra: JSON.stringify(extra) }, {
                success: function (data) {
                    var newName = data.row ? data.row.name : name;
                    announce(newName !== row.name ? fmt(t.renamed, row.name, newName) : fmt(t.saved, newName));
                    focusPart({ id: row.id, part: 'rename' });
                    flash(row.id);
                },
                error: function (result, changed) {
                    if (!changed) {
                        setFormBusy(form, false);
                        input.focus();
                        input.select();
                    } else {
                        focusPart({ id: row.id, part: 'name' });
                    }
                }
            });
        });
        panel.appendChild(form);
        input.focus();
        input.select();
    }

    // The "All cases" Edit panel: only the add-on fields offered for it. The
    // server refuses every other change to "All" (op all_edit).
    function openAllPanel() {
        var all = state.all;
        var panel = els.all ? els.all.querySelector('.medbeafgallery-cm-panel') : null;
        if (!all || !panel) {
            return;
        }
        showError('');
        closePanels(null);
        panel.textContent = '';
        panel.hidden = false;
        els.all.classList.add('is-editing');

        var cancel = function () {
            showError('');
            panel.hidden = true;
            panel.textContent = '';
            els.all.classList.remove('is-editing');
            focusPart({ id: 'all', part: 'edit' });
        };
        panel.onkeydown = function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                cancel();
            }
        };

        var extraFields = buildExtraFields(fieldsFor('all'), all.extra, 'medbeafgallery-cm-all-field');
        var body = [el('p', { className: 'medbeafgallery-cm-panel-note', text: t.allEditNote })].concat(extraFields.nodes);
        body.push(el('div', { className: 'medbeafgallery-cm-edit-buttons' }, [
            el('button', { type: 'submit', className: 'button button-primary', text: t.save }),
            el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: cancel } })
        ]));
        var form = el('form', { className: 'medbeafgallery-cm-edit-form', novalidate: true, 'aria-label': t.allEditLabel }, body);
        var focusFirst = function () {
            var first = form.querySelector('input:checked') || form.querySelector('input, button');
            if (first) {
                first.focus();
            }
        };
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            setFormBusy(form, true);
            write('all_edit', { id: all.id, extra: JSON.stringify(extraFields.collect()) }, {
                success: function () {
                    announce(t.allSaved);
                    focusPart({ id: 'all', part: 'edit' });
                },
                error: function (result, changed) {
                    if (!changed) {
                        setFormBusy(form, false);
                        focusFirst();
                    } else {
                        focusPart({ id: 'all', part: 'edit' });
                    }
                }
            });
        });
        panel.appendChild(form);
        focusFirst();
    }

    function closeAllPanel() {
        if (!els.all) {
            return;
        }
        var panel = els.all.querySelector('.medbeafgallery-cm-panel');
        if (panel && !panel.hidden) {
            panel.hidden = true;
            panel.textContent = '';
        }
        els.all.classList.remove('is-editing');
    }

    function buildAddSubPanel(panel, row, cancel) {
        var id = inputId('addsub', row.id);
        var input = el('input', { type: 'text', id: id, className: 'regular-text', maxlength: '200', autocomplete: 'off' });
        var form = el('form', { className: 'medbeafgallery-cm-inline-form', novalidate: true }, [
            el('label', { 'for': id, text: fmt(t.addSubLabel, row.name) }),
            input,
            el('button', { type: 'submit', className: 'button button-primary', text: t.add }),
            el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: function () { cancel('add-sub'); } } })
        ]);
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = input.value.trim();
            if (!name) {
                showError(t.nameRequired);
                announce(t.nameRequired);
                input.focus();
                return;
            }
            setFormBusy(form, true);
            write('add', { name: name, parent: row.id }, {
                success: function (data) {
                    var created = data.row;
                    announce(fmt(t.addedInside, created ? created.name : name, row.name));
                    if (created) {
                        focusPart({ id: created.id, part: 'name' });
                        flash(created.id);
                    }
                },
                error: function (result, changed) {
                    if (!changed) {
                        setFormBusy(form, false);
                        input.focus();
                    } else {
                        focusPart({ id: row.id, part: 'name' });
                    }
                }
            });
        });
        panel.appendChild(form);
        input.focus();
    }

    function buildMovePanel(panel, row, cancel) {
        var id = inputId('move', row.id);
        var destinations = moveDestinations(row);
        var select = el('select', { id: id });
        destinations.forEach(function (dest) {
            select.appendChild(el('option', { value: String(dest.id), text: dest.name }));
        });
        var form = el('form', { className: 'medbeafgallery-cm-inline-form', novalidate: true }, [
            el('label', { 'for': id, text: fmt(t.moveToLabel, row.name) }),
            select,
            el('button', { type: 'submit', className: 'button button-primary', text: t.move }),
            el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: function () { cancel('move'); } } })
        ]);
        if (childrenOf(row.id).length) {
            form.appendChild(el('p', { className: 'medbeafgallery-cm-panel-note', text: t.hasChildrenNote }));
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var parent = Number(select.value) || 0;
            var destName = parent ? (rowById(parent) ? rowById(parent).name : '') : t.theTopLevel;
            setFormBusy(form, true);
            write('move', { id: row.id, parent: parent }, {
                success: function () {
                    announce(fmt(t.moved, row.name, parent ? '“' + destName + '”' : destName));
                    focusPart({ id: row.id, part: 'name' });
                    flash(row.id);
                },
                error: function (result, changed) {
                    if (!changed) {
                        setFormBusy(form, false);
                        select.focus();
                    } else {
                        focusPart({ id: row.id, part: 'name' });
                    }
                }
            });
        });
        panel.appendChild(form);
        select.focus();
    }

    function buildDeletePanel(panel, rowEl, item, row, cancel) {
        rowEl.classList.add('is-confirming');
        var qid = inputId('delq', row.id);
        var did = inputId('deld', row.id);
        var count = Number(row.count) || 0;
        var details = [];
        details.push(count === 0 ? t.deleteNoCases : fmt(count === 1 ? t.deleteCasesOne : t.deleteCasesMany, count));
        if (childrenOf(row.id).length) {
            var parent = rowById(row.parent);
            details.push(fmt(t.deleteChildren, parent ? '“' + parent.name + '”' : t.theTopLevel));
        }

        var confirmButton = el('button', { type: 'button', className: 'button medbeafgallery-cm-delete-button', text: t.deleteConfirm });
        var cancelButton = el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: function () { cancel('delete'); } } });
        var box = el('div', { className: 'medbeafgallery-cm-confirm', role: 'group', 'aria-labelledby': qid, 'aria-describedby': did }, [
            el('p', { id: qid, className: 'medbeafgallery-cm-confirm-title', text: fmt(t.deleteQuestion, row.name) }),
            el('p', { id: did, className: 'medbeafgallery-cm-confirm-text', text: details.join(' ') }),
            el('div', { className: 'medbeafgallery-cm-confirm-buttons' }, [confirmButton, cancelButton])
        ]);

        confirmButton.addEventListener('click', function () {
            // Where focus goes afterwards: next sibling, previous sibling, parent, add field.
            var next = nextFocusAfterDelete(item, row);
            confirmButton.disabled = true;
            cancelButton.disabled = true;
            write('delete', { id: row.id }, {
                success: function () {
                    announce(fmt(t.deleted, row.name));
                    focusAfterDelete(next);
                },
                error: function (result, changed) {
                    if (!changed) {
                        confirmButton.disabled = false;
                        cancelButton.disabled = false;
                        cancelButton.focus();
                    } else {
                        focusAfterDelete(next);
                    }
                }
            });
        });

        panel.appendChild(box);
        cancelButton.focus();
    }

    function nextFocusAfterDelete(item, row) {
        var sibling = item.nextElementSibling || item.previousElementSibling;
        if (sibling && sibling.getAttribute('data-id')) {
            return { id: Number(sibling.getAttribute('data-id')), part: 'name' };
        }
        // Its children move up into its place: focus the first of them.
        var kids = childrenOf(row.id);
        if (kids.length) {
            return { id: Number(kids[0].id), part: 'name' };
        }
        if (Number(row.parent)) {
            return { id: Number(row.parent), part: 'name' };
        }
        return null;
    }

    function focusAfterDelete(spec) {
        if (spec && rowById(spec.id)) {
            focusPart(spec);
            return;
        }
        var first = els.list.querySelector('[data-part="name"]');
        if (first) {
            first.focus();
        } else if (els.addName && !els.addName.disabled) {
            els.addName.focus();
        } else {
            var chip = els.empty && els.empty.querySelector('button');
            if (chip) {
                chip.focus();
            }
        }
    }

    function setFormBusy(form, busy) {
        Array.prototype.forEach.call(form.querySelectorAll('input, select, button'), function (control) {
            control.disabled = busy;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Adding                                                              */
    /* ------------------------------------------------------------------ */

    function onAddSubmit(e) {
        e.preventDefault();
        if (atLimit()) {
            return;
        }
        var name = els.addName.value.trim();
        var parent = Number(els.addParent.value) || 0;
        if (!name) {
            showError(t.nameRequired);
            announce(t.nameRequired);
            els.addName.focus();
            return;
        }
        setFormBusy(els.addForm, true);
        write('add', { name: name, parent: parent }, {
            success: function (data) {
                setFormBusy(els.addForm, false);
                renderAddForm();
                els.addName.value = '';
                var created = data.row;
                var parentRow = rowById(parent);
                announce(parentRow ? fmt(t.addedInside, created ? created.name : name, parentRow.name) : fmt(t.added, created ? created.name : name));
                if (created) {
                    focusPart({ id: created.id, part: 'name' });
                    flash(created.id);
                }
            },
            error: function () {
                setFormBusy(els.addForm, false);
                renderAddForm();
                if (!els.addName.disabled) {
                    els.addName.focus();
                    els.addName.select();
                }
            }
        });
    }

    function addSuggestion(name, button) {
        if (atLimit()) {
            return;
        }
        // Next chip to focus, so several can be added in a row.
        var chips = Array.prototype.slice.call(els.empty.querySelectorAll('.medbeafgallery-cm-chip'));
        var at = chips.indexOf(button);
        var nextChip = chips[at + 1] || chips[at - 1] || null;
        var nextName = nextChip ? nextChip.getAttribute('data-chip') : null;
        chips.forEach(function (chip) { chip.disabled = true; });

        write('add', { name: name, parent: 0 }, {
            success: function (data) {
                announce(fmt(t.added, data.row ? data.row.name : name));
                if (data.row) {
                    flash(data.row.id);
                }
                var again = nextName ? els.empty.querySelector('.medbeafgallery-cm-chip[data-chip="' + cssEscape(nextName) + '"]') : null;
                if (again && !again.disabled) {
                    again.focus();
                } else if (data.row) {
                    focusPart({ id: data.row.id, part: 'name' });
                }
            },
            error: function () {
                renderEmpty();
                var same = els.empty.querySelector('.medbeafgallery-cm-chip[data-chip="' + cssEscape(name) + '"]');
                if (same) {
                    same.focus();
                }
            }
        });
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/["\\]/g, '\\$&');
    }

    /* ------------------------------------------------------------------ */
    /* Images                                                              */
    /* ------------------------------------------------------------------ */

    function hasMedia() {
        return !!(window.wp && window.wp.media);
    }

    function pickImage(id) {
        if (!hasMedia()) {
            return;
        }
        var current = id === 'all' ? state.all : rowById(id);
        var frame = window.wp.media({
            title: t.mediaTitle,
            button: { text: t.mediaButton },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('open', function () {
            if (current && current.image_id) {
                var attachment = window.wp.media.attachment(current.image_id);
                attachment.fetch();
                frame.state().get('selection').reset([attachment]);
            }
        });
        frame.on('select', function () {
            var chosen = frame.state().get('selection').first();
            if (!chosen) {
                return;
            }
            var data = chosen.toJSON();
            if (current && Number(current.image_id) === Number(data.id)) {
                return;
            }
            setImage(id, data.id);
        });
        frame.on('close', function () {
            window.setTimeout(function () {
                if (!pending) {
                    focusPart({ id: id, part: 'thumb' });
                }
            }, 0);
        });
        frame.open();
    }

    function setImage(id, attachmentId) {
        var isAll = id === 'all';
        var target = isAll ? state.all : rowById(id);
        if (!target) {
            return;
        }
        var label = isAll ? t.allTitle : target.name;
        write('image', { id: target.id, attachment_id: attachmentId || 0 }, {
            success: function () {
                announce(fmt(attachmentId ? t.imageSet : t.imageRemoved, label));
                focusPart({ id: isAll ? 'all' : target.id, part: 'thumb' });
                if (!isAll) {
                    flash(target.id);
                }
            },
            error: function () {
                focusPart({ id: isAll ? 'all' : target.id, part: 'thumb' });
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Reordering                                                          */
    /* ------------------------------------------------------------------ */

    /** Reorder siblings locally (optimistic) and keep the flat tree order. */
    function reorderLocal(parentId, ids) {
        var position = {};
        ids.forEach(function (id, index) {
            position[Number(id)] = index;
        });
        var byParent = {};
        state.rows.forEach(function (row) {
            var key = Number(row.parent);
            (byParent[key] = byParent[key] || []).push(row);
            if (Number(row.parent) === Number(parentId) && position[Number(row.id)] !== undefined) {
                row.order = position[Number(row.id)];
            }
        });
        if (byParent[Number(parentId)]) {
            byParent[Number(parentId)].sort(function (a, b) {
                return position[Number(a.id)] - position[Number(b.id)];
            });
        }
        var out = [];
        var seen = {};
        var walk = function (pid) {
            (byParent[pid] || []).forEach(function (row) {
                if (seen[row.id]) {
                    return;
                }
                seen[row.id] = true;
                out.push(row);
                walk(Number(row.id));
            });
        };
        walk(0);
        state.rows.forEach(function (row) {
            if (!seen[row.id]) {
                out.push(row);
            }
        });
        state.rows = out;
    }

    function saveOrder(parentId, ids, movedRow, focusSpec) {
        var position = ids.indexOf(Number(movedRow.id)) + 1;
        write('reorder', { parent: parentId, ids: ids }, {
            success: function () {
                announce(fmt(t.reordered, movedRow.name, position, ids.length));
                if (focusSpec) {
                    focusPart(focusSpec);
                }
            },
            error: function () {
                // The server's list was applied and rendered; keep focus on the row.
                render();
                focusPart({ id: movedRow.id, part: 'handle' });
            }
        });
    }

    function moveBy(row, delta, part) {
        var siblings = childrenOf(row.parent).map(function (r) { return Number(r.id); });
        var from = siblings.indexOf(Number(row.id));
        var to = delta === Infinity ? siblings.length - 1 : (delta === -Infinity ? 0 : from + delta);
        to = Math.max(0, Math.min(siblings.length - 1, to));
        if (from < 0 || to === from) {
            return;
        }
        siblings.splice(from, 1);
        siblings.splice(to, 0, Number(row.id));

        reorderLocal(row.parent, siblings);
        renderList();
        var spec = { id: row.id, part: part };
        // At an end the arrow button is disabled: hand focus to the other one.
        if (part === 'up' && to === 0) {
            spec.part = 'down';
        } else if (part === 'down' && to === siblings.length - 1) {
            spec.part = 'up';
        }
        focusPart(spec);
        flash(row.id);
        saveOrder(Number(row.parent), siblings, row, spec);
    }

    function onHandleKey(e, row) {
        var delta = null;
        if (e.key === 'ArrowUp') {
            delta = -1;
        } else if (e.key === 'ArrowDown') {
            delta = 1;
        } else if (e.key === 'Home') {
            delta = -Infinity;
        } else if (e.key === 'End') {
            delta = Infinity;
        }
        if (delta === null) {
            return;
        }
        e.preventDefault();
        moveBy(row, delta, 'handle');
    }

    function siblingItems(list) {
        return Array.prototype.filter.call(list.children, function (node) {
            return node.matches && node.matches('li.medbeafgallery-cm-item');
        });
    }

    function startDrag(e, item, row) {
        if (e.button !== 0 || drag) {
            return;
        }
        var handle = e.currentTarget;
        e.preventDefault();
        try {
            handle.setPointerCapture(e.pointerId);
        } catch (err) {
            // Ignore: capture is a nicety.
        }
        drag = {
            item: item,
            row: row,
            handle: handle,
            list: item.parentNode,
            startY: e.clientY,
            next: item.nextSibling,
            moved: false,
            pointerId: e.pointerId
        };
        handle.addEventListener('pointermove', onDragMove);
        handle.addEventListener('pointerup', onDragEnd);
        handle.addEventListener('pointercancel', onDragCancel);
        document.addEventListener('keydown', onDragKey, true);
    }

    function onDragMove(e) {
        if (!drag || e.pointerId !== drag.pointerId) {
            return;
        }
        var y = e.clientY;
        if (!drag.moved) {
            if (Math.abs(y - drag.startY) < 4) {
                return;
            }
            drag.moved = true;
            closePanels(null);
            drag.item.classList.add('is-dragging');
            root.classList.add('is-sorting');
        }

        var others = siblingItems(drag.list).filter(function (node) {
            return node !== drag.item;
        });
        var before = null;
        for (var i = 0; i < others.length; i++) {
            var rect = others[i].getBoundingClientRect();
            if (y < rect.top + rect.height / 2) {
                before = others[i];
                break;
            }
        }
        if (before) {
            if (drag.item.nextElementSibling !== before) {
                drag.list.insertBefore(drag.item, before);
            }
        } else if (drag.list.lastElementChild !== drag.item) {
            drag.list.appendChild(drag.item);
        }

        // Scroll when dragging near the window edges.
        var edge = 48;
        if (y < edge) {
            window.scrollBy(0, -12);
        } else if (y > window.innerHeight - edge) {
            window.scrollBy(0, 12);
        }
    }

    function stopDrag() {
        if (!drag) {
            return null;
        }
        var d = drag;
        drag = null;
        d.handle.removeEventListener('pointermove', onDragMove);
        d.handle.removeEventListener('pointerup', onDragEnd);
        d.handle.removeEventListener('pointercancel', onDragCancel);
        document.removeEventListener('keydown', onDragKey, true);
        try {
            d.handle.releasePointerCapture(d.pointerId);
        } catch (err) {
            // Already released.
        }
        d.item.classList.remove('is-dragging');
        root.classList.remove('is-sorting');
        return d;
    }

    function onDragEnd() {
        var d = stopDrag();
        if (!d || !d.moved) {
            return;
        }
        var ids = siblingItems(d.list).map(function (node) {
            return Number(node.getAttribute('data-id'));
        });
        var old = childrenOf(d.row.parent).map(function (r) { return Number(r.id); });
        if (ids.join(',') === old.join(',')) {
            return;
        }
        reorderLocal(d.row.parent, ids);
        renderList();
        var spec = { id: d.row.id, part: 'handle' };
        focusPart(spec);
        flash(d.row.id);
        saveOrder(Number(d.row.parent), ids, d.row, spec);
    }

    function restoreDrag(d) {
        if (d && d.moved) {
            d.list.insertBefore(d.item, d.next && d.next.parentNode === d.list ? d.next : null);
        }
    }

    function onDragCancel() {
        restoreDrag(stopDrag());
    }

    function onDragKey(e) {
        if (e.key === 'Escape' && drag) {
            e.preventDefault();
            e.stopPropagation();
            var d = stopDrag();
            restoreDrag(d);
            if (d) {
                d.handle.focus();
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Start                                                               */
    /* ------------------------------------------------------------------ */

    root.classList.add('has-js');
    if (els.addForm) {
        els.addForm.addEventListener('submit', onAddSubmit);
    }
    render();
}());
