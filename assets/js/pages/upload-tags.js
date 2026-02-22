/**
 * Upload Page Tag System
 */

// --- Global State ---
let tags = [];

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    initTagSystem();
});

function initTagSystem() {
    const tagInput = document.getElementById('tagInput');
    const addTagBtn = document.getElementById('addTagBtn');
    const keywordsHidden = document.getElementById('keywordsHidden');

    // Load existing tags from hidden input
    if (keywordsHidden && keywordsHidden.value) {
        tags = keywordsHidden.value.split(',').map(t => t.trim()).filter(t => t);
        renderTags();
    }

    // Input Events
    if (tagInput) {
        tagInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTag();
            }
        });

        // Save on blur (useful for bulk mode switching)
        tagInput.addEventListener('blur', function () {
            if (tagInput.value.trim() !== '') addTag(); // Auto-add pending tag? OR just save existing?
            // Actually, usually user expects typing + enter. 
            // Better to just save current state to file object.
            saveTagsToCurrentFile();
        });
    }

    if (addTagBtn) {
        addTagBtn.addEventListener('click', addTag);
    }
}

function addTag() {
    const tagInput = document.getElementById('tagInput');
    if (!tagInput) return;

    const tagValue = tagInput.value.trim();

    if (tagValue) {
        // Handle comma-separated input
        const newTags = tagValue.split(',').map(t => t.trim()).filter(t => t);

        let added = false;
        newTags.forEach(tag => {
            // Prevent duplicates
            if (!tags.includes(tag)) {
                tags.push(tag);
                added = true;
            }
        });

        if (added) {
            renderTags();
            updateHiddenInput();
            saveTagsToCurrentFile();
            tagInput.value = '';
        } else {
            tagInput.value = ''; // Duplicate or empty
        }
    }
}

function removeTag(index) {
    tags.splice(index, 1);
    renderTags();
    updateHiddenInput();
    saveTagsToCurrentFile();
}

function renderTags() {
    const tagsContainer = document.getElementById('tagsContainer');
    if (!tagsContainer) return;

    tagsContainer.innerHTML = '';

    tags.forEach((tag, index) => {
        const tagBadge = document.createElement('span');
        tagBadge.className = 'tag-badge';
        // Style handled by CSS, strictly structure here
        tagBadge.innerHTML = `
            ${tag}
            <i class="bi bi-x" style="cursor: pointer;" onclick="removeTag(${index})"></i>
        `;
        tagsContainer.appendChild(tagBadge);
    });
}

// Expose setTags globally for upload.js
window.setTags = function (newTags) {
    tags = newTags || [];
    renderTags();
    updateHiddenInput();
};

function updateHiddenInput() {
    const keywordsHidden = document.getElementById('keywordsHidden');
    if (keywordsHidden) {
        keywordsHidden.value = tags.join(',');
        keywordsHidden.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function saveTagsToCurrentFile() {
    // Optional: If upload.js needs to be notified, or we just rely on its saveCurrentFormData reading the hidden input
    // saveCurrentFormData reads hidden input, so updating hidden input is enough.
}

function updateHiddenInput() {
    const keywordsHidden = document.getElementById('keywordsHidden');
    if (keywordsHidden) {
        keywordsHidden.value = tags.join(', ');
        keywordsHidden.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

// --- Bulk Integration ---

function saveTagsToCurrentFile() {
    // Access global variables from upload.js
    if (typeof bulkFiles !== 'undefined' && typeof activeFileIndex !== 'undefined') {
        const file = bulkFiles[activeFileIndex];
        if (file) {
            file.keywords = tags.join(', ');
        }
    }
}

// Global Exports for upload.js
window.loadFileTags = function (index) {
    if (typeof bulkFiles !== 'undefined' && bulkFiles[index]) {
        const val = bulkFiles[index].keywords || '';
        tags = val ? val.split(',').map(t => t.trim()).filter(t => t) : [];
        renderTags();
        updateHiddenInput();
    }
};

window.clearTags = function () {
    tags = [];
    renderTags();
    updateHiddenInput();
};

window.removeTag = removeTag;
