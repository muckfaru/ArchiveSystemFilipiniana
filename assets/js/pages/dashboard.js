/**
 * Dashboard Page JavaScript
 * Archive System
 */

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
                        confirmModal.hide();

                        // 2. Show Success Modal
                        const successModal = new bootstrap.Modal(document.getElementById('deleteSuccessModal'));
                        successModal.show();

                        // 3. Remove item from grid
                        if (currentCardElement) {
                            currentCardElement.remove();
                        }
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
        const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
        if (modal) {
            modal.show();
            // Clean up URL
            history.replaceState({}, document.title, window.location.pathname);
        }
    }
});

