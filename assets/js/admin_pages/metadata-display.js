/**
 * Metadata Display Configuration JS
 * Archive System - Quezon City Public Library
 *
 * Works with form_fields (field_label, field_type, id) from form_templates.
 */

(function () {
    'use strict';

    let activeTab = 'card';
    let saveTimer = null;
    const DEBOUNCE_MS = 800;

    // ── State ────────────────────────────────────────────────────────────
    const state = {};
    (MD_FIELDS || []).forEach(f => {
        state[f.id] = {
            id: parseInt(f.id, 10),
            field_label: f.field_label,
            field_type: f.field_type,
            show_on_card: parseInt(f.show_on_card, 10),
            show_in_modal: parseInt(f.show_in_modal, 10),
        };
    });

    // Sample values keyed by form_field id
    const sampleValues = {};
    (MD_SAMPLE_META || []).forEach(m => {
        sampleValues[m.field_id] = { label: m.field_label, value: m.field_value, type: m.field_type };
    });

    // Placeholder values for preview when no real data exists
    const placeholderValues = {
        'title': 'Sample Document Title',
        'publisher': 'Sample Publisher',
        'category': 'General',
        'date published': 'Jan 1, 2025',
        'publication date': 'Jan 1, 2025',
        'tags': 'history, archive, filipiniana',
        'description': 'A brief description of the document...',
        'language': 'Filipino',
        'edition': 'First Edition',
        'volume': 'Vol. 1',
        'pages': '24',
        'page count': '24',
    };

    // ── DOM refs ─────────────────────────────────────────────────────────
    const tabs = document.querySelectorAll('[data-tab]');
    const fieldRows = document.querySelectorAll('.md-field-row');
    const toast = document.getElementById('mdSaveToast');
    const toastMsg = document.getElementById('mdSaveToastMsg');
    const previewCardEl = document.getElementById('previewCard');
    const previewModalEl = document.getElementById('previewModal');
    const previewCardMeta = document.getElementById('previewCardMeta');
    const previewModalMeta = document.getElementById('previewModalMeta');

    // ── Tab switching ─────────────────────────────────────────────────────
    tabs.forEach(tab => {
        tab.addEventListener('click', e => {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeTab = tab.dataset.tab;
            renderToggles();
            renderPreview();
        });
    });

    function renderToggles() {
        fieldRows.forEach(row => {
            const id = parseInt(row.dataset.fieldId, 10);
            const cb = row.querySelector('.md-field-toggle');
            if (!cb || !state[id]) return;
            cb.checked = activeTab === 'card' ? !!state[id].show_on_card : !!state[id].show_in_modal;
        });
    }

    // ── Toggle change ─────────────────────────────────────────────────────
    fieldRows.forEach(row => {
        const cb = row.querySelector('.md-field-toggle');
        if (!cb) return;
        cb.addEventListener('change', () => {
            const id = parseInt(row.dataset.fieldId, 10);
            if (!state[id]) return;
            if (activeTab === 'card') state[id].show_on_card = cb.checked ? 1 : 0;
            else state[id].show_in_modal = cb.checked ? 1 : 0;
            renderPreview();
            scheduleSave();
        });
    });

    // ── Preview rendering ─────────────────────────────────────────────────
    function formatValue(type, raw) {
        if (!raw) return '—';
        if (type === 'date') {
            const d = new Date(raw);
            if (!isNaN(d)) return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        if (type === 'checkbox') {
            try { const a = JSON.parse(raw); if (Array.isArray(a)) return a.join(', '); } catch (_) { }
        }
        return String(raw);
    }

    function renderPreview() {
        if (activeTab === 'card') {
            previewCardEl.style.display = '';
            previewModalEl.style.display = 'none';
            renderCardPreview();
        } else {
            previewCardEl.style.display = 'none';
            previewModalEl.style.display = '';
            renderModalPreview();
        }
    }

    function renderCardPreview() {
        // Filter out Title field (already shown as card title) and get visible fields
        const visible = Object.values(state).filter(f => 
            f.show_on_card && f.field_label.toLowerCase() !== 'title'
        );
        if (!visible.length) {
            previewCardMeta.innerHTML = '<div class="md-no-fields-hint">No fields enabled</div>';
            return;
        }
        let html = '';
        // Show all visible fields (removed slice limit)
        visible.forEach(f => {
            const s = sampleValues[f.id];
            const placeholder = placeholderValues[f.field_label.toLowerCase()] || 'Sample value';
            const v = s ? formatValue(f.field_type, s.value) : placeholder;
            html += `<div class="md-preview-meta-row">
                <span class="md-preview-meta-label">${esc(f.field_label)}</span>
                <span class="md-preview-meta-value">${esc(v)}</span>
            </div>`;
        });
        previewCardMeta.innerHTML = html || '<div style="font-size:11px;color:#9CA3AF">No values yet</div>';
    }

    function renderModalPreview() {
        // Filter out Title field (already shown as modal title) and get visible fields
        const visible = Object.values(state).filter(f => 
            f.show_in_modal && f.field_label.toLowerCase() !== 'title'
        );
        if (!visible.length) {
            previewModalMeta.innerHTML = '<div class="md-no-fields-hint">No fields enabled</div>';
            return;
        }
        let html = '';
        visible.forEach(f => {
            const s = sampleValues[f.id];
            const placeholder = placeholderValues[f.field_label.toLowerCase()] || 'Sample value';
            const v = s ? formatValue(f.field_type, s.value) : placeholder;
            html += `<div class="md-modal-field-row">
                <span class="md-modal-field-label">${esc(f.field_label)}</span>
                <span class="md-modal-field-value">${esc(v)}</span>
            </div>`;
        });
        previewModalMeta.innerHTML = html;
    }

    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => saveConfig(), DEBOUNCE_MS);
    }

    function saveConfig() {
        const configurations = Object.values(state).map(f => ({
            field_id: f.id,
            show_on_card: f.show_on_card,
            show_in_modal: f.show_in_modal,
        }));

        fetch(MD_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'update', configurations })
        })
            .then(r => r.json())
            .then(data => {
                showToast(data.success ? 'Configuration saved' : (data.message || 'Error'), !data.success);
            })
            .catch(() => {
                showToast('Network error', true);
            });
    }

    // ── Toast ─────────────────────────────────────────────────────────────
    let toastTimer = null;
    function showToast(msg, isError = false) {
        toastMsg.textContent = msg;
        toast.className = isError ? 'error' : '';
        requestAnimationFrame(() => toast.classList.add('show'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ── Init ──────────────────────────────────────────────────────────────
    renderToggles();
    renderPreview();

})();
