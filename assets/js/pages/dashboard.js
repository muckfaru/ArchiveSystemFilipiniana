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
            // Index order: Total Archives [0], Issues Count [1], Years Covered [2], Total Categories [3]
            if (vals[0]) vals[0].textContent = data.archives.toLocaleString();
            if (vals[1]) vals[1].textContent = data.issues.toLocaleString();
            if (vals[2]) vals[2].textContent = data.years;
            if (vals[3]) vals[3].textContent = data.categories.toLocaleString();
        })
        .catch(() => { }); // silently ignore network errors
}

document.addEventListener('DOMContentLoaded', function () {
    let currentFileId = null;
    let currentCardElement = null; // Store reference to the card element

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
    document.addEventListener('click', function(e) {
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

            // Get data from card
            const title = card.dataset.title;
            const thumbnail = card.dataset.thumbnail;
            const date = card.dataset.date;
            const edition = card.dataset.edition;
            const pages = card.dataset.pages;
            const format = card.dataset.format;
            const uploader = card.dataset.uploader;
            const tags = card.dataset.tags;
            const file = card.dataset.file;
            const id = card.dataset.id;
            const publisher = card.dataset.publisher; // Add publisher
            const description = card.dataset.description || '';
            const isBulk = card.dataset.isBulk === '1';
            const imagePaths = card.dataset.imagePaths ? JSON.parse(card.dataset.imagePaths) : [];

            // Store current file ID for delete
            currentFileId = id;

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
                    readNowBtn.href = APP_URL + `/pages/reader.php?id=${id}`;
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
                        readNowBtn.href = APP_URL + `/pages/reader.php?id=${id}`;
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

            // Update Metadata
            const dateEl = document.getElementById('metaDate');
            if (dateEl) dateEl.textContent = date || 'N/A';

            const editionEl = document.getElementById('metaEdition');
            if (editionEl) editionEl.textContent = edition || 'Standard';

            const pagesEl = document.getElementById('metaPages');
            if (pagesEl) pagesEl.textContent = pages ? pages + ' Pages' : 'N/A';

            const formatEl = document.getElementById('metaFormat');
            if (formatEl) {
                formatEl.textContent = isBulk ? 'IMAGES' : (format || 'PDF');
                formatEl.style.background = isBulk ? '#E3F2FD' : '#FFEBEE';
                formatEl.style.color = isBulk ? '#1976D2' : '#D32F2F';
            }

            const uploaderEl = document.getElementById('metaUploader');
            if (uploaderEl) uploaderEl.textContent = uploader || 'Admin';

            // Update Publisher
            const publisherEl = document.getElementById('metaPublisher');
            if (publisherEl) publisherEl.textContent = publisher || 'N/A';

            // Update Title
            const titleEl = document.getElementById('previewTitle');
            if (titleEl) titleEl.textContent = title || 'File Preview';

            // Update Category subtitle
            const categoryEl = document.getElementById('previewCategory');
            const category = card.dataset.category;
            if (categoryEl) {
                categoryEl.textContent = (category || 'UNCATEGORIZED').toUpperCase();
                // Reset classes and add the dynamic one from dataset
                categoryEl.className = 'newspaper-category mb-0 ' + (category ? category.toLowerCase() : '');
            }

            // Update Description
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

            // Update Tags
            const tagsContainer = document.getElementById('metaTags');
            if (tagsContainer) {
                tagsContainer.innerHTML = '';
                if (tags) {
                    const tagList = tags.split(',').filter(t => t.trim());
                    tagList.forEach(tag => {
                        const tagEl = document.createElement('span');
                        tagEl.className = 'badge rounded-pill bg-light text-dark border';
                        tagEl.style.fontWeight = '500';
                        tagEl.textContent = tag.trim();
                        tagsContainer.appendChild(tagEl);
                    });
                }
                if (tagsContainer.children.length === 0) {
                    tagsContainer.innerHTML = '<span class="text-muted small">No tags</span>';
                }
            }

            // Update Links
            if (!isBulk && readNowBtn) readNowBtn.href = APP_URL + '/pages/reader.php?id=' + id;

            const editBtn = document.getElementById('editBtn');
            if (editBtn) editBtn.href = APP_URL + '/pages/upload.php?edit=' + id;

            // Bind delete button
            const deleteBtn = document.getElementById('deleteBtn');
            if (deleteBtn) {
                deleteBtn.onclick = function () {
                    showDeleteConfirmation();
                };
            }
        });

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
    window.deleteFile = function(fileId) {
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

                        // 4. Refresh stat cards live
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
            // Clean up URL
            history.replaceState({}, document.title, window.location.pathname);
        }
    }

    // Clear search function
    window.clearSearch = function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            window.location.href = APP_URL + '/dashboard.php';
        }
    }

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
});

