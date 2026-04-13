/**
 * Report Page JS
 * Archive System - Quezon City Public Library
 */

(function () {
    'use strict';

    // State
    const state = {
        search: '',
        reportType: 'most_viewed',
        period: 'all',
        startDate: '',
        endDate: '',
        publicationType: '',
        category: '',
        limit: 10,
        page: 1,
        total: 0,
        exportMode: 'print'
    };

    let searchTimer = null;

    // DOM Elements
    const searchInput = document.getElementById('reportSearch');
    const periodSelect = document.getElementById('reportPeriod');
    const startDateInput = document.getElementById('reportStartDate');
    const endDateInput = document.getElementById('reportEndDate');
    const publicationTypeSelect = document.getElementById('reportPublicationType');
    const categorySelect = document.getElementById('reportCategory');
    const reportPeriodWrap = document.getElementById('reportPeriodWrap');
    const customDateRange = document.getElementById('customDateRange');
    const reportPublicationTypeWrap = document.getElementById('reportPublicationTypeWrap');
    const reportCategoryWrap = document.getElementById('reportCategoryWrap');
    const reportDateActions = document.getElementById('reportDateActions');
    const clearReportDatesBtn = document.getElementById('clearReportDatesBtn');
    const reportPage = document.querySelector('.report-page');

    const limitSelect = document.getElementById('reportLimit');
    const btnPrev = document.getElementById('btnPrevPage');
    const btnNext = document.getElementById('btnNextPage');
    const paginationInfo = document.getElementById('reportPaginationInfo');

    const tableHead = document.getElementById('reportTableHead');
    const tableBody = document.getElementById('reportTableBody');
    const reportTabMostViewed = document.getElementById('reportTabMostViewed');
    const reportTabFileSummary = document.getElementById('reportTabFileSummary');
    let currentFileId = null;

    // Init
    initEvents();
    updateDateTime();
    renderTableHead();
    updateFilterVisibility();
    updateClearDatesVisibility();
    fetchReportData();
    setInterval(updateDateTime, 1000);

    function initEvents() {
        // Search (Debounced)
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    state.search = e.target.value.trim();
                    state.page = 1; // Reset to page 1 on search
                    fetchReportData();
                }, 500);
            });
        }

        // Period filter
        if (periodSelect) {
            periodSelect.addEventListener('change', (e) => {
                state.period = e.target.value;
                updateClearDatesVisibility();
                state.page = 1;
                fetchReportData();
            });
        }

        // Custom Date Range changes
        if (startDateInput) {
            startDateInput.addEventListener('change', (e) => {
                state.startDate = e.target.value;
                updateClearDatesVisibility();
                state.page = 1;
                fetchReportData();
            });
        }

        if (endDateInput) {
            endDateInput.addEventListener('change', (e) => {
                state.endDate = e.target.value;
                updateClearDatesVisibility();
                state.page = 1;
                fetchReportData();
            });
        }

        if (clearReportDatesBtn) {
            clearReportDatesBtn.addEventListener('click', () => {
                clearDateFilters();
            });
        }

        if (publicationTypeSelect) {
            publicationTypeSelect.addEventListener('change', (e) => {
                state.publicationType = e.target.value;
                state.page = 1;
                fetchReportData();
            });
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', (e) => {
                state.category = e.target.value;
                state.page = 1;
                fetchReportData();
            });
        }

        // Limit
        if (limitSelect) {
            limitSelect.addEventListener('change', (e) => {
                state.limit = parseInt(e.target.value, 10);
                state.page = 1;
                fetchReportData();
            });
        }

        if (reportTabMostViewed) {
            reportTabMostViewed.addEventListener('click', () => {
                switchReportType('most_viewed');
            });
        }

        if (reportTabFileSummary) {
            reportTabFileSummary.addEventListener('click', () => {
                switchReportType('file_summary');
            });
        }

        // Export logic with Modal
        const exportPdfBtnEl = document.getElementById('exportPdfBtn');

        if (exportPdfBtnEl) {
            exportPdfBtnEl.addEventListener('click', () => {
                state.exportMode = 'print';
                openExportModal();
            });
        }

        // confirmExportBtn also looked up at runtime for same reason
        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.id === 'confirmExportBtn' ? e.target : (e.target && e.target.closest ? e.target.closest('#confirmExportBtn') : null);
            if (btn) {
                const params = new URLSearchParams({
                    export: 'print',
                    report_type: state.reportType,
                    search: state.search,
                    period: state.period,
                    start_date: state.startDate,
                    end_date: state.endDate,
                    publication_type: state.publicationType,
                    category: state.category
                });

                const url = `${APP_URL}/backend/api/report.php?${params.toString()}`;
                fetch(url, { credentials: 'same-origin' })
                    .then(response => response.text())
                    .then(html => {
                        const printWindow = window.open('', '_blank');
                        if (!printWindow) {
                            window.location.href = url;
                            return;
                        }

                        printWindow.document.open();
                        printWindow.document.write(html);
                        printWindow.document.close();
                    })
                    .catch(() => {
                        window.location.href = url;
                    });

                const exportModalEl2 = document.getElementById('exportModal');
                if (exportModalEl2) {
                    const inst = bootstrap.Modal.getInstance(exportModalEl2);
                    if (inst) inst.hide();
                }
            }
        });
    }

    function switchReportType(type) {
        if (state.reportType === type) {
            return;
        }

        state.reportType = type;
        state.page = 1;

        if (reportTabMostViewed) {
            const isActive = type === 'most_viewed';
            reportTabMostViewed.classList.toggle('active', isActive);
            reportTabMostViewed.setAttribute('aria-selected', isActive ? 'true' : 'false');
        }

        if (reportTabFileSummary) {
            const isActive = type === 'file_summary';
            reportTabFileSummary.classList.toggle('active', isActive);
            reportTabFileSummary.setAttribute('aria-selected', isActive ? 'true' : 'false');
        }

        renderTableHead();
        updateFilterVisibility();
        updateClearDatesVisibility();
        fetchReportData();
    }

    function updateFilterVisibility() {
        const isFileSummary = state.reportType === 'file_summary';

        if (reportPage) {
            reportPage.classList.toggle('file-summary-filters', isFileSummary);
        }

        if (reportPeriodWrap) {
            reportPeriodWrap.classList.toggle('d-none', isFileSummary);
        }

        if (customDateRange) {
            customDateRange.classList.remove('d-none');
        }

        if (reportPublicationTypeWrap) {
            reportPublicationTypeWrap.classList.toggle('d-none', !isFileSummary);
        }

        if (reportCategoryWrap) {
            reportCategoryWrap.classList.toggle('d-none', !isFileSummary);
        }
    }

    function renderTableHead() {
        if (!tableHead) {
            return;
        }

        if (state.reportType === 'most_viewed') {
            tableHead.innerHTML = `
                <tr>
                    <th class="ps-4 py-3 text-uppercase text-secondary" style="width: 80px; text-align: center;">Rank</th>
                    <th class="py-3 text-uppercase text-secondary" style="width: 100px;">Thumbnail</th>
                    <th class="py-3 text-uppercase text-secondary">Title</th>
                    <th class="py-3 text-uppercase text-secondary" style="width: 14%">Publication Type</th>
                    <th class="py-3 text-uppercase text-secondary" style="width: 14%">Category</th>
                    <th class="text-end pe-4 py-3 text-uppercase text-secondary" style="width: 14%">Total Views</th>
                </tr>
            `;
            return;
        }

        tableHead.innerHTML = `
            <tr>
                <th class="ps-4 py-3 text-uppercase text-secondary" style="width: 80px; text-align: center;">Rank</th>
                <th class="py-3 text-uppercase text-secondary" style="width: 100px;">Thumbnail</th>
                <th class="py-3 text-uppercase text-secondary">Title</th>
                <th class="py-3 text-uppercase text-secondary" style="width: 12%">Publication Type</th>
                <th class="py-3 text-uppercase text-secondary" style="width: 12%">Category</th>
                <th class="py-3 text-uppercase text-secondary" style="width: 12%">Uploader</th>
                <th class="py-3 text-uppercase text-secondary" style="width: 12%">Uploaded At</th>
                <th class="text-end pe-4 py-3 text-uppercase text-secondary" style="width: 10%">File Size</th>
            </tr>
        `;
    }

    function getCurrentColspan() {
        return state.reportType === 'most_viewed' ? 6 : 8;
    }

    function updateClearDatesVisibility() {
        if (!reportDateActions) {
            return;
        }

        const hasDateFilters = Boolean(
            (startDateInput && startDateInput.value) ||
            (endDateInput && endDateInput.value)
        );

        reportDateActions.classList.toggle('d-none', !hasDateFilters);
    }

    function clearDateFilters() {
        if (startDateInput) startDateInput.value = '';
        if (endDateInput) endDateInput.value = '';

        state.startDate = '';
        state.endDate = '';
        state.page = 1;

        updateClearDatesVisibility();
        fetchReportData();
    }

    function fetchReportData() {
        setLoading(true);

        const params = new URLSearchParams({
            report_type: state.reportType,
            search: state.search,
            period: state.period,
            start_date: state.startDate,
            end_date: state.endDate,
            publication_type: state.publicationType,
            category: state.category,
            limit: state.limit,
            page: state.page
        });

        fetch(`${APP_URL}/backend/api/report.php?${params.toString()}`)
            .then(response => response.json())
            .then(result => {
                setLoading(false);
                if (result.success) {
                    state.total = result.total;
                    state.limit = result.limit;
                    state.page = result.page;
                    updateFilterOptions(result.filter_options || {});
                    renderTable(result.data);
                    updatePaginationUI();
                } else {
                    showError(result.message || 'Failed to fetch data');
                }
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                setLoading(false);
                showError('A network error occurred');
            });
    }

    function openExportModal() {
        const now = new Date();
        const fmt = (d) => d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        const fmtStr = (s) => fmt(new Date(s + 'T00:00:00'));

        let periodLabel = 'All Time';
        let dateRangeText = 'All available records';
        let badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary-subtle';

        if (state.period === 'daily') {
            periodLabel = 'Daily';
            dateRangeText = fmt(now);
            badgeClass = 'bg-info bg-opacity-10 text-info border border-info-subtle';
        } else if (state.period === 'weekly') {
            const day = now.getDay();
            const diffToMonday = day === 0 ? 6 : day - 1;
            const weekStart = new Date(now);
            weekStart.setDate(now.getDate() - diffToMonday);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            periodLabel = 'This Week';
            dateRangeText = `${fmt(weekStart)} - ${fmt(weekEnd)}`;
            badgeClass = 'bg-success bg-opacity-10 text-success border border-success-subtle';
        } else if (state.period === 'monthly') {
            periodLabel = 'This Month';
            dateRangeText = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning-subtle';
        } else if (state.period === 'yearly') {
            periodLabel = 'This Year';
            dateRangeText = now.getFullYear().toString();
            badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger-subtle';
        }

        if (state.startDate && state.endDate) {
            periodLabel = 'Date Range';
            dateRangeText = `${fmtStr(state.startDate)} - ${fmtStr(state.endDate)}`;
            badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle';
        } else if (state.startDate) {
            periodLabel = 'Date Range';
            dateRangeText = `${fmtStr(state.startDate)} onward`;
            badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle';
        } else if (state.endDate) {
            periodLabel = 'Date Range';
            dateRangeText = `Until ${fmtStr(state.endDate)}`;
            badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle';
        }

        const formatName = 'PDF / Print';
        const reportTypeLabel = state.reportType === 'most_viewed' ? 'Most Viewed File' : 'File Summary';
        const formatHint = 'A print-ready view will open in a new tab. Save it as PDF from the print dialog.';

        const msgParts = [];
        msgParts.push('<div class="mb-3">');
        msgParts.push('<span class="badge rounded-pill px-3 py-2 ' + badgeClass + '" style="font-size:12px;font-weight:600;letter-spacing:.5px;">');
        msgParts.push('<i class="bi bi-clock-history me-1"></i>' + periodLabel);
        msgParts.push('</span></div>');
        msgParts.push('<p class="mb-2 text-secondary" style="font-size:14px;">You are about to export the <strong>' + reportTypeLabel + '</strong> report in <strong>' + formatName + '</strong> format:</p>');
        msgParts.push('<div class="rounded-3 px-3 py-2 mb-3" style="background:#f8f9fa;border:1px solid #e9ecef;font-size:13px;">');
        msgParts.push('<i class="bi bi-calendar-range me-2 text-muted"></i><strong>' + dateRangeText + '</strong>');
        msgParts.push('</div>');

        if (state.search) {
            msgParts.push('<div class="rounded-3 px-3 py-2 mb-3" style="background:#fff3cd;border:1px solid #ffc107;font-size:13px;">');
            msgParts.push('<i class="bi bi-funnel me-2 text-warning"></i>Filtered by keyword: <strong>"' + escapeHtml(state.search) + '"</strong>');
            msgParts.push('</div>');
        }

        if (state.reportType === 'file_summary' && (state.publicationType || state.category)) {
            const filterParts = [];
            if (state.publicationType) {
                filterParts.push('Publication Type: <strong>' + escapeHtml(state.publicationType) + '</strong>');
            }
            if (state.category) {
                filterParts.push('Category: <strong>' + escapeHtml(state.category) + '</strong>');
            }

            msgParts.push('<div class="rounded-3 px-3 py-2 mb-3" style="background:#eff6ff;border:1px solid #93c5fd;font-size:13px;">');
            msgParts.push('<i class="bi bi-tags me-2 text-primary"></i>' + filterParts.join(' | '));
            msgParts.push('</div>');
        }

        msgParts.push('<p class="mb-0 text-muted" style="font-size:13px;">' + formatHint + '</p>');

        const msgEl = document.getElementById('exportModalMessage');
        const titleEl = document.getElementById('exportModalLabel');
        const confirmBtn = document.getElementById('confirmExportBtn');
        if (msgEl) {
            msgEl.innerHTML = msgParts.join('');
        }
        if (titleEl) {
            titleEl.textContent = 'Export PDF Report';
        }
        if (confirmBtn) {
            confirmBtn.innerHTML = '<i class="bi bi-printer"></i> Open Print View';
        }

        const exportModalEl = document.getElementById('exportModal');
        if (exportModalEl) {
            bootstrap.Modal.getOrCreateInstance(exportModalEl).show();
        }
    }

    function updateFilterOptions(filterOptions) {
        populateSelectOptions(
            publicationTypeSelect,
            Array.isArray(filterOptions.publication_types) ? filterOptions.publication_types : [],
            'All publication types',
            state.publicationType
        );

        populateSelectOptions(
            categorySelect,
            Array.isArray(filterOptions.categories) ? filterOptions.categories : [],
            'All categories',
            state.category
        );
    }

    function populateSelectOptions(selectEl, values, defaultLabel, selectedValue) {
        if (!selectEl) {
            return;
        }

        const normalizedValues = values
            .map(value => String(value || '').trim())
            .filter(Boolean);

        const currentValue = normalizedValues.includes(selectedValue) ? selectedValue : '';
        const options = [`<option value="">${escapeHtml(defaultLabel)}</option>`];

        normalizedValues.forEach(value => {
            const isSelected = value === currentValue ? ' selected' : '';
            options.push(`<option value="${escapeHtml(value)}"${isSelected}>${escapeHtml(value)}</option>`);
        });

        selectEl.innerHTML = options.join('');
        if (selectEl.value !== currentValue) {
            selectEl.value = currentValue;
        }

        if (selectEl === publicationTypeSelect) {
            state.publicationType = currentValue;
        } else if (selectEl === categorySelect) {
            state.category = currentValue;
        }
    }

    function renderTable(data) {
        if (!data || data.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${getCurrentColspan()}" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No records found for the selected criteria.
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        data.forEach((item, index) => {
            const rank = item.rank;
            let rankClass = '';
            if (rank === 1) rankClass = 'rank-1';
            else if (rank === 2) rankClass = 'rank-2';
            else if (rank === 3) rankClass = 'rank-3';

            const thumbHtml = item.thumbnail_path
                ? `<img src="${item.thumbnail_url}" alt="Thumbnail" class="report-thumbnail">`
                : `<div class="report-thumbnail-placeholder"><i class="bi bi-file-earmark-text"></i></div>`;

            let typeBadge = '';
            let categoryBadge = '';

            // Use publication_type provided by the backend API
            let pubType = item.publication_type || '-';
            let category = item.category || '-';

            if (pubType.toLowerCase() === 'newspaper') {
                typeBadge = `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">Newspaper</span>`;
            } else if (pubType.toLowerCase() === 'magazine') {
                typeBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">Magazine</span>`;
            } else {
                typeBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">${escapeHtml(pubType)}</span>`;
            }

            categoryBadge = `<span class="badge bg-info bg-opacity-10 text-info border border-info-subtle">${escapeHtml(category)}</span>`;

            if (state.reportType === 'most_viewed') {
                html += `
                    <tr class="report-row-link" data-report-index="${index}">
                        <td class="text-center">
                            <span class="rank-badge ${rankClass}">${rank}</span>
                        </td>
                        <td>
                            ${thumbHtml}
                        </td>
                        <td class="report-title-cell">
                            ${escapeHtml(item.title)}
                        </td>
                        <td>
                            ${typeBadge}
                        </td>
                        <td>
                            ${categoryBadge}
                        </td>
                        <td class="text-end report-views-cell">
                            ${Number(item.total_views || 0).toLocaleString()}
                        </td>
                    </tr>
                `;
            } else {
                html += `
                    <tr class="report-row-link" data-report-index="${index}">
                        <td class="text-center">
                            <span class="rank-badge ${rankClass}">${rank}</span>
                        </td>
                        <td>
                            ${thumbHtml}
                        </td>
                        <td class="report-title-cell">
                            ${escapeHtml(item.title)}
                        </td>
                        <td>
                            ${typeBadge}
                        </td>
                        <td>
                            ${categoryBadge}
                        </td>
                        <td>
                            ${escapeHtml(item.uploader_name || 'Unknown User')}
                        </td>
                        <td>
                            ${formatDateTime(item.created_at)}
                        </td>
                        <td class="text-end report-views-cell">
                            ${formatFileSize(Number(item.file_size || 0))}
                        </td>
                    </tr>
                `;
            }
        });

        tableBody.innerHTML = html;

        const clickableRows = tableBody.querySelectorAll('.report-row-link[data-report-index]');
        clickableRows.forEach((row) => {
            const rowIndex = parseInt(row.getAttribute('data-report-index'), 10);
            if (Number.isNaN(rowIndex) || !data[rowIndex]) {
                return;
            }

            row.addEventListener('click', () => {
                openPreviewModal(data[rowIndex]);
            });
        });
    }

    function updateDateTime() {
        const now = new Date();
        const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };

        const dateEl = document.getElementById('currentDate');
        const timeEl = document.getElementById('currentTime');

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
        }

        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
    }

    function openPreviewModal(item) {
        const reportPreviewModalEl = document.getElementById('filePreviewModal');
        const reportPreviewImage = document.getElementById('photoViewerImg');
        const reportPreviewPlaceholder = document.getElementById('noPreviewIcon');
        const reportPreviewFileTitle = document.getElementById('previewTitle');
        const reportPreviewCategory = document.getElementById('previewCategory');
        const reportPreviewDescriptionWrap = document.getElementById('metaDescriptionWrap');
        const reportPreviewDescription = document.getElementById('metaDescription');
        const reportPreviewMetaRows = document.getElementById('reportPreviewMetaRows');
        const readNowBtn = document.getElementById('readNowBtn');
        const editBtn = document.getElementById('editBtn');
        const deleteBtn = document.getElementById('deleteBtn');
        const reportPreviewModal = reportPreviewModalEl ? bootstrap.Modal.getOrCreateInstance(reportPreviewModalEl) : null;

        if (!reportPreviewModal) {
            return;
        }

        currentFileId = item.id || null;

        if (reportPreviewFileTitle) {
            reportPreviewFileTitle.textContent = item.title || 'Untitled file';
        }

        if (reportPreviewCategory) {
            const categoryText = (item.category || item.publication_type || 'DOCUMENT').toUpperCase();
            reportPreviewCategory.textContent = categoryText;
            reportPreviewCategory.className = 'public-modal-category-badge';
            reportPreviewCategory.style.display = '';
        }

        if (reportPreviewDescriptionWrap && reportPreviewDescription) {
            reportPreviewDescriptionWrap.style.display = 'none';
            reportPreviewDescription.textContent = '';
        }

        if (reportPreviewMetaRows) {
            reportPreviewMetaRows.innerHTML = '';
        }

        if (readNowBtn) {
            readNowBtn.href = `${APP_URL}/admin/read?id=${item.id}`;
            readNowBtn.target = '_blank';
        }

        if (editBtn) {
            editBtn.href = `${APP_URL}/upload?edit=${item.id}&return_to=report`;
        }

        if (deleteBtn) {
            deleteBtn.onclick = () => {
                showDeleteConfirmation();
            };
        }

        if (item.thumbnail_url && reportPreviewImage && reportPreviewPlaceholder) {
            reportPreviewImage.src = item.thumbnail_url;
            reportPreviewImage.style.display = '';
            reportPreviewPlaceholder.style.display = 'none';
        } else if (reportPreviewImage && reportPreviewPlaceholder) {
            reportPreviewImage.src = '';
            reportPreviewImage.style.display = 'none';
            reportPreviewPlaceholder.style.display = 'flex';
        }

        fetch(`${APP_URL}/backend/api/file-metadata.php?id=${item.id}&context=modal`)
            .then(response => response.json())
            .then(result => {
                if (!result.success || !reportPreviewMetaRows) {
                    renderReportModalMetadata([], item);
                    return;
                }

                const metadata = Array.isArray(result.metadata) ? result.metadata : [];
                hydrateReportPreviewContent(metadata, item);
                renderReportModalMetadata(metadata, item);
            })
            .catch(error => {
                console.error('Error loading report preview metadata:', error);
                renderReportModalMetadata([], item);
            });

        reportPreviewModal.show();
    }

    function hydrateReportPreviewContent(metadata, item) {
        const reportPreviewCategory = document.getElementById('previewCategory');
        const reportPreviewDescriptionWrap = document.getElementById('metaDescriptionWrap');
        const reportPreviewDescription = document.getElementById('metaDescription');

        const categoryMeta = metadata.find(meta => (meta.field_label || '').toLowerCase() === 'category');
        const descriptionMeta = metadata.find(meta => {
            const label = (meta.field_label || '').toLowerCase();
            return label === 'description' || label === 'summary' || label === 'about';
        });

        if (reportPreviewCategory) {
            const categoryValue = (categoryMeta && categoryMeta.field_value ? categoryMeta.field_value : item.publication_type || 'Document').trim();
            reportPreviewCategory.textContent = categoryValue.toUpperCase();
            reportPreviewCategory.className = 'public-modal-category-badge';
            reportPreviewCategory.style.display = categoryValue ? '' : 'none';
        }

        if (reportPreviewDescriptionWrap && reportPreviewDescription) {
            const descriptionValue = descriptionMeta && descriptionMeta.field_value ? descriptionMeta.field_value.trim() : '';
            if (descriptionValue) {
                reportPreviewDescription.textContent = descriptionValue;
                reportPreviewDescriptionWrap.style.display = 'block';
            } else {
                reportPreviewDescription.textContent = '';
                reportPreviewDescriptionWrap.style.display = 'none';
            }
        }
    }

    function renderReportModalMetadata(metadata, item) {
        const reportPreviewMetaRows = document.getElementById('reportPreviewMetaRows');
        if (!reportPreviewMetaRows) {
            return;
        }

        const html = [];

        metadata.forEach(meta => {
            const fieldLabel = (meta.field_label || '').trim();
            const fieldLabelLower = fieldLabel.toLowerCase();

            if (fieldLabelLower === 'title' || fieldLabelLower === 'description' || fieldLabelLower === 'summary' || fieldLabelLower === 'about') {
                return;
            }

            let icon = 'bi-info-circle';
            const fieldNameLower = (meta.field_name || '').toLowerCase();
            if (fieldNameLower === 'publication_date' || fieldLabelLower === 'date published' || fieldLabelLower === 'publication date' || fieldLabelLower === 'date issued' || fieldLabelLower === 'date') icon = 'bi-calendar3';
            else if (fieldNameLower === 'publisher' || fieldLabelLower === 'publisher') icon = 'bi-building';
            else if (fieldLabelLower === 'publication type') icon = 'bi-grid';
            else if (fieldLabelLower === 'language') icon = 'bi-translate';
            else if (fieldLabelLower === 'category') icon = 'bi-tag';
            else if (fieldLabelLower === 'tags' || fieldLabelLower === 'keywords') icon = 'bi-tags';
            else if (fieldLabelLower === 'author') icon = 'bi-person';

            let displayValue = meta.field_value;

            if (meta.field_type === 'date' && displayValue && displayValue.trim() !== '') {
                const date = new Date(displayValue);
                if (!Number.isNaN(date.getTime())) {
                    displayValue = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                }
            }

            if (meta.field_type === 'tags' && displayValue && displayValue.trim() !== '') {
                const tags = displayValue.split(',').map(tag => tag.trim()).filter(Boolean);
                if (tags.length > 0) {
                    html.push(`
                        <div class="public-modal-meta-row">
                            <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(fieldLabel)}</span>
                            <div class="public-modal-keywords-wrap">${tags.map(tag => `<span class="public-modal-keyword-pill">${escapeHtml(tag)}</span>`).join('')}</div>
                        </div>
                    `);
                    return;
                }
            }

            if (meta.field_type === 'checkbox' && displayValue) {
                try {
                    const values = JSON.parse(displayValue);
                    if (Array.isArray(values) && values.length > 0) {
                        html.push(`
                            <div class="public-modal-meta-row">
                                <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(fieldLabel)}</span>
                                <div class="public-modal-keywords-wrap">${values.map(value => `<span class="public-modal-keyword-pill">${escapeHtml(value)}</span>`).join('')}</div>
                            </div>
                        `);
                        return;
                    }
                } catch (error) {
                    console.error('Error parsing checkbox metadata:', error);
                }
            }

            if (displayValue === null || displayValue === undefined || String(displayValue).trim() === '') {
                displayValue = '-';
            }

            html.push(`
                <div class="public-modal-meta-row">
                    <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(fieldLabel)}</span>
                    <span class="public-modal-meta-value">${escapeHtml(String(displayValue))}</span>
                </div>
            `);
        });

        html.push(`
            <div class="public-modal-meta-row">
                <span class="public-modal-meta-label"><i class="bi bi-bar-chart-line"></i> Total Views</span>
                <span class="public-modal-meta-value report-total-views-value">${Number(item.total_views || 0).toLocaleString()}</span>
            </div>
        `);

        html.push(`
            <div class="public-modal-meta-row">
                <span class="public-modal-meta-label"><i class="bi bi-clock-history"></i> Uploaded At</span>
                <span class="public-modal-meta-value report-total-views-value">${escapeHtml(formatDateTime(item.created_at))}</span>
            </div>
        `);

        html.push(`
            <div class="public-modal-meta-row">
                <span class="public-modal-meta-label"><i class="bi bi-file-earmark"></i> Format</span>
                <span class="public-format-badge">${escapeHtml(String(item.file_type || 'Document').toUpperCase())}</span>
            </div>
        `);

        html.push(`
            <div class="public-modal-meta-row">
                <span class="public-modal-meta-label"><i class="bi bi-hdd"></i> File Size</span>
                <span class="public-modal-meta-value">${escapeHtml(formatFileSize(Number(item.file_size || 0)))}</span>
            </div>
        `);

        reportPreviewMetaRows.innerHTML = html.join('');
    }

    function showDeleteConfirmation() {
        const previewModalEl = document.getElementById('filePreviewModal');
        const deleteModalEl = document.getElementById('deleteConfirmModal');
        if (!deleteModalEl) {
            return;
        }

        const previewModal = previewModalEl ? bootstrap.Modal.getInstance(previewModalEl) : null;
        if (previewModal) {
            previewModal.hide();
        }

        setTimeout(() => {
            bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
        }, 250);
    }

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!currentFileId) {
                return;
            }

            const button = this;
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

            const formData = new FormData();
            formData.append('action', 'move_to_trash');
            formData.append('item_id', currentFileId);

            fetch(`${APP_URL}/backend/api/dashboard.php`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || 'Delete failed');
                    }

                    const deleteModalEl = document.getElementById('deleteConfirmModal');
                    const successModalEl = document.getElementById('deleteSuccessModal');
                    const deleteModal = deleteModalEl ? bootstrap.Modal.getInstance(deleteModalEl) : null;
                    if (deleteModal) {
                        deleteModal.hide();
                    }

                    if (successModalEl) {
                        const successModal = bootstrap.Modal.getOrCreateInstance(successModalEl);
                        successModal.show();
                        setTimeout(() => successModal.hide(), 2000);
                    }

                    currentFileId = null;
                    fetchReportData();
                })
                .catch(error => {
                    console.error('Error deleting report item:', error);
                    alert(error.message || 'An error occurred while deleting.');
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                });
        });
    }

    function updatePaginationUI() {
        const totalPages = Math.ceil(state.total / state.limit);

        let startItem = (state.page - 1) * state.limit + 1;
        let endItem = state.page * state.limit;
        if (endItem > state.total) endItem = state.total;
        if (state.total === 0) {
            startItem = 0;
            endItem = 0;
        }

        if (paginationInfo) {
            paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${state.total}`;
        }

        const controlsContainer = document.getElementById('reportPaginationControls');
        if (controlsContainer) {
            let html = '';

            // Prev Button
            const prevDisabled = state.page <= 1 ? 'disabled' : '';
            html += `<button class="pagination-circle ${prevDisabled}" id="btnReportPrev"><i class="bi bi-chevron-left"></i></button>`;

            if (totalPages === 0) {
                html += '<button class="pagination-circle active" data-page="1">1</button>';
            }

            // Page Numbers
            const startPage = Math.max(1, state.page - 2);
            const endPage = Math.min(totalPages, state.page + 2);

            for (let i = startPage; i <= endPage; i++) {
                if (i < 1) continue;
                const activeClass = state.page === i ? 'active' : '';
                html += `<button class="pagination-circle ${activeClass}" data-page="${i}">${i}</button>`;
            }

            // Ellipsis if needed
            if (totalPages > endPage) {
                html += `<span class="text-muted small px-1">...</span>`;
                html += `<button class="pagination-circle" data-page="${totalPages}">${totalPages}</button>`;
            }

            // Next Button
            const nextDisabled = state.page >= totalPages ? 'disabled' : '';
            html += `<button class="pagination-circle ${nextDisabled}" id="btnReportNext"><i class="bi bi-chevron-right"></i></button>`;

            controlsContainer.innerHTML = html;

            // Bind click events
            const pageButtons = controlsContainer.querySelectorAll('button[data-page]');
            pageButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!btn.classList.contains('active')) {
                        state.page = parseInt(btn.dataset.page, 10);
                        fetchReportData();
                    }
                });
            });

            const btnPrevObj = document.getElementById('btnReportPrev');
            if (btnPrevObj) {
                btnPrevObj.addEventListener('click', () => {
                    if (state.page > 1) {
                        state.page--;
                        fetchReportData();
                    }
                });
            }

            const btnNextObj = document.getElementById('btnReportNext');
            if (btnNextObj) {
                btnNextObj.addEventListener('click', () => {
                    if (state.page < totalPages) {
                        state.page++;
                        fetchReportData();
                    }
                });
            }
        }
    }

    function setLoading(isLoading) {
        if (isLoading) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="${getCurrentColspan()}" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    function showError(message) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="${getCurrentColspan()}" class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}
                </td>
            </tr>
        `;
    }

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatFileSize(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 B';
        }

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex += 1;
        }

        const decimals = unitIndex === 0 ? 0 : 2;
        return `${size.toFixed(decimals)} ${units[unitIndex]}`;
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return (unsafe + '')
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

})();
