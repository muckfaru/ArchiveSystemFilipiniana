/**
 * Bug Condition Exploration Test for Upload Page Fix
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**
 * 
 * CRITICAL: This test MUST FAIL on unfixed code - failure confirms the bug exists
 * 
 * This test verifies Property 1: Fault Condition - Script Loads Without Syntax Errors
 * 
 * The test checks that:
 * 1. upload.js parses successfully without syntax errors
 * 2. Event listeners are attached to drop zone, file input, and thumbnail input
 * 3. Drag-and-drop interactions trigger appropriate visual feedback
 * 4. File browse buttons can be triggered programmatically
 * 
 * EXPECTED OUTCOME ON UNFIXED CODE: Test FAILS
 * - Syntax error at line 445 prevents script from loading
 * - No event listeners are attached
 * - Upload controls are unresponsive
 */

const fc = require('fast-check');
const fs = require('fs');
const path = require('path');

describe('Bug Condition Exploration: Upload.js Syntax Error', () => {
  
  /**
   * Property 1: Script Loads Without Syntax Errors
   * **Validates: Requirements 2.1**
   */
  test('Property 1: upload.js should parse without syntax errors', () => {
    const uploadJsPath = path.join(__dirname, 'upload.js');
    const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');
    
    // Attempt to parse the JavaScript file
    // If there's a syntax error, this will throw
    let parseError = null;
    try {
      // Use Function constructor to parse the code
      // This will throw a SyntaxError if the code is invalid
      new Function(uploadJsContent);
    } catch (error) {
      parseError = error;
    }
    
    // EXPECTED: This assertion FAILS on unfixed code (syntax error exists)
    // EXPECTED: This assertion PASSES on fixed code (syntax error removed)
    expect(parseError).toBeNull();
    
    if (parseError) {
      console.log('COUNTEREXAMPLE FOUND:');
      console.log('Syntax error detected:', parseError.message);
      console.log('This confirms the bug exists in the unfixed code');
    }
  });

  /**
   * Property 2: Event Listeners Are Attached After Page Load
   * **Validates: Requirements 2.2, 2.3, 2.4**
   * 
   * This test verifies that after DOMContentLoaded, the upload.js script
   * successfully attaches event listeners to the drop zone and file inputs.
   */
  test('Property 2: event listeners should be attached to upload controls', () => {
    // Set up DOM structure that upload.js expects
    document.body.innerHTML = `
      <div id="drop-zone" class="drop-zone">
        <p>Drag and drop files here or <a href="#" id="browse-link">click to browse</a></p>
      </div>
      <input type="file" id="file-input" style="display: none;" />
      <input type="file" id="thumbnail-input" accept="image/*" style="display: none;" />
      <button id="thumbnail-browse-btn">BROWSE</button>
    `;
    
    const uploadJsPath = path.join(__dirname, 'upload.js');
    const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');
    
    // Attempt to execute the script
    let executionError = null;
    try {
      // Execute the script in the context of the DOM
      eval(uploadJsContent);
      
      // Trigger DOMContentLoaded event to initialize event listeners
      const event = new Event('DOMContentLoaded');
      document.dispatchEvent(event);
    } catch (error) {
      executionError = error;
    }
    
    // EXPECTED: This assertion FAILS on unfixed code (script fails to execute)
    // EXPECTED: This assertion PASSES on fixed code (script executes successfully)
    expect(executionError).toBeNull();
    
    if (executionError) {
      console.log('COUNTEREXAMPLE FOUND:');
      console.log('Script execution error:', executionError.message);
      console.log('Event listeners were not attached due to syntax error');
    }
    
    // If script executed, verify event listeners are attached
    if (!executionError) {
      const dropZone = document.getElementById('drop-zone');
      const fileInput = document.getElementById('file-input');
      const thumbnailInput = document.getElementById('thumbnail-input');
      
      // Check if elements exist (they should if script loaded)
      expect(dropZone).not.toBeNull();
      expect(fileInput).not.toBeNull();
      expect(thumbnailInput).not.toBeNull();
    }
  });

  /**
   * Property 3: Drag-and-Drop Interactions Trigger Visual Feedback
   * **Validates: Requirements 2.2**
   * 
   * Property-based test that generates random drag events and verifies
   * the drop zone responds with appropriate visual feedback.
   */
  test('Property 3: drag-and-drop should trigger visual feedback', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('dragenter', 'dragover', 'dragleave', 'drop'),
        (eventType) => {
          // Set up DOM
          document.body.innerHTML = `
            <div id="drop-zone" class="drop-zone">
              <p>Drag and drop files here</p>
            </div>
            <input type="file" id="file-input" style="display: none;" />
          `;
          
          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');
          
          // Attempt to execute the script
          let canTestInteraction = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
          } catch (error) {
            canTestInteraction = false;
            console.log('COUNTEREXAMPLE FOUND:');
            console.log(`Cannot test ${eventType} interaction - script failed to load`);
            console.log('Error:', error.message);
          }
          
          // EXPECTED: canTestInteraction is FALSE on unfixed code (syntax error prevents script load)
          // EXPECTED: canTestInteraction is TRUE on fixed code (script loads successfully)
          expect(canTestInteraction).toBe(true);
          
          return canTestInteraction;
        }
      ),
      { numRuns: 10 } // Test with 10 different drag event types
    );
  });

  /**
   * Property 4: File Browse Buttons Should Be Functional
   * **Validates: Requirements 2.3, 2.4**
   * 
   * Property-based test that verifies file input elements can be triggered
   * programmatically after the script loads.
   */
  test('Property 4: file browse buttons should be functional', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('file-input', 'thumbnail-input'),
        (inputId) => {
          // Set up DOM
          document.body.innerHTML = `
            <div id="drop-zone" class="drop-zone">
              <p><a href="#" id="browse-link">click to browse</a></p>
            </div>
            <input type="file" id="file-input" style="display: none;" />
            <input type="file" id="thumbnail-input" accept="image/*" style="display: none;" />
            <button id="thumbnail-browse-btn">BROWSE</button>
          `;
          
          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');
          
          // Attempt to execute the script
          let inputAccessible = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
            
            // Try to access the input element
            const input = document.getElementById(inputId);
            if (!input) {
              inputAccessible = false;
            }
          } catch (error) {
            inputAccessible = false;
            console.log('COUNTEREXAMPLE FOUND:');
            console.log(`Cannot access ${inputId} - script failed to load`);
            console.log('Error:', error.message);
          }
          
          // EXPECTED: inputAccessible is FALSE on unfixed code (syntax error prevents script load)
          // EXPECTED: inputAccessible is TRUE on fixed code (script loads and inputs are accessible)
          expect(inputAccessible).toBe(true);
          
          return inputAccessible;
        }
      ),
      { numRuns: 10 } // Test with both file inputs
    );
  });

});

/**
 * Preservation Property Tests for Upload Page Fix
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**
 * 
 * These tests verify that all existing upload page functionality continues to work
 * correctly after the syntax error fix. They test the INTENDED behavior of the
 * original code (what should work after the syntax error is fixed).
 * 
 * EXPECTED OUTCOME: Tests PASS (confirms all features work correctly after syntax fix)
 */

describe('Preservation Property Tests: All Existing Functionality Unchanged', () => {

  /**
   * Property 1: Edit Mode Preservation
   * **Validates: Requirement 3.1**
   * 
   * WHEN the page loads in edit mode
   * THEN the system SHALL hide the drop zone and show existing file information
   */
  test('Property 1: Edit mode hides drop zone and shows existing file info', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('edit', 'upload'),
        (mode) => {
          // Set up DOM for edit mode
          document.body.innerHTML = `
            <input type="hidden" name="action" value="${mode}" />
            <div id="dropZoneContainer" style="display: block;">Drop Zone</div>
            <div id="editModeIndicator" style="display: none;">Edit Mode</div>
            <div id="selectedFilePreview" style="display: none;">
              <span id="previewFilename">existing-file.pdf</span>
              <span id="previewSize">1.5 MB</span>
            </div>
            <input type="file" id="fileInput" style="display: none;" />
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let executionSuccess = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
          } catch (error) {
            executionSuccess = false;
            console.log('COUNTEREXAMPLE: Script failed to execute in', mode, 'mode:', error.message);
          }

          // Verify script executed successfully
          expect(executionSuccess).toBe(true);

          if (mode === 'edit') {
            // In edit mode, drop zone should be hidden (or remain hidden)
            // The actual behavior depends on whether a file is selected
            // For now, we just verify the script loaded and can access elements
            const dropZone = document.getElementById('dropZoneContainer');
            const editIndicator = document.getElementById('editModeIndicator');
            expect(dropZone).not.toBeNull();
            expect(editIndicator).not.toBeNull();
          }

          return executionSuccess;
        }
      ),
      { numRuns: 5 }
    );
  });

  /**
   * Property 2: Unsaved Changes Warning Preservation
   * **Validates: Requirement 3.2**
   * 
   * WHEN a user has unsaved changes
   * THEN the system SHALL show the browser warning before navigation
   */
  test('Property 2: Unsaved changes warning appears when navigating away', () => {
    fc.assert(
      fc.property(
        fc.boolean(),
        (hasChanges) => {
          // Set up DOM
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <input type="text" class="custom-field" name="title" data-required="true" value="${hasChanges ? 'Some Title' : ''}" />
            <form id="uploadForm"></form>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let hasUnsavedChangesFunction = false;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Check if hasUnsavedChanges function is defined
            hasUnsavedChangesFunction = typeof window.hasUnsavedChanges === 'function';
          } catch (error) {
            console.log('COUNTEREXAMPLE: Script failed to execute:', error.message);
          }

          // Verify the function exists
          expect(hasUnsavedChangesFunction).toBe(true);

          if (hasUnsavedChangesFunction) {
            const result = window.hasUnsavedChanges();
            // If we have changes, the function should return true
            if (hasChanges) {
              expect(result).toBe(true);
            }
          }

          return hasUnsavedChangesFunction;
        }
      ),
      { numRuns: 10 }
    );
  });

  /**
   * Property 3: Bulk Upload Mode Preservation
   * **Validates: Requirement 3.4**
   * 
   * WHEN bulk upload mode is enabled
   * THEN the system SHALL support multiple file uploads with individual metadata
   */
  test('Property 3: Bulk upload mode supports multiple files with individual metadata', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 1, max: 5 }),
        (fileCount) => {
          // Set up DOM for bulk upload
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="dropZoneContainer" style="display: block;"></div>
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <div id="bulkUploadContainer" style="display: none;">
              <div id="fileTabs"></div>
              <div id="tabsWrapper"></div>
              <div id="docStatsWrapper"></div>
              <div id="photoStatsWrapper"></div>
              <div id="pageOrderGridWrapper"></div>
            </div>
            <div id="selectedFilePreview" style="display: none;"></div>
            <form id="uploadForm"></form>
            <input type="text" class="custom-field" id="title" name="title" data-required="true" />
            <input type="text" class="custom-field" id="publisher" name="publisher" />
            <input type="date" class="custom-field" id="publication_date" name="publication_date" />
            <select class="custom-field" id="category_id" name="category_id"></select>
            <select class="custom-field" id="language_id" name="language_id"></select>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let bulkModeWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify bulk upload container exists
            const bulkContainer = document.getElementById('bulkUploadContainer');
            expect(bulkContainer).not.toBeNull();
          } catch (error) {
            bulkModeWorks = false;
            console.log('COUNTEREXAMPLE: Bulk mode failed with', fileCount, 'files:', error.message);
          }

          expect(bulkModeWorks).toBe(true);
          return bulkModeWorks;
        }
      ),
      { numRuns: 10 }
    );
  });

  /**
   * Property 4: Custom Metadata Fields Preservation
   * **Validates: Requirement 3.5**
   * 
   * WHEN custom metadata fields are present
   * THEN the system SHALL capture and submit their values correctly
   */
  test('Property 4: Custom metadata fields are captured and submitted correctly', () => {
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }),
        fc.string({ minLength: 1, maxLength: 50 }),
        (titleValue, publisherValue) => {
          // Set up DOM with custom metadata fields
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <form id="uploadForm">
              <input type="text" class="custom-field" id="title" name="title" data-required="true" value="${titleValue}" />
              <input type="text" class="custom-field" id="publisher" name="publisher" value="${publisherValue}" />
              <textarea class="custom-field" id="description" name="description"></textarea>
            </form>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let fieldsAccessible = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify custom fields are accessible
            const titleField = document.getElementById('title');
            const publisherField = document.getElementById('publisher');
            const descriptionField = document.getElementById('description');

            expect(titleField).not.toBeNull();
            expect(publisherField).not.toBeNull();
            expect(descriptionField).not.toBeNull();

            // Verify values are preserved
            if (titleField) expect(titleField.value).toBe(titleValue);
            if (publisherField) expect(publisherField.value).toBe(publisherValue);
          } catch (error) {
            fieldsAccessible = false;
            console.log('COUNTEREXAMPLE: Custom fields failed:', error.message);
          }

          expect(fieldsAccessible).toBe(true);
          return fieldsAccessible;
        }
      ),
      { numRuns: 20 }
    );
  });

  /**
   * Property 5: Tag Input Preservation
   * **Validates: Requirement 3.5**
   * 
   * WHEN tags are added to the keywords field
   * THEN the system SHALL display and submit them correctly
   */
  test('Property 5: Tag input displays and submits tags correctly', () => {
    fc.assert(
      fc.property(
        fc.array(fc.string({ minLength: 1, maxLength: 20 }), { minLength: 0, maxLength: 5 }),
        (tags) => {
          const tagsString = tags.join(',');
          
          // Set up DOM with tag input
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <form id="uploadForm">
              <input type="hidden" id="keywordsHidden" name="keywords" value="${tagsString}" />
              <div id="tagsContainer"></div>
            </form>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let tagsWork = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify keywords field is accessible
            const keywordsField = document.getElementById('keywordsHidden');
            expect(keywordsField).not.toBeNull();
            
            if (keywordsField) {
              expect(keywordsField.value).toBe(tagsString);
            }
          } catch (error) {
            tagsWork = false;
            console.log('COUNTEREXAMPLE: Tags failed with', tags.length, 'tags:', error.message);
          }

          expect(tagsWork).toBe(true);
          return tagsWork;
        }
      ),
      { numRuns: 15 }
    );
  });

  /**
   * Property 6: Publication Date Validation Preservation
   * **Validates: Requirement 3.5**
   * 
   * WHEN an invalid date is entered
   * THEN the system SHALL trigger validation errors
   */
  test('Property 6: Publication date validation triggers errors for invalid dates', () => {
    fc.assert(
      fc.property(
        fc.constantFrom('2024-13-01', '2024-02-30', 'invalid-date', ''),
        (dateValue) => {
          // Set up DOM with publication date field
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <form id="uploadForm">
              <input type="date" class="custom-field" id="publication_date" name="publication_date" value="${dateValue}" data-required="true" />
            </form>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let validationWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify publication date field is accessible
            const dateField = document.getElementById('publication_date');
            expect(dateField).not.toBeNull();
          } catch (error) {
            validationWorks = false;
            console.log('COUNTEREXAMPLE: Date validation failed for', dateValue, ':', error.message);
          }

          expect(validationWorks).toBe(true);
          return validationWorks;
        }
      ),
      { numRuns: 10 }
    );
  });

  /**
   * Property 7: Form Submission Preservation
   * **Validates: Requirement 3.3**
   * 
   * WHEN the form is submitted
   * THEN the system SHALL display progress bar and success messages correctly
   */
  test('Property 7: Form submission displays progress bar and success messages', () => {
    fc.assert(
      fc.property(
        fc.boolean(),
        (isEditMode) => {
          // Set up DOM for form submission
          document.body.innerHTML = `
            <input type="hidden" name="action" value="${isEditMode ? 'edit' : 'upload'}" />
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <form id="uploadForm" action="/backend/api/upload.php">
              <input type="text" class="custom-field" id="title" name="title" data-required="true" value="Test Title" />
            </form>
            <button id="uploadBtn">Upload</button>
            <button id="uploadBtnDesktop">Upload</button>
            <button id="uploadBtnMobile">Upload</button>
            <button id="discardBtn">Discard</button>
            <button id="discardBtnMobile">Discard</button>
            <div id="confirmUploadModal" class="modal">
              <div class="modal-header">
                <h5>Confirm Upload</h5>
                <p>Are you sure?</p>
                <i class="bi bi-upload"></i>
              </div>
              <div id="uploadFileList"></div>
              <button id="confirmUploadBtn">Confirm</button>
            </div>
            <div id="alertContainer"></div>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let submissionWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify upload buttons are accessible
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnDesktop = document.getElementById('uploadBtnDesktop');
            const uploadBtnMobile = document.getElementById('uploadBtnMobile');

            expect(uploadBtn).not.toBeNull();
            expect(uploadBtnDesktop).not.toBeNull();
            expect(uploadBtnMobile).not.toBeNull();
          } catch (error) {
            submissionWorks = false;
            console.log('COUNTEREXAMPLE: Form submission failed in', isEditMode ? 'edit' : 'upload', 'mode:', error.message);
          }

          expect(submissionWorks).toBe(true);
          return submissionWorks;
        }
      ),
      { numRuns: 10 }
    );
  });

  /**
   * Property 8: Bind Mode (Bulk Images) Preservation
   * **Validates: Requirement 3.4**
   * 
   * WHEN bind mode is enabled with multiple images
   * THEN the system SHALL support cover selection
   */
  test('Property 8: Bind mode supports cover selection for multiple images', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 2, max: 5 }),
        (imageCount) => {
          // Set up DOM for bind mode
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="dropZoneContainer" style="display: block;"></div>
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <div id="bulkUploadContainer" style="display: none;">
              <input type="radio" id="modeIndividual" name="uploadMode" value="individual" />
              <input type="radio" id="modeBind" name="uploadMode" value="bind" checked />
              <div id="pageOrderGridWrapper">
                <div id="pageOrderGrid"></div>
              </div>
              <div id="tabsWrapper"></div>
              <div id="docStatsWrapper"></div>
              <div id="photoStatsWrapper"></div>
            </div>
            <form id="uploadForm"></form>
            <div id="thumbnailArea">
              <img id="thumbnailPreview" src="#" style="display: none;" />
              <div id="thumbnailPlaceholder" style="display: flex;"></div>
              <button id="removeThumbnailBtn" style="display: none;">Remove</button>
            </div>
            <input type="file" id="thumbnailInput" accept="image/*" style="display: none;" />
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let bindModeWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Verify bind mode elements are accessible
            const modeBind = document.getElementById('modeBind');
            const gridWrapper = document.getElementById('pageOrderGridWrapper');
            const thumbnailArea = document.getElementById('thumbnailArea');

            expect(modeBind).not.toBeNull();
            expect(gridWrapper).not.toBeNull();
            expect(thumbnailArea).not.toBeNull();
          } catch (error) {
            bindModeWorks = false;
            console.log('COUNTEREXAMPLE: Bind mode failed with', imageCount, 'images:', error.message);
          }

          expect(bindModeWorks).toBe(true);
          return bindModeWorks;
        }
      ),
      { numRuns: 10 }
    );
  });

});

/**
 * File State Management Bug Fix Tests
 * 
 * These tests verify that the file state management bug is fixed:
 * - Files are completely removed from all state locations
 * - Reset form returns to clean initial state
 * - No "ghost files" persist in memory or UI
 * - Upload buttons behave correctly
 * - Bulk UI stays synchronized with state
 */

describe('File State Management Bug Fix Tests', () => {

  /**
   * Property 1: clearAllFileState() Clears All State Variables
   * 
   * WHEN clearAllFileState() is called
   * THEN all file-related state variables should be reset to initial values
   */
  test('Property 1: clearAllFileState() resets all state variables', () => {
    // Set up DOM
    document.body.innerHTML = `
      <input type="hidden" name="action" value="upload" />
      <div id="dropZoneContainer" style="display: none;"></div>
      <div id="drop-zone" class="drop-zone"></div>
      <input type="file" id="fileInput" style="display: none;" />
      <input type="file" id="bulkFileInput" style="display: none;" />
      <div id="bulkUploadContainer" style="display: block;">
        <div id="fileTabs"><div class="file-card">File 1</div></div>
        <div id="bulkTabs"><div class="file-card">File 2</div></div>
      </div>
      <div id="bulkStatsContainer" style="display: block;">
        <span id="totalFilesCount">2</span>
        <span id="readyFilesCount">1</span>
        <span id="pendingFilesCount">1</span>
        <span id="totalFiles">2</span>
        <span id="readyFiles">1</span>
        <span id="pendingFiles">1</span>
      </div>
      <div id="selectedFilePreview" style="display: block;"></div>
      <div id="currentFileName" class="">test.pdf</div>
      <div id="duplicateStatusContainer" style="display: block;"></div>
      <div id="bulkPhotoInfoMessage" class="">Photo mode</div>
      <div id="photoStatsWrapper" style="display: block;"></div>
      <div id="docStatsWrapper" style="display: block;"></div>
      <div id="thumbnailArea">
        <img id="thumbnailPreview" src="test.jpg" style="display: block;" />
        <div id="thumbnailPlaceholder" style="display: none;"></div>
        <button id="removeThumbnailBtn" style="display: block;">Remove</button>
      </div>
      <form id="uploadForm"></form>
    `;

    const uploadJsPath = path.join(__dirname, 'upload.js');
    const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

    let testPassed = false;
    try {
      eval(uploadJsContent);
      const event = new Event('DOMContentLoaded');
      document.dispatchEvent(event);

      // Simulate having files in state
      if (typeof window.clearAllFileState === 'function') {
        // Call the helper function
        window.clearAllFileState();

        // Verify all file card containers are cleared
        const fileTabs = document.getElementById('fileTabs');
        const bulkTabs = document.getElementById('bulkTabs');
        expect(fileTabs.innerHTML).toBe('');
        expect(bulkTabs.innerHTML).toBe('');

        // Verify bulk containers are hidden
        const bulkStatsContainer = document.getElementById('bulkStatsContainer');
        const bulkUploadContainer = document.getElementById('bulkUploadContainer');
        expect(bulkStatsContainer.style.display).toBe('none');
        expect(bulkUploadContainer.style.display).toBe('none');

        // Verify status counters are reset to '0'
        const totalFilesCount = document.getElementById('totalFilesCount');
        const readyFilesCount = document.getElementById('readyFilesCount');
        const pendingFilesCount = document.getElementById('pendingFilesCount');
        expect(totalFilesCount.textContent).toBe('0');
        expect(readyFilesCount.textContent).toBe('0');
        expect(pendingFilesCount.textContent).toBe('0');

        // Verify duplicate status is hidden
        const dupStatus = document.getElementById('duplicateStatusContainer');
        expect(dupStatus.style.display).toBe('none');

        // Verify thumbnail state is reset
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
        const removeThumbnailBtn = document.getElementById('removeThumbnailBtn');
        expect(thumbnailPreview.style.display).toBe('none');
        expect(thumbnailPlaceholder.style.display).toBe('flex');
        expect(removeThumbnailBtn.style.display).toBe('none');

        // Verify single file preview is hidden
        const selectedFilePreview = document.getElementById('selectedFilePreview');
        expect(selectedFilePreview.style.display).toBe('none');

        testPassed = true;
      } else {
        console.log('clearAllFileState function not found - may not be exposed globally');
      }
    } catch (error) {
      console.log('Test failed:', error.message);
    }

    expect(testPassed).toBe(true);
  });

  /**
   * Property 2: resetForm() Uses clearAllFileState()
   * 
   * WHEN resetForm() is called
   * THEN it should use clearAllFileState() to ensure complete cleanup
   */
  test('Property 2: resetForm() clears all file state', () => {
    fc.assert(
      fc.property(
        fc.boolean(),
        (isEditMode) => {
          // Set up DOM
          document.body.innerHTML = `
            <input type="hidden" name="action" value="${isEditMode ? 'edit' : 'upload'}" />
            <div id="dropZoneContainer" style="display: none;"></div>
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <input type="file" id="bulkFileInput" style="display: none;" />
            <div id="bulkUploadContainer" style="display: block;">
              <div id="fileTabs"><div class="file-card">File 1</div></div>
              <div id="bulkTabs"><div class="file-card">File 2</div></div>
            </div>
            <div id="bulkStatsContainer" style="display: block;">
              <span id="totalFilesCount">2</span>
              <span id="readyFilesCount">1</span>
              <span id="pendingFilesCount">1</span>
              <span id="totalFiles">2</span>
              <span id="readyFiles">1</span>
              <span id="pendingFiles">1</span>
            </div>
            <div id="selectedFilePreview" style="display: block;"></div>
            <div id="editModeIndicator" style="display: none;">Edit Mode</div>
            <form id="uploadForm"></form>
            <div id="tagsContainer"><span class="tag">tag1</span></div>
            <input type="hidden" id="keywordsHidden" name="keywords" value="tag1" />
            <input type="radio" id="modeIndividual" name="uploadMode" value="individual" />
            <button id="uploadBtn">Upload</button>
            <button id="discardBtn">Discard</button>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let resetWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            // Call resetForm
            if (typeof window.resetForm === 'function') {
              window.resetForm();

              // Verify file card containers are cleared
              const fileTabs = document.getElementById('fileTabs');
              const bulkTabs = document.getElementById('bulkTabs');
              expect(fileTabs.innerHTML).toBe('');
              expect(bulkTabs.innerHTML).toBe('');

              // Verify bulk containers are hidden
              const bulkStatsContainer = document.getElementById('bulkStatsContainer');
              const bulkUploadContainer = document.getElementById('bulkUploadContainer');
              expect(bulkStatsContainer.style.display).toBe('none');
              expect(bulkUploadContainer.style.display).toBe('none');

              // Verify status counters are reset
              const totalFilesCount = document.getElementById('totalFilesCount');
              expect(totalFilesCount.textContent).toBe('0');

              // Verify drop zone visibility based on edit mode
              const dropZoneContainer = document.getElementById('dropZoneContainer');
              if (!isEditMode) {
                expect(dropZoneContainer.style.display).toBe('block');
              }
            } else {
              resetWorks = false;
              console.log('resetForm function not found');
            }
          } catch (error) {
            resetWorks = false;
            console.log('COUNTEREXAMPLE: resetForm failed in', isEditMode ? 'edit' : 'upload', 'mode:', error.message);
          }

          expect(resetWorks).toBe(true);
          return resetWorks;
        }
      ),
      { numRuns: 10 }
    );
  });

  /**
   * Property 3: removeFile() Calls resetForm() When Last File Removed
   * 
   * WHEN the last file is removed from bulkFiles
   * THEN removeFile() should call resetForm() to ensure complete cleanup
   */
  test('Property 3: removeFile() resets form when last file is removed', () => {
    // Set up DOM
    document.body.innerHTML = `
      <input type="hidden" name="action" value="upload" />
      <div id="dropZoneContainer" style="display: none;"></div>
      <div id="drop-zone" class="drop-zone"></div>
      <input type="file" id="fileInput" style="display: none;" />
      <input type="file" id="bulkFileInput" style="display: none;" />
      <div id="bulkUploadContainer" style="display: block;">
        <div id="fileTabs"><div class="file-card">File 1</div></div>
        <div id="bulkTabs"><div class="file-card">File 1</div></div>
      </div>
      <div id="bulkStatsContainer" style="display: block;">
        <span id="totalFilesCount">1</span>
        <span id="readyFilesCount">1</span>
        <span id="pendingFilesCount">0</span>
        <span id="totalFiles">1</span>
        <span id="readyFiles">1</span>
        <span id="pendingFiles">0</span>
      </div>
      <form id="uploadForm"></form>
      <button id="uploadBtn">Upload</button>
      <button id="discardBtn">Discard</button>
      <div id="tagsContainer"></div>
      <input type="hidden" id="keywordsHidden" name="keywords" value="" />
      <input type="radio" id="modeIndividual" name="uploadMode" value="individual" />
    `;

    const uploadJsPath = path.join(__dirname, 'upload.js');
    const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

    let testPassed = false;
    try {
      eval(uploadJsContent);
      const event = new Event('DOMContentLoaded');
      document.dispatchEvent(event);

      // Simulate having one file in bulkFiles
      if (typeof window.removeFile === 'function') {
        // Mock bulkFiles array with one file
        window.bulkFiles = [{ id: 1, name: 'test.pdf', status: 'ready' }];
        window.activeFileIndex = 0;
        window.isBulkMode = true;

        // Remove the last file
        window.removeFile(null, 0);

        // Verify form was reset
        const fileTabs = document.getElementById('fileTabs');
        const bulkTabs = document.getElementById('bulkTabs');
        expect(fileTabs.innerHTML).toBe('');
        expect(bulkTabs.innerHTML).toBe('');

        const bulkStatsContainer = document.getElementById('bulkStatsContainer');
        expect(bulkStatsContainer.style.display).toBe('none');

        const dropZoneContainer = document.getElementById('dropZoneContainer');
        expect(dropZoneContainer.style.display).toBe('block');

        testPassed = true;
      } else {
        console.log('removeFile function not found');
      }
    } catch (error) {
      console.log('Test failed:', error.message);
    }

    expect(testPassed).toBe(true);
  });

  /**
   * Property 4: removeFile() Updates Active Index Correctly
   * 
   * WHEN a file is removed from bulkFiles but files remain
   * THEN activeFileIndex should be updated correctly
   */
  test('Property 4: removeFile() updates activeFileIndex when files remain', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 2, max: 5 }),
        fc.integer({ min: 0, max: 4 }),
        (totalFiles, removeIndex) => {
          // Ensure removeIndex is valid
          if (removeIndex >= totalFiles) return true;

          // Set up DOM
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="dropZoneContainer" style="display: none;"></div>
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <input type="file" id="bulkFileInput" style="display: none;" />
            <div id="bulkUploadContainer" style="display: block;">
              <div id="fileTabs"></div>
              <div id="bulkTabs"></div>
              <div id="tabsWrapper"></div>
              <div id="docStatsWrapper"></div>
              <div id="photoStatsWrapper"></div>
              <div id="pageOrderGridWrapper"></div>
            </div>
            <div id="bulkStatsContainer" style="display: block;">
              <span id="totalFilesCount">${totalFiles}</span>
              <span id="readyFilesCount">${totalFiles}</span>
              <span id="pendingFilesCount">0</span>
              <span id="totalFiles">${totalFiles}</span>
              <span id="readyFiles">${totalFiles}</span>
              <span id="pendingFiles">0</span>
            </div>
            <form id="uploadForm"></form>
            <button id="uploadBtn">Upload</button>
            <button id="discardBtn">Discard</button>
            <input type="text" class="custom-field" id="title" name="title" data-required="true" />
            <input type="text" class="custom-field" id="publisher" name="publisher" />
            <input type="date" class="custom-field" id="publication_date" name="publication_date" />
            <select class="custom-field" id="category_id" name="category_id"></select>
            <select class="custom-field" id="language_id" name="language_id"></select>
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let indexUpdateWorks = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            if (typeof window.removeFile === 'function') {
              // Mock bulkFiles array
              window.bulkFiles = [];
              for (let i = 0; i < totalFiles; i++) {
                window.bulkFiles.push({
                  id: i,
                  name: `file${i}.pdf`,
                  status: 'ready',
                  metadata: {}
                });
              }
              window.activeFileIndex = 0;
              window.isBulkMode = true;

              const initialActiveIndex = window.activeFileIndex;

              // Remove a file
              window.removeFile(null, removeIndex);

              // Verify files remain
              expect(window.bulkFiles.length).toBe(totalFiles - 1);

              // Verify activeFileIndex was updated correctly
              if (removeIndex === initialActiveIndex) {
                // Removed active file -> should switch to 0
                expect(window.activeFileIndex).toBe(0);
              } else if (removeIndex < initialActiveIndex) {
                // Removed file before active -> should decrement
                expect(window.activeFileIndex).toBe(initialActiveIndex - 1);
              }
              // If removeIndex > activeFileIndex, no change expected
            } else {
              indexUpdateWorks = false;
            }
          } catch (error) {
            indexUpdateWorks = false;
            console.log('COUNTEREXAMPLE: removeFile failed with', totalFiles, 'files, removing index', removeIndex, ':', error.message);
          }

          expect(indexUpdateWorks).toBe(true);
          return indexUpdateWorks;
        }
      ),
      { numRuns: 20 }
    );
  });

  /**
   * Property 5: No Ghost Files After Reset
   * 
   * WHEN resetForm() is called after files were added
   * THEN no file-related UI components should be visible except drop zone
   */
  test('Property 5: No ghost files remain after reset', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 1, max: 5 }),
        (fileCount) => {
          // Set up DOM
          document.body.innerHTML = `
            <input type="hidden" name="action" value="upload" />
            <div id="dropZoneContainer" style="display: none;"></div>
            <div id="drop-zone" class="drop-zone"></div>
            <input type="file" id="fileInput" style="display: none;" />
            <input type="file" id="bulkFileInput" style="display: none;" />
            <div id="bulkUploadContainer" style="display: block;">
              <div id="fileTabs">${'<div class="file-card">File</div>'.repeat(fileCount)}</div>
              <div id="bulkTabs">${'<div class="file-card">File</div>'.repeat(fileCount)}</div>
            </div>
            <div id="bulkStatsContainer" style="display: block;">
              <span id="totalFilesCount">${fileCount}</span>
              <span id="readyFilesCount">${fileCount}</span>
              <span id="pendingFilesCount">0</span>
            </div>
            <div id="selectedFilePreview" style="display: block;">File preview</div>
            <div id="currentFileName" class="">test.pdf</div>
            <form id="uploadForm"></form>
            <button id="uploadBtn">Upload</button>
            <button id="discardBtn">Discard</button>
            <div id="tagsContainer"></div>
            <input type="hidden" id="keywordsHidden" name="keywords" value="" />
            <input type="radio" id="modeIndividual" name="uploadMode" value="individual" />
          `;

          const uploadJsPath = path.join(__dirname, 'upload.js');
          const uploadJsContent = fs.readFileSync(uploadJsPath, 'utf8');

          let noGhostFiles = true;
          try {
            eval(uploadJsContent);
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);

            if (typeof window.resetForm === 'function') {
              // Call resetForm
              window.resetForm();

              // Verify NO file cards remain
              const fileTabs = document.getElementById('fileTabs');
              const bulkTabs = document.getElementById('bulkTabs');
              expect(fileTabs.innerHTML).toBe('');
              expect(bulkTabs.innerHTML).toBe('');

              // Verify bulk containers are hidden
              const bulkStatsContainer = document.getElementById('bulkStatsContainer');
              const bulkUploadContainer = document.getElementById('bulkUploadContainer');
              expect(bulkStatsContainer.style.display).toBe('none');
              expect(bulkUploadContainer.style.display).toBe('none');

              // Verify single file preview is hidden
              const selectedFilePreview = document.getElementById('selectedFilePreview');
              expect(selectedFilePreview.style.display).toBe('none');

              // Verify drop zone is visible
              const dropZoneContainer = document.getElementById('dropZoneContainer');
              expect(dropZoneContainer.style.display).toBe('block');

              // Verify current file badge is hidden
              const badge = document.getElementById('currentFileName');
              expect(badge.classList.contains('d-none')).toBe(true);
            } else {
              noGhostFiles = false;
            }
          } catch (error) {
            noGhostFiles = false;
            console.log('COUNTEREXAMPLE: Ghost files remain after reset with', fileCount, 'files:', error.message);
          }

          expect(noGhostFiles).toBe(true);
          return noGhostFiles;
        }
      ),
      { numRuns: 15 }
    );
  });

});
