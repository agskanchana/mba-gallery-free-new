/**
 * Case editor layout (admin/case-editor.php).
 *
 * Reads the real form fields — never writes to them — to tick the checklist in
 * the header band, jumps to a box when a step is chosen, labels the description
 * editor, and mirrors WordPress's own Save / Publish buttons in the band (the
 * band's buttons only click the real ones, so every save goes through
 * WordPress's form and every metabox's own save handler).
 */
(function () {
    'use strict';

    var cfg = window.medbeafgalleryCaseEditor || {};
    var i18n = cfg.i18n || {};
    var band = document.getElementById('medbeafgallery-ce-band');
    if (!band) {
        return;
    }

    var steps = document.getElementById('medbeafgallery-ce-steps');
    var progress = document.getElementById('medbeafgallery-ce-progress');
    var actions = document.getElementById('medbeafgallery-ce-actions');
    var lastSummary = '';

    function byId(id) {
        return document.getElementById(id);
    }

    function filled(input) {
        return !!(input && String(input.value || '').trim() !== '' && String(input.value) !== '0');
    }

    function format(str, a, b) {
        return String(str).replace('%1$d', a).replace('%2$d', b);
    }

    /* ── What each step checks ───────────────────────────────────────────── */

    function descriptionText() {
        var editor = window.tinymce && window.tinymce.get ? window.tinymce.get('content') : null;
        if (editor && !editor.isHidden()) {
            return editor.getContent({ format: 'text' }).trim();
        }
        var area = byId('content');
        return area ? area.value.replace(/<[^>]*>/g, '').trim() : '';
    }

    function detailSources() {
        var sources = [];
        if (byId('postdivrich') || byId('content')) {
            sources.push(function () { return descriptionText() !== ''; });
        }
        var doctor = byId('medbeafgallery_case_doctor');
        if (doctor) {
            sources.push(function () { return filled(doctor); });
        }
        var box = byId('medbeafgallery_case_details');
        if (box) {
            sources.push(function () {
                return Array.prototype.some.call(box.querySelectorAll('select, input[type="text"], input[type="number"]'), filled);
            });
        }
        return sources;
    }

    var details = detailSources();

    var checks = {
        title: function () {
            return filled(byId('title'));
        },
        photos: function () {
            return filled(byId('medbeafgallery-main-before-id')) && filled(byId('medbeafgallery-main-after-id'));
        },
        category: function () {
            var list = byId('medbeafgallery_categorychecklist');
            if (!list) {
                return false;
            }
            return Array.prototype.some.call(list.querySelectorAll('input[type="checkbox"]:checked'), function (box) {
                return parseInt(box.value, 10) !== parseInt(cfg.allCategoryId, 10);
            });
        },
        details: function () {
            return details.some(function (fn) { return fn(); });
        },
        publish: function () {
            var status = byId('original_post_status') || byId('hidden_post_status');
            var value = status ? status.value : '';
            return value === 'publish' || value === 'future' || value === 'private';
        }
    };

    /* ── Targets (fall back when a box is not on this screen) ────────────── */

    function targetFor(step) {
        var link = step.querySelector('a');
        var id = link.getAttribute('data-target');
        var key = step.getAttribute('data-step');
        var el = byId(id);
        if (!el && key === 'details') {
            el = byId('medbeafgallery_case_details') || byId('medbeafgallery_doctor_box');
        }
        return el;
    }

    function isShown(el) {
        return !!(el && el.getClientRects().length);
    }

    /* ── Checklist ───────────────────────────────────────────────────────── */

    function update() {
        if (!steps) {
            return;
        }
        var done = 0;
        var total = 0;
        Array.prototype.forEach.call(steps.querySelectorAll('.medbeafgallery-ce-step'), function (step) {
            var key = step.getAttribute('data-step');
            var target = targetFor(step);
            // A step whose box is not on the screen (or switched off in Screen
            // Options) is left out rather than shown as impossible to finish.
            var available = !!target && (key !== 'details' || details.length > 0) && isShown(target);
            step.hidden = !available;
            if (!available) {
                return;
            }
            total++;
            var ok = !!checks[key] && checks[key]();
            if (ok) {
                done++;
            }
            step.classList.toggle('is-done', ok);
            var state = step.querySelector('.medbeafgallery-ce-step-state');
            if (state) {
                state.textContent = ' (' + (ok ? i18n.done : (key === 'details' ? i18n.optional : i18n.todo)) + ')';
            }
            step.classList.toggle('is-optional', key === 'details');
        });

        var summary = done === total && total > 0 ? i18n.allDone : format(i18n.progress, done, total);
        if (summary !== lastSummary && progress) {
            progress.textContent = summary;
            lastSummary = summary;
        }
        band.classList.toggle('is-complete', done === total && total > 0);
        syncActions();
    }

    function focusInside(target) {
        var field = target.id === 'titlediv' ? byId('title')
            : target.querySelector('input:not([type="hidden"]):not([disabled]), select, textarea, button:not(.handlediv):not(.handle-order-higher):not(.handle-order-lower), [href]:not(.postbox-header a)');
        if (target.id === 'postdivrich' && window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) {
            window.tinymce.get('content').focus();
            return;
        }
        if (field) {
            field.focus({ preventScroll: true });
        }
    }

    if (steps) {
        steps.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-target]');
            if (!link) {
                return;
            }
            var target = targetFor(link.closest('.medbeafgallery-ce-step'));
            if (!target) {
                return;
            }
            event.preventDefault();
            // Open a collapsed box with WordPress's own toggle (keeps its saved state in step).
            if (target.classList.contains('postbox') && target.classList.contains('closed')) {
                var toggle = target.querySelector('.handlediv');
                if (toggle) {
                    toggle.click();
                }
            }
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            target.classList.remove('medbeafgallery-ce-flash');
            void target.offsetWidth; // restart the highlight
            target.classList.add('medbeafgallery-ce-flash');
            focusInside(target);
        });
    }

    /* ── Proxy Save / Publish buttons ────────────────────────────────────── */

    function syncActions() {
        if (!actions) {
            return;
        }
        var any = false;
        Array.prototype.forEach.call(actions.querySelectorAll('[data-proxy]'), function (proxy) {
            var real = byId(proxy.getAttribute('data-proxy'));
            var show = !!(real && isShown(real));
            proxy.hidden = !show;
            if (!show) {
                return;
            }
            any = true;
            var label = real.value || real.textContent || '';
            if (proxy.textContent !== label) {
                proxy.textContent = label;
            }
            var off = real.disabled || real.classList.contains('disabled');
            proxy.disabled = off;
        });
        actions.hidden = !any;
    }

    if (actions) {
        actions.addEventListener('click', function (event) {
            var proxy = event.target.closest('[data-proxy]');
            if (!proxy || proxy.disabled) {
                return;
            }
            var real = byId(proxy.getAttribute('data-proxy'));
            if (real && !real.disabled) {
                real.click();
            }
        });
    }

    /* ── Description editor: a heading like the other cards ─────────────── */

    var rich = byId('postdivrich');
    if (rich && !byId('medbeafgallery-ce-desc-head')) {
        var head = document.createElement('div');
        head.className = 'medbeafgallery-ce-desc-head';
        head.id = 'medbeafgallery-ce-desc-head';
        var h = document.createElement('h2');
        h.className = 'medbeafgallery-ce-desc-title';
        h.textContent = i18n.description || 'Description';
        var hint = document.createElement('p');
        hint.className = 'medbeafgallery-ce-desc-hint';
        hint.textContent = i18n.descHint || '';
        head.appendChild(h);
        head.appendChild(hint);
        rich.parentNode.insertBefore(head, rich);
        rich.classList.add('medbeafgallery-ce-has-head');
    }

    /* ── Keep it current ─────────────────────────────────────────────────── */

    document.addEventListener('input', update, true);
    document.addEventListener('change', update, true);
    document.addEventListener('click', function () {
        window.setTimeout(update, 50);
    }, true);
    // Photos are set by the media modal and the cropper through hidden inputs,
    // which fire no events; a light poll catches them (and TinyMCE typing).
    window.setInterval(update, 800);
    update();
    band.classList.add('is-ready');
}());
