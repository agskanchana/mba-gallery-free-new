/**
 * Cases screen (admin/cases-manager.php).
 *
 * The page works without this script (GET filters, WordPress's own nonce links).
 * This swaps in server-rendered results over admin-ajax, adds selection and bulk
 * changes, inline confirmation for permanent deletion, Undo after trashing, and a
 * grid/list toggle remembered per user in localStorage.
 */
(function () {
    'use strict';

    var cfg = window.medbeafgalleryCasesManager;
    var root = document.getElementById('medbeafgallery-cs');
    if (!cfg || !root || !window.fetch || !window.FormData) {
        return;
    }

    var i18n = cfg.i18n || {};
    var els = {
        form: document.getElementById('medbeafgallery-cs-filters'),
        status: document.getElementById('medbeafgallery-cs-status'),
        q: document.getElementById('medbeafgallery-cs-q'),
        cat: document.getElementById('medbeafgallery-cs-cat'),
        doctor: document.getElementById('medbeafgallery-cs-doctor'),
        sort: document.getElementById('medbeafgallery-cs-sort'),
        view: document.getElementById('medbeafgallery-cs-view'),
        tabs: document.getElementById('medbeafgallery-cs-tabs'),
        results: document.getElementById('medbeafgallery-cs-results'),
        live: document.getElementById('medbeafgallery-cs-live'),
        notice: document.getElementById('medbeafgallery-cs-notice'),
        bulk: document.getElementById('medbeafgallery-cs-bulk'),
        bulkCount: document.getElementById('medbeafgallery-cs-bulk-count'),
        bulkButtons: document.getElementById('medbeafgallery-cs-bulk-buttons'),
        bulkConfirm: document.getElementById('medbeafgallery-cs-bulk-confirm'),
        selectAll: document.getElementById('medbeafgallery-cs-select-all'),
        usage: document.getElementById('medbeafgallery-cs-usage'),
        add: document.getElementById('medbeafgallery-cs-add')
    };
    if (!els.form || !els.results) {
        return;
    }

    root.classList.add('has-js');

    var state = {
        status: els.status ? els.status.value : 'all',
        paged: 1
    };
    try {
        var params = new URLSearchParams(window.location.search);
        state.paged = Math.max(1, parseInt(params.get('paged'), 10) || 1);
    } catch (e) { /* old browser: page 1 */ }

    var seq = 0;
    var confirmCount = 0;
    var searchTimer = null;

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    function t(key, fallback) {
        return Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : (fallback || '');
    }

    function format(str, value) {
        return String(str).replace(/%(\d\$)?[sd]/, String(value));
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function announce(text) {
        if (!els.live) {
            return;
        }
        els.live.textContent = '';
        window.setTimeout(function () {
            els.live.textContent = text;
        }, 60);
    }

    function filters() {
        return {
            status: state.status,
            q: els.q ? els.q.value.trim() : '',
            cat: els.cat ? els.cat.value : '0',
            doctor: els.doctor ? els.doctor.value : '0',
            sort: els.sort ? els.sort.value : 'newest',
            paged: String(state.paged)
        };
    }

    function post(op, extra) {
        var data = new FormData();
        var f = filters();
        data.append('action', cfg.action);
        data.append('nonce', cfg.nonce);
        data.append('op', op);
        Object.keys(f).forEach(function (key) {
            data.append(key, f[key]);
        });
        (extra && extra.ids ? extra.ids : []).forEach(function (id) {
            data.append('ids[]', String(id));
        });

        return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function (response) {
                return response.json().catch(function () {
                    return { success: false, data: { message: t('networkError') } };
                });
            });
    }

    /* ── Rendering ───────────────────────────────────────────────────────── */

    function applyPayload(p) {
        if (!p || typeof p.results !== 'string') {
            return;
        }
        els.results.innerHTML = p.results;
        els.results.setAttribute('data-status', p.status || state.status);
        if (els.tabs && typeof p.tabs === 'string') {
            var focusedStatus = document.activeElement && els.tabs.contains(document.activeElement)
                ? document.activeElement.getAttribute('data-status') : null;
            els.tabs.innerHTML = p.tabs;
            if (focusedStatus) {
                var again = els.tabs.querySelector('[data-status="' + focusedStatus + '"]');
                if (again) {
                    again.focus();
                }
            }
        }
        if (p.status) {
            state.status = p.status;
            if (els.status) {
                els.status.value = p.status;
            }
        }
        if (p.paged) {
            state.paged = p.paged;
        }
        if (p.usage) {
            applyUsage(p.usage, p.usageText);
        }
        if (p.url && window.history && window.history.replaceState) {
            try {
                window.history.replaceState(null, '', p.url);
            } catch (e) { /* ignore */ }
        }
        afterRender();
    }

    function applyUsage(usage, text) {
        if (els.usage) {
            els.usage.hidden = !usage.limited;
            els.usage.classList.toggle('is-full', !!usage.at_limit);
            var label = els.usage.querySelector('.medbeafgallery-cs-usage-text');
            if (label && text) {
                label.textContent = text;
            }
            var bar = els.usage.querySelector('.medbeafgallery-cs-usage-meter > span');
            if (bar && usage.max > 0) {
                bar.style.width = Math.min(100, Math.round(usage.used / usage.max * 100)) + '%';
            }
        }
        if (els.add) {
            var full = !!(usage.limited && usage.at_limit);
            els.add.classList.toggle('disabled', full);
            if (full) {
                els.add.removeAttribute('href');
                els.add.setAttribute('role', 'link');
                els.add.setAttribute('aria-disabled', 'true');
                els.add.setAttribute('aria-describedby', 'medbeafgallery-cs-usage-text');
            } else {
                els.add.setAttribute('href', els.add.getAttribute('data-href'));
                els.add.removeAttribute('role');
                els.add.removeAttribute('aria-disabled');
                els.add.removeAttribute('aria-describedby');
            }
        }
    }

    function afterRender() {
        applyView(currentView());
        if (els.bulkConfirm) {
            els.bulkConfirm.hidden = true;
            els.bulkConfirm.innerHTML = '';
        }
        refreshBulk();
    }

    /* ── Loading ─────────────────────────────────────────────────────────── */

    function load(options) {
        options = options || {};
        var mine = ++seq;
        els.results.setAttribute('aria-busy', 'true');

        return post('list').then(function (json) {
            if (mine !== seq) {
                return;
            }
            els.results.removeAttribute('aria-busy');
            if (!json || !json.success) {
                showNotice((json && json.data && json.data.message) || t('networkError'), 'error');
                return;
            }
            applyPayload(json.data);
            var summary = document.getElementById('medbeafgallery-cs-summary');
            if (summary) {
                announce(summary.textContent.replace(/\s+/g, ' ').trim());
                if (options.focusResults) {
                    summary.focus();
                }
            }
        }).catch(function () {
            if (mine === seq) {
                els.results.removeAttribute('aria-busy');
                showNotice(t('networkError'), 'error');
            }
        });
    }

    /* ── Notices ─────────────────────────────────────────────────────────── */

    function hideNotice() {
        if (els.notice) {
            els.notice.hidden = true;
            els.notice.innerHTML = '';
        }
    }

    /**
     * @param {string} message
     * @param {string} type   success|error
     * @param {Object} extra  { undo: [ids], failed: [{title, message}] }
     * @return {HTMLElement|null} The Undo button, when there is one.
     */
    function showNotice(message, type, extra) {
        extra = extra || {};
        if (!els.notice) {
            announce(message);
            return null;
        }
        els.notice.innerHTML = '';
        els.notice.className = 'medbeafgallery-cs-notice' + (type === 'error' ? ' is-error' : '');
        els.notice.setAttribute('role', type === 'error' ? 'alert' : 'status');

        els.notice.appendChild(el('p', 'medbeafgallery-cs-notice-text', message));

        if (extra.failed && extra.failed.length) {
            var list = el('ul', 'medbeafgallery-cs-notice-list');
            extra.failed.forEach(function (f) {
                list.appendChild(el('li', '', (f.title ? f.title + ': ' : '') + f.message));
            });
            els.notice.appendChild(list);
        }

        var undo = null;
        if (extra.undo && extra.undo.length) {
            undo = el('button', 'button', t('undo', 'Undo'));
            undo.type = 'button';
            undo.addEventListener('click', function () {
                undo.disabled = true;
                change('undo', extra.undo, { focusAfter: true });
            });
            els.notice.appendChild(undo);
        }

        var close = el('button', 'medbeafgallery-cs-notice-close');
        close.type = 'button';
        close.setAttribute('aria-label', t('dismiss', 'Dismiss'));
        close.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        close.addEventListener('click', function () {
            hideNotice();
            var summary = document.getElementById('medbeafgallery-cs-summary');
            if (summary) {
                summary.focus();
            }
        });
        els.notice.appendChild(close);

        els.notice.hidden = false;
        if (type !== 'error') {
            announce(message);
        }
        return undo;
    }

    /* ── Changes ─────────────────────────────────────────────────────────── */

    function change(op, ids, options) {
        options = options || {};
        var mine = ++seq;
        els.results.setAttribute('aria-busy', 'true');
        announce(t('working', 'Working…'));

        return post(op, { ids: ids }).then(function (json) {
            if (mine !== seq) {
                return;
            }
            els.results.removeAttribute('aria-busy');
            var data = (json && json.data) || {};
            if (typeof data.results === 'string') {
                applyPayload(data);
            }
            if (!json || !json.success) {
                showNotice(data.message || t('networkError'), 'error', { failed: (data.failed || []).length > 1 ? data.failed : [] });
                focusAfterChange(null);
                return;
            }
            var undo = showNotice(data.message, 'success', {
                undo: op === 'trash' ? data.ids : null,
                failed: data.failed
            });
            if (options.focusAfter) {
                focusAfterChange(undo);
            }
        }).catch(function () {
            if (mine === seq) {
                els.results.removeAttribute('aria-busy');
                showNotice(t('networkError'), 'error');
            }
        });
    }

    /** Focus was on a card that is gone (or on a button that is): put it somewhere sensible. */
    function focusAfterChange(preferred) {
        var active = document.activeElement;
        if (active && active !== document.body && document.contains(active) && !active.disabled) {
            return;
        }
        if (preferred && document.contains(preferred)) {
            preferred.focus();
            return;
        }
        var summary = document.getElementById('medbeafgallery-cs-summary');
        if (summary) {
            summary.focus();
        }
    }

    /* ── Inline confirmation (delete permanently) ────────────────────────── */

    function closeConfirm(card, returnFocus) {
        var box = card.querySelector('.medbeafgallery-cs-confirm');
        if (box) {
            box.parentNode.removeChild(box);
        }
        card.classList.remove('is-confirming');
        if (returnFocus) {
            var trigger = card.querySelector('[data-op="delete"]');
            if (trigger) {
                trigger.focus();
            }
        }
    }

    function buildConfirm(question, note, onConfirm, onCancel) {
        var box = el('div', 'medbeafgallery-cs-confirm');
        box.setAttribute('role', 'group');
        var title = el('p', 'medbeafgallery-cs-confirm-title', question);
        title.tabIndex = -1;
        title.id = 'medbeafgallery-cs-confirm-' + (++confirmCount);
        box.setAttribute('aria-labelledby', title.id);
        box.appendChild(title);
        box.appendChild(el('p', 'medbeafgallery-cs-confirm-text', note));

        var buttons = el('div', 'medbeafgallery-cs-confirm-buttons');
        var yes = el('button', 'button medbeafgallery-cs-delete-button', t('deleteForever', 'Delete permanently'));
        yes.type = 'button';
        var no = el('button', 'button', t('cancel', 'Cancel'));
        no.type = 'button';
        yes.addEventListener('click', onConfirm);
        no.addEventListener('click', onCancel);
        box.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCancel();
            }
        });
        buttons.appendChild(yes);
        buttons.appendChild(no);
        box.appendChild(buttons);
        return { box: box, title: title };
    }

    function confirmDelete(card) {
        Array.prototype.forEach.call(els.results.querySelectorAll('.medbeafgallery-cs-card.is-confirming'), function (other) {
            closeConfirm(other, false);
        });
        var id = card.getAttribute('data-id');
        var built = buildConfirm(
            format(t('deleteQuestion', 'Delete “%s” permanently?'), card.getAttribute('data-title') || ''),
            t('deleteNote'),
            function () {
                change('delete', [id], { focusAfter: true });
            },
            function () {
                closeConfirm(card, true);
            }
        );
        card.classList.add('is-confirming');
        card.querySelector('.medbeafgallery-cs-body').appendChild(built.box);
        built.title.focus();
    }

    /* ── Selection and bulk changes ──────────────────────────────────────── */

    function checks() {
        return Array.prototype.slice.call(els.results.querySelectorAll('.medbeafgallery-cs-check'));
    }

    function selectedIds() {
        return checks().filter(function (box) {
            return box.checked;
        }).map(function (box) {
            return box.value;
        });
    }

    function refreshBulk() {
        if (!els.bulk) {
            return;
        }
        var all = checks();
        var chosen = selectedIds();
        var inTrash = state.status === 'trash';

        els.bulk.hidden = all.length === 0;
        els.bulk.classList.toggle('has-selection', chosen.length > 0);

        all.forEach(function (box) {
            var card = box.closest('.medbeafgallery-cs-card');
            if (card) {
                card.classList.toggle('is-selected', box.checked);
            }
        });

        if (els.selectAll) {
            els.selectAll.checked = all.length > 0 && chosen.length === all.length;
            els.selectAll.indeterminate = chosen.length > 0 && chosen.length < all.length;
        }
        if (els.bulkCount) {
            els.bulkCount.textContent = chosen.length
                ? format(chosen.length === 1 ? t('selectedOne', '%d selected') : t('selectedMany', '%d selected'), chosen.length)
                : (inTrash ? t('noneSelectedTrash') : t('noneSelected'));
        }
        if (els.bulkButtons) {
            Array.prototype.forEach.call(els.bulkButtons.querySelectorAll('[data-bulk]'), function (button) {
                var scope = button.getAttribute('data-scope');
                var show = inTrash ? scope === 'trash' : scope === 'live';
                if (button.getAttribute('data-bulk') === 'publish' && cfg.can && !cfg.can.publish) {
                    show = false;
                }
                button.hidden = !show;
            });
        }
        if (!chosen.length && els.bulkConfirm) {
            els.bulkConfirm.hidden = true;
            els.bulkConfirm.innerHTML = '';
        }
    }

    function bulk(op) {
        var ids = selectedIds();
        if (!ids.length) {
            return;
        }
        if (op !== 'delete') {
            change(op, ids, { focusAfter: true }).then(function () {
                if (els.selectAll && document.activeElement === document.body) {
                    els.selectAll.focus();
                }
            });
            return;
        }
        var built = buildConfirm(
            format(ids.length === 1 ? t('bulkDeleteQuestionOne') : t('bulkDeleteQuestionMany'), ids.length),
            t('deleteNote'),
            function () {
                change('delete', ids, { focusAfter: true });
            },
            function () {
                els.bulkConfirm.hidden = true;
                els.bulkConfirm.innerHTML = '';
                var trigger = els.bulkButtons.querySelector('[data-bulk="delete"]');
                if (trigger) {
                    trigger.focus();
                }
            }
        );
        els.bulkConfirm.innerHTML = '';
        els.bulkConfirm.appendChild(built.box);
        els.bulkConfirm.hidden = false;
        built.title.focus();
    }

    /* ── Grid / list ─────────────────────────────────────────────────────── */

    function currentView() {
        try {
            return window.localStorage.getItem(cfg.viewKey) === 'list' ? 'list' : 'grid';
        } catch (e) {
            return root.getAttribute('data-view') === 'list' ? 'list' : 'grid';
        }
    }

    function applyView(view) {
        root.setAttribute('data-view', view);
        els.results.classList.toggle('is-list', view === 'list');
        if (els.view) {
            Array.prototype.forEach.call(els.view.querySelectorAll('[data-view]'), function (button) {
                button.setAttribute('aria-pressed', button.getAttribute('data-view') === view ? 'true' : 'false');
            });
        }
    }

    function setView(view) {
        try {
            window.localStorage.setItem(cfg.viewKey, view);
        } catch (e) { /* private mode: this page only */ }
        applyView(view);
    }

    /* ── Events ──────────────────────────────────────────────────────────── */

    els.form.addEventListener('submit', function (event) {
        event.preventDefault();
        state.paged = 1;
        load({ focusResults: false });
    });

    [els.cat, els.doctor, els.sort].forEach(function (select) {
        if (select) {
            select.addEventListener('change', function () {
                state.paged = 1;
                load();
            });
        }
    });

    if (els.q) {
        els.q.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                state.paged = 1;
                load();
            }, 350);
        });
        // The "x" of a search field fires "search", not always "input".
        els.q.addEventListener('search', function () {
            window.clearTimeout(searchTimer);
            state.paged = 1;
            load();
        });
    }

    if (els.view) {
        els.view.hidden = false;
        els.view.addEventListener('click', function (event) {
            var button = event.target.closest('[data-view]');
            if (button) {
                setView(button.getAttribute('data-view'));
            }
        });
    }

    if (els.tabs) {
        els.tabs.addEventListener('click', function (event) {
            var tab = event.target.closest('a[data-status]');
            if (!tab || event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }
            event.preventDefault();
            state.status = tab.getAttribute('data-status');
            state.paged = 1;
            if (els.status) {
                els.status.value = state.status;
            }
            hideNotice();
            load();
        });
    }

    els.results.addEventListener('click', function (event) {
        var target = event.target;

        var action = target.closest('a[data-op]');
        if (action && els.results.contains(action)) {
            event.preventDefault();
            var card = action.closest('.medbeafgallery-cs-card');
            var op = action.getAttribute('data-op');
            if (!card) {
                return;
            }
            if (op === 'delete') {
                confirmDelete(card);
            } else {
                change(op, [card.getAttribute('data-id')], { focusAfter: true });
            }
            return;
        }

        var toggle = target.closest('.medbeafgallery-cs-issues-toggle');
        if (toggle) {
            var panel = document.getElementById(toggle.getAttribute('aria-controls'));
            if (panel) {
                var open = toggle.getAttribute('aria-expanded') !== 'true';
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.hidden = !open;
            }
            return;
        }

        var pageLink = target.closest('a[data-paged]');
        if (pageLink && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            state.paged = parseInt(pageLink.getAttribute('data-paged'), 10) || 1;
            load({ focusResults: true }).then(function () {
                var top = els.tabs || root;
                if (top.scrollIntoView) {
                    top.scrollIntoView({ block: 'start' });
                }
            });
            return;
        }

        var clear = target.closest('a.medbeafgallery-cs-clear');
        if (clear && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            if (clear.getAttribute('data-status')) {
                state.status = clear.getAttribute('data-status');
                if (els.status) {
                    els.status.value = state.status;
                }
            }
            if (clear.getAttribute('data-clear')) {
                if (els.q) {
                    els.q.value = '';
                }
                if (els.cat) {
                    els.cat.value = '0';
                }
                if (els.doctor) {
                    els.doctor.value = '0';
                }
            }
            state.paged = 1;
            load({ focusResults: true });
        }
    });

    els.results.addEventListener('change', function (event) {
        if (event.target.classList.contains('medbeafgallery-cs-check')) {
            refreshBulk();
        }
    });

    if (els.selectAll) {
        els.selectAll.addEventListener('change', function () {
            var on = els.selectAll.checked;
            checks().forEach(function (box) {
                box.checked = on;
            });
            refreshBulk();
        });
    }

    if (els.bulkButtons) {
        els.bulkButtons.addEventListener('click', function (event) {
            var button = event.target.closest('[data-bulk]');
            if (button) {
                bulk(button.getAttribute('data-bulk'));
            }
        });
    }

    afterRender();
}());
