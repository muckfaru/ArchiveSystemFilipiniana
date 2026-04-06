/**
 * Public Landing Page – Interactivity
 * Quezon City Public Library Archive System
 */

(function () {
    'use strict';

    function initPublicPage() {

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
        const readUrl = APP_URL + '/read?id=' + encodeURIComponent(d.id);

        if (!backdrop || !readBtn) {
            window.location.href = readUrl;
            return;
        }

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
                let visibleFieldCount = 0;
                modalMeta.forEach(function (field) {
                    const label = field.label || '';
                    const value = field.value || '';
                    const fieldName = field.field_name || '';
                    const fieldType = field.field_type || 'text';
                    const trimmedValue = value.toString().trim();

                    if (!trimmedValue) {
                        return;
                    }

                    // Pick icon
                    const iconClass = fieldIcons[fieldName] || 'bi-info-circle';

                    // Format display value
                    let displayHtml = '';
                    if (!value || !value.toString().trim()) {
                        displayHtml = '<span style="color:#999;">—</span>';
                    } else if (fieldType === 'tags' || fieldName === 'tags' || fieldName === 'keywords') {
                        // Render as pills
                        const tags = trimmedValue.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
                        if (tags.length === 0) {
                            return;
                        }
                        displayHtml = tags.map(function (t) {
                            return '<span class="public-modal-keyword-pill">' + escapeHtml(t) + '</span>';
                        }).join(' ');
                    } else if (fieldType === 'date' || fieldName === 'date_published' || fieldName === 'publication_date') {
                        displayHtml = escapeHtml(formatDate(trimmedValue));
                    } else if (fieldType === 'textarea' || fieldName === 'description') {
                        displayHtml = '<span style="white-space:pre-wrap;">' + escapeHtml(trimmedValue) + '</span>';
                    } else {
                        displayHtml = escapeHtml(trimmedValue);
                    }

                    const row = document.createElement('div');
                    row.className = 'public-modal-meta-row';
                    row.innerHTML =
                        '<span class="public-modal-meta-label"><i class="bi ' + iconClass + '"></i> ' + escapeHtml(label) + '</span>' +
                        '<span class="public-modal-meta-value">' + displayHtml + '</span>';
                    metadataContainer.appendChild(row);
                    visibleFieldCount += 1;
                });

                if (visibleFieldCount === 0) {
                    metadataContainer.innerHTML = '<p style="color:#888; font-size:13px;">No metadata available.</p>';
                }
            }
        }

        // Thumbnail
        if (modalImg && modalNoImg) {
            if (d.thumbnail) {
                modalImg.src = d.thumbnail;
                modalImg.style.display = '';
                modalNoImg.style.display = 'none';
            } else {
                modalImg.style.display = 'none';
                modalNoImg.style.display = '';
            }
        }

        // Open the public reader directly to avoid the extra redirect/database
        // hop that was making public reads noticeably slower than admin.
        readBtn.href = readUrl;

        // Show backdrop
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Helper: format a date string nicely
    function formatDate(val) {
        if (!val) return '—';
        var d = new Date(val);
        if (isNaN(d.getTime())) return val; // return as-is if not parseable
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
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
        if (!backdrop) {
            return;
        }
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeModal();
        });
    }

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
    function initCatalogScroll() {
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

            // Remove previous event listeners if initialized before
            const newLeftBtn = leftBtn.cloneNode(true);
            const newRightBtn = rightBtn.cloneNode(true);
            leftBtn.parentNode.replaceChild(newLeftBtn, leftBtn);
            rightBtn.parentNode.replaceChild(newRightBtn, rightBtn);

            newLeftBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });
            newRightBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });

            track.addEventListener('scroll', updateArrows);
            // Initial check
            setTimeout(updateArrows, 100);
            window.addEventListener('resize', updateArrows);
        });
    }
    initCatalogScroll();

    // ── Live search (debounced) & Auto Reset ──────────────────────────────────
    const header = document.querySelector('.public-header');
    const headerSearchToggle = document.getElementById('publicHeaderSearchToggle');
    const headerSearchForm = document.getElementById('publicHeaderSearchForm');
    const headerSearchInput = document.getElementById('publicHeaderSearchInput');
    const headerSearchClear = document.getElementById('publicHeaderSearchClear');
    const headerSearchPersistKey = 'publicHeaderSearchKeepOpen';

    function buildSearchUrl(form) {
        const action = form.getAttribute('action') || window.location.pathname;
        const url = new URL(action, window.location.origin);
        const params = new URLSearchParams();
        const formData = new FormData(form);

        formData.forEach(function (value, key) {
            if (typeof value === 'string') {
                const trimmedValue = value.trim();
                if (trimmedValue === '') {
                    return;
                }
                params.append(key, trimmedValue);
                return;
            }

            params.append(key, value);
        });

        url.search = params.toString();
        return url.toString();
    }

    function initLiveSearch(form, input) {
        if (!form || !input) {
            return;
        }

        let debounceTimer = null;
        let isComposing = false;

        function submitLiveSearch() {
            const nextUrl = buildSearchUrl(form);
            const currentUrl = new URL(window.location.href);

            if (nextUrl === currentUrl.toString()) {
                return;
            }

            window.location.href = nextUrl;
        }

        function queueLiveSearch() {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(submitLiveSearch, 280);
        }

        input.addEventListener('compositionstart', function () {
            isComposing = true;
        });

        input.addEventListener('compositionend', function () {
            isComposing = false;
            queueLiveSearch();
        });

        input.addEventListener('input', function () {
            updateHeaderSearchClear();

            if (isComposing) {
                return;
            }

            queueLiveSearch();
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                window.clearTimeout(debounceTimer);
            }
        });
    }

    // ── Rotating hero chips ───────────────────────────────────────────────────
    // ── Clear (X) button ──────────────────────────────────────────────────────
    // ── Auto-submit on type (Debounced / True Live Search AJAX) ───────────────
    function setHeaderSearchOpen(isOpen) {
        if (!header || !headerSearchToggle) {
            return;
        }

        header.classList.toggle('public-header-search-open', isOpen);
        headerSearchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen && headerSearchInput) {
            window.setTimeout(() => {
                headerSearchInput.focus();
                const valueLength = headerSearchInput.value.length;
                headerSearchInput.setSelectionRange(valueLength, valueLength);
            }, 30);
        }
    }

    function shouldKeepHeaderSearchOpen() {
        if (!headerSearchInput) {
            return false;
        }

        return headerSearchInput.value.trim() !== '';
    }

    function shouldRestoreHeaderSearchOpen() {
        try {
            return window.sessionStorage.getItem(headerSearchPersistKey) === 'true';
        } catch (e) {
            return false;
        }
    }

    function persistHeaderSearchOpen(shouldPersist) {
        try {
            if (shouldPersist) {
                window.sessionStorage.setItem(headerSearchPersistKey, 'true');
            } else {
                window.sessionStorage.removeItem(headerSearchPersistKey);
            }
        } catch (e) {
            // Ignore storage access issues.
        }
    }

    function updateHeaderSearchClear() {
        if (!headerSearchClear || !headerSearchInput) {
            return;
        }

        const hasValue = headerSearchInput.value.trim() !== '';
        headerSearchClear.classList.toggle('is-visible', hasValue);
        headerSearchClear.setAttribute('aria-hidden', hasValue ? 'false' : 'true');
    }

    if (header && headerSearchToggle && headerSearchForm) {
        updateHeaderSearchClear();

        if (shouldKeepHeaderSearchOpen() || shouldRestoreHeaderSearchOpen()) {
            setHeaderSearchOpen(true);
            persistHeaderSearchOpen(false);
        }

        headerSearchToggle.addEventListener('click', function (e) {
            e.preventDefault();
            const willOpen = shouldKeepHeaderSearchOpen() ? true : !header.classList.contains('public-header-search-open');
            setHeaderSearchOpen(willOpen);
        });

        headerSearchForm.addEventListener('submit', function () {
            persistHeaderSearchOpen(true);
            setHeaderSearchOpen(true);
        });

        if (headerSearchClear && headerSearchInput) {
            headerSearchClear.addEventListener('click', function () {
                headerSearchInput.value = '';
                updateHeaderSearchClear();
                persistHeaderSearchOpen(true);
                setHeaderSearchOpen(true);
                headerSearchInput.focus();
                window.location.href = buildSearchUrl(headerSearchForm);
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && header.classList.contains('public-header-search-open') && !shouldKeepHeaderSearchOpen()) {
                setHeaderSearchOpen(false);
            }
        });

        initLiveSearch(headerSearchForm, headerSearchInput);
    }

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

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPublicPage, { once: true });
    } else {
        initPublicPage();
    }

})();
