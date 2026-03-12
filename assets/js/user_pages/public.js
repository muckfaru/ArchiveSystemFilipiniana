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
        document.querySelectorAll('.public-file-card, .catalog-card, .browse-card, .browse-file-card-compact').forEach(card => {
            card.addEventListener('click', () => openModal(card));
        });
    }
    attachCardListeners();

    // ── Catalog shelf scroll arrows ───────────────────────────────────────────
    document.querySelectorAll('.catalog-shelf-track-wrap').forEach(wrap => {
        const track = wrap.querySelector('.catalog-shelf-track');
        const leftBtn = wrap.querySelector('.catalog-scroll-left');
        const rightBtn = wrap.querySelector('.catalog-scroll-right');
        if (!track || !leftBtn || !rightBtn) return;

        const scrollAmount = 400;

        function updateArrows() {
            leftBtn.classList.toggle('hidden', track.scrollLeft <= 10);
            rightBtn.classList.toggle('hidden', track.scrollLeft + track.clientWidth >= track.scrollWidth - 10);
        }

        leftBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        rightBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        track.addEventListener('scroll', updateArrows);
        // Initial check
        setTimeout(updateArrows, 100);
        window.addEventListener('resize', updateArrows);
    });

    // ── Live search (debounced) ───────────────────────────────────────────────
    const searchInput = document.getElementById('publicSearchInput');
    const advPanel = document.getElementById('advSearchPanel');
    const advKeyword = document.getElementById('advKeyword');
    const advDateFrom = document.getElementById('advDateFrom');
    const advDateTo = document.getElementById('advDateTo');
    const advApplyBtn = document.getElementById('advApplyBtn');
    const advResetBtn = document.getElementById('advResetBtn');
    const clearBtn = document.getElementById('publicSearchClear');

    // ── Clear (X) button ──────────────────────────────────────────────────────
    if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = APP_URL + '/user_pages/public.php';
        });
    }

    // ── Show advanced search on focus / click ─────────────────────────────────
    function showAdvPanel() {
        if (!advPanel) return;
        advPanel.classList.add('active');
        // Sync keyword from main input
        if (advKeyword && searchInput) {
            advKeyword.value = searchInput.value;
        }
    }

    function hideAdvPanel() {
        if (!advPanel) return;
        advPanel.classList.remove('active');
    }

    if (searchInput) {
        // Show advanced panel on focus
        searchInput.addEventListener('focus', showAdvPanel);

        // Show on click too (in case already focused)
        searchInput.addEventListener('click', showAdvPanel);

        // Sync typing from main input to adv keyword
        searchInput.addEventListener('input', function () {
            if (advKeyword) advKeyword.value = this.value;
        });

        // Prevent form submit when adv panel is open — use adv search instead
        const form = document.getElementById('publicSearchForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (advPanel && advPanel.classList.contains('active')) {
                    e.preventDefault();
                    doAdvancedSearch();
                }
            });
        }
    }

    // Close adv panel on outside click
    document.addEventListener('click', function (e) {
        if (!advPanel || !advPanel.classList.contains('active')) return;
        const wrapper = document.querySelector('.public-search-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            hideAdvPanel();
        }
    });

    // ── Date input auto-format (mm/dd/yyyy) ───────────────────────────────────
    function autoFormatDate(input) {
        if (!input) return;
        input.addEventListener('input', function (e) {
            let v = this.value.replace(/[^\d]/g, '');
            if (v.length > 8) v = v.slice(0, 8);
            let formatted = '';
            if (v.length > 4) {
                formatted = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
            } else if (v.length > 2) {
                formatted = v.slice(0, 2) + '/' + v.slice(2);
            } else {
                formatted = v;
            }
            this.value = formatted;
        });
    }
    autoFormatDate(advDateFrom);
    autoFormatDate(advDateTo);

    // ── Convert mm/dd/yyyy → YYYY-MM-DD for query string ──────────────────────
    function toIsoDate(mmddyyyy) {
        if (!mmddyyyy) return '';
        var parts = mmddyyyy.split('/');
        if (parts.length !== 3) return '';
        var mm = parts[0].padStart(2, '0');
        var dd = parts[1].padStart(2, '0');
        var yyyy = parts[2];
        if (yyyy.length !== 4) return '';
        return yyyy + '-' + mm + '-' + dd;
    }

    // ── Apply advanced search → navigate to browse.php ────────────────────────
    function doAdvancedSearch() {
        var params = [];
        var kw = (advKeyword ? advKeyword.value.trim() : '') || (searchInput ? searchInput.value.trim() : '');
        if (kw) params.push('q=' + encodeURIComponent(kw));
        var df = advDateFrom ? toIsoDate(advDateFrom.value.trim()) : '';
        var dt = advDateTo ? toIsoDate(advDateTo.value.trim()) : '';
        if (df) params.push('date_from=' + encodeURIComponent(df));
        if (dt) params.push('date_to=' + encodeURIComponent(dt));

        window.location.href = APP_URL + '/user_pages/browse.php' + (params.length ? '?' + params.join('&') : '');
    }

    if (advApplyBtn) {
        advApplyBtn.addEventListener('click', doAdvancedSearch);
    }

    // ── Reset advanced search fields ──────────────────────────────────────────
    if (advResetBtn) {
        advResetBtn.addEventListener('click', function () {
            if (advKeyword) advKeyword.value = '';
            if (advDateFrom) advDateFrom.value = '';
            if (advDateTo) advDateTo.value = '';
            if (searchInput) searchInput.value = '';
        });
    }

    // ── Enter key in advanced fields triggers search ──────────────────────────
    [advKeyword, advDateFrom, advDateTo].forEach(function (el) {
        if (!el) return;
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doAdvancedSearch();
            }
        });
    });

    // ── Live update polling (reflects admin upload/delete) ────────────────────
    // Read the initial archive count embedded by PHP in the grid/catalog container.
    const totalContainer = document.querySelector('.public-grid-container[data-total], .catalog-container[data-total]');
    if (totalContainer && typeof APP_URL !== 'undefined') {
        let knownCount = parseInt(totalContainer.dataset.total, 10);
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
