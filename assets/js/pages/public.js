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
    const elDate = document.getElementById('publicModalDate');
    const elPublisher = document.getElementById('publicModalPublisher');
    const elDescription = document.getElementById('publicModalDescription');
    const elDescWrap = document.getElementById('publicModalDescriptionWrap');
    const elLanguage = document.getElementById('publicModalLanguage');
    const elRowLang = document.getElementById('modalRowLanguage');
    const elPages = document.getElementById('publicModalPages');
    const elRowPages = document.getElementById('modalRowPages');
    const elVolume = document.getElementById('publicModalVolume');
    const elRowVolume = document.getElementById('modalRowVolume');
    const elEdition = document.getElementById('publicModalEdition');
    const elRowEdition = document.getElementById('modalRowEdition');
    const elKeywords = document.getElementById('publicModalKeywords');
    const elRowKeywords = document.getElementById('modalRowKeywords');

    // ── Category → badge CSS class ────────────────────────────────────────────
    function catClass(name) {
        return 'public-cat-' + (name || '').toLowerCase().replace(/[^a-z0-9]/g, '-');
    }


    // ── Open modal ────────────────────────────────────────────────────────────
    function openModal(card) {
        const d = card.dataset;

        // Title
        elTitle.textContent = d.title || '—';

        // Category badge
        const cat = d.category || 'Uncategorized';
        elCategory.textContent = cat;
        elCategory.className = 'public-modal-category-badge ' + catClass(cat);

        // Date
        elDate.textContent = d.date || '—';

        // Publisher
        elPublisher.textContent = d.publisher || '—';


        // Description
        if (d.description) {
            elDescription.textContent = d.description;
            elDescWrap.style.display = '';
        } else {
            elDescWrap.style.display = 'none';
        }

        // Language
        if (d.language) {
            elLanguage.textContent = d.language;
            elRowLang.style.display = '';
        } else {
            elRowLang.style.display = 'none';
        }

        // Pages
        if (d.pageCount) {
            elPages.textContent = Number(d.pageCount).toLocaleString() + ' pages';
            elRowPages.style.display = '';
        } else {
            elRowPages.style.display = 'none';
        }

        // Volume / Issue
        if (d.volume) {
            elVolume.textContent = d.volume;
            elRowVolume.style.display = '';
        } else {
            elRowVolume.style.display = 'none';
        }

        // Edition
        if (d.edition) {
            elEdition.textContent = d.edition;
            elRowEdition.style.display = '';
        } else {
            elRowEdition.style.display = 'none';
        }

        // Keywords – render as pill badges
        if (d.keywords) {
            elKeywords.innerHTML = d.keywords.split(',').map(k => k.trim()).filter(Boolean)
                .map(k => `<span class="public-modal-keyword-pill">${k.replace(/</g, '&lt;')}</span>`).join('');
            elRowKeywords.style.display = '';
        } else {
            elKeywords.innerHTML = '';
            elRowKeywords.style.display = 'none';
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

        // Read Now link  → public reader
        readBtn.href = APP_URL + '/reader.php?id=' + encodeURIComponent(d.id);

        // Show backdrop
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
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
        document.querySelectorAll('.public-file-card, .browse-card').forEach(card => {
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
