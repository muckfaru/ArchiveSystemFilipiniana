/**
 * Dashboard Page JavaScript
 * Archive System
 */

// ── Live Stats Refresh ─────────────────────────────────────────────────────
function refreshStats() {
    fetch(APP_URL + '/backend/api/stats.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const vals = document.querySelectorAll('.stat-card-value');
            // Index order: Total Archives [0], Total Views [1], Years Covered [2], Total Categories [3]
            if (vals[0]) vals[0].textContent = data.archives.toLocaleString();
            if (vals[1]) vals[1].textContent = data.views.toLocaleString();
            if (vals[2]) vals[2].textContent = data.years;
            if (vals[3]) vals[3].textContent = data.categories.toLocaleString();
        })
        .catch(() => { }); // silently ignore network errors
}

document.addEventListener('DOMContentLoaded', function () {
    let currentFileId = null;
    let currentCardElement = null; // Store reference to the card element

    // Show empty state when all cards have been deleted
    function showEmptyStateIfNeeded() {
        const remainingCards = document.querySelectorAll('#recentUploadsGrid .dashboard-file-card');
        if (remainingCards.length > 0) return;

        // Find the recent-activities container
        const recentActivities = document.querySelector('.recent-activities');
        if (!recentActivities) return;

        // Replace entire content with empty state
        recentActivities.innerHTML = `
            <div class="recent-activities-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h2 class="recent-activities-title mb-0">Recent Activities</h2>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="${APP_URL}/collections" class="view-all-link m-0">View all</a>
                </div>
            </div>
            <div class="empty-state-container">
                <div class="empty-state-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h5 class="empty-state-title">No Archives Yet</h5>
                <p class="empty-state-text">Start building your repository by uploading documents.</p>
                <a href="${APP_URL}/upload" class="btn btn-primary empty-state-btn">
                    <i class="bi bi-plus-lg me-2"></i>Upload Now
                </a>
            </div>
        `;
    }

    // Category color mapping
    const categoryColors = {
        'culture': { bg: '#FFF3E0', color: '#E65100' },
        'politics': { bg: '#E3F2FD', color: '#1565C0' },
        'sports': { bg: '#E8F5E9', color: '#2E7D32' },
        'business': { bg: '#FBE9E7', color: '#BF360C' },
        'news': { bg: '#F3E5F5', color: '#7B1FA2' },
        'entertainment': { bg: '#FCE4EC', color: '#C2185B' },
        'default': { bg: '#ECEFF1', color: '#546E7A' }
    };

    // Handle card clicks to open modal (but not when clicking checkbox or action buttons)
    document.addEventListener('click', function (e) {
        const card = e.target.closest('.dashboard-file-card');
        if (!card) return;

        // Don't open modal if clicking checkbox or action buttons
        if (e.target.closest('.dashboard-item-checkbox') ||
            e.target.closest('.dashboard-card-actions') ||
            e.target.closest('.btn-edit') ||
            e.target.closest('.btn-delete')) {
            return;
        }

        // Open the modal
        const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));

        // Trigger the modal show event with the card as relatedTarget
        const modalEl = document.getElementById('filePreviewModal');
        const event = new Event('show.bs.modal');
        event.relatedTarget = card;
        modalEl.dispatchEvent(event);

        modal.show();
    });

    // File Preview Modal Handler
    const filePreviewModal = document.getElementById('filePreviewModal');
    if (filePreviewModal) {
        // Image Slider State
        let bulkImagePaths = [];
        let currentImageIndex = 0;

        filePreviewModal.addEventListener('show.bs.modal', function (event) {
            // event.relatedTarget is the element that triggered the modal (the card)
            const card = event.relatedTarget;
            if (!card) return;

            // Store card reference for removal later
            currentCardElement = card.closest('.col-md-6'); // Adjust selector based on grid structure

            // Get basic data from card
            const title = card.dataset.title;
            const thumbnail = card.dataset.thumbnail;
            const file = card.dataset.file;
            const id = card.dataset.id;         // encrypted, used for reader URL
            const rawId = card.dataset.rawId;   // numeric, used for delete/edit APIs
            const category = card.dataset.category;
            const description = card.dataset.description || '';
            const isBulk = card.dataset.isBulk === '1';
            const imagePaths = card.dataset.imagePaths ? JSON.parse(card.dataset.imagePaths) : [];
            const format = card.dataset.format;

            // Store current file ID (raw numeric) for delete
            currentFileId = rawId || id;

            // Update basic info immediately
            const titleEl = document.getElementById('previewTitle');
            if (titleEl) titleEl.textContent = title || 'File Preview';

            const categoryEl = document.getElementById('previewCategory');
            if (categoryEl) {
                if (category && category.toLowerCase() !== 'uncategorized') {
                    categoryEl.textContent = category.toUpperCase();
                    categoryEl.className = 'newspaper-category mb-0 ' + category.toLowerCase();
                    categoryEl.style.display = '';
                } else {
                    categoryEl.style.display = 'none';
                }
            }

            // Update description
            const descWrap = document.getElementById('metaDescriptionWrap');
            const descEl = document.getElementById('metaDescription');
            if (descWrap && descEl) {
                if (description && description.trim()) {
                    descEl.textContent = description;
                    descWrap.style.display = 'block';
                } else {
                    descWrap.style.display = 'none';
                }
            }

            // Handle Bulk Image Mode
            const photoViewerPrev = document.getElementById('photoViewerPrev');
            const photoViewerNext = document.getElementById('photoViewerNext');
            const photoViewerCounter = document.getElementById('photoViewerCounter');
            const readNowBtn = document.getElementById('readNowBtn');

            if (isBulk && imagePaths.length > 0) {
                // Bulk Image Mode
                if (photoViewerPrev) photoViewerPrev.style.display = 'none';
                if (photoViewerNext) photoViewerNext.style.display = 'none';
                if (photoViewerCounter) photoViewerCounter.style.display = 'none';

                if (readNowBtn) {
                    readNowBtn.style.display = 'flex';
                    readNowBtn.href = APP_URL + `/admin/read?id=${id}`;
                    readNowBtn.target = '_blank';
                }

                const noPreviewIcon = document.getElementById('noPreviewIcon');
                if (noPreviewIcon) noPreviewIcon.style.display = 'none';

                const previewImg = document.getElementById('photoViewerImg');
                if (previewImg) {
                    previewImg.src = thumbnail || (imagePaths.length > 0 ? imagePaths[0] : '');
                    previewImg.style.display = 'block';
                }
            } else {
                // Normal Document Mode
                if (photoViewerPrev) photoViewerPrev.style.display = 'none';
                if (photoViewerNext) photoViewerNext.style.display = 'none';
                if (photoViewerCounter) photoViewerCounter.style.display = 'none';

                if (readNowBtn) {
                    readNowBtn.style.display = 'flex';
                    // Update Read Now Link (Normal Mode)
                    if (format === 'pdf' || format === 'epub' || format === 'mobi') {
                        readNowBtn.href = APP_URL + `/admin/read?id=${id}`;
                        readNowBtn.target = '_blank';
                    } else {
                        readNowBtn.href = file;
                        readNowBtn.target = '_blank';
                    }
                }

                // Handle Image
                const previewImg = document.getElementById('photoViewerImg');
                const noPreviewIcon = document.getElementById('noPreviewIcon');
                if (thumbnail) {
                    if (previewImg) {
                        previewImg.src = thumbnail;
                        previewImg.style.display = 'block';
                    }
                    if (noPreviewIcon) noPreviewIcon.style.display = 'none';
                } else {
                    if (previewImg) previewImg.style.display = 'none';
                    if (noPreviewIcon) noPreviewIcon.style.display = 'block';
                }
            }

            // Update Links
            if (!isBulk && readNowBtn) readNowBtn.href = APP_URL + '/admin/read?id=' + id;

            const editBtn = document.getElementById('editBtn');
            if (editBtn) editBtn.href = APP_URL + '/upload?edit=' + (rawId || id);

            // Bind delete button
            const deleteBtn = document.getElementById('deleteBtn');
            if (deleteBtn) {
                deleteBtn.onclick = function () {
                    showDeleteConfirmation();
                };
            }

            // INTEGRATION: Fetch modal metadata dynamically using display configuration
            fetch(APP_URL + `/backend/api/file-metadata.php?id=${rawId || id}&context=modal`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.metadata) {
                        renderModalMetadata(data.metadata, isBulk, format);
                    }
                })
                .catch(error => {
                    console.error('Error loading metadata:', error);
                });
        });

    }

    // INTEGRATION: Render modal metadata dynamically based on display configuration
    function renderModalMetadata(metadata, isBulk, format) {
        // Clear existing metadata rows (keep the section title)
        const modalRight = document.querySelector('.public-modal-right');
        if (!modalRight) return;

        // Find all existing meta rows and remove them
        const existingRows = modalRight.querySelectorAll('.public-modal-meta-row');
        existingRows.forEach(row => row.remove());

        // Get the section title element to insert after it
        const sectionTitle = modalRight.querySelector('.public-modal-meta-section-title');
        if (!sectionTitle) return;

        // Track the last inserted element to maintain order
        let lastInserted = sectionTitle;

        // Render each metadata field
        metadata.forEach(meta => {
            // Skip Title field (already shown as modal heading)
            if (meta.field_label && meta.field_label.toLowerCase() === 'title') {
                return;
            }

            const row = document.createElement('div');
            row.className = 'public-modal-meta-row';

            // Determine icon based on field name/label
            let icon = 'bi-info-circle';
            const fieldNameLower = (meta.field_name || '').toLowerCase();
            const fieldLabelLower = (meta.field_label || '').toLowerCase();
            if (fieldNameLower === 'publication_date' || fieldLabelLower === 'date published') icon = 'bi-calendar3';
            else if (fieldNameLower === 'publisher' || fieldLabelLower === 'publisher') icon = 'bi-building';
            else if (fieldNameLower === 'language' || fieldLabelLower === 'language') icon = 'bi-translate';
            else if (fieldNameLower === 'page_count' || fieldLabelLower === 'pages') icon = 'bi-book';
            else if (fieldNameLower === 'volume_issue' || fieldLabelLower === 'volume') icon = 'bi-layers';
            else if (fieldNameLower === 'edition' || fieldLabelLower === 'edition') icon = 'bi-sun';
            else if (fieldNameLower === 'category' || fieldLabelLower === 'category') icon = 'bi-tag';
            else if (fieldLabelLower === 'tags' || fieldLabelLower === 'keywords') icon = 'bi-tags';
            else if (fieldLabelLower === 'description') icon = 'bi-file-text';
            else if (fieldLabelLower === 'author') icon = 'bi-person';

            // Format value based on field type
            let displayValue = meta.field_value;
            if (meta.field_type === 'date') {
                // Format date nicely if it exists
                if (meta.field_value && meta.field_value.trim() !== '') {
                    const date = new Date(meta.field_value);
                    if (!isNaN(date.getTime())) {
                        displayValue = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    }
                }
            } else if (meta.field_type === 'tags') {
                // Handle comma-separated tags
                if (meta.field_value && meta.field_value.trim() !== '') {
                    const tags = meta.field_value.split(',').map(t => t.trim()).filter(Boolean);
                    if (tags.length > 0) {
                        const tagsHtml = tags.map(v =>
                            `<span class="public-modal-keyword-pill">${escapeHtml(v)}</span>`
                        ).join(' ');
                        row.innerHTML = `
                            <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(meta.field_label)}</span>
                            <div class="public-modal-keywords-wrap">${tagsHtml}</div>
                        `;
                        lastInserted.after(row);
                        lastInserted = row;
                        return;
                    }
                }
            } else if (meta.field_type === 'checkbox') {
                // Handle checkbox (tags)
                try {
                    const values = JSON.parse(meta.field_value);
                    if (Array.isArray(values)) {
                        const tagsHtml = values.map(v =>
                            `<span class="badge rounded-pill bg-light text-dark border" style="font-weight: 500;">${escapeHtml(v)}</span>`
                        ).join(' ');
                        row.innerHTML = `
                            <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(meta.field_label)}</span>
                            <div class="public-modal-keywords-wrap">${tagsHtml}</div>
                        `;
                        lastInserted.after(row);
                        lastInserted = row;
                        return;
                    }
                } catch (e) {
                    // If parsing fails, treat as regular text
                }
            }

            // Formatting for empty values
            if (displayValue === null || displayValue === undefined || displayValue === '') {
                displayValue = '—'; // default fallback for empty
            }

            // Regular field rendering
            row.innerHTML = `
                <span class="public-modal-meta-label"><i class="bi ${icon}"></i> ${escapeHtml(meta.field_label)}</span>
                <span class="public-modal-meta-value">${escapeHtml(displayValue)}</span>
            `;

            lastInserted.after(row);
            lastInserted = row;
        });

        // Add format field (always shown) at the end
        const formatRow = document.createElement('div');
        formatRow.className = 'public-modal-meta-row';
        const formatValue = isBulk ? 'IMAGES' : (format || 'PDF');
        const formatBg = isBulk ? '#E3F2FD' : '#FFEBEE';
        const formatColor = isBulk ? '#1976D2' : '#D32F2F';
        formatRow.innerHTML = `
            <span class="public-modal-meta-label"><i class="bi bi-file-earmark"></i> Format</span>
            <span class="public-format-badge" style="background: ${formatBg}; color: ${formatColor};">${formatValue}</span>
        `;
        lastInserted.after(formatRow);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Show delete confirmation (handles modal nesting)
    window.showDeleteConfirmation = function () {
        // Close the preview modal first
        const previewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('filePreviewModal'));
        if (previewModal) {
            previewModal.hide();
        }

        // Wait for preview modal to close, then show delete modal
        setTimeout(() => {
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }, 300);
    }

    // Global delete function for inline onclick handlers
    window.deleteFile = function (fileId) {
        currentFileId = fileId;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }

    // Delete confirmation handler (AJAX)
    document.getElementById('confirmDeleteBtn')?.addEventListener('click', function () {
        if (currentFileId) {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

            // Send AJAX request
            const formData = new FormData();
            formData.append('action', 'move_to_trash');
            formData.append('item_id', currentFileId);

            // CHANGED URL TO NEW API ENDPOINT
            fetch(APP_URL + '/backend/api/dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 1. Hide Confirm Modal
                        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                        if (confirmModal) confirmModal.hide();

                        // 2. Show Success Modal, then auto-close after 2s
                        const successModalEl = document.getElementById('deleteSuccessModal');
                        const successModal = new bootstrap.Modal(successModalEl);
                        successModal.show();
                        setTimeout(() => {
                            successModal.hide();
                        }, 2000);

                        // 3. Remove item from grid
                        if (currentCardElement) {
                            currentCardElement.remove();
                        } else {
                            // Fallback: find and remove by file ID
                            const cardToRemove = document.querySelector(`.dashboard-file-card[data-id="${currentFileId}"]`);
                            if (cardToRemove) {
                                const colWrapper = cardToRemove.closest('.col-md-6, .col-lg-3');
                                if (colWrapper) colWrapper.remove();
                            }
                        }

                        // 4. Show empty state if no cards remain
                        showEmptyStateIfNeeded();

                        // 5. Refresh stat cards live
                        refreshStats();
                    } else {
                        alert('Error deleting item: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }
    });

    // Show upload success modal if redirected from upload
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'upload') {
        const uploadModalEl = document.getElementById('uploadSuccessModal');
        if (uploadModalEl) {
            const modal = new bootstrap.Modal(uploadModalEl);
            modal.show();
            // Auto-close after 2.5s
            setTimeout(() => modal.hide(), 2500);
            // Refresh stat cards
            refreshStats();
            // Clean up only the one-time success flag.
            urlParams.delete('success');
            const cleanQuery = urlParams.toString();
            history.replaceState({}, document.title, window.location.pathname + (cleanQuery ? '?' + cleanQuery : ''));
        }
    }

    // Clear search function
    window.clearSearch = function () {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            window.location.href = APP_URL + '/dashboard';
        }
    }

    // Dashboard chart rendering
    const uploadChartCanvas = document.getElementById('uploadSummaryChart');
    const viewsChartCanvas = document.getElementById('viewsTrendChart');
    const uploadRangeSelect = document.getElementById('uploadChartRange');
    const uploadTypeSelect = document.getElementById('uploadChartType');
    const viewsRangeSelect = document.getElementById('viewsChartRange');
    let uploadChartData = null;
    let viewsChartData = null;
    let uploadChartHits = [];
    let viewsChartHits = [];
    let chartTooltip = null;

    function sumValues(values) {
        return values.reduce((total, value) => total + Number(value || 0), 0);
    }

    function setupCanvas(canvas) {
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        const width = Math.max(320, Math.floor(rect.width || canvas.parentElement.clientWidth || 600));
        const height = Math.max(220, Number(canvas.getAttribute('height')) || 240);
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        return { ctx, width, height };
    }

    function drawGrid(ctx, plot, maxValue) {
        ctx.strokeStyle = '#EEF2F7';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#94A3B8';
        ctx.font = '11px Poppins, sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';

        for (let i = 0; i <= 4; i++) {
            const y = plot.bottom - (plot.height * i / 4);
            const value = Math.round(maxValue * i / 4);
            ctx.beginPath();
            ctx.moveTo(plot.left, y);
            ctx.lineTo(plot.right, y);
            ctx.stroke();
            ctx.fillText(value.toLocaleString(), plot.left - 10, y);
        }
    }

    function drawXAxisLabels(ctx, labels, plot) {
        const count = labels.length;
        const skip = count > 16 ? Math.ceil(count / 8) : count > 8 ? 2 : 1;
        ctx.fillStyle = '#64748B';
        ctx.font = '11px Poppins, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        labels.forEach((label, index) => {
            if (index % skip !== 0 && index !== count - 1) return;
            const x = plot.left + (count <= 1 ? plot.width / 2 : (plot.width * index / (count - 1)));
            ctx.fillText(label, x, plot.bottom + 14);
        });
    }

    function updateLegend(elementId, series) {
        const legend = document.getElementById(elementId);
        if (!legend) return;
        legend.innerHTML = series.map(item => `
            <span class="dashboard-chart-legend-item">
                <span class="dashboard-chart-legend-dot" style="background:${item.color}"></span>
                ${escapeHtml(item.label)}
            </span>
        `).join('');
    }

    function setChartEmpty(elementId, show) {
        const empty = document.getElementById(elementId);
        if (empty) empty.style.display = show ? 'flex' : 'none';
    }

    function getChartTooltip() {
        if (chartTooltip) return chartTooltip;
        chartTooltip = document.createElement('div');
        chartTooltip.className = 'dashboard-chart-tooltip';
        chartTooltip.style.display = 'none';
        document.body.appendChild(chartTooltip);
        return chartTooltip;
    }

    function showChartTooltip(canvas, event, hit) {
        const tooltip = getChartTooltip();
        const rows = hit.rows
            .filter(row => Number(row.value || 0) > 0 || hit.kind === 'views')
            .map(row => `
                <div class="dashboard-chart-tooltip-row">
                    <span class="dashboard-chart-tooltip-dot" style="background:${row.color}"></span>
                    <span>${escapeHtml(row.label)}</span>
                    <strong>${Number(row.value || 0).toLocaleString()}</strong>
                </div>
            `).join('');

        tooltip.innerHTML = `
            <div class="dashboard-chart-tooltip-title">${escapeHtml(hit.label)}</div>
            ${rows || '<div class="dashboard-chart-tooltip-row"><span>No data</span><strong>0</strong></div>'}
            ${hit.total !== undefined ? `<div class="dashboard-chart-tooltip-total">Total: ${Number(hit.total || 0).toLocaleString()}</div>` : ''}
        `;

        const offset = 14;
        tooltip.style.display = 'block';
        tooltip.style.left = event.clientX + offset + 'px';
        tooltip.style.top = event.clientY + offset + 'px';

        const tooltipRect = tooltip.getBoundingClientRect();
        if (tooltipRect.right > window.innerWidth - 12) {
            tooltip.style.left = event.clientX - tooltipRect.width - offset + 'px';
        }
        if (tooltipRect.bottom > window.innerHeight - 12) {
            tooltip.style.top = event.clientY - tooltipRect.height - offset + 'px';
        }

        canvas.style.cursor = 'pointer';
    }

    function hideChartTooltip(canvas) {
        if (chartTooltip) chartTooltip.style.display = 'none';
        if (canvas) canvas.style.cursor = '';
    }

    function getCanvasPointer(canvas, event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    }

    function bindChartHover(canvas, getHits) {
        if (!canvas || canvas.dataset.hoverBound === '1') return;
        canvas.dataset.hoverBound = '1';

        canvas.addEventListener('mousemove', function (event) {
            const pointer = getCanvasPointer(canvas, event);
            const hit = getHits().find(region =>
                pointer.x >= region.left &&
                pointer.x <= region.right &&
                pointer.y >= region.top &&
                pointer.y <= region.bottom
            );

            if (hit) {
                showChartTooltip(canvas, event, hit);
            } else {
                hideChartTooltip(canvas);
            }
        });

        canvas.addEventListener('mouseleave', function () {
            hideChartTooltip(canvas);
        });
    }

    function drawUploadChart(data) {
        if (!uploadChartCanvas || !data) return;
        const { ctx, width, height } = setupCanvas(uploadChartCanvas);
        ctx.clearRect(0, 0, width, height);
        uploadChartHits = [];

        const labels = data.labels || [];
        const series = data.series || [];
        const totals = labels.map((_, index) => series.reduce((sum, item) => sum + Number(item.values[index] || 0), 0));
        const maxValue = Math.max(1, ...totals);
        const hasData = totals.some(value => value > 0);
        setChartEmpty('uploadSummaryEmpty', !hasData);
        updateLegend('uploadSummaryLegend', series);

        const plot = { left: 52, right: width - 20, top: 18, bottom: height - 46 };
        plot.width = plot.right - plot.left;
        plot.height = plot.bottom - plot.top;

        drawGrid(ctx, plot, maxValue);
        if (!labels.length) return;

        const slot = plot.width / labels.length;
        const barWidth = Math.max(8, Math.min(34, slot * 0.58));

        labels.forEach((_, index) => {
            const x = plot.left + slot * index + slot / 2 - barWidth / 2;
            let yCursor = plot.bottom;
            const rows = [];
            series.forEach(item => {
                const value = Number(item.values[index] || 0);
                rows.push({
                    label: item.label,
                    value,
                    color: item.color,
                });
                if (value <= 0) return;
                const barHeight = Math.max(2, plot.height * value / maxValue);
                yCursor -= barHeight;
                ctx.fillStyle = item.color;
                ctx.fillRect(x, yCursor, barWidth, barHeight);
            });

            uploadChartHits.push({
                kind: 'uploads',
                label: labels[index],
                rows,
                total: sumValues(rows.map(row => row.value)),
                left: x - Math.max(5, slot * 0.12),
                right: x + barWidth + Math.max(5, slot * 0.12),
                top: plot.top,
                bottom: plot.bottom,
            });
        });

        drawXAxisLabels(ctx, labels, plot);
    }

    function drawViewsChart(data) {
        if (!viewsChartCanvas || !data) return;
        const { ctx, width, height } = setupCanvas(viewsChartCanvas);
        ctx.clearRect(0, 0, width, height);
        viewsChartHits = [];

        const labels = data.labels || [];
        const series = (data.series || [])[0] || { values: [], color: '#3A9AFF', label: 'Views' };
        const values = series.values || [];
        const maxValue = Math.max(1, ...values.map(value => Number(value || 0)));
        const hasData = values.some(value => Number(value || 0) > 0);
        setChartEmpty('viewsTrendEmpty', !hasData);
        updateLegend('viewsTrendLegend', [series]);

        const plot = { left: 52, right: width - 22, top: 20, bottom: height - 46 };
        plot.width = plot.right - plot.left;
        plot.height = plot.bottom - plot.top;

        drawGrid(ctx, plot, maxValue);
        if (!labels.length) return;

        const points = labels.map((_, index) => {
            const x = plot.left + (labels.length <= 1 ? plot.width / 2 : (plot.width * index / (labels.length - 1)));
            const y = plot.bottom - (plot.height * Number(values[index] || 0) / maxValue);
            return { x, y, value: Number(values[index] || 0) };
        });

        ctx.strokeStyle = series.color || '#3A9AFF';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.beginPath();
        points.forEach((point, index) => {
            if (index === 0) ctx.moveTo(point.x, point.y);
            else ctx.lineTo(point.x, point.y);
        });
        ctx.stroke();

        points.forEach(point => {
            ctx.fillStyle = '#FFFFFF';
            ctx.strokeStyle = series.color || '#3A9AFF';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        });

        points.forEach((point, index) => {
            viewsChartHits.push({
                kind: 'views',
                label: labels[index],
                rows: [{
                    label: series.label || 'Views',
                    value: point.value,
                    color: series.color || '#3A9AFF',
                }],
                left: point.x - 12,
                right: point.x + 12,
                top: point.y - 18,
                bottom: point.y + 18,
            });
        });

        drawXAxisLabels(ctx, labels, plot);
    }

    function loadUploadChart() {
        if (!uploadChartCanvas) return;
        const range = uploadRangeSelect ? uploadRangeSelect.value : '30';
        const fileType = uploadTypeSelect ? uploadTypeSelect.value : 'all';
        fetch(APP_URL + `/backend/api/dashboard.php?action=charts&range=${encodeURIComponent(range)}&file_type=${encodeURIComponent(fileType)}`, { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (!data.success) return;
                uploadChartData = data.uploads;
                drawUploadChart(uploadChartData);
            })
            .catch(error => console.error('Error loading upload chart:', error));
    }

    function loadViewsChart() {
        if (!viewsChartCanvas) return;
        const range = viewsRangeSelect ? viewsRangeSelect.value : '30';
        fetch(APP_URL + `/backend/api/dashboard.php?action=charts&range=${encodeURIComponent(range)}&file_type=all`, { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (!data.success) return;
                viewsChartData = data.views;
                drawViewsChart(viewsChartData);
            })
            .catch(error => console.error('Error loading views chart:', error));
    }

    if (uploadChartCanvas) {
        bindChartHover(uploadChartCanvas, () => uploadChartHits);
        loadUploadChart();
        uploadRangeSelect?.addEventListener('change', loadUploadChart);
        uploadTypeSelect?.addEventListener('change', loadUploadChart);
    }

    if (viewsChartCanvas) {
        bindChartHover(viewsChartCanvas, () => viewsChartHits);
        loadViewsChart();
        viewsRangeSelect?.addEventListener('change', loadViewsChart);
    }

    window.addEventListener('resize', function () {
        if (uploadChartData) drawUploadChart(uploadChartData);
        if (viewsChartData) drawViewsChart(viewsChartData);
    });

    // Real-time Date and Time Logic
    function updateDateTime() {
        const now = new Date();

        // Format Date: Monday, 21 October 2024
        const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const formattedDate = now.toLocaleDateString('en-US', dateOptions);

        // Format Time: 14:32:05 PM
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const formattedTime = now.toLocaleTimeString('en-US', timeOptions);

        const dateEl = document.getElementById('currentDate');
        const timeEl = document.getElementById('currentTime');

        if (dateEl) dateEl.textContent = formattedDate;
        if (timeEl) timeEl.textContent = formattedTime;
    }

    // Initial call
    updateDateTime();

    // Update every second
    setInterval(updateDateTime, 1000);

    // Live Search with Automatic Reset
    const searchForm = document.getElementById('searchFilterForm');
    const searchInput = document.querySelector('input[name="q"]');

    if (searchForm && searchInput) {
        let searchTimeout = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const currentUrl = new URL(window.location.href);
                const hasSearchQuery = currentUrl.searchParams.has('q');

                if (this.value.trim() === '') {
                    // Reset search but stay on the current page (dashboard or collections)
                    if (hasSearchQuery) {
                        currentUrl.searchParams.delete('q');
                        window.location.href = currentUrl.pathname + currentUrl.search;
                    }
                } else {
                    // Live search query length condition (optional, e.g., > 1 char)
                    if (this.value.trim().length >= 2 || !hasSearchQuery) {
                        searchForm.submit();
                    }
                }
            }, 600); // 600ms debounce
        });
    }

    // --- Bulk Delete Logic ---
    const selectAllCheck = document.getElementById('dashboardSelectAll');
    const bulkDeleteBtn = document.getElementById('dashboardBulkDeleteBtn');

    function updateBulkDeleteBtn() {
        if (!bulkDeleteBtn) return;
        const checkedCount = document.querySelectorAll('.dashboard-item-checkbox:checked').length;
        const anyChecked = checkedCount > 0;

        // Update bulk delete button visibility
        if (anyChecked) {
            bulkDeleteBtn.classList.remove('d-none');
        } else {
            bulkDeleteBtn.classList.add('d-none');
        }

        // Update selected files count badge
        const selectedBadge = document.getElementById('selectedFilesCount');
        const selectedCountSpan = document.getElementById('selectedCount');
        if (selectedBadge && selectedCountSpan) {
            if (anyChecked) {
                selectedCountSpan.textContent = checkedCount;
                selectedBadge.style.display = 'inline-flex';
            } else {
                selectedBadge.style.display = 'none';
            }
        }
    }

    if (selectAllCheck) {
        selectAllCheck.addEventListener('change', function () {
            const isChecked = this.checked;
            const currentItemCheckboxes = document.querySelectorAll('.dashboard-item-checkbox');
            currentItemCheckboxes.forEach(cb => {
                cb.checked = isChecked;
            });
            updateBulkDeleteBtn();
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('dashboard-item-checkbox')) {
            if (selectAllCheck) {
                const currentItemCheckboxes = document.querySelectorAll('.dashboard-item-checkbox');
                const total = currentItemCheckboxes.length;
                const checked = document.querySelectorAll('.dashboard-item-checkbox:checked').length;
                selectAllCheck.checked = (total > 0 && checked === total);
            }
            updateBulkDeleteBtn();
        }
    });

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function () {
            const checkedBoxes = document.querySelectorAll('.dashboard-item-checkbox:checked');
            if (checkedBoxes.length === 0) return;

            const msgEl = document.getElementById('bulkDeleteMessage');
            if (msgEl) msgEl.textContent = `Are you sure you want to move ${checkedBoxes.length} items to trash? This action can be undone from the Trash page.`;

            const bulkModal = new bootstrap.Modal(document.getElementById('bulkDeleteConfirmModal'));
            bulkModal.show();
        });
    }

    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDeleteBtn');
    if (confirmBulkDeleteBtn) {
        confirmBulkDeleteBtn.addEventListener('click', function () {
            const checkedBoxes = document.querySelectorAll('.dashboard-item-checkbox:checked');
            if (checkedBoxes.length === 0) return;

            const originalText = confirmBulkDeleteBtn.innerHTML;
            confirmBulkDeleteBtn.disabled = true;
            confirmBulkDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

            const formData = new FormData();
            formData.append('action', 'move_to_trash');
            checkedBoxes.forEach(cb => {
                formData.append('item_ids[]', cb.value);
            });

            fetch(APP_URL + '/backend/api/dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hide Confirm Modal
                        const bulkModalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteConfirmModal'));
                        if (bulkModalInstance) bulkModalInstance.hide();

                        // Show Success Modal
                        const successModalEl = document.getElementById('deleteSuccessModal');
                        if (successModalEl) {
                            const successModal = new bootstrap.Modal(successModalEl);
                            successModal.show();
                            setTimeout(() => successModal.hide(), 2000);
                        }

                        // Remove checked cards
                        checkedBoxes.forEach(cb => {
                            const card = cb.closest('.col-md-6, .col-lg-3');
                            if (card) card.remove();
                        });

                        // Show empty state if no cards remain
                        showEmptyStateIfNeeded();

                        // Reset select all button
                        if (selectAllCheck) selectAllCheck.checked = false;
                        updateBulkDeleteBtn();
                        refreshStats();
                    } else {
                        alert('Error deleting items: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting.');
                })
                .finally(() => {
                    confirmBulkDeleteBtn.disabled = false;
                    confirmBulkDeleteBtn.innerHTML = originalText;
                });
        });
    }
    // --- Refresh Rankings Logic ---
    const refreshRankingBtn = document.getElementById('refreshRankingBtn');
    const topReadsList = document.getElementById('topReadsList');
    const mostReadPeriod = document.getElementById('mostReadPeriod');

    function refreshRankings(period = 'all') {
        if (!topReadsList) return;

        // Visual feedback
        if (refreshRankingBtn) {
            refreshRankingBtn.disabled = true;
            refreshRankingBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> REFRESHING...';
        }
        topReadsList.style.opacity = '0.5';

        fetch(APP_URL + `/backend/api/dashboard.php?action=refresh_rankings&period=${period}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    // Render new ranking list
                    topReadsList.innerHTML = '';

                    if (data.data.length === 0) {
                        topReadsList.innerHTML = '<div class="text-center py-4 text-muted small">No view data available for this period.</div>';
                    } else {
                        data.data.forEach((tr, idx) => {
                            const rank = idx + 1;
                            const isTop = (rank === 1);

                            // Extract metadata for modal quick-preview
                            const getMetaValue = (labels) => {
                                if (!tr.custom_metadata) return '';
                                const targetLabels = Array.isArray(labels) ? labels.map(l => l.toLowerCase()) : [labels.toLowerCase()];
                                const found = tr.custom_metadata.find(m => targetLabels.includes((m.field_label || '').toLowerCase()));
                                return found ? found.field_value : '';
                            };

                            const category = getMetaValue('Category') || 'Uncategorized';
                            const description = getMetaValue(['Description', 'Summary', 'About']);
                            const isBulk = tr.is_bulk == 1;
                            const imagePaths = tr.image_paths ? JSON.parse(tr.image_paths) : [];
                            const fileUrl = tr.file_path ? `${APP_URL}/${tr.file_path}` : '';
                            const thumbnail = tr.thumbnail_path ? `${APP_URL}/${tr.thumbnail_path}` : '';

                            const item = document.createElement('div');
                            item.className = `most-read-item dashboard-file-card ${isTop ? 'top-rank' : ''}`;
                            item.style.animationDelay = `${idx * 0.1}s`;

                            // Set data attributes for the modal handler
                            item.dataset.id = tr.id;      // raw numeric (admin reader accepts both)
                            item.dataset.rawId = tr.id;   // explicit raw numeric for delete/edit APIs
                            item.dataset.title = tr.title;
                            item.dataset.thumbnail = thumbnail;
                            item.dataset.file = fileUrl;
                            item.dataset.category = category;
                            item.dataset.description = description;
                            item.dataset.format = (tr.file_type || 'pdf').toLowerCase();
                            item.dataset.isBulk = isBulk ? '1' : '0';
                            item.dataset.imagePaths = JSON.stringify(imagePaths);

                            item.innerHTML = `
                                <div class="rank-badge ${isTop ? 'bg-primary' : 'bg-light text-muted'}">
                                    ${rank}
                                </div>
                                <div class="most-read-thumb">
                                    ${tr.thumbnail_path
                                    ? `<img src="${thumbnail}" alt="Thumbnail">`
                                    : `<div class="most-read-thumb-placeholder"><i class="bi bi-image"></i></div>`
                                }
                                </div>
                                <div class="most-read-content">
                                    <h6 class="most-read-item-title">${escapeHtml(tr.title)}</h6>
                                    <div class="most-read-views">
                                        <i class="bi bi-eye"></i>
                                        <span>${Number(tr.view_count).toLocaleString()} views</span>
                                    </div>
                                </div>
                            `;

                            topReadsList.appendChild(item);
                        });
                    }
                }
            })
            .catch(error => console.error('Error refreshing rankings:', error))
            .finally(() => {
                topReadsList.style.opacity = '1';
                if (refreshRankingBtn) {
                    refreshRankingBtn.disabled = false;
                    refreshRankingBtn.innerHTML = 'REFRESH RANKING';
                }
            });
    }

    if (refreshRankingBtn) {
        refreshRankingBtn.addEventListener('click', () => {
            const period = mostReadPeriod ? mostReadPeriod.value : 'all';
            refreshRankings(period);
        });
    }

    if (mostReadPeriod) {
        mostReadPeriod.addEventListener('change', (e) => {
            refreshRankings(e.target.value);
        });
    }
});

// --- Pagination Logic for Recent Uploads ---
let currentRecentPage = 1;

window.changeRecentPage = function (event, targetPage) {
    if (event) event.preventDefault();

    const items = document.querySelectorAll('.recent-upload-item');
    const totalPages = Math.ceil(items.length / 8);

    if (targetPage === 'prev') {
        if (currentRecentPage > 1) targetPage = currentRecentPage - 1;
        else return;
    } else if (targetPage === 'next') {
        if (currentRecentPage < totalPages) targetPage = currentRecentPage + 1;
        else return;
    }

    // Now targetPage is a number
    if (typeof targetPage !== 'number') return;
    currentRecentPage = targetPage;

    // Show/Hide items based on page
    items.forEach(item => {
        if (item.classList.contains(`page-${currentRecentPage}`)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });

    // Update pagination UI
    const paginationItems = document.querySelectorAll('#recentUploadsPagination .page-item');
    if (paginationItems.length > 0) {
        // Update Prev button
        const prevBtn = paginationItems[0];
        if (currentRecentPage === 1) prevBtn.classList.add('disabled');
        else prevBtn.classList.remove('disabled');

        // Update Next button
        const nextBtn = paginationItems[paginationItems.length - 1];
        if (currentRecentPage === totalPages) nextBtn.classList.add('disabled');
        else nextBtn.classList.remove('disabled');

        // Update Number buttons (1 is at index 1, 2 at index 2, etc.)
        for (let i = 1; i <= totalPages; i++) {
            if (paginationItems[i]) {
                if (i === currentRecentPage) paginationItems[i].classList.add('active');
                else paginationItems[i].classList.remove('active');
            }
        }
    }
}

