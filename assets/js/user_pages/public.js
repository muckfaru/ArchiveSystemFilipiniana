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

    // metadata
    const elTitle = document.getElementById('publicModalTitle');
    const elCategory = document.getElementById('publicModalCategory');
    const elDescription = document.getElementById('publicModalDescription');
    const elDescriptionWrap = document.getElementById('publicModalDescriptionWrap');
    const elDate = document.getElementById('publicModalDate');
    const elPublisher = document.getElementById('publicModalPublisher');
    const elLanguage = document.getElementById('publicModalLanguage');
    const elPages = document.getElementById('publicModalPages');
    const elVolume = document.getElementById('publicModalVolume');
    const elEdition = document.getElementById('publicModalEdition');
    const elKeywords = document.getElementById('publicModalKeywords');
    
    // Modal rows
    const rowLanguage = document.getElementById('modalRowLanguage');
    const rowPages = document.getElementById('modalRowPages');
    const rowVolume = document.getElementById('modalRowVolume');
    const rowEdition = document.getElementById('modalRowEdition');
    const rowKeywords = document.getElementById('modalRowKeywords');

    // Field icon mapping
    const fieldIcons = {
        'publication_date': 'bi-calendar3',
        'publisher': 'bi-building',
        'category': 'bi-tag',
        'language': 'bi-translate',
        'description': 'bi-file-text',
        'page_count': 'bi-book',
        'volume_issue': 'bi-layers',
        'edition': 'bi-sun',
        'keywords': 'bi-tags'
    };

    // ── Open modal ────────────────────────────────────────────────────────────
    function openModal(card) {
        const d = card.dataset;

        // Title
        if (elTitle) elTitle.textContent = d.title || '—';

        // Category
        if (elCategory) {
            const catName = d.category || 'Uncategorized';
            const catClass = 'public-cat-' + catName.toLowerCase().replace(/[^a-z0-9]/g, '-');
            elCategory.textContent = catName;
            elCategory.className = 'public-modal-category-badge public-file-category ' + catClass;
        }

        // Description
        if (elDescription && elDescriptionWrap) {
            if (d.description && d.description.trim()) {
                elDescription.textContent = d.description;
                elDescriptionWrap.style.display = '';
            } else {
                elDescriptionWrap.style.display = 'none';
            }
        }

        // Date
        if (elDate) elDate.textContent = d.date || '—';

        // Publisher
        if (elPublisher) elPublisher.textContent = d.publisher || '—';

        // Language
        if (elLanguage && rowLanguage) {
            if (d.language && d.language.trim()) {
                elLanguage.textContent = d.language;
                rowLanguage.style.display = '';
            } else {
                rowLanguage.style.display = 'none';
            }
        }

        // Pages
        if (elPages && rowPages) {
            if (d.pageCount && d.pageCount.trim()) {
                elPages.textContent = d.pageCount + ' pages';
                rowPages.style.display = '';
            } else {
                rowPages.style.display = 'none';
            }
        }

        // Volume/Issue
        if (elVolume && rowVolume) {
            if (d.volume && d.volume.trim()) {
                elVolume.textContent = d.volume;
                rowVolume.style.display = '';
            } else {
                rowVolume.style.display = 'none';
            }
        }

        // Edition
        if (elEdition && rowEdition) {
            if (d.edition && d.edition.trim()) {
                elEdition.textContent = d.edition;
                rowEdition.style.display = '';
            } else {
                rowEdition.style.display = 'none';
            }
        }

        // Keywords
        if (elKeywords && rowKeywords) {
            if (d.keywords && d.keywords.trim()) {
                const keywords = d.keywords.split(',').map(k => k.trim()).filter(Boolean);
                elKeywords.innerHTML = keywords.map(k => 
                    `<span class="public-modal-keyword-pill">${escapeHtml(k)}</span>`
                ).join('');
                rowKeywords.style.display = '';
            } else {
                rowKeywords.style.display = 'none';
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

        // Read Now link  → user reader (public access, no login required)
        readBtn.href = APP_URL + '/user_pages/reader.php?id=' + encodeURIComponent(d.id);

        // Show backdrop
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
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
