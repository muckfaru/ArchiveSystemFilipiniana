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

        // Change "Add File" to "Change File"
        const btnAddFile = document.querySelector('.btn-add-file');
        if (btnAddFile) btnAddFile.textContent = 'CHANGE FILE';

        // Initialize Tags visually
        const keywordsHidden = document.getElementById('keywordsHidden');
        if (keywordsHidden && keywordsHidden.value && typeof setTags === 'function') {
            const tagsList = keywordsHidden.value.split(',').map(t => t.trim()).filter(t => t);
            setTags(tagsList);
        }
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
                let m = bootstrap.Modal.getOrCreateInstance(modalEl);
                m.show();
            } else {
                const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
                if (isEdit) {
                    window.isNavigatingAway = true;
                    window.location.href = APP_URL + '/dashboard.php';
                } else if (typeof window.resetForm === 'function') {
                    window.resetForm();
                }
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
            const modalEl = document.getElementById('confirmUploadModal');

            // Populate file list with improved styling
            const fileListContainer = document.getElementById('uploadFileList');
            if (fileListContainer) {
                fileListContainer.innerHTML = '';
                fileListContainer.style.display = 'block';
                if (activeFileIndex !== -1) {
                    const readyFiles = bulkFiles.filter(f => f.status === 'ready');
                    const ul = document.createElement('ul');
                    ul.className = 'list-unstyled mb-0';
                    ul.style.cssText = 'display: flex; flex-direction: column; gap: 8px;';
                    readyFiles.forEach((f, index) => {
                        const li = document.createElement('li');
                        li.style.cssText = 'display: flex; align-items: center; gap: 12px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #E5E7EB;';
                        li.innerHTML = `
                            <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-file-earmark-text" style="font-size: 16px; color: #3A9AFF;"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 14px; font-weight: 600; color: #1F2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${f.name}</div>
                                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">Ready for upload</div>
                            </div>
                            <div style="width: 24px; height: 24px; background: #DCFCE7; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-check" style="font-size: 14px; color: #16A34A; font-weight: bold;"></i>
                            </div>
                        `;
                        ul.appendChild(li);
                    });
                    fileListContainer.appendChild(ul);
                } else {
                    const fileToUpload = (fileInput.files.length > 0) ? fileInput.files[0] : selectedFile;
                    if (fileToUpload) {
                        fileListContainer.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #E5E7EB;">
                                <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="bi bi-file-earmark-text" style="font-size: 16px; color: #3A9AFF;"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; font-weight: 600; color: #1F2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${fileToUpload.name}</div>
                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">Ready for upload</div>
                                </div>
                                <div style="width: 24px; height: 24px; background: #DCFCE7; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="bi bi-check" style="font-size: 14px; color: #16A34A; font-weight: bold;"></i>
                                </div>
                            </div>
                        `;
                    } else if (isEdit) {
                        const fileName = document.getElementById('previewFilename')?.textContent || 'Current File';
                        fileListContainer.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #E5E7EB;">
                                <div style="width: 32px; height: 32px; background: #EFF6FF; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="bi bi-file-earmark-text" style="font-size: 16px; color: #3A9AFF;"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; font-weight: 600; color: #1F2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${fileName}</div>
                                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">Current file</div>
                                </div>
                            </div>
                        `;
                    }
                }
            }

            const m = bootstrap.Modal.getOrCreateInstance(modalEl);

            // Edit Mode Modal Text Update
            if (isEdit) {
                const modalTitle = modalEl.querySelector('.modal-header h5');
                const modalSubtitle = modalEl.querySelector('.modal-header p');
                const confirmButton = document.getElementById('confirmUploadBtn');
                const modalIcon = modalEl.querySelector('.modal-header i');
                
                if (modalTitle) modalTitle.textContent = 'Save Changes?';
                if (modalSubtitle) modalSubtitle.textContent = 'Confirm to update this archive';
                if (modalIcon) {
                    modalIcon.className = 'bi bi-pencil-square';
                }
                if (confirmButton) {
                    confirmButton.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Save Changes';
                }
            }

            m.show();
        });
    });

    // Form Field Event Listeners - Update buttons when metadata changes
    const formFields = ['title', 'publisher', 'publication_date', 'edition', 'category_id', 'language_id', 'page_count', 'volume_issue', 'description', 'keywordsHidden'];
    formFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function () {
                // Clear invalid state on input
                this.classList.remove('is-invalid');
                if (isBindMode) {
                    validateBindModeForm();
                } else if (activeFileIndex !== -1) {
                    saveCurrentFormData();
                } else {
                    updateButtons();
                }
            });
            // Also clear on change for selects
            field.addEventListener('change', function () {
                this.classList.remove('is-invalid');
                if (isBindMode) {
                    validateBindModeForm();
                } else if (activeFileIndex !== -1) {
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
            isBindMode = false; // Individual files
            updateBulkUI();
        });
        modeBind.addEventListener('change', () => {
            isBindMode = true; // Photo Gallery
            updateBulkUI();
        });
    }

    // --- Unsaved Changes / Navigation Warning ---

    // 1. Helper to check for unsaved changes
    window.hasUnsavedChanges = function () {
        // Check files
        const hasFiles = (fileInput && fileInput.files.length > 0) || (bulkFiles && bulkFiles.length > 0);
        if (hasFiles) return true;
        
        // Check thumbnail changes
        const hasThumbnailChange = thumbnailInput && thumbnailInput.files && thumbnailInput.files.length > 0;
        if (hasThumbnailChange) return true;

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
            const currentPc = document.getElementById('page_count')?.value || '';
            const currentVol = document.getElementById('volume_issue')?.value?.trim() || '';
            const currentTags = document.getElementById('keywordsHidden')?.value?.trim() || '';

            if (currentTitle !== (originalFormData.title || '')) return true;
            if (currentPub !== (originalFormData.publisher || '')) return true;
            if (currentDesc !== (originalFormData.description || '')) return true;
            if (currentDate !== (originalFormData.publication_date || '')) return true;
            if (currentCat != (originalFormData.category_id || '')) return true; // loose comparison for string/int
            if (currentLang != (originalFormData.language_id || '')) return true;
            if (currentEd.trim() !== (originalFormData.edition || '').trim()) return true;
            if (currentPc != (originalFormData.page_count || '')) return true;
            if (currentVol !== (originalFormData.volume_issue || '')) return true;
            if (currentTags !== (originalFormData.tags || '')) return true;

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
            edition: document.getElementById('edition')?.value || '',
            page_count: document.getElementById('page_count')?.value || '',
            volume_issue: document.getElementById('volume_issue')?.value?.trim() || '',
            tags: document.getElementById('keywordsHidden')?.value?.trim() || ''
        };
        console.log('Original state captured:', originalFormData);
    }

    // 2. Browser Native Warning (Reload / Close Tab)
    window.addEventListener('beforeunload', function (e) {
        if (window.isNavigatingAway) return; // Allow approved navigation
        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        if (window.hasUnsavedChanges() || isEdit) {
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

        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';

        // If it's a real navigation and we have unsaved changes or we are in edit mode
        if (!window.isNavigatingAway && (window.hasUnsavedChanges() || isEdit)) {
            e.preventDefault();
            e.stopPropagation();

            // Show Custom Modal
            pendingNavigationUrl = link.href;
            const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('unsavedChangesModal'));
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
                window.isNavigatingAway = true;
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
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl); // Use getOrCreateInstance

        try {
            if (isEdit) {
                window.isNavigatingAway = true;
                window.hasUnsavedChanges = () => false;
                window.location.href = APP_URL + '/dashboard.php';
            } else {
                if (typeof window.resetForm === 'function') window.resetForm();
            }
        } catch (e) {
            console.error('Error in discard action:', e);
        }

        modal.hide();

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
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
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
                        if (m.volume_issue) singleFormData.append('volume_issue', m.volume_issue);
                        if (m.description) singleFormData.append('description', m.description);
                        if (m.tags) singleFormData.append('keywords', m.tags);

                        // Append thumbnail if present
                        if (f.customThumbnail) {
                            singleFormData.append('thumbnail', f.customThumbnail, 'thumbnail.jpg');
                        }

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
                        window.isNavigatingAway = true;
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
                        console.log('📸 Thumbnail attached to FormData:', thumbnailInput.files[0].name);
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
                        console.log('📸 Thumbnail attached to FormData:', thumbnailInput.files[0].name);
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
                    window.isNavigatingAway = true;
                    const isEdit = document.querySelector('input[name="action"]').value === 'edit';
                    if (isEdit) {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard.php?success=edit';
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
                    window.isNavigatingAway = true;
                    const isEdit = document.querySelector('input[name="action"]').value === 'edit';
                    if (isEdit) {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard.php?success=edit';
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

    // Show the remove button on page load in edit mode
    const isEditModeCurrent = document.querySelector('input[name="action"]')?.value === 'edit';
    if (isEditModeCurrent && thumbnailPreview && thumbnailPreview.src &&
        !thumbnailPreview.src.endsWith('#') &&
        thumbnailPreview.src !== window.location.href) {
        if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'flex';
        if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'none';
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
                
                // Update buttons to enable save in edit mode
                updateButtons();
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

        // Clear existing_thumbnail input
        const existingThumb = document.getElementById('existing_thumbnail');
        if (existingThumb) existingThumb.value = '';

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

    // ── EPUB / MOBI metadata prefill ──────────────────────────────────────────
    /**
     * Sends the file to the PHP extract_meta API and auto-fills any empty
     * metadata fields.  Fields the user has already filled are never overwritten.
     *
     * @param {File}   file       - The EPUB or MOBI File object
     * @param {number|null} bulkIdx - bulkFiles index if bulk mode, null for single
     */
    async function extractAndPrefillMeta(file, bulkIdx = null) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['epub', 'mobi', 'pdf', 'jpg', 'jpeg', 'png'].includes(ext)) return;

        // Removed loading placeholder - metadata extraction happens silently in background
        const fd = new FormData();
        fd.append('file', file);

        try {
            const res = await fetch(APP_URL + '/backend/api/extract_meta.php', {
                method: 'POST', body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!data.success || !data.meta) return;

            const m = data.meta;

            /**
             * Safely fill a form field only if it is currently empty.
             * Also updates the corresponding bulkFiles metadata entry.
             */
            const fillField = (id, value, metaKey) => {
                if (!value) return;
                const el = document.getElementById(id);
                if (el && !el.value.trim()) {
                    el.value = value;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
                // Update bulk state too
                if (bulkIdx !== null && bulkFiles[bulkIdx] && metaKey) {
                    if (!bulkFiles[bulkIdx].metadata[metaKey]) {
                        bulkFiles[bulkIdx].metadata[metaKey] = value;
                    }
                }
            };

            // Use publisher; fall back to creator (author) only if publisher blank
            const publisher = m.publisher || m.creator || '';

            fillField('title', m.title, 'title');
            fillField('publisher', publisher, 'publisher');
            fillField('publication_date', m.publication_date, 'publication_date');
            fillField('description', m.description, 'description');

            // Keywords → tags
            if (m.keywords) {
                const hiddenKw = document.getElementById('keywordsHidden');
                if (hiddenKw && !hiddenKw.value.trim()) {
                    hiddenKw.value = m.keywords;
                    if (typeof setTags === 'function') {
                        setTags(m.keywords.split(',').map(t => t.trim()).filter(Boolean));
                    }
                    if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                        bulkFiles[bulkIdx].metadata.tags = m.keywords;
                    }
                }
            }

            // Language: auto-select the matched DB language_id
            if (data.languageId) {
                const langEl = document.getElementById('language_id');
                if (langEl && !langEl.value) {
                    langEl.value = data.languageId;
                    langEl.dispatchEvent(new Event('change', { bubbles: true }));
                    if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                        bulkFiles[bulkIdx].metadata.language_id = String(data.languageId);
                    }
                }
            }

            // Re-validate: refresh tab statuses and counters
            if (bulkIdx !== null) {
                // Trigger status recalculation via field input event (saveCurrentFormData path)
                const titleInp = document.getElementById('title');
                if (titleInp) titleInp.dispatchEvent(new Event('input', { bubbles: true }));
                // Refresh tab cards and counters
                if (typeof renderTabs === 'function') renderTabs();
                if (typeof updateBulkControls === 'function') updateBulkControls();
            } else {
                updateButtons();
            }

            // Thumbnail Auto-fill
            if (data.thumbnail_url) {
                const thumbPreview = document.getElementById('thumbnailPreview');
                const thumbPlaceholder = document.getElementById('thumbnailPlaceholder');
                const removeBtn = document.getElementById('removeThumbnailBtn');

                // Check if user has explicitly uploaded a thumbnail already (bulk or single)
                const hasCustomThumb = (bulkIdx !== null && bulkFiles[bulkIdx] && bulkFiles[bulkIdx].customThumbnail);

                if (thumbPreview && thumbPreview.src.endsWith('#') === false && !hasCustomThumb) {
                    thumbPreview.src = data.thumbnail_url;
                    thumbPreview.style.display = 'block';
                    if (thumbPlaceholder) thumbPlaceholder.style.display = 'none';

                    // In bulk mode, store the path so it's sent to the server on final upload
                    if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                        bulkFiles[bulkIdx].metadata.thumbnail_path = data.thumbnail_path;
                    } else {
                        // Single file fallback, add hidden input
                        let hiddenThumb = document.getElementById('auto_thumbnail_path');
                        if (!hiddenThumb) {
                            hiddenThumb = document.createElement('input');
                            hiddenThumb.type = 'hidden';
                            hiddenThumb.id = 'auto_thumbnail_path';
                            hiddenThumb.name = 'auto_thumbnail_path';
                            document.getElementById('uploadForm').appendChild(hiddenThumb);
                        }
                        hiddenThumb.value = data.thumbnail_path;
                    }
                }
            }

            // Subtle success flash on title field (optional visual feedback)
            const titleEl = document.getElementById('title');
            if (titleEl && m.title) {
                titleEl.style.transition = 'background 0.4s';
                titleEl.style.background = '#f0fff4';
                setTimeout(() => { titleEl.style.background = ''; }, 1200);
            }

        } catch (err) {
            console.warn('Metadata extraction failed:', err);
        }
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

        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';

        // Treat everything as bulk, except Edit mode where we strictly replace one existing record
        if (!isEdit) {
            // Check types for Mode Detection IF starting fresh
            if (bulkFiles.length === 0) {
                const firstFile = files[0];
                if (firstFile.type.startsWith('image/')) {
                    isBindMode = true; // Images -> Photo Gallery / Grid Mode
                    isBulkMode = true; // Still bulk
                    activeFileIndex = -1; // Bind Mode shares a single metadata entry
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

            // Hide drop zone and edit mode indicator, show file preview
            if (dropZoneContainer) dropZoneContainer.style.display = 'none';
            const editModeIndicator = document.getElementById('editModeIndicator');
            if (editModeIndicator) editModeIndicator.style.display = 'none';

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

            // Auto-fill metadata from EPUB/MOBI
            extractAndPrefillMeta(file, null);

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

                // Auto-fill metadata from EPUB/MOBI for each bulk doc file
                const bulkIdx = bulkFiles.length - 1;
                const bExt = file.name.split('.').pop().toLowerCase();
                if (['epub', 'mobi'].includes(bExt)) {
                    // Run after UI updates (async, non-blocking)
                    setTimeout(() => extractAndPrefillMeta(file, bulkIdx), 50);
                }
            }
        });

        if (addedCount > 0) {
            // 1. Enable Mode & UI Containers FIRST
            if (!isBulkMode) {
                enableBulkMode();
            }

            // 2. Refresh UI Counts & Tabs (Critical Step - Must run before data loading)
            updateBulkUI();

            // 3. Load Data & Async Checks
            if (activeFileIndex === -1) {
                if (!isBindMode) {
                    activeFileIndex = 0;
                    try {
                        loadFileData(0);
                    } catch (e) {
                        console.error('Autoload error:', e);
                    }
                } else {
                    validateBindModeForm();
                }
            } else {
                // If we already have an active file
                if (isBindMode) validateBindModeForm();
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
        if (isBindMode) return; // Skip in Bind Mode as metadata is shared
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
        if (typeof setTags === 'function') {
            setTags(file.metadata.tags ? file.metadata.tags.split(',').filter(t => t.trim() !== '') : []);
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
                <div class="card-filename" title="${file.name}" ${isReady ? 'style="color: #10B981 !important;"' : ''}>
                    ${file.name}
                </div>
                <div class="card-footer-row">
                    <span class="card-status-text ${isReady ? 'text-success' : ''}" ${isReady ? 'style="color: #10B981 !important;"' : ''}>${statusText}</span>
                    <div class="status-dot" ${isReady ? 'style="background-color: #10B981 !important;"' : ''}></div>
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

        const photoTotalSpan = document.getElementById('totalPhotosCount');
        if (photoTotalSpan) photoTotalSpan.textContent = total;

        document.getElementById('readyFilesCount').textContent = ready;
        document.getElementById('pendingFilesCount').textContent = pending;

        // Duplicate Status Visibility
        const dupStatus = document.getElementById('duplicateStatusContainer');
        if (dupStatus) {
            if (total > 0 && duplicates === 0) {
                dupStatus.style.display = 'flex';
                dupStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> NO DUPLICATE FILES DETECTED';
                dupStatus.className = 'duplicate-status'; // Ensure green
                dupStatus.style.color = '#22c55e';
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

        // Check for edit mode
        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        const editModeIndicator = document.getElementById('editModeIndicator');
        if (isEdit && editModeIndicator) {
            editModeIndicator.style.display = '';
        }

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

        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        if (!isEdit && dropZoneContainer) {
            dropZoneContainer.style.display = 'block';
        }

        const editModeIndicator = document.getElementById('editModeIndicator');
        if (isEdit && editModeIndicator) {
            editModeIndicator.style.display = '';
        }

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

        const photoMsg = document.getElementById('bulkPhotoInfoMessage');
        if (photoMsg) photoMsg.classList.add('d-none');

        const photoStats = document.getElementById('photoStatsWrapper');
        if (photoStats) photoStats.style.display = 'none';

        const docStats = document.getElementById('docStatsWrapper');
        if (docStats) docStats.style.display = 'none';

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
        const hasFile = ((fileInput && fileInput.files) ? fileInput.files.length > 0 : false) || bulkFiles.length > 0 || selectedFile !== null;
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
                // Edit Mode: Enable if (Dirty OR New File Selected) AND Form Valid AND No Error
                const newFileSelected = (fileInput && fileInput.files) ? fileInput.files.length > 0 : false;
                shouldEnable = (isDirty || newFileSelected) && isFormValid && !hasError;
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
            if (btn) {
                if (isEdit) {
                    btn.disabled = false; // Always allow discard in edit mode
                } else {
                    const newFileSelected = (fileInput && fileInput.files) ? fileInput.files.length > 0 : false;
                    btn.disabled = !(isDirty || newFileSelected);
                }
            }
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
        if (isBindMode) return; // Managed globally for photos
        const file = bulkFiles[index];
        if (!file) return;

        // Check Metadata
        const m = file.metadata;
        const isMetaComplete = (m.title && m.publication_date && m.category_id && m.language_id);

        if (file.isDuplicate) {
            file.status = 'error'; // Duplicate takes precedence
        } else if (isMetaComplete) {
            file.status = 'ready';
        } else {
            file.status = 'pending';
        }

        updateBulkUI();
        updateButtons();
    }

    window.validateBindModeForm = function () {
        const title = document.getElementById('title')?.value?.trim();
        const date = document.getElementById('publication_date')?.value?.trim();
        const category = document.getElementById('category_id')?.value?.trim();
        const language = document.getElementById('language_id')?.value?.trim();
        const hasFiles = bulkFiles.length > 0;

        const isValid = title && date && category && language && hasFiles;

        const uploadBtns = [
            document.getElementById('uploadBtnDesktop'),
            document.getElementById('uploadBtnMobile'),
            document.getElementById('uploadBtn')
        ];
        uploadBtns.forEach(btn => {
            if (btn) btn.disabled = !isValid;
        });
    }

    // Override validateCurrentFile to use validateFile (if called from other places)
    window.validateCurrentFile = function () {
        if (isBindMode) {
            validateBindModeForm();
        } else if (activeFileIndex !== -1) {
            validateFile(activeFileIndex);
        }
    }

    // Compatibility for Bind Mode
    function updateBulkUI() {
        updateBulkControls();
        const tabsWrapper = document.getElementById('tabsWrapper');
        const gridWrapper = document.getElementById('pageOrderGridWrapper');
        const docStats = document.getElementById('docStatsWrapper');
        const photoStats = document.getElementById('photoStatsWrapper');

        if (isBindMode) {
            if (tabsWrapper) tabsWrapper.style.display = 'none';
            if (gridWrapper) gridWrapper.style.display = 'block';
            if (docStats) docStats.style.display = 'none';
            if (photoStats) photoStats.style.display = 'flex';

            // Hide the "Editing: [filename]" badge during Bulk Photo config
            const currentObjCountBadge = document.getElementById('currentFileName');
            if (currentObjCountBadge) currentObjCountBadge.classList.add('d-none');

            renderImageGrid();
        } else {
            if (tabsWrapper) tabsWrapper.style.display = 'block';
            if (gridWrapper) gridWrapper.style.display = 'none';
            if (docStats) docStats.style.display = 'flex';
            if (photoStats) photoStats.style.display = 'none';
            renderTabs();
        }
    }

    // Replaces renderBulkList (Legacy) - Now handled by renderTabs
    function renderBulkList() {
        updateBulkUI();
    }

    window.removeBulkFile = function (id) {
        const index = bulkFiles.findIndex(f => f.id === id);
        if (index !== -1) {
            removeFile(new Event('click'), index);
        }
    }


    function renderImageGrid() {
        const grid = document.getElementById('pageOrderGrid');
        grid.innerHTML = '';

        bulkFiles.forEach((file, index) => {
            const col = document.createElement('div');
            // Adding a container specifically for hover and scaling
            col.className = 'd-inline-flex flex-column align-items-center m-2 photo-card-wrapper';
            col.style.width = '180px';
            col.style.cursor = 'pointer';
            col.style.transition = 'all 0.3s ease';
            col.draggable = true;
            col.dataset.index = index;

            // Ensure first item is cover if nothing is selected
            if (!coverFileId && index === 0) coverFileId = file.id;
            const isCover = (coverFileId === file.id);

            // Container for the image itself
            const imgContainer = document.createElement('div');
            imgContainer.className = 'position-relative rounded shadow-sm w-100 mb-2 bg-white photo-gallery-item';
            imgContainer.style.height = '240px';
            imgContainer.style.padding = '8px';
            imgContainer.style.border = isCover ? '3px solid #3A9AFF' : '2px solid #E5E7EB';
            imgContainer.style.backgroundColor = '#fff';
            imgContainer.style.transition = 'all 0.3s ease';
            imgContainer.style.overflow = 'hidden';

            // Allow clicking anywhere on the thumbnail to set it as primary
            imgContainer.onclick = (e) => {
                setAsCover(file.id);
            };

            // Preview
            let preview = '';
            if (file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file.file);
                // The image gets centered within the container like a paper
                preview = `<img src="${url}" class="w-100 h-100 rounded" style="object-fit: cover; background-color: white;">`;
            } else {
                preview = `<div class="w-100 h-100 rounded bg-light d-flex align-items-center justify-content-center text-muted"><i class="bi bi-file-earmark-text fs-2"></i></div>`;
            }

            // Star Icon Configuration
            const starIcon = isCover
                ? `<i class="bi bi-star-fill text-warning fs-5"></i>`
                : `<i class="bi bi-star text-secondary fs-5"></i>`;

            // Adding elements inside the Image Container
            imgContainer.innerHTML = `
                ${preview}
                
                ${isCover ? '<span class="badge position-absolute shadow-sm" style="top: 8px; left: 8px; background-color: #3A9AFF; color: white; border-radius: 6px; font-size: 0.65rem; padding: 0.35em 0.7em; letter-spacing: 0.5px; font-weight: 600;">PRIMARY</span>' : ''}

                <!-- Remove Button - Hidden by default, shown on hover -->
                <div class="photo-remove-btn position-absolute d-flex justify-content-center align-items-center rounded-circle shadow bg-danger text-white" 
                     style="top: 8px; right: 8px; width: 28px; height: 28px; cursor: pointer; z-index: 10; opacity: 0; transition: opacity 0.2s ease;" 
                     onclick="removeBulkFile('${file.id}'); event.stopPropagation();" title="Remove Photo">
                     <i class="bi bi-x" style="font-size: 18px; line-height: 1;"></i>
                </div>
            `;

            // Filename layout and separate Star outside the image bounding box below it
            const titleRow = document.createElement('div');
            titleRow.className = 'd-flex justify-content-between align-items-center w-100 px-1 mt-2';

            const fileTitle = file.name.length > 18 ? file.name.substring(0, 15) + '...' : file.name;
            titleRow.innerHTML = `
                <div class="d-flex align-items-center flex-grow-1 overflow-hidden me-2">
                    <span class="badge rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.7rem; background-color: #3A9AFF; color: white; font-weight: 600;">${index + 1}</span>
                    <span class="text-dark fw-medium text-truncate" style="font-size: 0.85rem;">${fileTitle}</span>
                </div>
                <div class="cursor-pointer ms-1" onclick="setAsCover('${file.id}'); event.stopPropagation();" title="Set as Primary">
                    ${starIcon}
                </div>
            `;

            col.appendChild(imgContainer);
            col.appendChild(titleRow);

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
        if (isBindMode) {
            coverFileId = null; // Clear selection
            renderImageGrid(); // Remove badge
        }
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
        this.classList.add('drag-active-card');
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
        this.classList.remove('drag-active-card');
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
            updateBulkUI();
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

            updateBulkUI();
            updateButtons();
        }
    };

    // Helper to save current form data to the object
    function saveCurrentFormData() {
        if (isBindMode) return; // Skip in Bind Mode as metadata is globally shared
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
