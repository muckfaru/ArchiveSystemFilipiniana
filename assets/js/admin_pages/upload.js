/**
 * Upload Page Logic
 * Archive System - Quezon City Public Library
 */

console.log('✅ Upload script loaded - Version 2.0');
console.log('APP_URL:', typeof APP_URL !== 'undefined' ? APP_URL : 'NOT DEFINED');

// --- Global State ---
let bulkFiles = []; // Array of objects
let isBulkMode = false;
let isBindMode = false; // "Bind as Book" mode (Bulk Images)
let activeFileIndex = -1; // Current tab index
let selectedFile = null;
let originalFormData = {}; // Store initial values for dirty checking
let coverFileId = null; // ID of the file selected as cover/thumbnail in Bind Mode
let draftSaveTimeout = null;
let singleSelectionAction = 'change';
const ALLOWED_UPLOAD_EXTENSIONS = ['pdf', 'epub', 'mobi', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'];
const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'epub', 'mobi', 'txt'];
const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'];
const ALLOWED_FILE_TYPES_LABEL = 'PDF, EPUB, MOBI, TXT, JPG, JPEG, PNG, WEBP, TIF, TIFF';

document.addEventListener('DOMContentLoaded', function () {
    // FIX: BUG 1 - Add defensive guard for APP_URL
    if (typeof APP_URL === 'undefined') {
        console.error('❌ CRITICAL: APP_URL is not defined! Upload functionality will not work.');
        return;
    }

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
    const publicationDateInput = document.getElementById('publication_date');

    function getFileExtension(file) {
        return (file?.name || '').split('.').pop().toLowerCase();
    }

    function isImageExtension(extension) {
        return ALLOWED_IMAGE_EXTENSIONS.includes(extension);
    }

    function isDocumentExtension(extension) {
        return ALLOWED_DOCUMENT_EXTENSIONS.includes(extension);
    }

    function getInvalidFileTypeMessage(fileName, allowedLabel = ALLOWED_FILE_TYPES_LABEL) {
        return `"${fileName}" is not a supported file type. Accepted file types: ${allowedLabel}.`;
    }

    function showSingleFileTypeError(message) {
        const previewError = document.getElementById('previewError');
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        const dropZoneContainer = document.getElementById('dropZoneContainer');
        const previewFilename = document.getElementById('previewFilename');
        const previewSize = document.getElementById('previewSize');

        selectedFile = null;
        if (fileInput) {
            fileInput.value = '';
        }

        if (selectedFilePreview) {
            selectedFilePreview.style.display = 'none';
        }

        if (dropZoneContainer && document.querySelector('input[name="action"]')?.value !== 'edit') {
            dropZoneContainer.style.display = '';
        }

        if (previewFilename) {
            previewFilename.textContent = 'filename.ext';
        }

        if (previewSize) {
            previewSize.textContent = '0 KB';
        }

        if (previewError) {
            previewError.textContent = message;
            previewError.style.display = 'block';
        }

        showAlert('danger', message);
        updateButtons();
    }

    function clearSingleFileTypeError() {
        const previewError = document.getElementById('previewError');
        if (previewError) {
            previewError.textContent = '';
            previewError.style.display = 'none';
        }
    }

    function buildMetadataSnapshot() {
        const metadata = {
            title: document.getElementById('title')?.value?.trim() || '',
            publisher: document.getElementById('publisher')?.value?.trim() || '',
            publication_date: document.getElementById('publication_date')?.value?.trim() || '',
            edition: document.getElementById('edition')?.value?.trim() || '',
            category_id: document.getElementById('category_id')?.value?.trim() || '',
            language_id: document.getElementById('language_id')?.value?.trim() || '',
            page_count: document.getElementById('page_count')?.value?.trim() || '',
            volume_issue: document.getElementById('volume_issue')?.value?.trim() || '',
            description: document.getElementById('description')?.value?.trim() || '',
            tags: document.getElementById('keywordsHidden')?.value?.trim() || ''
        };

        document.querySelectorAll('.custom-field').forEach(field => {
            const fieldName = field.name;
            if (!fieldName) return;

            if (field.type === 'checkbox') {
                const checkedBoxes = document.querySelectorAll(`input[name="${fieldName}"]:checked`);
                metadata[fieldName] = Array.from(checkedBoxes).map(cb => cb.value);
            } else if (field.type === 'radio') {
                const selectedRadio = document.querySelector(`input[name="${fieldName}"]:checked`);
                metadata[fieldName] = selectedRadio ? selectedRadio.value : '';
            } else {
                metadata[fieldName] = field.value?.trim() || '';
            }
        });

        return metadata;
    }

    function createBulkFileObject(file, metadata = null) {
        return {
            id: Math.random().toString(36).substr(2, 9),
            file: file,
            name: file.name,
            size: file.size,
            type: file.type,
            status: 'pending',
            isDuplicate: false,
            metadata: metadata || {
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
    }

    function promoteSingleSelectionToBulk() {
        if (!selectedFile) {
            return;
        }

        const baseExt = getFileExtension(selectedFile);
        const baseIsImage = selectedFile.type.startsWith('image/') || isImageExtension(baseExt);
        const baseFileObject = createBulkFileObject(selectedFile, buildMetadataSnapshot());

        bulkFiles = [baseFileObject];
        selectedFile = null;

        if (fileInput) {
            fileInput.value = '';
        }

        if (baseIsImage) {
            isBindMode = true;
            isBulkMode = true;
            activeFileIndex = -1;
        } else {
            isBindMode = false;
            isBulkMode = true;
            activeFileIndex = 0;
        }

        enableBulkMode();
        updateBulkUI();

        if (!isBindMode) {
            loadFileData(0);
        } else {
            validateBindModeForm();
        }

        checkAllDuplicates();
    }

    // --- Initialization ---
    const isEditMode = document.querySelector('input[name="action"]').value === 'edit';

    function isValidPublicationDate(value) {
        const dateValue = (value || '').trim();
        if (!dateValue) return false;

        const fullDateMatch = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (fullDateMatch) {
            const year = Number(fullDateMatch[1]);
            const month = Number(fullDateMatch[2]);
            const day = Number(fullDateMatch[3]);
            const parsed = new Date(year, month - 1, day);

            return (
                parsed.getFullYear() === year &&
                parsed.getMonth() === month - 1 &&
                parsed.getDate() === day
            );
        }

        return false;
    }

    // FIX: MISSING FUNCTION - Add normalizePublicationDateForInput function
    /**
     * Normalizes publication date value for input field based on input type
     * @param {string} value - The date value to normalize
     * @param {string} inputType - The type of input field ('date', 'text', etc.)
     * @returns {string} - Normalized date value
     */
    function normalizePublicationDateForInput(value, inputType) {
        if (!value) return '';

        const dateValue = (value || '').trim();

        // If input type is 'date', ensure format is YYYY-MM-DD
        if (inputType === 'date') {
            // Check if already in correct format
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
                return dateValue;
            }

            // Try to parse and convert to YYYY-MM-DD
            const date = new Date(dateValue);
            if (!isNaN(date.getTime())) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
        }

        // For text inputs or if parsing fails, return as-is
        return dateValue;
    }

    // Capture Original State if Edit Mode
    if (isEditMode) {
        captureOriginalState();

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
        console.log('✅ File input listener attached');
    } else {
        console.error('❌ fileInput element not found during initialization!');
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
            console.log('🖱️ Drop zone clicked', e.target);
            // FIX: BUG 4 - Use broader check for child elements (buttons, links, inputs)
            if (e.target.closest('button, a, input')) {
                console.log('� Clicked on interactive element, skipping file picker');
                return;
            }
            // Trigger file input
            if (fileInput) {
                console.log('📂 Opening file picker...');
                fileInput.click();
            } else {
                console.error('❌ fileInput element not found!');
            }
        });
    } else {
        console.error('❌ dropZone element not found!');
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
                    window.location.href = APP_URL + '/dashboard';
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
            const hasFile = (fileInput.files.length > 0 || bulkFiles.length > 0 || selectedFile !== null);
            const isEdit = document.querySelector('input[name="action"]').value === 'edit';

            // Validation Messages
            let errors = [];

            let isValid = true;

            // Validate ONLY custom required fields that exist
            const customFields = document.querySelectorAll('.custom-field[data-required="true"]');
            customFields.forEach(field => {
                if (field.type === 'checkbox') {
                    // For checkboxes, check if at least one is checked
                    const checkboxGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                    if (checkboxGroup.length === 0) {
                        field.closest('.form-group-full, .form-group')?.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.closest('.form-group-full, .form-group')?.classList.remove('is-invalid');
                    }
                } else if (field.type === 'radio') {
                    // For radio buttons, check if one is selected
                    const radioGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                    if (radioGroup.length === 0) {
                        field.closest('.form-group-full, .form-group')?.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.closest('.form-group-full, .form-group')?.classList.remove('is-invalid');
                    }
                } else if (field.type === 'number') {
                    // Validate number fields
                    if (!field.value || field.value.trim() === '') {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else if (isNaN(field.value)) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                } else if (field.type === 'date') {
                    // Validate date fields
                    if (!field.value || field.value.trim() === '') {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                } else {
                    // Text, textarea, select
                    if (!field.value || field.value.trim() === '') {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                }
            });

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

    // Add event listeners for custom metadata fields
    const customFields = document.querySelectorAll('.custom-field');
    customFields.forEach(field => {
        field.addEventListener('input', function () {
            // Clear invalid state on input
            this.classList.remove('is-invalid');
            if (this.closest('.form-group-full, .form-group')) {
                this.closest('.form-group-full, .form-group').classList.remove('is-invalid');
            }
            if (isBindMode) {
                validateBindModeForm();
            } else if (activeFileIndex !== -1) {
                saveCurrentFormData();
                // Update tabs to show metadata preview in real-time
                renderTabs();
            } else {
                updateButtons();
            }
        });
        field.addEventListener('change', function () {
            this.classList.remove('is-invalid');
            if (this.closest('.form-group-full, .form-group')) {
                this.closest('.form-group-full, .form-group').classList.remove('is-invalid');
            }
            if (isBindMode) {
                validateBindModeForm();
            } else if (activeFileIndex !== -1) {
                saveCurrentFormData();
                // Update tabs to show metadata preview in real-time
                renderTabs();
            } else {
                updateButtons();
            }
        });
    });

    // Helper to save draft to session
    function saveDraft() {
        if (draftSaveTimeout) clearTimeout(draftSaveTimeout);
        draftSaveTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'save_draft');

            // Get all custom fields
            const fields = document.querySelectorAll('.custom-field');
            fields.forEach(field => {
                if (field.type === 'checkbox') {
                    if (field.checked) {
                        formData.append(field.name, field.value);
                    }
                } else if (field.type === 'radio') {
                    if (field.checked) {
                        formData.append(field.name, field.value);
                    }
                } else if (field.value && field.value.trim() !== '') {
                    formData.append(field.name, field.value);
                }
            });

                fetch(APP_URL + '/upload', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(response => response.json())
                .then(data => console.log('📝 Draft saved:', data))
                .catch(err => console.error('❌ Draft save failed:', err));
        }, 1000); // 1 second debounce
    }

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
            // Compare current values with original for custom fields
            const customFields = document.querySelectorAll('.custom-field');
            for (let field of customFields) {
                const fieldName = field.name;
                const originalValue = originalFormData[fieldName] || '';
                let currentValue = '';

                if (field.type === 'checkbox') {
                    const checkedBoxes = document.querySelectorAll(`input[name="${fieldName}"]:checked`);
                    currentValue = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                } else if (field.type === 'radio') {
                    const selectedRadio = document.querySelector(`input[name="${fieldName}"]:checked`);
                    currentValue = selectedRadio ? selectedRadio.value : '';
                } else {
                    currentValue = field.value?.trim() || '';
                }

                if (currentValue !== originalValue) return true;
            }

            return false;
        } else {
            // Upload Mode: Dirty if any custom field has value
            const customFields = document.querySelectorAll('.custom-field');
            for (let field of customFields) {
                if (field.type === 'checkbox') {
                    const checkedBoxes = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                    if (checkedBoxes.length > 0) return true;
                } else if (field.type === 'radio') {
                    const selectedRadio = document.querySelector(`input[name="${field.name}"]:checked`);
                    if (selectedRadio) return true;
                } else {
                    if (field.value && field.value.trim() !== '') return true;
                }
            }
            return false;
        }
    }

    function captureOriginalState() {
        originalFormData = {};

        // Capture custom field values
        const customFields = document.querySelectorAll('.custom-field');
        customFields.forEach(field => {
            const fieldName = field.name;
            if (field.type === 'checkbox') {
                const checkedBoxes = document.querySelectorAll(`input[name="${fieldName}"]:checked`);
                originalFormData[fieldName] = Array.from(checkedBoxes).map(cb => cb.value).join(',');
            } else if (field.type === 'radio') {
                const selectedRadio = document.querySelector(`input[name="${fieldName}"]:checked`);
                originalFormData[fieldName] = selectedRadio ? selectedRadio.value : '';
            } else {
                originalFormData[fieldName] = field.value?.trim() || '';
            }
        });
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
                window.location.href = APP_URL + '/dashboard';
            } else {
                // Clear session draft on discard
                fetch(APP_URL + '/upload', {
                    method: 'POST',
                    body: new URLSearchParams({ 'action': 'clear_draft' }),
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' }
                }).finally(() => {
                    if (typeof window.resetForm === 'function') window.resetForm();
                });
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

                        // Append CUSTOM METADATA FIELDS
                        const customFields = document.querySelectorAll('.custom-field');
                        customFields.forEach(field => {
                            const fieldName = field.name;
                            const savedValue = m[fieldName];

                            if (field.type === 'checkbox') {
                                // For checkboxes, send as array
                                if (Array.isArray(savedValue) && savedValue.length > 0) {
                                    savedValue.forEach(val => {
                                        singleFormData.append(`${fieldName}[]`, val);
                                    });
                                }
                            } else if (savedValue !== undefined && savedValue !== '') {
                                // For other field types
                                singleFormData.append(fieldName, savedValue);
                            }
                        });

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
                        window.location.href = APP_URL + '/dashboard?success=upload&count=' + successCount;
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

            const rawBody = await response.text();
            let result = null;

            try {
                result = rawBody ? JSON.parse(rawBody) : null;
            } catch (parseError) {
                result = null;
            }

            if (result && typeof result === 'object') {
                if (result.success) {
                    window.isNavigatingAway = true;
                    const isEdit = document.querySelector('input[name="action"]').value === 'edit';
                    if (isEdit) {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard?success=edit';
                    } else {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard?success=upload';
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
                        window.location.href = APP_URL + '/dashboard?success=edit';
                    } else {
                        window.hasUnsavedChanges = function () { return false; };
                        window.onbeforeunload = null;
                        window.location.href = APP_URL + '/dashboard?success=upload';
                    }
                } else {
                    throw new Error(rawBody || ('Server returned ' + response.status));
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
            console.log('📂 Browse thumbnail button clicked');
            e.preventDefault(); // Stop form submit or other default actions
            e.stopPropagation(); // Stop bubbling to area
            if (thumbnailInput) {
                console.log('📂 Opening thumbnail file picker from browse button...');
                thumbnailInput.click();
            } else {
                console.error('❌ thumbnailInput not found!');
            }
        });
    } else {
        console.warn('⚠️ browseThumbnailBtn element not found (may be hidden in edit mode)');
    }

    // 2. Thumbnail Area Click (Delegation)
    if (thumbnailArea && thumbnailInput) {
        thumbnailArea.addEventListener('click', function (e) {
            console.log('🖼️ Thumbnail area clicked', e.target);
            // Check if we clicked the remove button or its children
            if (e.target.closest('#removeThumbnailBtn')) {
                console.log('🗑️ Remove button clicked, skipping file picker');
                return;
            }

            // Check if we clicked the browse button 
            if (e.target.closest('#browseThumbnailBtn')) {
                console.log('📂 Browse button clicked, letting its handler take over');
                // Let the browse button listener handle it
                return;
            }

            // Trigger the input regardless of whether image is shown or not
            console.log('📂 Opening thumbnail file picker...');
            thumbnailInput.click();
        });
    } else {
        if (!thumbnailArea) console.error('❌ thumbnailArea element not found!');
        if (!thumbnailInput) console.error('❌ thumbnailInput element not found!');
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
            if (!file) {
                console.warn('⚠️ No thumbnail file selected');
                return;
            }

            console.log('📸 Thumbnail selected:', file.name, file.size, 'bytes', file.type);

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

    // ── Title prefill from filename (fallback) ────────────────────────────────
    /**
     * If the title field is still empty after metadata extraction,
     * fill it with the cleaned-up filename (without extension).
     * Replaces underscores/dashes with spaces and title-cases.
     *
     * @param {string} filename - The original file name (with extension)
     */
    function prefillTitleFromFilename(filename) {
        // Build the field map to find the title field
        const fieldMap = {};
        document.querySelectorAll('.form-group label, .form-group-full label').forEach(lbl => {
            const input = lbl.closest('.form-group, .form-group-full')?.querySelector('input, textarea, select');
            if (input && input.id) {
                const label = lbl.textContent.replace(/\*/g, '').trim().toLowerCase();
                fieldMap[label] = input.id;
            }
        });
        const titleFieldId = fieldMap['title'] || 'title';
        const titleEl = document.getElementById(titleFieldId);

        if (titleEl && !titleEl.value.trim()) {
            // Remove extension
            const baseName = filename.replace(/\.[^.]+$/, '');
            // Replace underscores and dashes with spaces, then title-case
            const cleanName = baseName
                .replace(/[_]/g, ' ')
                .replace(/[-]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (cleanName) {
                titleEl.value = cleanName;
                titleEl.dispatchEvent(new Event('input', { bubbles: true }));
                console.log('📝 Title pre-filled from filename:', cleanName);
            }
        }
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
                const elType = el ? el.type : (publicationDateInput?.type || 'date');

                const isDateField = id === 'publication_date' || (el && elType === 'date');
                const formattedValue = isDateField
                    ? normalizePublicationDateForInput(value, elType)
                    : value;

                // Update bulk state too
                if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                    if (metaKey && !bulkFiles[bulkIdx].metadata[metaKey]) {
                        bulkFiles[bulkIdx].metadata[metaKey] = formattedValue;
                    }
                    if (id && !bulkFiles[bulkIdx].metadata[id]) {
                        bulkFiles[bulkIdx].metadata[id] = formattedValue;
                    }
                }

                // Only update DOM if it's the active tab (or single mode)
                if (bulkIdx === null || bulkIdx === activeFileIndex) {
                    if (el && !el.value.trim()) {
                        el.value = formattedValue;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            };

            // Use publisher; fall back to creator (author) only if publisher blank
            const publisher = m.publisher || m.creator || '';

            // ── Smart field finder: match form fields by label ──
            // Form fields use id="field_XX", so we find them by their label text
            const fieldMap = {};
            document.querySelectorAll('.form-group label, .form-group-full label').forEach(lbl => {
                const input = lbl.closest('.form-group, .form-group-full')?.querySelector('input, textarea, select');
                if (input && input.id) {
                    const label = lbl.textContent.replace(/\*/g, '').trim().toLowerCase();
                    fieldMap[label] = input.id;
                }
            });

            // Fill by label match (falls back to hardcoded id if no label match)
            const findFieldId = (labels) => {
                for (const l of labels) {
                    if (fieldMap[l]) return fieldMap[l];
                }
                return null;
            };

            const titleFieldId = findFieldId(['title']) || 'title';
            const publisherFieldId = findFieldId(['publisher']) || 'publisher';
            const dateFieldId = findFieldId(['date', 'date published', 'publication date', 'date issued']) || 'publication_date';
            const descFieldId = findFieldId(['description']) || 'description';

            fillField(titleFieldId, m.title, 'title');
            fillField(publisherFieldId, publisher, 'publisher');
            fillField(dateFieldId, m.publication_date, 'publication_date');
            fillField(descFieldId, m.description, 'description');

            // Keywords → tags (find by label or fall back to hidden input)
            const tagsFieldId = findFieldId(['tags', 'keywords']);
            if (m.keywords) {
                // Try the tags widget hidden input first
                const hiddenKw = tagsFieldId
                    ? document.getElementById(tagsFieldId + '_hidden')
                    : document.getElementById('keywordsHidden');

                // Update bulk state too
                if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                    if (!bulkFiles[bulkIdx].metadata.tags) bulkFiles[bulkIdx].metadata.tags = m.keywords;
                    if (tagsFieldId && !bulkFiles[bulkIdx].metadata[tagsFieldId]) bulkFiles[bulkIdx].metadata[tagsFieldId] = m.keywords;
                }

                // Only visually update if active tab or single file
                if (bulkIdx === null || bulkIdx === activeFileIndex) {
                    if (hiddenKw && !hiddenKw.value.trim()) {
                        hiddenKw.value = m.keywords;
                        const pillsFieldName = hiddenKw.id.replace('_hidden', '');
                        if (typeof addTagChip === 'function') {
                            m.keywords.split(',').map(t => t.trim()).filter(Boolean).forEach(tag => {
                                addTagChip(pillsFieldName, tag);
                            });
                        } else if (typeof setTags === 'function') {
                            setTags(m.keywords.split(',').map(t => t.trim()).filter(Boolean));
                        }
                    }
                }
            }

            // Language: auto-select the matched DB language_id
            if (data.languageId) {
                const langFieldId = findFieldId(['language']) || 'language_id';
                const langEl = document.getElementById(langFieldId);

                // Update bulk state too
                if (bulkIdx !== null && bulkFiles[bulkIdx]) {
                    if (!bulkFiles[bulkIdx].metadata.language_id) bulkFiles[bulkIdx].metadata.language_id = String(data.languageId);
                    if (langFieldId && !bulkFiles[bulkIdx].metadata[langFieldId]) bulkFiles[bulkIdx].metadata[langFieldId] = String(data.languageId);
                }

                // Only visually update if active tab or single file
                if (bulkIdx === null || bulkIdx === activeFileIndex) {
                    if (langEl && !langEl.value) {
                        langEl.value = data.languageId;
                        langEl.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }

            // Re-validate: refresh tab statuses and counters
            if (bulkIdx !== null) {
                // Trigger status recalculation via field input event (saveCurrentFormData path)
                const titleInp = document.getElementById(titleFieldId);
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

                // FIX: BUG 5 - Properly check if thumbnail is empty (src is '#' or empty or ends with '#')
                const isThumbnailEmpty = !thumbPreview || !thumbPreview.src || thumbPreview.src.endsWith('#') || thumbPreview.src === window.location.href;

                if (thumbPreview && isThumbnailEmpty && !hasCustomThumb) {
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
            const titleEl = document.getElementById(titleFieldId);
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
        console.log('📁 Files selected:', files.length, 'file(s)');
        if (files.length > 0) {
            console.log('First file:', files[0].name, files[0].size, 'bytes');
        }
        handleFiles(files);
        singleSelectionAction = 'change';
    }

    function handleBulkFileAdditionFromInput(e) {
        addBulkFiles(e.target.files);
    }

    function handleFiles(files) {
        console.log('🔄 handleFiles called with', files.length, 'file(s)');
        if (files.length === 0) {
            console.warn('⚠️ No files to handle');
            return;
        }

        const fileList = Array.from(files);
        const invalidFiles = fileList.filter(file => {
            const extension = getFileExtension(file);
            return !ALLOWED_UPLOAD_EXTENSIONS.includes(extension);
        });

        if (invalidFiles.length > 0) {
            const firstInvalid = invalidFiles[0];
            showSingleFileTypeError(getInvalidFileTypeMessage(firstInvalid.name));
            return;
        }

        clearSingleFileTypeError();

        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        console.log('📝 Mode:', isEdit ? 'Edit' : 'Upload');

        if (!isEdit && singleSelectionAction === 'add' && selectedFile && !isBulkMode) {
            promoteSingleSelectionToBulk();
        }

        // FIX: BUG 2 - Detect single non-image file and route as single upload
        // Only route to bulk mode if: multiple files OR starting fresh with images
        const firstFile = files[0];
        const firstFileExt = getFileExtension(firstFile);
        const isImageFile = firstFile.type.startsWith('image/') || isImageExtension(firstFileExt);
        const isSingleNonImageFile = files.length === 1 && !isImageFile && bulkFiles.length === 0;

        if (!isEdit && !isSingleNonImageFile) {
            // Check types for Mode Detection IF starting fresh
            if (bulkFiles.length === 0) {
                if (isImageFile) {
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
            // Single File (Edit mode OR single non-image file in upload mode)
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

            // Auto-fill metadata from EPUB/MOBI/PDF (async)
            // Then fallback to filename if title is still empty
            extractAndPrefillMeta(file, null).then(() => {
                prefillTitleFromFilename(file.name);
            }).catch(() => {
                prefillTitleFromFilename(file.name);
            });

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
            // Get file extension
            const fileExt = getFileExtension(file);
            const isImageFile = file.type.startsWith('image/') || isImageExtension(fileExt);
            const isDocumentFile = isDocumentExtension(fileExt);

            if (!ALLOWED_UPLOAD_EXTENSIONS.includes(fileExt)) {
                const message = getInvalidFileTypeMessage(file.name);
                showAlert('danger', message);
                return;
            }

            // Enforce Mode Consistency
            if (isBindMode && !isImageFile) {
                showAlert('warning', `Skipped "${file.name}": Only image files are allowed in Photo Mode. Accepted image types: JPG, JPEG, PNG, WEBP, TIF, TIFF.`);
                return;
            }
            if (!isBindMode && isImageFile) {
                showAlert('warning', `Skipped "${file.name}": Only document files are allowed in Document Mode. Accepted document types: PDF, EPUB, MOBI, TXT.`);
                return;
            }

            // Prevent exact duplicate in current list
            if (!bulkFiles.some(f => f.name === file.name && f.size === file.size)) {
                const fileObj = createBulkFileObject(file);
                bulkFiles.push(fileObj);
                addedCount++;

                // Auto-fill metadata from EPUB/MOBI/PDF for each bulk doc file
                const bulkIdx = bulkFiles.length - 1;
                const bExt = file.name.split('.').pop().toLowerCase();
                if (['epub', 'mobi', 'pdf'].includes(bExt)) {
                    // Run after UI updates (async, non-blocking)
                    const capturedIdx = bulkIdx;
                    const capturedName = file.name;
                    setTimeout(() => {
                        extractAndPrefillMeta(file, capturedIdx).then(() => {
                            // Fallback: fill title from filename if still empty
                            if (bulkFiles[capturedIdx] && !bulkFiles[capturedIdx].metadata.title) {
                                const baseName = capturedName.replace(/\.[^.]+$/, '');
                                const cleanName = baseName.replace(/[_-]/g, ' ').replace(/\s+/g, ' ').trim();
                                if (cleanName) {
                                    bulkFiles[capturedIdx].metadata.title = cleanName;
                                    if (activeFileIndex === capturedIdx) {
                                        prefillTitleFromFilename(capturedName);
                                    }
                                    if (typeof renderTabs === 'function') renderTabs();
                                    if (typeof updateBulkControls === 'function') updateBulkControls();
                                }
                            }
                        }).catch(() => {
                            // Extraction failed — use filename as title
                            if (bulkFiles[capturedIdx] && !bulkFiles[capturedIdx].metadata.title) {
                                const baseName = capturedName.replace(/\.[^.]+$/, '');
                                const cleanName = baseName.replace(/[_-]/g, ' ').replace(/\s+/g, ' ').trim();
                                if (cleanName) {
                                    bulkFiles[capturedIdx].metadata.title = cleanName;
                                    if (activeFileIndex === capturedIdx) {
                                        prefillTitleFromFilename(capturedName);
                                    }
                                    if (typeof renderTabs === 'function') renderTabs();
                                    if (typeof updateBulkControls === 'function') updateBulkControls();
                                }
                            }
                        });
                    }, 50);
                } else {
                    // Non-extractable file types: use filename as title directly
                    const baseName = file.name.replace(/\.[^.]+$/, '');
                    const cleanName = baseName.replace(/[_-]/g, ' ').replace(/\s+/g, ' ').trim();
                    if (cleanName) {
                        bulkFiles[bulkIdx].metadata.title = cleanName;
                    }
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
        const bulkStatsContainer = document.getElementById('bulkStatsContainer');

        if (dropZone) dropZone.style.display = 'none';
        if (bulkContainer) bulkContainer.style.display = 'block';
        if (filePreview) filePreview.style.display = 'none';
        if (bulkStatsContainer) bulkStatsContainer.style.display = 'none'; // Hide the old stats container

        updateButtons();
    }

    function loadFileData(index) {
        if (isBindMode) return; // Skip in Bind Mode as metadata is shared
        const file = bulkFiles[index];
        if (!file) return;

        window.isProgrammaticDataLoad = true;
        try {
            // Populate Form Fields (Safe Set)
            const safelySetValue = (id, value) => {
                const el = document.getElementById(id);
                if (!el) return;
                if (id === 'publication_date') {
                    el.value = normalizePublicationDateForInput(value, el.type);
                    return;
                }
                el.value = value || '';
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
                const mainTagInput = document.getElementById('tagInput');
                if (mainTagInput) mainTagInput.value = '';
            }

            // Load CUSTOM METADATA FIELDS from file's metadata
            const customFields = document.querySelectorAll('.custom-field');
            customFields.forEach(field => {
                const fieldName = field.name;
                const savedValue = file.metadata[fieldName];

                if (field.type === 'checkbox') {
                    // For checkboxes, uncheck all first, then check saved values
                    const allCheckboxes = document.querySelectorAll(`input[name="${fieldName}"]`);
                    allCheckboxes.forEach(cb => cb.checked = false);

                    if (Array.isArray(savedValue)) {
                        savedValue.forEach(val => {
                            const checkbox = document.querySelector(`input[name="${fieldName}"][value="${val}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                } else if (field.type === 'radio') {
                    // For radio buttons, uncheck all first, then check saved value
                    const allRadios = document.querySelectorAll(`input[name="${fieldName}"]`);
                    allRadios.forEach(rb => rb.checked = false);

                    if (savedValue) {
                        const radio = document.querySelector(`input[name="${fieldName}"][value="${savedValue}"]`);
                        if (radio) radio.checked = true;
                    }
                } else {
                    // For other field types (text, textarea, number, date, select, and hidden tags)
                    field.value = savedValue || '';

                    // If this is a custom tags field (hidden input), reset its visual pills and text input
                    if (field.id && field.id.endsWith('_hidden')) {
                        const baseName = field.id.substring(0, field.id.length - 7); // remove '_hidden'
                        const pillsContainer = document.getElementById(baseName + '_pills');
                        const textInput = document.getElementById(baseName + '_input');

                        if (pillsContainer) {
                            pillsContainer.innerHTML = '';
                            if (savedValue) {
                                const tagsArray = savedValue.split(',').map(t => t.trim()).filter(Boolean);
                                tagsArray.forEach(tagText => {
                                    const chip = document.createElement('span');
                                    chip.className = 'tag-chip';

                                    const div = document.createElement('div');
                                    div.appendChild(document.createTextNode(tagText));
                                    const escapedTag = div.innerHTML;

                                    chip.innerHTML = `${escapedTag}<button type="button" class="tag-chip-remove" onclick="removeTagChip(this,'${baseName}')" title="Remove"><i class="bi bi-x"></i></button>`;
                                    pillsContainer.appendChild(chip);
                                });
                            }
                        }
                        if (textInput) {
                            textInput.value = '';
                        }
                    }
                }
            });

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
        } finally {
            window.isProgrammaticDataLoad = false;
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
            let dotClass = 'pending';

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
        singleSelectionAction = 'change';
        clearSingleFileTypeError();

        // Hide file badge, show drop zone
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        const dropZoneContainer = document.getElementById('dropZoneContainer');
        const previewFilename = document.getElementById('previewFilename');
        const previewSize = document.getElementById('previewSize');

        if (selectedFilePreview) selectedFilePreview.style.display = 'none';
        if (dropZoneContainer) dropZoneContainer.style.display = 'block';
        if (previewFilename) previewFilename.textContent = 'filename.pdf';
        if (previewSize) previewSize.textContent = '0 KB';

        // Check for edit mode
        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';
        const editModeIndicator = document.getElementById('editModeIndicator');
        if (isEdit && editModeIndicator) {
            editModeIndicator.style.display = '';
        }

        updateButtons();
    }

    /**
     * Centralized helper function to clear all file state
     * This ensures consistent state management across the application
     * and prevents "ghost files" from persisting in memory or UI
     */
    function clearAllFileState() {
        console.log('🧹 Clearing all file state...');

        // 1. Clear state variables FIRST (critical order)
        selectedFile = null;
        singleSelectionAction = 'change';
        bulkFiles = [];
        activeFileIndex = -1;
        isBulkMode = false;
        isBindMode = false;
        coverFileId = null;

        // 2. Clear HTML file inputs
        if (fileInput) fileInput.value = '';
        if (bulkFileInput) bulkFileInput.value = '';

        // 3. Clear all file card containers from DOM
        const bulkTabs = document.getElementById('bulkTabs');
        if (bulkTabs) bulkTabs.innerHTML = '';

        const fileTabs = document.getElementById('fileTabs');
        if (fileTabs) fileTabs.innerHTML = '';

        if (bulkFileTabs) bulkFileTabs.innerHTML = '';

        // 4. Hide bulk containers
        const bulkStatsContainer = document.getElementById('bulkStatsContainer');
        if (bulkStatsContainer) bulkStatsContainer.style.display = 'none';

        const bulkUploadContainer = document.getElementById('bulkUploadContainer');
        if (bulkUploadContainer) bulkUploadContainer.style.display = 'none';

        // 5. Reset all status counters to '0'
        const totalFilesCount = document.getElementById('totalFilesCount');
        const readyFilesCount = document.getElementById('readyFilesCount');
        const pendingFilesCount = document.getElementById('pendingFilesCount');
        if (totalFilesCount) totalFilesCount.textContent = '0';
        if (readyFilesCount) readyFilesCount.textContent = '0';
        if (pendingFilesCount) pendingFilesCount.textContent = '0';

        const totalFiles = document.getElementById('totalFiles');
        const readyFiles = document.getElementById('readyFiles');
        const pendingFiles = document.getElementById('pendingFiles');
        if (totalFiles) totalFiles.textContent = '0';
        if (readyFiles) readyFiles.textContent = '0';
        if (pendingFiles) pendingFiles.textContent = '0';

        // 6. Hide duplicate status message
        const dupStatus = document.getElementById('duplicateStatusContainer');
        if (dupStatus) dupStatus.style.display = 'none';

        // 7. Hide photo/document stats
        const photoMsg = document.getElementById('bulkPhotoInfoMessage');
        if (photoMsg) photoMsg.classList.add('d-none');

        const photoStats = document.getElementById('photoStatsWrapper');
        if (photoStats) photoStats.style.display = 'none';

        const docStats = document.getElementById('docStatsWrapper');
        if (docStats) docStats.style.display = 'none';

        // 8. Reset thumbnail state
        if (thumbnailPreview) {
            thumbnailPreview.src = '#';
            thumbnailPreview.style.display = 'none';
        }
        if (thumbnailPlaceholder) thumbnailPlaceholder.style.display = 'flex';
        if (removeThumbnailBtn) removeThumbnailBtn.style.display = 'none';

        // 9. Hide single file preview
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        if (selectedFilePreview) selectedFilePreview.style.display = 'none';

        // 10. Clear current file badge
        const badge = document.getElementById('currentFileName');
        if (badge) {
            badge.classList.add('d-none');
            badge.textContent = '';
        }

        console.log('✅ All file state cleared');
    }

    window.resetForm = function () {
        console.log('🔄 Resetting form...');

        // Reset form fields
        document.getElementById('uploadForm').reset();

        // Use centralized helper to clear all file state
        clearAllFileState();

        // Handle drop zone visibility based on edit mode
        const dropZoneContainer = document.getElementById('dropZoneContainer');
        const isEdit = document.querySelector('input[name="action"]')?.value === 'edit';

        if (!isEdit && dropZoneContainer) {
            dropZoneContainer.style.display = 'block';
        }

        const editModeIndicator = document.getElementById('editModeIndicator');
        if (isEdit && editModeIndicator) {
            editModeIndicator.style.display = '';
        }

        // Clear Tags
        const tagsContainer = document.getElementById('tagsContainer');
        if (tagsContainer) tagsContainer.innerHTML = '';
        if (typeof tags !== 'undefined') tags = [];

        const keywordsHidden = document.getElementById('keywordsHidden');
        if (keywordsHidden) keywordsHidden.value = '';

        // Remove slider buttons if any (Legacy cleanup)
        const tabsWrapper = document.querySelector('.tabs-container');
        if (tabsWrapper) {
            const prev = tabsWrapper.querySelector('.prev');
            const next = tabsWrapper.querySelector('.next');
            if (prev) prev.remove();
            if (next) next.remove();
        }

        // Reset mode toggle to individual
        if (modeIndividual) modeIndividual.checked = true;

        // Remove thumbnail using external function if available
        if (typeof removeThumbnail === 'function') removeThumbnail();

        // Clear tags using external function if available
        if (typeof clearTags === 'function') clearTags();

        // Re-enable/disable buttons based on new state
        updateButtons();

        console.log('✅ Form reset complete - bulkFiles cleared:', bulkFiles.length);
    }

    window.startAddFileMode = function () {
        singleSelectionAction = 'add';
        if (fileInput) {
            fileInput.click();
        }
    }

    window.startChangeFileMode = function () {
        singleSelectionAction = 'change';
        if (fileInput) {
            fileInput.click();
        }
    }

    function updateButtons() {
        // FIX: BUG 3 - Correctly set hasFile variable for both single and bulk states
        const hasFile = ((fileInput && fileInput.files && fileInput.files.length > 0) || bulkFiles.length > 0 || selectedFile !== null);
        const isEdit = document.querySelector('input[name="action"]').value === 'edit';

        // Check Dirty State for Discard Button
        const isDirty = window.hasUnsavedChanges();

        let shouldEnable = false;

        console.log('🔍 updateButtons called - hasFile:', hasFile, 'isEdit:', isEdit, 'isDirty:', isDirty);

        if (activeFileIndex !== -1) {
            // Bulk Mode
            const readyCount = bulkFiles.filter(f => f.status === 'ready').length;
            shouldEnable = readyCount > 0;
        } else {
            // Single Mode Logic - Check ONLY custom required fields
            let allRequiredFieldsFilled = true;
            const customFields = document.querySelectorAll('.custom-field[data-required="true"]');

            console.log('🔍 Found', customFields.length, 'required custom fields');

            if (customFields.length === 0) {
                // No required fields defined, form is valid
                allRequiredFieldsFilled = true;
            } else {
                customFields.forEach(field => {
                    // Skip fields that are not visible, but always validate type="hidden" inputs
                    // (e.g. tags fields use a hidden input to store their value)
                    if (field.type !== 'hidden' && field.offsetParent === null) {
                        console.log('⏭️ Skipping hidden field:', field.name);
                        return; // Field is not visible, skip validation
                    }

                    if (field.type === 'checkbox') {
                        const checkboxGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                        if (checkboxGroup.length === 0) {
                            console.log('❌ Required checkbox field empty:', field.name);
                            allRequiredFieldsFilled = false;
                        }
                    } else if (field.type === 'radio') {
                        const radioGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                        if (radioGroup.length === 0) {
                            console.log('❌ Required radio field empty:', field.name);
                            allRequiredFieldsFilled = false;
                        }
                    } else {
                        if (!field.value || field.value.trim() === '') {
                            console.log('❌ Required field empty:', field.name, 'type:', field.type);
                            allRequiredFieldsFilled = false;
                        }
                    }
                });
            }

            console.log('🔍 All required fields filled:', allRequiredFieldsFilled);

            // Single file specific: Check if duplicate error exists
            const hasError = document.getElementById('previewError')?.style.display !== 'none' && document.getElementById('previewError')?.textContent !== '';

            const isFormValid = allRequiredFieldsFilled;

            console.log('🔍 hasError:', hasError, 'isFormValid:', isFormValid);

            if (isEdit) {
                // Edit Mode: Enable if (Dirty OR New File Selected) AND Form Valid AND No Error
                const newFileSelected = (fileInput && fileInput.files && fileInput.files.length > 0);
                shouldEnable = (isDirty || newFileSelected) && isFormValid && !hasError;
                console.log('🔍 Edit mode - shouldEnable:', shouldEnable, '(isDirty:', isDirty, 'newFileSelected:', newFileSelected, ')');
            } else {
                // Upload Mode: Enable ONLY if File Selected AND Form Valid AND No Error
                shouldEnable = hasFile && isFormValid && !hasError;
                console.log('🔍 Upload mode - shouldEnable:', shouldEnable, '(hasFile:', hasFile, 'isFormValid:', isFormValid, 'hasError:', hasError, ')');
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
                    const newFileSelected = (fileInput && fileInput.files && fileInput.files.length > 0);
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

        // Check ONLY custom required fields that exist in the DOM
        let allRequiredFieldsFilled = true;
        const customFields = document.querySelectorAll('.custom-field[data-required="true"]');

        if (customFields.length === 0) {
            // No required fields defined, file is ready
            allRequiredFieldsFilled = true;
        } else {
            customFields.forEach(field => {
                const fieldName = field.name;
                const fieldValue = m[fieldName];

                if (field.type === 'checkbox') {
                    // For checkboxes, check if at least one value exists
                    if (!fieldValue || (Array.isArray(fieldValue) && fieldValue.length === 0)) {
                        allRequiredFieldsFilled = false;
                    }
                } else {
                    // For other field types, check if value exists and is not empty
                    if (!fieldValue || (typeof fieldValue === 'string' && fieldValue.trim() === '')) {
                        allRequiredFieldsFilled = false;
                    }
                }
            });
        }

        if (file.isDuplicate) {
            file.status = 'error'; // Duplicate takes precedence
        } else if (allRequiredFieldsFilled) {
            file.status = 'ready';
        } else {
            file.status = 'pending';
        }

        updateBulkUI();
        updateButtons();
    }

    window.validateBindModeForm = function () {
        const hasFiles = bulkFiles.length > 0;

        // Check ONLY custom required fields that exist in the DOM
        let allRequiredFieldsFilled = true;
        const customFields = document.querySelectorAll('.custom-field[data-required="true"]');

        if (customFields.length === 0) {
            // No required fields defined, form is valid if has files
            allRequiredFieldsFilled = true;
        } else {
            customFields.forEach(field => {
                if (field.type === 'checkbox') {
                    // For checkboxes, check if at least one is checked
                    const checkboxGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                    if (checkboxGroup.length === 0) {
                        allRequiredFieldsFilled = false;
                    }
                } else if (field.type === 'radio') {
                    // For radio buttons, check if one is selected
                    const radioGroup = document.querySelectorAll(`input[name="${field.name}"]:checked`);
                    if (radioGroup.length === 0) {
                        allRequiredFieldsFilled = false;
                    }
                } else {
                    // For other field types
                    if (!field.value || field.value.trim() === '') {
                        allRequiredFieldsFilled = false;
                    }
                }
            });
        }

        const isValid = hasFiles && allRequiredFieldsFilled;

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
                // Re-render tabs to show updated metadata preview
                if (!isBindMode) {
                    renderTabs();
                }
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

        console.log('🗑️ removeFile called for index:', index);

        const fileToRemove = bulkFiles[index];

        // Remove from array
        bulkFiles.splice(index, 1);

        console.log('📊 Files remaining:', bulkFiles.length);

        // Adjust active index
        if (bulkFiles.length === 0) {
            // No files left -> Reset completely using centralized helper
            console.log('🔄 Last file removed, resetting form...');
            resetForm();
        } else {
            // Files still remain - update UI and state
            if (index === activeFileIndex) {
                // Removed active file -> Switch to first file
                activeFileIndex = 0;
                loadFileData(0);
            } else if (index < activeFileIndex) {
                // Removed file before active -> decrement active index
                activeFileIndex--;
            }
            // If index > activeFileIndex, no change needed

            // Re-render tabs to reflect removal
            renderTabs();

            // Update bulk controls (stats, counters)
            updateBulkControls();

            // Update UI to show current file
            updateBulkUI();

            // Update button states
            updateButtons();
        }
    };

    // Helper to save current form data to the object
    function saveCurrentFormData() {
        if (window.isProgrammaticDataLoad) return; // Prevent saving stale DOM data during tab switch
        if (isBindMode) return; // Skip in Bind Mode as metadata is globally shared
        if (activeFileIndex === -1 || !bulkFiles[activeFileIndex]) return;

        const f = bulkFiles[activeFileIndex];
        const m = f.metadata;

        // Save tags from hidden input
        const keywordsHidden = document.getElementById('keywordsHidden');
        if (keywordsHidden) {
            m.tags = keywordsHidden.value?.trim() || '';
        }

        // Save ONLY custom metadata fields that exist in the DOM
        const customFields = document.querySelectorAll('.custom-field');
        customFields.forEach(field => {
            const fieldName = field.name;
            if (field.type === 'checkbox') {
                // For checkboxes, collect all checked values
                const checkedBoxes = document.querySelectorAll(`input[name="${fieldName}"]:checked`);
                const values = Array.from(checkedBoxes).map(cb => cb.value);
                m[fieldName] = values;
            } else if (field.type === 'radio') {
                // For radio buttons, get the selected value
                const selectedRadio = document.querySelector(`input[name="${fieldName}"]:checked`);
                m[fieldName] = selectedRadio ? selectedRadio.value : '';
            } else {
                // For other field types (text, textarea, number, date, select)
                m[fieldName] = field.value?.trim() || '';
            }
        });

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
