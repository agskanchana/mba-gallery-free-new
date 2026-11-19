/**
 * Doctors manager (admin/doctors-manager.php).
 *
 * Renders the doctor directory from medbeafgalleryDoctorsManager and talks to
 * the wp_ajax_medbeafgallery_doctors_manager handler. Every response carries
 * the whole list, so the screen re-renders from the server's truth after each
 * change and puts focus back where the user was.
 *
 * Plain ES2017, no dependencies (wp.media is used when present).
 */
(function () {
    'use strict';

    var cfg = window.medbeafgalleryDoctorsManager;
    var root = document.getElementById('medbeafgallery-dm');
    if (!cfg || !root) {
        return;
    }

    var t = cfg.i18n || {};
    var can = cfg.can || {};
    var searchFrom = Number(cfg.searchFrom) || 7;

    var els = {
        grid: document.getElementById('medbeafgallery-dm-grid'),
        empty: document.getElementById('medbeafgallery-dm-empty'),
        emptyAdd: document.getElementById('medbeafgallery-dm-empty-add'),
        add: document.getElementById('medbeafgallery-dm-add'),
        live: document.getElementById('medbeafgallery-dm-live'),
        notice: document.getElementById('medbeafgallery-dm-notice'),
        toolbar: document.getElementById('medbeafgallery-dm-toolbar'),
        search: document.getElementById('medbeafgallery-dm-search-input'),
        count: document.getElementById('medbeafgallery-dm-count'),
        nomatch: document.getElementById('medbeafgallery-dm-nomatch'),
        drawer: document.getElementById('medbeafgallery-dm-drawer'),
        sheet: root.querySelector('.medbeafgallery-dm-sheet'),
        form: document.getElementById('medbeafgallery-dm-form'),
        title: document.getElementById('medbeafgallery-dm-drawer-title'),
        formError: document.getElementById('medbeafgallery-dm-form-error'),
        preview: document.getElementById('medbeafgallery-dm-photo-preview'),
        choose: document.getElementById('medbeafgallery-dm-photo-choose'),
        remove: document.getElementById('medbeafgallery-dm-photo-remove'),
        name: document.getElementById('medbeafgallery-dm-name'),
        nameError: document.getElementById('medbeafgallery-dm-name-error'),
        credentials: document.getElementById('medbeafgallery-dm-credentials'),
        location: document.getElementById('medbeafgallery-dm-location'),
        url: document.getElementById('medbeafgallery-dm-url'),
        urlError: document.getElementById('medbeafgallery-dm-url-error'),
        save: document.getElementById('medbeafgallery-dm-save'),
        more: document.getElementById('medbeafgallery-dm-more')
    };

    var state = {
        rows: Array.isArray(cfg.rows) ? cfg.rows : [],
        query: ''
    };

    // The open add/edit panel.
    var panel = {
        open: false,
        id: 0,
        tone: 'new',
        photoId: 0,
        photoUrl: '',
        trigger: null,
        busy: false,
        closeTimer: null
    };

    var queue = Promise.resolve();
    var pending = 0;
    var drag = null;
    var mediaFrame = null;

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

    function casesText(count) {
        return count === 1 ? t.casesOne : fmt(t.casesMany, count);
    }

    /**
     * Up to two initials, e.g. "Dr. Jane Smith, MD" → "JS".
     * Kept in step with medbeafgallery_dm_initials() in PHP.
     */
    function initials(name) {
        name = String(name || '').trim();
        var comma = name.indexOf(',');
        if (comma > 0) {
            name = name.slice(0, comma);
        }
        // Spaces only: "Lindqvist-Barros" gives L, not B.
        var words = name.split(/\s+/).filter(Boolean);
        if (!words.length) {
            return '';
        }
        var titles = ['dr', 'dr.', 'dra', 'dra.', 'prof', 'prof.', 'mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'miss', 'mx', 'mx.'];
        while (words.length > 1 && titles.indexOf(words[0].toLowerCase()) !== -1) {
            words.shift();
        }
        // First letter or digit, skipping quotes and brackets (ES2017: no \p{L}).
        var first = function (word) {
            var match = word.match(/[0-9A-Za-zÀ-ɏͰ-ϿЀ-ӿ֐-׿؀-ۿ぀-ヿ一-鿿가-힯]/);
            return match ? match[0] : '';
        };
        var out = first(words[0]);
        if (words.length > 1) {
            out += first(words[words.length - 1]);
        }
        return out.toLocaleUpperCase();
    }

    /**
     * '' stays '', "example.com/x" gains https://, otherwise null when it is
     * not an http(s) address with a host. Kept in step with
     * medbeafgallery_dm_normalise_url() in PHP (the server has the last word).
     */
    function normaliseUrl(raw) {
        raw = String(raw || '').replace(/[\r\n\t ]+/g, '');
        if (!raw) {
            return '';
        }
        if (!/^[a-z][a-z0-9+.-]*:/i.test(raw)) {
            raw = 'https://' + raw.replace(/^\/+/, '');
        }
        var parsed;
        try {
            parsed = new URL(raw);
        } catch (e) {
            return null;
        }
        if ((parsed.protocol !== 'http:' && parsed.protocol !== 'https:') || !parsed.hostname) {
            return null;
        }
        if (parsed.hostname.indexOf('.') === -1 && parsed.hostname !== 'localhost') {
            return null;
        }
        return raw;
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

    function cardEl(id) {
        return els.grid.querySelector('li[data-id="' + Number(id) + '"]');
    }

    function focusCard(id, part) {
        var card = cardEl(id);
        if (!card) {
            return false;
        }
        var target = card.querySelector('[data-part="' + (part || 'edit') + '"]');
        if (!target || target.disabled) {
            target = card.querySelector('[data-part="edit"]') || card.querySelector('[data-part="handle"]') || card.querySelector('[data-part="name"]');
        }
        if (target && !target.disabled) {
            target.focus();
            return true;
        }
        return false;
    }

    function focusFallback() {
        var first = els.grid.querySelector('[data-part="edit"]');
        if (first) {
            first.focus();
        } else if (els.emptyAdd && !els.empty.hidden) {
            els.emptyAdd.focus();
        } else if (els.add) {
            els.add.focus();
        }
    }

    function flash(id) {
        var card = cardEl(id);
        if (!card) {
            return;
        }
        card.classList.remove('is-flash');
        // Restart the animation.
        void card.offsetWidth;
        card.classList.add('is-flash');
    }

    function reducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    /* ------------------------------------------------------------------ */
    /* Server                                                              */
    /* ------------------------------------------------------------------ */

    function applyState(data) {
        if (!data || !Array.isArray(data.rows)) {
            return false;
        }
        var changed = !sameRows(state.rows, data.rows);
        state.rows = data.rows;
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

    /* ------------------------------------------------------------------ */
    /* Rendering                                                           */
    /* ------------------------------------------------------------------ */

    function render() {
        renderToolbar();
        renderGrid();
        renderEmpty();
    }

    function hasSearch() {
        return state.rows.length >= searchFrom;
    }

    function matches(row, query) {
        if (!query) {
            return true;
        }
        var hay = [row.name, row.credentials, row.location].join(' ').toLocaleLowerCase();
        return query.toLocaleLowerCase().split(/\s+/).filter(Boolean).every(function (word) {
            return hay.indexOf(word) !== -1;
        });
    }

    function visibleRows() {
        var query = hasSearch() ? state.query.trim() : '';
        return state.rows.filter(function (row) {
            return matches(row, query);
        });
    }

    function renderToolbar() {
        if (!els.toolbar) {
            return;
        }
        var show = hasSearch();
        els.toolbar.hidden = !show;
        if (!show) {
            // Fewer doctors than the threshold: a stale query would hide cards.
            state.query = '';
            if (els.search) {
                els.search.value = '';
            }
            return;
        }
        var total = state.rows.length;
        var shown = visibleRows().length;
        els.count.textContent = state.query.trim()
            ? fmt(t.countFiltered, shown, total)
            : (total === 1 ? t.countOne : fmt(t.countMany, total));
    }

    function renderGrid() {
        var rows = visibleRows();
        var reorderable = can.edit && !state.query.trim() && state.rows.length > 1;
        els.grid.textContent = '';
        rows.forEach(function (row, index) {
            els.grid.appendChild(buildCard(row, index, rows.length, reorderable));
        });
        els.grid.hidden = rows.length === 0;
        els.grid.classList.toggle('is-reorderable', reorderable);

        if (els.nomatch) {
            els.nomatch.textContent = '';
            var none = state.rows.length > 0 && rows.length === 0;
            els.nomatch.hidden = !none;
            if (none) {
                els.nomatch.appendChild(document.createTextNode(fmt(t.noMatch, state.query.trim()) + ' '));
                els.nomatch.appendChild(el('button', {
                    type: 'button',
                    className: 'button-link',
                    text: t.clearSearch,
                    on: {
                        click: function () {
                            state.query = '';
                            els.search.value = '';
                            render();
                            els.search.focus();
                        }
                    }
                }));
            }
        }
    }

    function renderEmpty() {
        if (!els.empty) {
            return;
        }
        els.empty.hidden = state.rows.length > 0;
        if (els.emptyAdd) {
            els.emptyAdd.hidden = !can.add;
        }
    }

    function avatarContent(container, photoUrl, name) {
        container.textContent = '';
        if (photoUrl) {
            container.appendChild(el('img', { src: photoUrl, alt: '' }));
            container.classList.add('has-photo');
        } else {
            container.appendChild(el('span', { className: 'medbeafgallery-dm-initials', 'aria-hidden': 'true', text: initials(name) }));
            container.classList.remove('has-photo');
        }
    }

    function buildCard(row, index, total, reorderable) {
        var card = el('li', {
            className: 'medbeafgallery-dm-card medbeafgallery-dm-tone-' + (Number(row.tone) || 0),
            'data-id': row.id
        });

        var avatar = el('span', { className: 'medbeafgallery-dm-avatar' });
        avatarContent(avatar, row.photo_url, row.name);

        var body = el('div', { className: 'medbeafgallery-dm-card-body' }, [
            avatar,
            el('h3', { className: 'medbeafgallery-dm-name', 'data-part': 'name', tabindex: '-1', text: row.name }),
            row.credentials ? el('p', { className: 'medbeafgallery-dm-credentials', text: row.credentials }) : null,
            row.location ? el('p', { className: 'medbeafgallery-dm-location' }, [icon('location'), row.location]) : null
        ]);

        var count = Number(row.count) || 0;
        var cases = count > 0
            ? el('a', { className: 'medbeafgallery-dm-cases', href: row.cases_url, 'data-part': 'cases' }, [
                icon('format-gallery'),
                el('span', { 'aria-hidden': 'true', text: casesText(count) }),
                srText(casesText(count) + ' — ' + fmt(t.casesLinkLabel, row.name))
            ])
            : el('span', { className: 'medbeafgallery-dm-cases is-zero' }, [icon('format-gallery'), t.noCases]);

        var profile = row.url
            ? el('a', {
                className: 'medbeafgallery-dm-profile',
                href: row.url,
                target: '_blank',
                rel: 'noopener noreferrer',
                'aria-label': fmt(t.profileLabel, row.name)
            }, [t.profile, icon('external')])
            : null;

        var meta = el('div', { className: 'medbeafgallery-dm-card-meta' }, [cases, profile]);

        var actions = el('div', { className: 'medbeafgallery-dm-card-actions' });
        if (reorderable) {
            actions.appendChild(el('div', { className: 'medbeafgallery-dm-order' }, [
                el('button', {
                    type: 'button',
                    className: 'medbeafgallery-dm-handle',
                    'data-part': 'handle',
                    'aria-label': fmt(t.reorder, row.name),
                    'aria-describedby': 'medbeafgallery-dm-reorder-hint',
                    title: fmt(t.reorder, row.name),
                    on: {
                        keydown: function (e) { onHandleKey(e, row); },
                        pointerdown: function (e) { startDrag(e, card, row); }
                    }
                }, [el('span', { className: 'medbeafgallery-dm-grip', 'aria-hidden': 'true' })]),
                el('button', {
                    type: 'button',
                    className: 'medbeafgallery-dm-icon-button',
                    'data-part': 'up',
                    'aria-label': fmt(t.moveUp, row.name),
                    title: fmt(t.moveUp, row.name),
                    disabled: index === 0,
                    on: { click: function () { moveBy(row, -1, 'up'); } }
                }, [icon('arrow-left-alt2')]),
                el('button', {
                    type: 'button',
                    className: 'medbeafgallery-dm-icon-button',
                    'data-part': 'down',
                    'aria-label': fmt(t.moveDown, row.name),
                    title: fmt(t.moveDown, row.name),
                    disabled: index === total - 1,
                    on: { click: function () { moveBy(row, 1, 'down'); } }
                }, [icon('arrow-right-alt2')])
            ]));
        }
        var buttons = el('div', { className: 'medbeafgallery-dm-buttons' });
        if (can.edit) {
            buttons.appendChild(el('button', {
                type: 'button',
                className: 'button medbeafgallery-dm-edit',
                'data-part': 'edit',
                'aria-label': fmt(t.editLabel, row.name),
                on: { click: function (e) { openPanel(row, e.currentTarget); } }
            }, [icon('edit'), t.edit]));
        }
        if (can.delete) {
            buttons.appendChild(el('button', {
                type: 'button',
                className: 'medbeafgallery-dm-delete',
                'data-part': 'delete',
                'aria-label': fmt(t.deleteLabel, row.name),
                title: fmt(t.deleteLabel, row.name),
                on: { click: function () { openConfirm(card, row); } }
            }, [icon('trash'), el('span', { className: 'medbeafgallery-dm-delete-text', text: t.delete })]));
        }
        actions.appendChild(buttons);

        card.appendChild(el('div', { className: 'medbeafgallery-dm-card-band', 'aria-hidden': 'true' }));
        card.appendChild(body);
        card.appendChild(meta);
        card.appendChild(actions);
        card.appendChild(el('div', { className: 'medbeafgallery-dm-confirm-slot', hidden: true }));
        return card;
    }

    /* ------------------------------------------------------------------ */
    /* Delete (inline confirmation on the card)                            */
    /* ------------------------------------------------------------------ */

    function closeConfirms(except) {
        Array.prototype.forEach.call(els.grid.querySelectorAll('.medbeafgallery-dm-card.is-confirming'), function (card) {
            if (card === except) {
                return;
            }
            card.classList.remove('is-confirming');
            var slot = card.querySelector('.medbeafgallery-dm-confirm-slot');
            slot.hidden = true;
            slot.textContent = '';
        });
    }

    function openConfirm(card, row) {
        closeConfirms(card);
        var slot = card.querySelector('.medbeafgallery-dm-confirm-slot');
        slot.textContent = '';
        slot.hidden = false;
        card.classList.add('is-confirming');

        var count = Number(row.count) || 0;
        var qid = 'medbeafgallery-dm-delq-' + row.id;
        var did = 'medbeafgallery-dm-deld-' + row.id;
        var detail = count === 0 ? t.deleteNoCases : (count === 1 ? t.deleteCasesOne : fmt(t.deleteCasesMany, count)) + ' ' + t.deleteKeep;

        var cancel = function () {
            card.classList.remove('is-confirming');
            slot.hidden = true;
            slot.textContent = '';
            focusCard(row.id, 'delete');
        };

        var confirmButton = el('button', { type: 'button', className: 'button medbeafgallery-dm-delete-button', text: t.deleteConfirm });
        var cancelButton = el('button', { type: 'button', className: 'button', text: t.cancel, on: { click: cancel } });
        var box = el('div', { className: 'medbeafgallery-dm-confirm', role: 'group', 'aria-labelledby': qid, 'aria-describedby': did }, [
            el('p', { id: qid, className: 'medbeafgallery-dm-confirm-title', text: fmt(t.deleteQuestion, row.name) }),
            el('p', { id: did, className: 'medbeafgallery-dm-confirm-text', text: detail }),
            el('div', { className: 'medbeafgallery-dm-confirm-buttons' }, [confirmButton, cancelButton])
        ]);
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                cancel();
            }
        });

        confirmButton.addEventListener('click', function () {
            // Focus afterwards: the next card, else the previous one.
            var next = card.nextElementSibling || card.previousElementSibling;
            var nextId = next ? Number(next.getAttribute('data-id')) : 0;
            confirmButton.disabled = true;
            cancelButton.disabled = true;
            confirmButton.textContent = t.working;
            request('delete', { id: row.id }).then(function (result) {
                var changed = applyState(result.data);
                if (result.ok) {
                    showError('');
                    render();
                    announce(fmt(t.deleted, row.name));
                    if (!(nextId && focusCard(nextId, 'edit'))) {
                        focusFallback();
                    }
                    return;
                }
                showError(result.message);
                announce(result.message);
                if (changed) {
                    render();
                    if (!focusCard(row.id, 'delete')) {
                        focusFallback();
                    }
                } else {
                    confirmButton.disabled = false;
                    cancelButton.disabled = false;
                    confirmButton.textContent = t.deleteConfirm;
                    cancelButton.focus();
                }
            });
        });

        slot.appendChild(box);
        cancelButton.focus();
    }

    /* ------------------------------------------------------------------ */
    /* Add / edit panel                                                    */
    /* ------------------------------------------------------------------ */

    function setFieldError(input, errorEl, message) {
        if (!input || !errorEl) {
            return;
        }
        var hintId = input.id + '-hint';
        var hasHint = !!document.getElementById(hintId);
        if (message) {
            errorEl.textContent = message;
            errorEl.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', (hasHint ? hintId + ' ' : '') + errorEl.id);
        } else {
            errorEl.textContent = '';
            errorEl.hidden = true;
            input.removeAttribute('aria-invalid');
            if (hasHint) {
                input.setAttribute('aria-describedby', hintId);
            } else {
                input.removeAttribute('aria-describedby');
            }
        }
    }

    function setFormError(message) {
        els.formError.textContent = message || '';
        els.formError.hidden = !message;
    }

    function clearErrors() {
        setFieldError(els.name, els.nameError, '');
        setFieldError(els.url, els.urlError, '');
        setFormError('');
    }

    function renderPreview() {
        var preview = els.preview;
        preview.className = 'medbeafgallery-dm-avatar medbeafgallery-dm-avatar-lg medbeafgallery-dm-tone-' + panel.tone;
        avatarContent(preview, panel.photoUrl, els.name.value || '');
        if (!panel.photoUrl && !initials(els.name.value || '')) {
            preview.textContent = '';
            preview.appendChild(icon('admin-users'));
        }
        els.choose.textContent = panel.photoUrl ? t.changePhoto : t.choosePhoto;
        els.remove.hidden = !panel.photoUrl;
    }

    function openPanel(row, trigger) {
        if (panel.open) {
            return;
        }
        if (drag) {
            return;
        }
        closeConfirms(null);
        window.clearTimeout(panel.closeTimer);

        panel.open = true;
        panel.busy = false;
        panel.id = row ? Number(row.id) : 0;
        panel.tone = row ? String(Number(row.tone) || 0) : 'new';
        panel.photoId = row ? Number(row.photo_id) || 0 : 0;
        panel.photoUrl = row ? row.photo_url || '' : '';
        panel.trigger = trigger || null;

        els.title.textContent = row ? fmt(t.editTitle, row.name) : t.addTitle;
        els.save.textContent = row ? t.saveChanges : t.save;
        els.save.disabled = false;
        els.name.value = row ? row.name : '';
        els.credentials.value = row ? row.credentials || '' : '';
        els.location.value = row ? row.location || '' : '';
        els.url.value = row ? row.url || '' : '';
        if (row && row.edit_url) {
            els.more.href = row.edit_url;
            els.more.hidden = false;
        } else {
            els.more.hidden = true;
            els.more.removeAttribute('href');
        }
        els.choose.disabled = !hasMedia();
        els.choose.title = hasMedia() ? '' : t.noMedia;
        clearErrors();
        renderPreview();
        setPanelBusy(false);

        els.drawer.hidden = false;
        document.documentElement.classList.add('medbeafgallery-dm-locked');
        // Next frame so the slide-in transition runs.
        window.requestAnimationFrame(function () {
            els.drawer.classList.add('is-open');
        });
        els.name.focus();
        if (row) {
            els.name.select();
        }
    }

    function closePanel(focusSpec) {
        if (!panel.open) {
            return;
        }
        panel.open = false;
        els.drawer.classList.remove('is-open');
        document.documentElement.classList.remove('medbeafgallery-dm-locked');
        var hide = function () {
            if (!panel.open) {
                els.drawer.hidden = true;
            }
        };
        if (reducedMotion()) {
            hide();
        } else {
            panel.closeTimer = window.setTimeout(hide, 220);
        }

        if (focusSpec && focusSpec.id && focusCard(focusSpec.id, focusSpec.part)) {
            return;
        }
        var trigger = panel.trigger;
        if (trigger && document.body.contains(trigger) && !trigger.disabled) {
            trigger.focus();
        } else if (panel.id && focusCard(panel.id, 'edit')) {
            // Focus is back on the card.
        } else {
            focusFallback();
        }
    }

    function setPanelBusy(busy) {
        panel.busy = busy;
        Array.prototype.forEach.call(els.form.querySelectorAll('input, button'), function (control) {
            if (busy) {
                control.setAttribute('data-was-disabled', control.disabled ? '1' : '0');
                control.disabled = true;
            } else if (control.hasAttribute('data-was-disabled')) {
                control.disabled = control.getAttribute('data-was-disabled') === '1';
                control.removeAttribute('data-was-disabled');
            }
        });
        if (!busy) {
            els.choose.disabled = !hasMedia();
        }
        els.sheet.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function focusables() {
        return Array.prototype.filter.call(
            els.sheet.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (node) {
                return !node.disabled && !node.hidden && node.offsetParent !== null;
            }
        );
    }

    function onSheetKey(e) {
        if (!panel.open) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            if (!panel.busy) {
                closePanel(null);
            }
            return;
        }
        if (e.key !== 'Tab') {
            return;
        }
        var items = focusables();
        if (!items.length) {
            return;
        }
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && (document.activeElement === first || !els.sheet.contains(document.activeElement))) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && (document.activeElement === last || !els.sheet.contains(document.activeElement))) {
            e.preventDefault();
            first.focus();
        }
    }

    function onSubmit(e) {
        e.preventDefault();
        if (!panel.open || panel.busy) {
            return;
        }
        clearErrors();

        var name = els.name.value.trim();
        var url = normaliseUrl(els.url.value);
        var firstBad = null;
        if (!name) {
            setFieldError(els.name, els.nameError, t.nameRequired);
            firstBad = firstBad || els.name;
        }
        if (url === null) {
            setFieldError(els.url, els.urlError, t.urlInvalid);
            firstBad = firstBad || els.url;
        }
        if (firstBad) {
            announce(t.fixErrors);
            firstBad.focus();
            return;
        }
        if (url !== els.url.value) {
            els.url.value = url;
        }

        var id = panel.id;
        setPanelBusy(true);
        els.save.textContent = t.working;

        request('save', {
            id: id,
            name: name,
            credentials: els.credentials.value.trim(),
            location: els.location.value.trim(),
            url: url,
            photo_id: panel.photoId || 0
        }).then(function (result) {
            var changed = applyState(result.data);
            if (result.ok) {
                showError('');
                var saved = result.data.row;
                var savedId = saved ? Number(saved.id) : id;
                // A new doctor joins the list: show it even when a search is active.
                if (!id && state.query && saved && !matches(saved, state.query.trim())) {
                    state.query = '';
                    els.search.value = '';
                }
                render();
                closePanel({ id: savedId, part: 'edit' });
                flash(savedId);
                announce(fmt(id ? t.saved : t.added, saved ? saved.name : name));
                return;
            }
            setPanelBusy(false);
            els.save.textContent = id ? t.saveChanges : t.save;
            if (changed) {
                render();
            }
            var field = result.data && result.data.field;
            if (field === 'name') {
                setFieldError(els.name, els.nameError, result.message);
                els.name.focus();
            } else if (field === 'url') {
                setFieldError(els.url, els.urlError, result.message);
                els.url.focus();
            } else {
                setFormError(result.message);
                if (field === 'photo') {
                    els.choose.focus();
                } else {
                    els.save.focus();
                }
            }
            announce(result.message);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Photo                                                               */
    /* ------------------------------------------------------------------ */

    function hasMedia() {
        return !!(window.wp && window.wp.media);
    }

    function pickPhoto() {
        if (!hasMedia() || panel.busy) {
            return;
        }
        if (!mediaFrame) {
            mediaFrame = window.wp.media({
                title: t.mediaTitle,
                button: { text: t.mediaButton },
                multiple: false,
                library: { type: 'image' }
            });
            mediaFrame.on('open', function () {
                var selection = mediaFrame.state().get('selection');
                if (panel.photoId) {
                    var attachment = window.wp.media.attachment(panel.photoId);
                    attachment.fetch();
                    selection.reset([attachment]);
                } else {
                    selection.reset([]);
                }
            });
            mediaFrame.on('select', function () {
                var chosen = mediaFrame.state().get('selection').first();
                if (!chosen) {
                    return;
                }
                var data = chosen.toJSON();
                panel.photoId = Number(data.id) || 0;
                panel.photoUrl = (data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url) ||
                    (data.sizes && data.sizes.medium && data.sizes.medium.url) || data.url || '';
                renderPreview();
            });
            mediaFrame.on('close', function () {
                window.setTimeout(function () {
                    if (panel.open) {
                        els.choose.focus();
                    }
                }, 0);
            });
        }
        mediaFrame.open();
    }

    function removePhoto() {
        panel.photoId = 0;
        panel.photoUrl = '';
        renderPreview();
        els.choose.focus();
    }

    /* ------------------------------------------------------------------ */
    /* Reordering                                                          */
    /* ------------------------------------------------------------------ */

    function reorderLocal(ids) {
        var position = {};
        ids.forEach(function (id, index) {
            position[Number(id)] = index;
        });
        state.rows.sort(function (a, b) {
            return position[Number(a.id)] - position[Number(b.id)];
        });
        state.rows.forEach(function (row) {
            row.order = position[Number(row.id)];
        });
    }

    var reorderSeq = 0;

    function saveOrder(ids, movedRow, focusPart) {
        var place = ids.indexOf(Number(movedRow.id)) + 1;
        var seq = ++reorderSeq;
        request('reorder', { ids: ids }).then(function (result) {
            // A newer move is already on screen and queued: this reply's list
            // is out of date, so leave the screen alone.
            if (seq !== reorderSeq) {
                if (!result.ok) {
                    showError(result.message);
                }
                return;
            }
            var changed = applyState(result.data);
            if (result.ok) {
                showError('');
                if (changed) {
                    render();
                    focusCard(movedRow.id, focusPart);
                }
                announce(fmt(t.reordered, movedRow.name, place, ids.length));
                return;
            }
            showError(result.message);
            announce(result.message);
            render();
            focusCard(movedRow.id, 'handle');
        });
    }

    function moveBy(row, delta, part) {
        var ids = state.rows.map(function (r) { return Number(r.id); });
        var from = ids.indexOf(Number(row.id));
        var to = delta === Infinity ? ids.length - 1 : (delta === -Infinity ? 0 : from + delta);
        to = Math.max(0, Math.min(ids.length - 1, to));
        if (from < 0 || to === from) {
            return;
        }
        ids.splice(from, 1);
        ids.splice(to, 0, Number(row.id));

        reorderLocal(ids);
        renderGrid();
        // At an end the arrow button is disabled: hand focus to the other one.
        if (part === 'up' && to === 0) {
            part = 'down';
        } else if (part === 'down' && to === ids.length - 1) {
            part = 'up';
        }
        focusCard(row.id, part);
        flash(row.id);
        saveOrder(ids, row, part);
    }

    function onHandleKey(e, row) {
        var delta = null;
        if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
            delta = -1;
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
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

    function cardItems() {
        return Array.prototype.filter.call(els.grid.children, function (node) {
            return node.matches && node.matches('li.medbeafgallery-dm-card');
        });
    }

    function startDrag(e, card, row) {
        if (e.button !== 0 || drag || panel.open) {
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
            card: card,
            row: row,
            handle: handle,
            startX: e.clientX,
            startY: e.clientY,
            next: card.nextSibling,
            moved: false,
            pointerId: e.pointerId
        };
        // On the document, not the handle: moving the card in the DOM drops
        // the handle's pointer capture, after which it would hear nothing.
        document.addEventListener('pointermove', onDragMove);
        document.addEventListener('pointerup', onDragEnd);
        document.addEventListener('pointercancel', onDragCancel);
        document.addEventListener('selectstart', preventSelect);
        document.addEventListener('keydown', onDragKey, true);
    }

    function preventSelect(e) {
        e.preventDefault();
    }

    function onDragMove(e) {
        if (!drag || e.pointerId !== drag.pointerId) {
            return;
        }
        var x = e.clientX;
        var y = e.clientY;
        if (!drag.moved) {
            if (Math.abs(x - drag.startX) < 5 && Math.abs(y - drag.startY) < 5) {
                return;
            }
            drag.moved = true;
            closeConfirms(null);
            drag.card.classList.add('is-dragging');
            root.classList.add('is-sorting');
        }

        // The card under the pointer decides the new slot: in a row of cards
        // the left/right half counts, in a single column the top/bottom half.
        var gridWidth = els.grid.getBoundingClientRect().width;
        var items = cardItems();
        for (var i = 0; i < items.length; i++) {
            var node = items[i];
            if (node === drag.card) {
                continue;
            }
            var rect = node.getBoundingClientRect();
            if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                continue;
            }
            var singleColumn = rect.width > gridWidth * 0.66;
            var after = singleColumn ? y > rect.top + rect.height / 2 : x > rect.left + rect.width / 2;
            var ref = after ? node.nextSibling : node;
            if (ref !== drag.card && ref !== drag.card.nextSibling) {
                els.grid.insertBefore(drag.card, ref);
            }
            break;
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
        document.removeEventListener('pointermove', onDragMove);
        document.removeEventListener('pointerup', onDragEnd);
        document.removeEventListener('pointercancel', onDragCancel);
        document.removeEventListener('selectstart', preventSelect);
        document.removeEventListener('keydown', onDragKey, true);
        try {
            d.handle.releasePointerCapture(d.pointerId);
        } catch (err) {
            // Already released.
        }
        d.card.classList.remove('is-dragging');
        root.classList.remove('is-sorting');
        return d;
    }

    function onDragEnd(e) {
        if (!drag || (e && e.pointerId !== drag.pointerId)) {
            return;
        }
        var d = stopDrag();
        if (!d || !d.moved) {
            return;
        }
        var ids = cardItems().map(function (node) {
            return Number(node.getAttribute('data-id'));
        });
        var old = state.rows.map(function (r) { return Number(r.id); });
        if (ids.join(',') === old.join(',')) {
            return;
        }
        reorderLocal(ids);
        renderGrid();
        focusCard(d.row.id, 'handle');
        flash(d.row.id);
        saveOrder(ids, d.row, 'handle');
    }

    function restoreDrag(d) {
        if (d && d.moved) {
            els.grid.insertBefore(d.card, d.next && d.next.parentNode === els.grid ? d.next : null);
        }
    }

    function onDragCancel(e) {
        if (!drag || (e && e.pointerId !== drag.pointerId)) {
            return;
        }
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

    // The "Add" links lead to the standard screen without JavaScript; here they
    // become buttons that open the panel.
    function asButton(link, extraClass) {
        if (!link) {
            return null;
        }
        var button = el('button', { type: 'button', className: link.className + (extraClass ? ' ' + extraClass : ''), id: link.id });
        while (link.firstChild) {
            button.appendChild(link.firstChild);
        }
        link.parentNode.replaceChild(button, link);
        button.addEventListener('click', function (e) {
            openPanel(null, e.currentTarget);
        });
        return button;
    }

    els.add = asButton(els.add);
    els.emptyAdd = asButton(els.emptyAdd);
    if (els.add && !can.add) {
        els.add.hidden = true;
    }

    els.form.addEventListener('submit', onSubmit);
    els.sheet.addEventListener('keydown', onSheetKey);
    Array.prototype.forEach.call(els.drawer.querySelectorAll('[data-dm-close]'), function (node) {
        node.addEventListener('click', function () {
            if (!panel.busy) {
                closePanel(null);
            }
        });
    });
    els.choose.addEventListener('click', pickPhoto);
    els.remove.addEventListener('click', removePhoto);
    els.name.addEventListener('input', function () {
        if (els.nameError && !els.nameError.hidden && els.name.value.trim()) {
            setFieldError(els.name, els.nameError, '');
        }
        if (!panel.photoUrl) {
            renderPreview();
        }
    });
    els.url.addEventListener('input', function () {
        if (els.urlError && !els.urlError.hidden) {
            setFieldError(els.url, els.urlError, '');
        }
    });
    els.url.addEventListener('blur', function () {
        var url = normaliseUrl(els.url.value);
        if (url && url !== els.url.value) {
            els.url.value = url;
        }
    });
    if (els.search) {
        els.search.addEventListener('input', function () {
            state.query = els.search.value;
            closeConfirms(null);
            renderToolbar();
            renderGrid();
        });
        els.search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && els.search.value) {
                e.preventDefault();
                els.search.value = '';
                state.query = '';
                renderToolbar();
                renderGrid();
            }
        });
    }

    root.classList.add('has-js');
    render();

    // ?add=1 (the case editor's "Add a doctor" link) opens the panel at once.
    try {
        var params = new URLSearchParams(window.location.search);
        if (params.get('add') === '1' && can.add) {
            params.delete('add');
            var query = params.toString();
            window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
            openPanel(null, els.add);
        }
    } catch (e) {
        // Old browser: the user can press "Add doctor".
    }
}());
