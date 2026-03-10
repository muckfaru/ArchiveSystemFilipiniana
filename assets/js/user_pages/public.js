/**
 * Public Landing Page – Interactivity
 * Quezon City Public Library Archive System
 */

(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const backdrop = document.getElementById('publicModalBackdrop');
    const modalClose = document.getElementById('publicModalClose');
    const modalImg = document.getElementById('publicModalImg');
    const modalNoImg = document.getElementById('publicModalNoImg');
    const readBtn = document.getElementById('publicModalReadBtn');

    // Dynamic metadata container
    const elTitle = document.getElementById('publicModalTitle');
    const metadataContainer = document.getElementById('publicModalMetadata');

    // Field icon mapping
    const fieldIcons = {
        'date_published': 'bi-calendar3',
        'publication_date': 'bi-calendar3',
        'publisher': 'bi-building',
        'category': 'bi-tag',
        'language': 'bi-translate',
        'description': 'bi-file-text',
        'page_count': 'bi-book',
        'pages': 'bi-book',
        'volume_issue': 'bi-layers',
        'volume': 'bi-layers',
        'edition': 'bi-sun',
        'keywords': 'bi-tags',
        'tags': 'bi-tags',
        'author': 'bi-person',
        'subject': 'bi-journal-text'
    };

    // ── Open modal ────────────────────────────────────────────────────────────
    function openModal(card) {
        const d = card.dataset;

        // Title
        if (elTitle) elTitle.textContent = d.title || '—';

        // Build dynamic metadata rows from data-modal-metadata JSON
        if (metadataContainer) {
            metadataContainer.innerHTML = '';

            let modalMeta = [];
            try {
                modalMeta = JSON.parse(d.modalMetadata || '[]');
            } catch (e) {
                modalMeta = [];
            }

            if (modalMeta.length === 0) {
                metadataContainer.innerHTML = '<p style="color:#888; font-size:13px;">No metadata available.</p>';
            } else {
                modalMeta.forEach(function (field) {
                    const label = field.label || '';
                    const value = field.value || '';
                    const fieldName = field.field_name || '';
                    const fieldType = field.field_type || 'text';

                    // Pick icon
                    const iconClass = fieldIcons[fieldName] || 'bi-info-circle';

                    // Format display value
                    let displayHtml = '';
                    if (!value || !value.toString().trim()) {
                        displayHtml = '<span style="color:#999;">—</span>';
                    } else if (fieldType === 'tags' || fieldName === 'tags' || fieldName === 'keywords') {
                        // Render as pills
                        const tags = value.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
                        displayHtml = tags.map(function (t) {
                            return '<span class="public-modal-keyword-pill">' + escapeHtml(t) + '</span>';
                        }).join(' ');
                    } else if (fieldType === 'date' || fieldName === 'date_published' || fieldName === 'publication_date') {
                        displayHtml = escapeHtml(formatDate(value));
                    } else if (fieldType === 'textarea' || fieldName === 'description') {
                        displayHtml = '<span style="white-space:pre-wrap;">' + escapeHtml(value) + '</span>';
                    } else {
                        displayHtml = escapeHtml(value);
                    }

                    const row = document.createElement('div');
                    row.className = 'public-modal-meta-row';
                    row.innerHTML =
                        '<span class="public-modal-meta-label"><i class="bi ' + iconClass + '"></i> ' + escapeHtml(label) + '</span>' +
                        '<span class="public-modal-meta-value">' + displayHtml + '</span>';
                    metadataContainer.appendChild(row);
                });
            }
        }

        // Thumbnail
        if (d.thumbnail) {
            modalImg.src = d.thumbnail;
            modalImg.style.display = '';
            modalNoImg.style.display = 'none';
        } else {
            modalImg.style.display = 'none';
            modalNoImg.style.display = '';
        }

        // Read Now link → user reader (public access, no login required)
        readBtn.href = APP_URL + '/user_pages/reader.php?id=' + encodeURIComponent(d.id);

        // Show backdrop
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Helper: format a date string nicely
    function formatDate(val) {
        if (!val) return '—';
        var d = new Date(val);
        if (isNaN(d.getTime())) return val; // return as-is if not parseable
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Close modal ───────────────────────────────────────────────────────────
    function closeModal() {
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    modalClose.addEventListener('click', closeModal);

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── Card click → open modal ───────────────────────────────────────────────
    function attachCardListeners() {
        document.querySelectorAll('.public-file-card, .browse-card, .browse-file-card-compact').forEach(card => {
            card.addEventListener('click', () => openModal(card));
        });
    }
    attachCardListeners();

    // ── Live search (debounced) ───────────────────────────────────────────────
    const searchInput = document.getElementById('publicSearchInput');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const form = document.getElementById('publicSearchForm');
                if (this.value.length === 0 || this.value.length >= 2) {
                    form && form.submit();
                }
            }, 600);
        });
    }

    // ── Live update polling (reflects admin upload/delete) ────────────────────
    // Read the initial archive count embedded by PHP in the grid container.
    const gridContainer = document.querySelector('.public-grid-container[data-total]');
    if (gridContainer && typeof APP_URL !== 'undefined') {
        let knownCount = parseInt(gridContainer.dataset.total, 10);
        if (!isNaN(knownCount)) {
            setInterval(() => {
                fetch(APP_URL + '/backend/api/stats.php', { cache: 'no-store' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.archives !== knownCount) {
                            // Archive count changed — reload to reflect new content
                            location.reload();
                        }
                    })
                    .catch(() => { }); // silently ignore network errors
            }, 30000); // poll every 30 seconds
        }
    }

})();
