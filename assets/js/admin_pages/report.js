/**
 * Report Page JS
 * Archive System - Quezon City Public Library
 */

(function () {
    'use strict';

    // State
    const state = {
        search: '',
        period: 'all',
        startDate: '',
        endDate: '',
        limit: 10,
        page: 1,
        total: 0
    };

    let searchTimer = null;

    // DOM Elements
    const searchInput = document.getElementById('reportSearch');
    const periodSelect = document.getElementById('reportPeriod');
    const customDateContainer = document.getElementById('customDateRange');
    const startDateInput = document.getElementById('reportStartDate');
    const endDateInput = document.getElementById('reportEndDate');

    const limitSelect = document.getElementById('reportLimit');
    const btnPrev = document.getElementById('btnPrevPage');
    const btnNext = document.getElementById('btnNextPage');
    const paginationInfo = document.getElementById('reportPaginationInfo');

    const tableBody = document.getElementById('reportTableBody');

    // Init
    initEvents();
    fetchReportData();

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
                // If they manually select a period, clear explicitly set custom dates
                if (startDateInput) startDateInput.value = '';
                if (endDateInput) endDateInput.value = '';
                state.startDate = '';
                state.endDate = '';
                state.page = 1;
                fetchReportData();
            });
        }

        // Custom Date Range changes
        if (startDateInput) {
            startDateInput.addEventListener('change', (e) => {
                state.startDate = e.target.value;
                if (state.startDate && state.endDate) {
                    periodSelect.value = 'all'; // visually reset
                    state.period = 'custom';
                    state.page = 1;
                    fetchReportData();
                }
            });
        }

        if (endDateInput) {
            endDateInput.addEventListener('change', (e) => {
                state.endDate = e.target.value;
                if (state.startDate && state.endDate) {
                    periodSelect.value = 'all'; // visually reset
                    state.period = 'custom';
                    state.page = 1;
                    fetchReportData();
                }
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

        // Export CSV logic with Modal
        const exportCsvBtnEl = document.getElementById('exportCsvBtn');

        if (exportCsvBtnEl) {
            exportCsvBtnEl.addEventListener('click', () => {
                const now = new Date();

                // Helper: format a Date to "Month DD, YYYY"
                const fmt = (d) => d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                // Helper: format a yyyy-mm-dd string to "Month DD, YYYY"
                const fmtStr = (s) => fmt(new Date(s + 'T00:00:00'));

                let periodLabel = '';
                let dateRangeText = '';
                let badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary-subtle';

                if (state.period === 'today') {
                    periodLabel = 'Today';
                    dateRangeText = fmt(now);
                    badgeClass = 'bg-info bg-opacity-10 text-info border border-info-subtle';

                } else if (state.period === 'weekly') {
                    const weekStart = new Date(now);
                    weekStart.setDate(now.getDate() - 6);
                    periodLabel = 'This Week';
                    dateRangeText = `${fmt(weekStart)} &ndash; ${fmt(now)}`;
                    badgeClass = 'bg-success bg-opacity-10 text-success border border-success-subtle';

                } else if (state.period === 'monthly') {
                    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
                    periodLabel = 'This Month';
                    dateRangeText = `${fmt(monthStart)} &ndash; ${fmt(now)}`;
                    badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning-subtle';

                } else if (state.period === 'yearly') {
                    const yearStart = new Date(now.getFullYear(), 0, 1);
                    periodLabel = 'This Year';
                    dateRangeText = `${fmt(yearStart)} &ndash; ${fmt(now)}`;
                    badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger-subtle';

                } else if (state.period === 'custom' && state.startDate && state.endDate) {
                    periodLabel = 'Custom Date Range';
                    dateRangeText = `${fmtStr(state.startDate)} &ndash; ${fmtStr(state.endDate)}`;
                    badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle';

                } else {
                    periodLabel = 'All Time';
                    dateRangeText = 'All available records';
                }

                // Build modal content (avoid template literals to prevent encoding issues)
                var msgParts = [];
                msgParts.push('<div class="mb-3">');
                msgParts.push('<span class="badge rounded-pill px-3 py-2 ' + badgeClass + '" style="font-size:12px;font-weight:600;letter-spacing:.5px;">');
                msgParts.push('<i class="bi bi-clock-history me-1"></i>' + periodLabel);
                msgParts.push('</span></div>');
                msgParts.push('<p class="mb-2 text-secondary" style="font-size:14px;">You are about to export the <strong>Most Read Files</strong> report covering:</p>');
                msgParts.push('<div class="rounded-3 px-3 py-2 mb-3" style="background:#f8f9fa;border:1px solid #e9ecef;font-size:13px;">');
                msgParts.push('<i class="bi bi-calendar-range me-2 text-muted"></i><strong>' + dateRangeText + '</strong>');
                msgParts.push('</div>');

                if (state.search) {
                    msgParts.push('<div class="rounded-3 px-3 py-2 mb-3" style="background:#fff3cd;border:1px solid #ffc107;font-size:13px;">');
                    msgParts.push('<i class="bi bi-funnel me-2 text-warning"></i>Filtered by keyword: <strong>"' + state.search + '"</strong>');
                    msgParts.push('</div>');
                }

                msgParts.push('<p class="mb-0 text-muted" style="font-size:13px;">The CSV file will open correctly in Excel and other spreadsheet apps.</p>');

                // Look up at runtime — modal HTML lives after this script tag
                var msgEl = document.getElementById('exportModalMessage');
                if (msgEl) {
                    msgEl.innerHTML = msgParts.join('');
                }

                // Use getOrCreateInstance to avoid stale references
                const exportModalEl2 = document.getElementById('exportModal');
                if (exportModalEl2) {
                    bootstrap.Modal.getOrCreateInstance(exportModalEl2).show();
                }
            });
        }

        // confirmExportBtn also looked up at runtime for same reason
        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.id === 'confirmExportBtn' ? e.target : (e.target && e.target.closest ? e.target.closest('#confirmExportBtn') : null);
            if (btn) {
                const params = new URLSearchParams({
                    export: 'csv',
                    search: state.search,
                    period: state.period,
                    start_date: state.startDate,
                    end_date: state.endDate
                });
                window.location.href = `${APP_URL}/backend/api/report.php?${params.toString()}`;

                const exportModalEl2 = document.getElementById('exportModal');
                if (exportModalEl2) {
                    const inst = bootstrap.Modal.getInstance(exportModalEl2);
                    if (inst) inst.hide();
                }
            }
        });
    }

    function fetchReportData() {
        setLoading(true);

        const params = new URLSearchParams({
            search: state.search,
            period: state.period,
            start_date: state.startDate,
            end_date: state.endDate,
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

    function renderTable(data) {
        if (!data || data.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No recorded views found for the selected criteria.
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

            // Use publication_type provided by the backend API
            let pubType = item.publication_type || item.file_type || 'Document';

            if (pubType.toLowerCase() === 'newspaper') {
                typeBadge = `<span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle">Newspaper</span>`;
            } else if (pubType.toLowerCase() === 'magazine') {
                typeBadge = `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle">Magazine</span>`;
            } else {
                typeBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle">${escapeHtml(pubType || 'Document')}</span>`;
            }

            html += `
                <tr>
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
                    <td class="text-end report-views-cell">
                        ${parseInt(item.view_count).toLocaleString()}
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;
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
                    <td colspan="5" class="text-center py-5">
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
                <td colspan="5" class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}
                </td>
            </tr>
        `;
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
