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
                        displayHtml = '<span class="public-modal-keywords-wrap">' + tags.map(function (t) {
                            return '<span class="public-modal-keyword-pill">' + escapeHtml(t) + '</span>';
                        }).join('') + '</span>';
                    } else if (fieldType === 'date' || fieldName === 'date_published' || fieldName === 'publication_date') {
                        displayHtml = escapeHtml(formatDate(trimmedValue));
                    } else if (fieldType === 'textarea' || fieldName === 'description') {
                        displayHtml = '<span style="white-space:pre-wrap;">' + escapeHtml(trimmedValue) + '</span>';
                    } else {
                        displayHtml = escapeHtml(trimmedValue);
                    }

                    const row = document.createElement('div');
                    row.className = 'public-modal-meta-row';
                    if (fieldType === 'tags' || fieldName === 'tags' || fieldName === 'keywords') {
                        row.classList.add('public-modal-meta-row-keywords');
                    }
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
    const headerSearchBar = headerSearchForm ? headerSearchForm.querySelector('.public-header-search-bar') : null;
    const headerSearchClear = document.getElementById('publicHeaderSearchClear');
    const headerSearchSurface = document.getElementById('publicHeaderSearchSurface');
    const headerSearchResults = document.getElementById('publicHeaderSearchResults');
    const headerSearchBackdrop = document.getElementById('publicSearchFocusBackdrop');
    const headerSearchPersistKey = 'publicHeaderSearchKeepOpen';
    const currentPageType = document.body.classList.contains('browse-page') ? 'browse' : 'public';

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

        let isComposing = false;
        let suggestionTimer = null;
        let suggestionAbortController = null;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function setSearchFocusState(isActive) {
            document.body.classList.toggle('public-search-active', isActive);
        }

        function setSuggestionsVisible(isVisible) {
            if (!headerSearchSurface) {
                return;
            }

            headerSearchSurface.classList.toggle('is-visible', isVisible);
            headerSearchSurface.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            setSearchFocusState(isVisible || (header && header.classList.contains('public-header-search-open')));
        }

        function buildPublicationTypeUrl(publicationType) {
            const url = new URL(APP_URL + '/browse', window.location.origin);
            const params = new URLSearchParams();
            params.set('publication_type', publicationType);
            url.search = params.toString();
            return url.toString();
        }

        function renderSuggestions(data, queryValue) {
            if (!headerSearchResults) {
                return;
            }

            const publications = Array.isArray(data.publications) ? data.publications : [];
            const publicationTypes = Array.isArray(data.publication_types) ? data.publication_types : [];
            const sections = [];

            if (publications.length > 0) {
                sections.push(
                    '<section class="public-search-section">' +
                        '<div class="public-search-section-header">' +
                            '<div class="public-search-section-title">Publications</div>' +
                            '<a class="public-search-section-link" href="' + escapeHtml(data.see_all_url || buildSearchUrl(form)) + '">See All</a>' +
                        '</div>' +
                        '<div class="public-search-publications">' +
                            publications.map(function (item) {
                                const thumbHtml = item.thumbnail
                                    ? '<img src="' + escapeHtml(item.thumbnail) + '" alt="' + escapeHtml(item.title) + '">'
                                    : '<i class="bi bi-newspaper"></i>';
                                const metaParts = [];
                                if (item.publication_date) {
                                    metaParts.push('<span>' + escapeHtml(item.publication_date) + '</span>');
                                }
                                if (item.publication_type) {
                                    metaParts.push('<span>' + escapeHtml(item.publication_type) + '</span>');
                                }

                                return (
                                    '<a class="public-search-publication" href="' + escapeHtml(item.url) + '">' +
                                        '<span class="public-search-publication-thumb">' + thumbHtml + '</span>' +
                                        '<span>' +
                                            '<span class="public-search-publication-title">' + escapeHtml(item.title) + '</span>' +
                                            '<span class="public-search-publication-meta">' + metaParts.join('') + '</span>' +
                                        '</span>' +
                                    '</a>'
                                );
                            }).join('') +
                        '</div>' +
                    '</section>'
                );
            }

            if (publicationTypes.length > 0) {
                sections.push(
                    '<section class="public-search-section">' +
                        '<div class="public-search-section-header">' +
                            '<div class="public-search-section-title">Publication Types</div>' +
                        '</div>' +
                        '<div class="public-search-tags">' +
                            publicationTypes.map(function (item) {
                                return '<a class="public-search-tag" href="' + escapeHtml(buildPublicationTypeUrl(item.name)) + '">' + escapeHtml(item.name) + '</a>';
                            }).join('') +
                        '</div>' +
                    '</section>'
                );
            }

            if (sections.length === 0) {
                sections.push('<div class="public-header-search-empty">No quick matches found for "' + escapeHtml(queryValue) + '". Press Search to view full results.</div>');
            } else {
                sections.push('<div class="public-search-helper">Press Enter or Search to open the full result list.</div>');
            }

            headerSearchResults.innerHTML = sections.join('');
            setSuggestionsVisible(true);
        }

        function clearSuggestions() {
            if (!headerSearchResults) {
                return;
            }

            headerSearchResults.innerHTML = '';
            setSuggestionsVisible(false);
        }

        function requestSuggestions() {
            const queryValue = input.value.trim();

            if (queryValue === '') {
                clearSuggestions();
                return;
            }

            if (suggestionAbortController) {
                suggestionAbortController.abort();
            }

            suggestionAbortController = new AbortController();

            const endpoint = new URL(APP_URL + '/backend/api/public-search-suggestions.php');
            endpoint.searchParams.set('q', queryValue);
            endpoint.searchParams.set('page_type', currentPageType);

            fetch(endpoint.toString(), {
                cache: 'no-store',
                signal: suggestionAbortController.signal
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        clearSuggestions();
                        return;
                    }

                    renderSuggestions(data, queryValue);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    clearSuggestions();
                });
        }

        function queueSuggestions() {
            window.clearTimeout(suggestionTimer);
            suggestionTimer = window.setTimeout(requestSuggestions, 180);
        }

        input.addEventListener('compositionstart', function () {
            isComposing = true;
        });

        input.addEventListener('compositionend', function () {
            isComposing = false;
            queueSuggestions();
        });

        input.addEventListener('input', function () {
            updateHeaderSearchClear();
            queueSuggestions();

            if (isComposing) {
                return;
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                persistHeaderSearchOpen(true);
                clearSuggestions();
            }

            if (e.key === 'Escape') {
                clearSuggestions();
            }
        });

        input.addEventListener('focus', function () {
            setSearchFocusState(true);
            if (input.value.trim() !== '') {
                queueSuggestions();
            }
        });

        if (headerSearchBackdrop) {
            headerSearchBackdrop.addEventListener('click', function () {
                clearSuggestions();
            });
        }

        return {
            clearSuggestions: clearSuggestions,
            refreshSuggestions: queueSuggestions
        };
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
        document.body.classList.toggle('public-search-active', isOpen);

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
        const liveSearchControls = initLiveSearch(headerSearchForm, headerSearchInput);

        updateHeaderSearchClear();

        if (shouldKeepHeaderSearchOpen() || shouldRestoreHeaderSearchOpen()) {
            setHeaderSearchOpen(true);
            persistHeaderSearchOpen(false);
        }

        if (headerSearchBar && headerSearchInput) {
            headerSearchBar.addEventListener('click', function (e) {
                if (e.target.closest('button, a, input')) {
                    return;
                }

                headerSearchInput.focus();
            });
        }

        headerSearchToggle.addEventListener('click', function (e) {
            e.preventDefault();
            const willOpen = true; // Always open when clicked
            setHeaderSearchOpen(willOpen);
            document.body.classList.toggle('public-search-active', willOpen);
            if (headerSearchInput) {
                headerSearchInput.focus();
            }
        });

        headerSearchForm.addEventListener('submit', function () {
            persistHeaderSearchOpen(true);
            setHeaderSearchOpen(true);
            document.body.classList.add('public-search-active');
            if (liveSearchControls) {
                liveSearchControls.clearSuggestions();
            }
        });

        if (headerSearchClear && headerSearchInput) {
            headerSearchClear.addEventListener('click', function () {
                headerSearchInput.value = '';
                updateHeaderSearchClear();
                persistHeaderSearchOpen(true);
                setHeaderSearchOpen(true);
                document.body.classList.add('public-search-active');
                headerSearchInput.focus();
                if (liveSearchControls) {
                    liveSearchControls.clearSuggestions();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && header.classList.contains('public-header-search-open')) {
                persistHeaderSearchOpen(false);
                setHeaderSearchOpen(false);
                document.body.classList.remove('public-search-active');
                if (liveSearchControls) {
                    liveSearchControls.clearSuggestions();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (!header.classList.contains('public-header-search-open')) {
                return;
            }

            // Keep search open if clicking inside header
            if (header.contains(e.target)) {
                return;
            }

            // Close search if clicking outside and input is not focused
            if (document.activeElement !== headerSearchInput) {
                persistHeaderSearchOpen(false);
                setHeaderSearchOpen(false);
                document.body.classList.remove('public-search-active');
                if (liveSearchControls) {
                    liveSearchControls.clearSuggestions();
                }
            }
        });
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
