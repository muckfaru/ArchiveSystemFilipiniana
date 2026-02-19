/**
 * Bulk File Metadata Management
 * Handles saving and loading metadata for individual files in bulk upload mode
 */

// Save current form data to active file's metadata
function saveCurrentMetadata() {
    if (!isBulkMode || bulkFiles.length === 0) return;

    const currentFile = bulkFiles[activeFileIndex];
    if (!currentFile) return;

    // Save form values to file's metadata
    currentFile.metadata.title = document.getElementById('archive_title')?.value || '';
    currentFile.metadata.publisher = document.getElementById('publisher')?.value || '';
    currentFile.metadata.datePublished = document.getElementById('date_published')?.value || '';
    currentFile.metadata.edition = document.getElementById('edition')?.value || '';
    currentFile.metadata.category = document.getElementById('category_id')?.value || '';
    currentFile.metadata.language = document.getElementById('language_id')?.value || '';
    currentFile.metadata.pageCount = document.getElementById('page_count')?.value || '';
    currentFile.metadata.volumeIssue = document.getElementById('volume_issue')?.value || '';
    currentFile.metadata.description = document.getElementById('description')?.value || '';

    // Save tags
    const tagsDisplay = document.getElementById('tagsDisplay');
    if (tagsDisplay) {
        const tagElements = tagsDisplay.querySelectorAll('.tag-item');
        currentFile.metadata.tags = Array.from(tagElements).map(tag =>
            tag.textContent.replace('×', '').trim()
        );
    }

    // Save thumbnail reference
    const thumbnailInput = document.getElementById('thumbnailInput');
    if (thumbnailInput && thumbnailInput.files[0]) {
        currentFile.thumbnail = thumbnailInput.files[0];
    }
}

// Load metadata from selected file into form
function loadMetadata(index) {
    const file = bulkFiles[index];
    if (!file) return;

    // Load metadata into form
    const titleInput = document.getElementById('archive_title');
    const publisherInput = document.getElementById('publisher');
    const dateInput = document.getElementById('date_published');
    const editionInput = document.getElementById('edition');
    const categorySelect = document.getElementById('category_id');
    const languageSelect = document.getElementById('language_id');
    const pageCountInput = document.getElementById('page_count');
    const volumeInput = document.getElementById('volume_issue');
    const descriptionInput = document.getElementById('description');

    if (titleInput) titleInput.value = file.metadata.title;
    if (publisherInput) publisherInput.value = file.metadata.publisher;
    if (dateInput) dateInput.value = file.metadata.datePublished;
    if (editionInput) editionInput.value = file.metadata.edition;
    if (categorySelect) categorySelect.value = file.metadata.category;
    if (languageSelect) languageSelect.value = file.metadata.language;
    if (pageCountInput) pageCountInput.value = file.metadata.pageCount;
    if (volumeInput) volumeInput.value = file.metadata.volumeIssue;
    if (descriptionInput) descriptionInput.value = file.metadata.description;

    // Load tags
    const tagsDisplay = document.getElementById('tagsDisplay');
    if (tagsDisplay) {
        tagsDisplay.innerHTML = '';
        file.metadata.tags.forEach(tag => {
            const tagEl = document.createElement('span');
            tagEl.className = 'tag-item';
            tagEl.innerHTML = `${tag} <button type="button" onclick="removeTag(this)">×</button>`;
            tagsDisplay.appendChild(tagEl);
        });
    }

    // Load thumbnail
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
    const removeThumbnailBtn = document.getElementById('removeThumbnailBtn');

    if (file.thumbnail) {
        const reader = new FileReader();
        reader.onload = function (e) {
            if (thumbnailPreview) {
                thumbnailPreview.src = e.target.result;
                thumbnailPreview.classList.remove('d-none');
            }
            if (thumbnailPlaceholder) thumbnailPlaceholder.classList.add('d-none');
            if (removeThumbnailBtn) removeThumbnailBtn.classList.remove('d-none');
        };
        reader.readAsDataURL(file.thumbnail);
    } else {
        if (thumbnailPreview) {
            thumbnailPreview.src = '#';
            thumbnailPreview.classList.add('d-none');
        }
        if (thumbnailPlaceholder) thumbnailPlaceholder.classList.remove('d-none');
        if (removeThumbnailBtn) removeThumbnailBtn.classList.add('d-none');
    }
}

// Switch to different file in bulk mode
function switchBulkFile(index) {
    if (!isBulkMode || index === activeFileIndex) return;

    // Save current file's metadata
    saveCurrentMetadata();

    // Switch active file
    activeFileIndex = index;

    // Load new file's metadata
    loadMetadata(index);

    // Re-render tabs to update active state
    renderBulkList();
}
