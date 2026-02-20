/**
 * Upload Page Logic
 * Archive System - Quezon City Public Library
 */

console.log('Upload script loaded');
console.log('APP_URL:', typeof APP_URL !== 'undefined' ? APP_URL : 'NOT DEFINED');

// --- Global State ---
let bulkFiles = []; // Array of objects
let isBulkMode = false;
let isBindMode = false; // "Bind as Book" mode (Bulk Images)
let activeFileIndex = -1; // Current tab index
let selectedFile = null;
let originalFormData = {}; // Store initial values for dirty checking
let coverFileId = null; // ID of the file selected as cover/thumbnail in Bind Mode

document.addEventListener('DOMContentLoaded', function () {
    // --- Elements ---
    const dropZone = document.getElementById('mainDropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const discardBtn = document.getElementById('discardBtn');
    const uploadForm = document.getElementById('uploadForm');
    const alertContainer = document.getElementById('alertContainer');

    // Bulk Elements
    const bulkFileTabs = document.getElementById('fileTabs');
    const bulkFileInput = document.getElementById('bulkFileInput');

    // Mode Toggles
    const modeIndividual = document.getElementById('modeIndividual');
    const modeBind = document.getElementById('modeBind');

    // Thumbnail Elements
    const thumbnailArea = document.getElementById('thumbnailArea');
    const thumbnailInput = document.getElementById('thumbnailInput');
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
    const removeThumbnailBtn = document.getElementById('removeThumbnailBtn');

    // --- Initialization ---
    const isEditMode = document.querySelector('input[name="action"]').value === 'edit';

    // Capture Original State if Edit Mode
    if (isEditMode) {
        captureOriginalState();
    }

    // --- Event Listeners ---

    // File Input Change
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelection);
    }

    // Drop Zone Events
    // Drop Zone Events
    const dropContainer = document.getElementById('dropZoneContainer');
    // We attach to container to ensure larger hit area, but visual cues on inner zone
    const dragTarget = dropContainer || dropZone;

    if (dragTarget) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dragTarget.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false); // Prevent browser default globally while dragging
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dragTarget.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dragTarget.addEventListener(eventName, unhighlight, false);
        });

        dragTarget.addEventListener('drop', handleDrop, false);
    }

    if (dropZone) {
        dropZone.addEventListener('click', (e) => {
            if (e.target !== fileInput) fileInput.click();
        });
    }

    // Bulk Input
    if (bulkFileInput) {
        bulkFileInput.addEventListener('change', handleBulkFileAdditionFromInput);
    }

    // Discard & Upload Buttons (Desktop & Mobile)
    const discardBtns = [document.getElementById('discardBtn'), document.getElementById('discardBtnMobile')];
    discardBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', (e) => {
            e.preventDefault();
            // Show Discard Modal instead of resetting immediately
            if (hasUnsavedChanges()) {
                const modalEl = document.getElementById('discardModal');
                let m = bootstrap.Modal.getInstance(modalEl);
                if (!m) m = new bootstrap.Modal(modalEl);
                m.show();
            } else {
                // If nothing to discard, just reset (or do nothing? usually reset implies clearing even empty state if dirty)
                // Actually if no changes, maybe just reset anyway to be sure.
                if (typeof window.resetForm === 'function') window.resetForm();
            }
        });
    });

    const uploadTriggerBtns = [document.getElementById('uploadBtnDesktop'), document.getElementById('uploadBtnMobile')];
    uploadTriggerBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', (e) => {
            e.preventDefault();

            // Perform Validation
            const title = document.getElementById('title')?.value?.trim();
            const date = document.getElementById('publication_date')?.value?.trim();
            const category = document.getElementById('category_id')?.value?.trim();
            const language = document.getElementById('language_id')?.value?.trim();

            const hasFile = (fileInput.files.length > 0 || bulkFiles.length > 0 || selectedFile !== null);
            const isEdit = document.querySelector('input[name="action"]').value === 'edit';

            // Validation Messages
            let errors = [];

            // Validation Helper
            const validate = (id) => {
                const el = document.getElementById(id);
                if (!el) return false;
                if (!el.value || el.value.trim() === '') {
                    el.classList.add('is-invalid');
                    return false;
                } else {
                    el.classList.remove('is-invalid');
                    return true;
                }
            };

            let isValid = true;
            if (!validate('title')) isValid = false;
            if (!validate('publication_date')) isValid = false;
            if (!validate('category_id')) isValid = false;
            if (!validate('language_id')) isValid = false;

            // File Validation
            if (!hasFile && !isEdit) {
                showAlert('danger', "Please select a file to upload.");
                btn.classList.add('shake');
                setTimeout(() => btn.classList.remove('shake'), 500);
                return;
            }

            if (!isValid) {
                // Shake validation feedback
                btn.classList.add('shake');
                setTimeout(() => btn.classList.remove('shake'), 500);
                return;
            }

            // If Valid, Show Confirmation Modal
            const m = new bootstrap.Modal(document.getElementById('confirmUploadModal'));
            m.show();
        });
    });

    // Form Field Event Listeners - Update buttons when metadata changes
    // Form Field Event Listeners - Update buttons when metadata changes
    const formFields = ['title', 'publisher', 'publication_date', 'edition', 'category_id', 'language_id'];
    formFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function () {
                // Clear invalid state on input
                this.classList.remove('is-invalid');
                if (activeFileIndex !== -1) {
                    saveCurrentFormData();
                } else {
                    updateButtons();
                }
            });
            // Also clear on change for selects
            field.addEventListener('change', function () {
                this.classList.remove('is-invalid');
                if (activeFileIndex !== -1) {
                    saveCurrentFormData();
                } else {
                    updateButtons();
                }
            });
        }
    });

    // Mode Toggle Logic
    if (modeIndividual && modeBind) {
        modeIndividual.addEventListener('change', () => {
            isBindMode = false;
            updateBulkUI();
        });
        modeBind.addEventListener('change', () => {
            isBindMode = true;
            updateBulkUI();
        });
    }

    // --- Unsaved Changes / Navigation Warning ---

    // 1. Helper to check for unsaved changes
    window.hasUnsavedChanges = function () {
        // Check files
        const hasFiles = (fileInput && fileInput.files.length > 0) || (bulkFiles && bulkFiles.length > 0);
        if (hasFiles) return true;

        const isEdit = document.querySelector('input[name="action"]').value === 'edit';

        if (isEdit) {
            // Compare current values with original
            const currentTitle = document.getElementById('title')?.value?.trim() || '';
            const currentPub = document.getElementById('publisher')?.value?.trim() || '';
            const currentDesc = document.getElementById('description')?.value?.trim() || '';
            const currentDate = document.getElementById('publication_date')?.value || '';
            const currentCat = document.getElementById('category_id')?.value || '';
            const currentLang = document.getElementById('language_id')?.value || '';
            const currentEd = document.getElementById('edition')?.value || '';
            // Add other fields as needed

            if (currentTitle !== (originalFormData.title || '')) return true;
            if (currentPub !== (originalFormData.publisher || '')) return true;
            if (currentDesc !== (originalFormData.description || '')) return true;
            if (currentDate !== (originalFormData.publication_date || '')) return true;
            if (currentCat != (originalFormData.category_id || '')) return true; // loose comparison for string/int
            if (currentLang != (originalFormData.language_id || '')) return true;
            if (currentEd !== (originalFormData.edition || '')) return true;

            return false;
        } else {
            // Upload Mode: Dirty if any text field has value
            const title = document.getElementById('title')?.value?.trim();
            const publisher = document.getElementById('publisher')?.value?.trim();
            const desc = document.getElementById('description')?.value?.trim();

            if (title || publisher || desc) return true;
            return false;
        }
    }

    function captureOriginalState() {
        originalFormData = {
            title: document.getElementById('title')?.value?.trim() || '',
            publisher: document.getElementById('publisher')?.value?.trim() || '',
            description: document.getElementById('description')?.value?.trim() || '',
            publication_date: document.getElementById('publication_date')?.value || '',
            category_id: document.getElementById('category_id')?.value || '',
            language_id: document.getElementById('language_id')?.value || '',
            edition: document.getElementById('edition')?.value || ''
        };
        console.log('Original state captured:', originalFormData);
    }

    // 2. Browser Native Warning (Reload / Close Tab)
    window.addEventListener('beforeunload', function (e) {
        if (window.hasUnsavedChanges()) {
            // Cancel the event
            e.preventDefault();
            // Chrome requires returnValue to be set
            e.returnValue = '';
        }
    });

    // 3. Internal Navigation Warning
    // We attach to all links that are not internal assignments or #
    document.body.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        // Ignore target="_blank", downloads, or null hrefs
        if (link.target === '_blank' || link.hasAttribute('download') || !link.href) return;

        // Ignore internal anchors
        const url = new URL(link.href, window.location.origin);
        if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.hash) return;

        // If it's a real navigation and we have unsaved changes
        if (window.hasUnsavedChanges()) {
            e.preventDefault();
            e.stopPropagation();

            // Show Custom Modal
            pendingNavigationUrl = link.href;
            const m = new bootstrap.Modal(document.getElementById('unsavedChangesModal'));
            m.show();
        }
    });

    // State for pending navigation
    let pendingNavigationUrl = null;

    // Handle "Leave" button click in modal
    const confirmLeaveBtn = document.getElementById('confirmLeaveBtn');
    if (confirmLeaveBtn) {
        confirmLeaveBtn.addEventListener('click', () => {
            if (pendingNavigationUrl) {
                // Skip check and navigate
                window.hasUnsavedChanges = () => false;
                window.location.href = pendingNavigationUrl;
            }
        });
    }

    // User confirmed leaving
    // Logic handled in click listener above.



    // --- Confirm Discard Action ---
    // --- Confirm Discard Action ---
    window.confirmDiscardAction = function () {
        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        const modalEl = document.getElementById('discardModal');

        // Try to get existing instance
        const modal = bootstrap.Modal.getInstance(modalEl);

        try {
            if (isEdit) {
                window.location.reload();
            } else {
                if (typeof window.resetForm === 'function') window.resetForm();
            }
        } catch (e) {
            console.error('Error in discard action:', e);
        }

        if (modal) {
            modal.hide();
        } else {
            // Fallback if no instance found but element exists (rare if opened via JS)
            const m = new bootstrap.Modal(modalEl);
            m.hide();
        }

        // Ensure backdrop is removed even if bootstrap fails
        setTimeout(() => {
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style = '';
        }, 300);
    }

    // Confirm Upload Modal Action
    const confirmUploadBtn = document.getElementById('confirmUploadBtn');
    if (confirmUploadBtn) {
        confirmUploadBtn.addEventListener('click', async function () {
            const modalEl = document.getElementById('confirmUploadModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            const originalText = confirmUploadBtn.innerText;

            // Show loading state
            confirmUploadBtn.disabled = true;
            confirmUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

            if (isBulkMode || isBindMode) {

                if (isBindMode) {
                    // Bind Mode: Upload all files as one Book entity
                    // We use the current form state (from active tab or just inputs) as the book metadata
                    const formData = new FormData(uploadForm);
                    formData.set('action', 'bulk_image_upload');

                    // Append all files
                    bulkFiles.forEach((f) => {
                        formData.append('files[]', f.file, f.name);
                    });

                    // Append Cover/Thumbnail if selected from grid
                    if (coverFileId) {
                        const coverFile = bulkFiles.find(f => f.id === coverFileId);
                        if (coverFile) {
                            formData.set('thumbnail', coverFile.file, 'cover.jpg');
                            console.log('📸 Setting thumbnail from grid:', coverFile.name);
                        }
                    }

                    if (bulkFiles.length === 0) {
                        showAlert('danger', 'No files to upload.');
                        resetBtn(confirmUploadBtn, originalText);
                        return;
                    }

                    await uploadData(formData, modal, confirmUploadBtn, originalText);

                } else {
                    // Individual Bulk Upload (NEW LOGIC)
                    const readyFiles = bulkFiles.filter(f => f.status === 'ready');

                    if (readyFiles.length === 0) {
                        showAlert('warning', 'No ready files to upload. Please fill in required fields for at least one file.');
                        resetBtn(confirmUploadBtn, originalText);
                        if (modal) modal.hide();
                        return;
                    }

                    let successCount = 0;
                    let errorCount = 0;

                    // Process each ready file
                    for (let i = 0; i < readyFiles.length; i++) {
                        const f = readyFiles[i];
                        confirmUploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Uploading ${i + 1}/${readyFiles.length}...`;

                        const singleFormData = new FormData();
                        singleFormData.append('action', 'upload');
                        singleFormData.append('file', f.file);

                        // Append Metadata manually
                        const m = f.metadata;
                        singleFormData.append('title', m.title || '');
                        if (m.publisher) singleFormData.append('publisher', m.publisher);
                        singleFormData.append('publication_date', m.publication_date || '');
                        if (m.edition) singleFormData.append('edition', m.edition);
                        singleFormData.append('category_id', m.category_id || '');
                        singleFormData.append('language_id', m.language_id || '');
                        if (m.page_count) singleFormData.append('page_count', m.page_count);
                        if (m.description) singleFormData.append('description', m.description);

                        // Note: Tags are handled via hidden input usually? 
                        // If tags are in m.tags array, we should format them.
                        // Assuming server expects 'tags' string or array.
                        // Existing code uses 'tags' input from form.
                        // We'll skip tags for now or append if we saved them in metadata.

                        try {
                            const res = await fetch(uploadForm.getAttribute('action'), {
                                method: 'POST',
                                body: singleFormData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });

                            if (res.ok) {
                                // Check JSON response if possible
                                try {
                                    const data = await res.json();
                                    if (data.success) successCount++;
                                    else errorCount++;
                                } catch (jsonErr) {
                                    // If plain HTML 200, count as success
                                    successCount++;
                                }
                            } else {
                                errorCount++;
                            }

                        } catch (e) {
                            console.error(e);
                            errorCount++;
                        }
                    }

                    // Result Handling
                    if (successCount > 0) {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard.php?success=upload&count=' + successCount;
                    } else {
                        showAlert('danger', `Upload failed. ${errorCount} errors.`);
                        resetBtn(confirmUploadBtn, originalText);
                        if (modal) modal.hide();
                    }
                }

            } else {
                // Check for Edit Mode
                const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';

                if (isEdit) {
                    // Edit Mode: Update existing record
                    const formData = new FormData(uploadForm);

                    // Append New File if selected (Optional upgrade)
                    if (fileInput.files.length > 0) {
                        formData.set('file', fileInput.files[0]);
                    }

                    // Append Thumbnail if selected (Optional upgrade)
                    if (thumbnailInput && thumbnailInput.files.length > 0) {
                        formData.set('thumbnail', thumbnailInput.files[0]);
                    }

                    await uploadData(formData, modal, confirmUploadBtn, originalText);

                } else {
                    // Single File Upload (New Record)
                    const formData = new FormData(uploadForm);

                    // File input is outside the form, so we must always attach the file manually
                    const fileToUpload = (fileInput.files.length > 0) ? fileInput.files[0] : selectedFile;
                    if (fileToUpload) {
                        formData.set('file', fileToUpload);
                    } else {
                        showAlert('danger', 'No file selected. Please select or drop a file first.');
                        resetBtn(confirmUploadBtn, originalText);
                        if (modal) modal.hide();
                        return;
                    }

                    // Thumbnail input is also outside the form — attach manually
                    if (thumbnailInput && thumbnailInput.files.length > 0) {
                        formData.set('thumbnail', thumbnailInput.files[0]);
                    }

                    await uploadData(formData, modal, confirmUploadBtn, originalText);
                }
            }
        });
    }


    function resetBtn(btn, text) {
        btn.disabled = false;
        btn.innerText = text;
    }

    async function uploadData(formData, modal, btn, text) {
        try {
            const actionUrl = uploadForm.getAttribute('action');
            console.log('🚀 Uploading to:', actionUrl);

            const response = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            // Check content type
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                const result = await response.json();
                if (result.success) {
                    const isEdit = document.querySelector('input[name="action"]').value === 'edit';
                    if (isEdit) {
                        showAlert('success', 'Changes saved successfully.');
                        resetBtn(btn, text);
                        if (modal) modal.hide();
                        if (typeof captureOriginalState === 'function') captureOriginalState();
                        // Also clear dirty state check just in case until next input
                        // But captureOriginalState handles it by updating reference.
                    } else {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard.php?success=upload';
                    }
                } else {
                    showAlert('danger', result.message || 'Upload failed');
                    resetBtn(btn, text);
                    if (modal) modal.hide();
                }
            } else {
                // Assumed HTML redirect or error
                if (response.ok) {
                    const isEdit = document.querySelector('input[name="action"]').value === 'edit';
                    if (isEdit) {
                        showAlert('success', 'Changes saved successfully.');
                        resetBtn(btn, text);
                        if (modal) modal.hide();
                        if (typeof captureOriginalState === 'function') captureOriginalState();
                    } else {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard.php?success=upload';
                    }
                } else {
                    throw new Error('Server returned ' + response.status);
                }
            }
        } catch (error) {
            console.error('Upload Error:', error);
            showAlert('danger', 'Upload Error: ' + error.message);
            resetBtn(btn, text);
            if (modal) modal.hide();
        }
    }


    // --- Thumbnail Handling ---

    // 1. Browse Button Click
    const browseBtn = document.getElementById('browseThumbnailBtn');
    if (browseBtn) {
        browseBtn.addEventListener('click', function (e) {
            e.preventDefault(); // Stop form submit or other default actions
            e.stopPropagation(); // Stop bubbling to area
            if (thumbnailInput) thumbnailInput.click();
        });
    }

    // 2. Thumbnail Area Click (Delegation)
    if (thumbnailArea && thumbnailInput) {
        thumbnailArea.addEventListener('click', function (e) {
            // Check if we clicked the remove button or its children
            if (e.target.closest('#removeThumbnailBtn')) return;

            // Check if we clicked the browse button 
            if (e.target.closest('#browseThumbnailBtn')) {
                // Let the browse button listener handle it
                return;
            }

            // Trigger the input regardless of whether image is shown or not
            thumbnailInput.click();
        });
    }

    // 2b. Remove Thumbnail Button
    if (removeThumbnailBtn) {
        removeThumbnailBtn.addEventListener('click', function (e) {
            if (typeof window.removeThumbnail === 'function') {
                window.removeThumbnail(e);
            }
        });
    }

    // 3. File Input Change (The actual upload)
    if (thumbnailInput) {
        thumbnailInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            console.log('📸 Thumbnail selected:', file.name);

            // Save to Bulk File State logic
            if (isBulkMode && activeFileIndex !== -1 && bulkFiles[activeFileIndex]) {
                bulkFiles[activeFileIndex].customThumbnail = file;
            }

            const reader = new FileReader();
            reader.onload = function (readerEvent) {
                // Update Image
                if (thumbnailPreview) {
                    thumbnailPreview.src = readerEvent.target.result;
                    thumbnailPreview.style.display = 'block';
                }

                // Hide Placeholder
                if (thumbnailPlaceholder) {
                    thumbnailPlaceholder.style.display = 'none';
                }

                // Show Remove Button
                if (removeThumbnailBtn) {
                    removeThumbnailBtn.style.display = 'flex';
                }
            };
            reader.onerror = function (err) {
                console.error('❌ Error reading file:', err);
            };
            reader.readAsDataURL(file);
        });
    }

    // 4. Global Remove Function
    window.removeThumbnail = function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        console.log('🗑️ Removing thumbnail...');

        // Clear cover state
        coverFileId = null;

        // Clear Bulk State
        if (isBulkMode && activeFileIndex !== -1 && bulkFiles[activeFileIndex]) {
            bulkFiles[activeFileIndex].customThumbnail = null;
        }

        // Clear Input
        if (thumbnailInput) thumbnailInput.value = '';

        // Reset Image
        if (thumbnailPreview) {
            thumbnailPreview.src = '#';
            thumbnailPreview.style.display = 'none';
        }

        // Show Placeholder
        if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'flex';

        // Hide Remove Button
        if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'none';

        // Try to restore main file preview if available
        const mainFile = (activeFileIndex !== -1 && bulkFiles[activeFileIndex]) ? bulkFiles[activeFileIndex].file : selectedFile;
        // Only load main file preview if no custom thumbnail is set (which we just cleared)
        // AND if the main file is an image
        if (mainFile && mainFile.type.startsWith('image/')) {
            updateMainPreview(mainFile);
        }
    };

    function updateMainPreview(file) {
        if (!file || !file.type.startsWith('image/')) return;

        // Only update if thumbnail input is empty (User hasn't explicitly chosen a cover)
        if (thumbnailInput && thumbnailInput.files.length > 0) return;

        // Check if we already have an overriding thumbnail in edit mode?
        // We'll assume input check is enough for new uploads. For edit mode, we might have existing thumbnail_path.
        // If edit mode and has thumbnail_path, we shouldn't override unless user cleared it.
        // DOM check:
        if (thumbnailPreview && thumbnailPreview.src && !thumbnailPreview.src.endsWith('#') && !thumbnailPreview.src.startsWith('blob:')) {
            // Existing server image?
            // Actually thumbnailPreview.src might be full URL.
            // If it's valid, don't override.
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            if (thumbnailPreview) {
                thumbnailPreview.src = e.target.result;
                thumbnailPreview.style.display = 'block';
            }
            if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'none';
            // Do NOT show remove button for Main File Preview (as it clears the main file?)
            // Or maybe show it but it acts as "Clear Preview"? 
            // Better to not show remove button for "Implicit" preview, only for "Explicit" thumbnail.
            if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    /* Ensure remove button is correctly positioned and visible */
    const style = document.createElement('style');
    style.textContent = `
    #removeThumbnailBtn {
        z-index: 100; /* Increased z-index */
        cursor: pointer;
        display: none; /* Hidden by default */
        align-items: center;
        justify-content: center;
    }
`;
    document.head.appendChild(style);

    // --- Helper Functions ---

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropZone.classList.add('drag-active'); // Add a class for CSS styling
        // dropZone.style.backgound = ... (Optional if not using CSS class)
    }

    function unhighlight(e) {
        dropZone.classList.remove('drag-active');
    }

    function highlightBulk(e) {
        bulkStatsBar.classList.add('drag-active');
        bulkStatsBar.style.backgroundColor = '#f0f0f0';
    }
    function unhighlightBulk(e) {
        bulkStatsBar.classList.remove('drag-active');
        bulkStatsBar.style.backgroundColor = '#fafafa';
    }


    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    function handleBulkDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        addBulkFiles(files);
    }

    function handleFileSelection(e) {
        const files = e.target.files;
        handleFiles(files);
    }

    function handleBulkFileAdditionFromInput(e) {
        addBulkFiles(e.target.files);
    }

    function handleFiles(files) {
        if (files.length === 0) return;

        // If multiple files selected or already in bulk mode -> Go Bulk
        if (files.length > 1 || bulkFiles.length > 0) {
            // Check types for Mode Detection IF starting fresh
            if (bulkFiles.length === 0) {
                const firstFile = files[0];
                if (firstFile.type.startsWith('image/')) {
                    isBindMode = true; // Images -> Bind/Grid Mode
                    isBulkMode = true; // Still bulk
                } else {
                    isBindMode = false; // Documents -> Tab Mode
                    isBulkMode = true;
                }
            }
            enableBulkMode();
            addBulkFiles(files); // Reuse bulk logic
        } else {
            // Single File
            const file = files[0];
            selectedFile = file;

            // Update Selected File Preview UI
            const selectedFilePreview = document.getElementById('selectedFilePreview');
            const previewFilename = document.getElementById('previewFilename');
            const previewSize = document.getElementById('previewSize');
            const dropZoneContainer = document.getElementById('dropZoneContainer');

            console.log('✅ File selected:', file.name);

            // Hide drop zone, show file preview
            if (dropZoneContainer) dropZoneContainer.style.display = 'none';

            if (selectedFilePreview) {
                selectedFilePreview.style.display = 'flex';
                if (previewFilename) previewFilename.textContent = file.name;
                if (previewSize) previewSize.textContent = formatFileSize(file.size);
            } else {
                console.error('❌ selectedFilePreview element not found!');
            }

            // Trigger Duplicate Check (status will update when check completes)
            checkDuplicateSingle(file.name);
            activeFileIndex = -1; // Single mode has no index
            updateButtons();

            // Auto-Preview if Image
            if (file.type.startsWith('image/')) {
                updateMainPreview(file);
            }
        }
    }

    // --- Bulk Upload Logic ---

    function addBulkFiles(files) {
        const newFiles = Array.from(files);
        let addedCount = 0;

        newFiles.forEach(file => {
            // Enforce Mode Consistency
            if (isBindMode && !file.type.startsWith('image/')) {
                showAlert('warning', `Skipped "${file.name}": Only images allowed in Photo Mode.`);
                return;
            }
            if (!isBindMode && file.type.startsWith('image/')) {
                showAlert('warning', `Skipped "${file.name}": Images not allowing in Document Mode.`);
                return;
            }

            // Prevent exact duplicate in current list
            if (!bulkFiles.some(f => f.name === file.name && f.size === file.size)) {
                const fileObj = {
                    id: Math.random().toString(36).substr(2, 9),
                    file: file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    status: 'pending', // pending, ready, error
                    isDuplicate: false, // Will be checked async
                    metadata: {
                        title: '',
                        publisher: '',
                        publication_date: '',
                        edition: '',
                        category_id: '',
                        language_id: '',
                        page_count: '',
                        volume_issue: '',
                        description: '',
                        tags: ''
                    },
                    customThumbnail: null
                };
                bulkFiles.push(fileObj);
                addedCount++;
            }
        });

        if (addedCount > 0) {
            // 1. Enable Mode & UI Containers FIRST
            if (!isBulkMode) {
                enableBulkMode();
            }

            // 2. Refresh UI Counts & Tabs (Critical Step - Must run before data loading)
            updateBulkControls();
            renderTabs();

            // 3. Load Data & Async Checks
            if (activeFileIndex === -1) {
                activeFileIndex = 0;
                // Safe load
                try {
                    loadFileData(0);
                } catch (e) {
                    console.error('Autoload error:', e);
                }
            } else {
                // If we already have an active file, just ensure tabs are refreshed (done above)
            }

            checkAllDuplicates(); // Async check
        }
    }

    function enableBulkMode() {
        isBulkMode = true;
        const dropZone = document.getElementById('dropZoneContainer');
        const bulkContainer = document.getElementById('bulkUploadContainer');
        const filePreview = document.getElementById('selectedFilePreview');

        if (dropZone) dropZone.style.display = 'none';
        if (bulkContainer) bulkContainer.style.display = 'block';
        if (filePreview) filePreview.style.display = 'none';

        updateButtons();
    }

    function loadFileData(index) {
        const file = bulkFiles[index];
        if (!file) return;

        // Populate Form Fields (Safe Set)
        const safelySetValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.value = value || '';
        };

        safelySetValue('title', file.metadata.title);
        safelySetValue('publisher', file.metadata.publisher);
        safelySetValue('publication_date', file.metadata.publication_date);
        safelySetValue('edition', file.metadata.edition);
        safelySetValue('category_id', file.metadata.category_id);
        safelySetValue('language_id', file.metadata.language_id);
        safelySetValue('page_count', file.metadata.page_count);
        safelySetValue('description', file.metadata.description);
        safelySetValue('volume_issue', file.metadata.volume_issue);

        // Tags
        safelySetValue('keywordsHidden', file.metadata.tags);

        // Update Tag Visuals if available
        if (typeof setTags === 'function' && file.metadata.tags) {
            setTags(file.metadata.tags.split(',').filter(t => t.trim() !== ''));
        }

        // Current File Badge
        const badge = document.getElementById('currentFileName');
        if (badge) {
            badge.textContent = `Editing: ${file.name}`;
            badge.classList.remove('d-none');
        }

        // Validate Status
        validateCurrentFile();

        // Thumbnail Handling
        if (thumbnailInput) thumbnailInput.value = '';

        if (file.customThumbnail) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (thumbnailPreview) {
                    thumbnailPreview.src = e.target.result;
                    thumbnailPreview.style.display = 'block';
                }
                if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'none';
                if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'flex';
            };
            reader.readAsDataURL(file.customThumbnail);
        } else if (file.file.type.startsWith('image/')) {
            updateMainPreview(file.file);
        } else {
            // Clear
            if (thumbnailPreview) {
                thumbnailPreview.src = '#';
                thumbnailPreview.style.display = 'none';
            }
            if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'flex';
            if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'none';
        }
    }

    function renderTabs() {
        const tabsContainer = document.getElementById('bulkTabs');
        if (!tabsContainer) return;

        tabsContainer.innerHTML = '';

        bulkFiles.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'nav-item';

            const isActive = index === activeFileIndex;
            const isReady = file.status === 'ready';
            const isError = file.status === 'error' || file.isDuplicate;

            // Status Text & Dot Logic
            let statusText = 'Pending Info';
            let dotClass = 'pending'; // css class handled by dot itself mostly or container

            if (isReady) {
                statusText = 'Ready for Upload';
            } else if (file.isDuplicate) {
                statusText = 'Duplicate File';
            } else if (file.status === 'error') {
                statusText = 'Missing Info';
            }

            // Card Classes
            let cardClasses = 'file-card-btn';
            if (isActive) cardClasses += ' active';
            if (file.isDuplicate) cardClasses += ' is-duplicate';
            if (isError) cardClasses += ' error';

            // Drag Attributes
            li.setAttribute('draggable', 'true');
            li.dataset.index = index;
            li.addEventListener('dragstart', handleDragStart);
            li.addEventListener('dragover', handleDragOver);
            li.addEventListener('drop', handleDrop);
            li.addEventListener('dragend', handleDragEnd);

            // Icon selection
            const iconClass = isActive ? 'bi-book-half' : 'bi-book';

            li.innerHTML = `
            <div class="${cardClasses}" onclick="switchTab(${index})">
                <div class="card-header-row">
                    <i class="bi ${iconClass} card-icon"></i>
                    <i class="bi bi-x card-close" onclick="removeFile(event, ${index})"></i>
                </div>
                <div class="card-filename" title="${file.name}">
                    ${file.name}
                </div>
                <div class="card-footer-row">
                    <span class="card-status-text">${statusText}</span>
                    <div class="status-dot"></div>
                </div>
            </div>
            `;
            tabsContainer.appendChild(li);
        });
    }

    // ... (Drag handlers remain same) ...
    // Note: ensure handleDragStart uses 'this.innerHTML' which now contains the div, which is fine.
    // Actually, 'this' refers to 'li', so innerHTML is the whole card div. dragging 'li' is correct.

    // ... 

    function updateBulkControls() {
        const total = bulkFiles.length;
        const ready = bulkFiles.filter(f => f.status === 'ready').length;
        const pending = total - ready;
        const duplicates = bulkFiles.filter(f => f.isDuplicate).length;

        document.getElementById('totalFilesCount').textContent = total;
        document.getElementById('readyFilesCount').textContent = ready;
        document.getElementById('pendingFilesCount').textContent = pending;

        // Duplicate Status Visibility
        const dupStatus = document.getElementById('duplicateStatusContainer');
        if (dupStatus) {
            if (total > 0 && duplicates === 0) {
                dupStatus.style.display = 'flex';
                dupStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> NO DUPLICATE FILES DETECTED';
                dupStatus.className = 'duplicate-status'; // Ensure green
            } else if (duplicates > 0) {
                dupStatus.style.display = 'flex';
                dupStatus.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> ${duplicates} DUPLICATE FOUND`;
                dupStatus.className = 'duplicate-status text-danger'; // Red warning
                dupStatus.style.color = '#EF4444';
            } else {
                dupStatus.style.display = 'none';
            }
        }
    }

    window.resetFileSelection = function () {
        selectedFile = null;
        fileInput.value = '';

        // Hide file badge, show drop zone
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        const dropZoneContainer = document.getElementById('dropZoneContainer');

        if (selectedFilePreview) selectedFilePreview.style.display = 'none';
        if (dropZoneContainer) dropZoneContainer.style.display = 'block';

        updateButtons();
    }

    window.resetForm = function () {
        console.log('🔄 Resetting form...');

        // Reset form fields
        document.getElementById('uploadForm').reset();

        // Clear file inputs
        if (fileInput) fileInput.value = '';
        if (bulkFileInput) bulkFileInput.value = '';

        // Clear selectedFile variable
        selectedFile = null;

        // Hide single file badge, show drop zone
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        const dropZoneContainer = document.getElementById('dropZoneContainer');

        if (selectedFilePreview) selectedFilePreview.style.display = 'none';
        if (dropZoneContainer) dropZoneContainer.style.display = 'block';

        // Clear Current File Badge (Gen Info)
        const badge = document.getElementById('currentFileName');
        if (badge) {
            badge.classList.add('d-none');
            badge.textContent = '';
        }

        // Clear Tags
        const tagsContainer = document.getElementById('tagsContainer');
        if (tagsContainer) tagsContainer.innerHTML = '';
        if (typeof tags !== 'undefined') tags = []; // Assuming tags global from upload-tags.js
        document.getElementById('keywordsHidden').value = '';


        // Reset Bulk Mode
        isBulkMode = false;
        isBindMode = false;
        bulkFiles = [];
        activeFileIndex = 0;

        // Hide bulk stats and container
        const bulkStatsContainer = document.getElementById('bulkStatsContainer');
        if (bulkStatsContainer) bulkStatsContainer.style.display = 'none';

        const bulkUploadContainer = document.getElementById('bulkUploadContainer');
        if (bulkUploadContainer) bulkUploadContainer.style.display = 'none';

        // Reset Counts
        document.getElementById('totalFilesCount').textContent = '0';
        document.getElementById('readyFilesCount').textContent = '0';
        document.getElementById('pendingFilesCount').textContent = '0';

        // Clear tabs
        const bulkTabs = document.getElementById('bulkTabs');
        if (bulkTabs) bulkTabs.innerHTML = '';

        // Explicitly reset thumbnail state
        if (thumbnailPreview) {
            thumbnailPreview.src = '#';
            thumbnailPreview.style.display = 'none';
        }
        if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'flex';
        if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'none';
        coverFileId = null;

        // Remove slider buttons if any (Legacy cleanup)
        const tabsWrapper = document.querySelector('.tabs-container');
        // Arrow cleanup handled by CSS/removal of render logic
        if (tabsWrapper) {
            const prev = tabsWrapper.querySelector('.prev');
            const next = tabsWrapper.querySelector('.next');
            if (prev) prev.remove();
            if (next) next.remove();
        }

        // Clear bulk tabs
        if (bulkFileTabs) bulkFileTabs.innerHTML = '';

        if (modeIndividual) modeIndividual.checked = true;

        // Remove thumbnail
        if (typeof removeThumbnail === 'function') removeThumbnail();

        // Clear tags
        if (typeof clearTags === 'function') clearTags();

        // Explicitly clear tag inputs
        const tagValues = document.getElementById('keywordsHidden');
        if (tagValues) tagValues.value = '';

        // Re-enable/disable buttons
        updateButtons();

        console.log('✅ Form reset complete');
    }

    function updateButtons() {
        const hasFile = (fileInput.files.length > 0 || bulkFiles.length > 0 || selectedFile !== null);
        const isEdit = document.querySelector('input[name="action"]').value === 'edit';

        // Check Dirty State for Discard Button
        const isDirty = window.hasUnsavedChanges();

        let shouldEnable = false;

        if (activeFileIndex !== -1) {
            // Bulk Mode
            const readyCount = bulkFiles.filter(f => f.status === 'ready').length;
            shouldEnable = readyCount > 0;
        } else {
            // Single Mode Logic
            const title = document.getElementById('title')?.value?.trim();
            const date = document.getElementById('publication_date')?.value?.trim();
            const category = document.getElementById('category_id')?.value?.trim();
            const language = document.getElementById('language_id')?.value?.trim();

            // Single file specific: Check if duplicate error exists
            const hasError = document.getElementById('previewError')?.style.display !== 'none' && document.getElementById('previewError')?.textContent !== '';

            // Additional check for required fields being filled
            const isFormValid = title && date && category && language;

            if (isEdit) {
                // Edit Mode: Enable only if Dirty AND Valid
                shouldEnable = isDirty && isFormValid;
            } else {
                // Upload Mode: Enable ONLY if File Selected AND Form Valid AND No Error
                shouldEnable = hasFile && isFormValid && !hasError;
            }
        }

        // Update Buttons
        const uploadBtns = [document.getElementById('uploadBtn'), document.getElementById('uploadBtnDesktop'), document.getElementById('uploadBtnMobile')];
        const discardBtns = [document.getElementById('discardBtn'), document.getElementById('discardBtnMobile')];

        uploadBtns.forEach(btn => {
            if (btn) {
                btn.disabled = !shouldEnable;
                // Update Text
                if (activeFileIndex !== -1) {
                    const readyCount = bulkFiles.filter(f => f.status === 'ready').length;
                    const text = `Upload ${readyCount} File${readyCount !== 1 ? 's' : ''}`;
                    btn.innerText = (btn.id === 'uploadBtnMobile') ? text.toUpperCase() : text;
                } else if (isEdit) {
                    btn.innerText = (btn.id === 'uploadBtnMobile') ? 'SAVE CHANGES' : 'Save Changes';
                } else {
                    btn.innerText = (btn.id === 'uploadBtnMobile') ? 'UPLOAD' : 'Upload';
                }
            }
        });

        discardBtns.forEach(btn => {
            if (btn) btn.disabled = !isDirty;
        });
    }



    // --- Duplicate Checking Logic ---

    async function checkDuplicateSingle(filename) {
        // We no longer have a status badge in the preview, so we'll just check and alert if needed.
        console.log('🔍 Starting duplicate check for:', filename);

        try {
            const formData = new FormData();
            formData.append('action', 'check_duplicate');
            formData.append('file_name', filename);

            const res = await fetch(APP_URL + '/backend/api/check_duplicate.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            // Only warn if duplicate found
            if (data.success === true && data.has_duplicates === true) {
                console.log('❌ Duplicate found!');

                // Show inline error
                const previewError = document.getElementById('previewError');
                if (previewError) {
                    previewError.textContent = 'File already exists';
                    previewError.style.display = 'block';
                    updateButtons();
                } else {
                    showAlert('warning', `Warning: A file named "${filename}" already exists.`);
                }
            } else if (!data.success) {
                console.log('⚠️ API returned error:', data.message);
            }
        } catch (e) {
            console.error('❌ Duplicate check error:', e);
        }
    }

    async function checkAllDuplicates() {
        for (let i = 0; i < bulkFiles.length; i++) {
            const f = bulkFiles[i];
            if (f.checkedDuplicate) continue;

            f.checkedDuplicate = true; // Mark checking start

            const formData = new FormData();
            formData.append('action', 'check_duplicate');
            formData.append('file_name', f.name);

            try {
                const res = await fetch(APP_URL + '/backend/api/check_duplicate.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success && data.has_duplicates) {
                    f.isDuplicate = true;
                } else {
                    f.isDuplicate = false;
                }

                // Update UI immediately for this item
                validateFile(i);

            } catch (e) {
                console.error('Duplicate Check Error', e);
            }
        }
    }

    function validateFile(index) {
        const file = bulkFiles[index];
        if (!file) return;

        // Check Metadata
        const m = file.metadata;
        // We can also allow some optional fields? 
        // Requirement said: "Validation: Prevent submission if required metadata... is missing."
        const isMetaComplete = (m.title && m.publication_date && m.category_id && m.language_id);

        if (file.isDuplicate) {
            file.status = 'error'; // Duplicate takes precedence
        } else if (isMetaComplete) {
            file.status = 'ready';
        } else {
            file.status = 'pending';
        }

        updateBulkControls();
        renderTabs();
        updateButtons();
    }

    // Override validateCurrentFile to use validateFile (if called from other places)
    window.validateCurrentFile = function () {
        if (activeFileIndex !== -1) validateFile(activeFileIndex);
    }

    // Compatibility for Bind Mode
    function updateBulkUI() {
        updateBulkControls();
        renderTabs();
        if (isBindMode) {
            renderImageGrid();
        }
    }

    // Replaces renderBulkList (Legacy) - Now handled by renderTabs
    function renderBulkList() {
        renderTabs();
    }

    window.removeBulkFile = function (id) {
        const index = bulkFiles.findIndex(f => f.id === id);
        if (index !== -1) {
            removeFile(new Event('click'), index);
        }
    }


    // --- Image Grid (Bind Mode) ---
    function renderImageGrid() {
        const grid = document.getElementById('pageOrderGrid');
        grid.innerHTML = '';

        bulkFiles.forEach((file, index) => {
            const col = document.createElement('div');
            col.className = 'page-order-item position-relative m-1';
            col.style.width = '140px'; // Slightly wider for buttons
            col.style.height = '180px';
            col.draggable = true;
            col.dataset.index = index;

            const isCover = (coverFileId === file.id);

            // Preview
            let preview = '';
            if (file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file.file);
                preview = `<img src="${url}" class="w-100 h-100 rounded border shadow-sm ${isCover ? 'border-primary border-3' : ''}" style="object-fit: cover;">`;
            } else {
                preview = `<div class="w-100 h-100 rounded border bg-light d-flex align-items-center justify-content-center text-muted"><i class="bi bi-file-earmark-text fs-2"></i></div>`;
            }

            col.innerHTML = `
                ${preview}
                
                ${isCover ? '<span class="position-absolute top-0 start-0 badge bg-primary m-1 shadow-sm">Cover</span>' : ''}

                <div class="position-absolute top-0 end-0 p-1">
                     <button class="btn btn-sm btn-danger py-0 px-1 rounded-circle" style="width:20px;height:20px;line-height:1;" onclick="removeBulkFile('${file.id}')" title="Remove">&times;</button>
                </div>

                <div class="position-absolute bottom-0 w-100 p-1 bg-white bg-opacity-90 d-flex flex-column gap-1 align-items-center">
                    <small class="fw-bold text-dark" style="font-size:10px;">Pg ${index + 1}</small>
                    ${!isCover ? `<button class="btn btn-xs btn-outline-primary py-0" style="font-size:10px;" onclick="setAsCover('${file.id}')">Set Cover</button>` : ''}
                </div>
            `;

            addDragEvents(col);
            grid.appendChild(col);
        });
    }

    window.setAsCover = function (id) {
        coverFileId = id;

        // Update Grid UI
        renderImageGrid();

        // Update Main Preview
        const fileObj = bulkFiles.find(f => f.id === id);
        if (fileObj) {
            updateMainPreview(fileObj.file);

            // Ensure Remove Button handles clearing this state
            if (removeThumbnailBtn) {
                removeThumbnailBtn.style.display = 'flex';
                // We might need to override the remove function to clear coverFileId?
                // Existing logic generally clears input. 
                // We should ensure updateMainPreview shows it.
            }
        }
    }

    // Update Global Remove to clear cover selection
    const originalRemoveThumbnail = window.removeThumbnail;
    window.removeThumbnail = function (e) {
        coverFileId = null; // Clear selection
        renderImageGrid(); // Remove badge
        if (originalRemoveThumbnail) originalRemoveThumbnail(e);
    }

    // Drag Reordering Logic for Grid
    let dragSrcEl = null;

    function addDragEvents(item) {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragenter', handleDragEnter);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('dragleave', handleDragLeave);
        item.addEventListener('drop', handleDropReorder);
        item.addEventListener('dragend', handleDragEnd);
    }

    function handleDragStart(e) {
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
        this.classList.add('dragging');
    }

    function handleDragOver(e) {
        if (e.preventDefault) e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    function handleDropReorder(e) {
        if (e.stopPropagation) e.stopPropagation();

        if (dragSrcEl !== this) {
            const srcIndex = parseInt(dragSrcEl.dataset.index);
            const targetIndex = parseInt(this.dataset.index);

            const movedItem = bulkFiles[srcIndex];
            bulkFiles.splice(srcIndex, 1);
            bulkFiles.splice(targetIndex, 0, movedItem);

            renderImageGrid();
        }
        return false;
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        document.querySelectorAll('.page-order-item').forEach(item => {
            item.classList.remove('drag-over');
        });
    }


    // Helper: Show Alert
    function showAlert(type, message) {
        if (!alertContainer) return;

        // Clear previous alerts if any (optional, or append?)
        // alertContainer.innerHTML = ''; 

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        alertContainer.appendChild(wrapper.firstElementChild);
        alertContainer.style.display = 'block'; // Ensure visible

        // Auto dismiss success
        if (type === 'success') {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert-success');
                if (alert) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
    }

    // Observer to hide alertContainer if empty (handle manual dismiss)
    const observer = new MutationObserver(function (mutations) {
        if (!alertContainer.hasChildNodes() || alertContainer.innerHTML.trim() === '') {
            alertContainer.style.display = 'none';
        } else {
            // Check if all children are effectively hidden/removed
            let visible = false;
            alertContainer.childNodes.forEach(node => {
                if (node.nodeType === 1) visible = true;
            });
            alertContainer.style.display = visible ? 'block' : 'none';
        }
    });

    if (alertContainer) {
        observer.observe(alertContainer, { childList: true, subtree: true });
        // Initial check
        if (alertContainer.innerHTML.trim() === '') {
            alertContainer.style.display = 'none';
        }
    }

    // Utilities
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // --- Bulk Container Drag & Drop ---
    const bulkContainer = document.getElementById('bulkUploadContainer');
    if (bulkContainer) {
        bulkContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
            bulkContainer.style.borderColor = '#4C3939';
            bulkContainer.style.backgroundColor = '#FDF8F6';
        });

        bulkContainer.addEventListener('dragleave', (e) => {
            e.preventDefault();
            bulkContainer.style.borderColor = 'transparent'; // Or reset to original
            bulkContainer.style.backgroundColor = 'transparent';
        });

        bulkContainer.addEventListener('drop', (e) => {
            e.preventDefault();
            bulkContainer.style.borderColor = 'transparent';
            bulkContainer.style.backgroundColor = 'transparent';

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                // Determine mode if needed or just add
                // If in bulk mode, just add
                addBulkFiles(e.dataTransfer.files);
            }
        });
    }

    // --- Global Functions for Inline Handlers ---
    window.switchTab = function (index) {
        if (bulkFiles[index]) {
            // Save current data before switching
            if (activeFileIndex !== -1 && bulkFiles[activeFileIndex]) {
                saveCurrentFormData();
            }

            activeFileIndex = index;
            loadFileData(index);
            renderTabs();
            updateButtons();
        }
    };

    window.removeFile = function (e, index) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Confirm? Maybe not needed for quick action, but good UX.
        // For now, direct remove.

        const fileToRemove = bulkFiles[index];

        // Remove from array
        bulkFiles.splice(index, 1);

        // Adjust active index
        if (bulkFiles.length === 0) {
            // No files left -> Reset
            resetForm();
        } else {
            if (index === activeFileIndex) {
                // Removed active -> Switch to 0 or previous
                activeFileIndex = 0;
                loadFileData(0);
            } else if (index < activeFileIndex) {
                // Removed one before active -> decrement active index
                activeFileIndex--;
            }
            // IF index > active, no change to activeIndex

            updateBulkControls();
            renderTabs();
            updateButtons();

            // Re-render grid if in bind mode
            if (isBindMode) renderImageGrid();
        }
    };

    // Helper to save current form data to the object
    function saveCurrentFormData() {
        if (activeFileIndex === -1 || !bulkFiles[activeFileIndex]) return;

        const f = bulkFiles[activeFileIndex];
        const m = f.metadata;

        m.title = document.getElementById('title')?.value?.trim() || '';
        m.publisher = document.getElementById('publisher')?.value?.trim() || '';
        m.publication_date = document.getElementById('publication_date')?.value || '';
        m.edition = document.getElementById('edition')?.value || '';
        m.category_id = document.getElementById('category_id')?.value || '';
        m.language_id = document.getElementById('language_id')?.value || '';
        m.page_count = document.getElementById('page_count')?.value || '';
        m.volume_issue = document.getElementById('volume_issue')?.value?.trim() || '';
        m.description = document.getElementById('description')?.value?.trim() || '';

        // Tags
        m.tags = document.getElementById('keywordsHidden')?.value || '';

        // Update Status based on completion
        validateFile(activeFileIndex);
    }

    // Check duplication when file name changes? 
    // Usually file name doesn't change, but title does.
    // We already have checkAllDuplicates.

    // --- Add More Files Logic ---
    const addMoreBtn = document.getElementById('addMoreBtn');
    const addMoreInput = document.getElementById('addMoreInput');

    if (addMoreBtn && addMoreInput) {
        addMoreBtn.addEventListener('click', () => {
            addMoreInput.click();
        });

        addMoreInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                addBulkFiles(e.target.files);
                // Reset value to allow selecting same file again if needed (though we filter dups)
                addMoreInput.value = '';
            }
        });
    }

    // Initial Button State
    updateButtons();

});
