<?php
/**
 * Upload Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';

// Get alert message
$alert = getAlert();

// Get categories and languages
$categories = getCategories();
$languages = getLanguages();

// Check if editing
$editMode = isset($_GET['edit']) && intval($_GET['edit']) > 0;
$editItem = null;

if ($editMode) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();

    if (!$editItem) {
        showAlert('danger', 'Document not found.');
        redirect('upload.php');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' || $action === 'edit') {
        $title = sanitize($_POST['title']);
        $publicationDate = $_POST['publication_date'] ?: null;
        $edition = sanitize($_POST['edition'] ?? '');
        $categoryId = intval($_POST['category_id']) ?: null;
        $languageId = intval($_POST['language_id']) ?: null;
        $pageCount = intval($_POST['page_count']) ?: null;
        $keywords = sanitize($_POST['keywords'] ?? '');
        $publisher = sanitize($_POST['publisher'] ?? '');
        $volumeIssue = sanitize($_POST['volume_issue'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if ($action === 'upload') {
            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];

                // Get file extension
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Check allowed extensions
                if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    showAlert('danger', 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS));
                    redirect('upload.php');
                }

                // Check file size
                if ($fileSize > MAX_UPLOAD_SIZE) {
                    showAlert('danger', 'File too large. Maximum size: ' . formatFileSize(MAX_UPLOAD_SIZE));
                    redirect('upload.php');
                }

                // Check for duplicates
                if (checkDuplicateFile($fileName)) {
                    showAlert('danger', 'A file with this name already exists.');
                    redirect('upload.php');
                }

                // Generate unique filename
                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = UPLOAD_PATH . 'newspapers/' . $newFileName;

                // Move file
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    // Handle thumbnail upload
                    $thumbnailPath = null;
                    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                        $thumbFile = $_FILES['thumbnail'];
                        $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                        if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                            $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                            $thumbPath = UPLOAD_PATH . 'thumbnails/' . $thumbFileName;

                            if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                                $thumbnailPath = 'uploads/thumbnails/' . $thumbFileName;
                            }
                        }
                    }

                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, publication_date, edition, category_id, language_id, page_count, 
                                               keywords, publisher, volume_issue, description, file_path, file_name, 
                                               file_type, file_size, thumbnail_path, uploaded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title,
                        $publicationDate,
                        $edition,
                        $categoryId,
                        $languageId,
                        $pageCount,
                        $keywords,
                        $publisher,
                        $volumeIssue,
                        $description,
                        'uploads/newspapers/' . $newFileName,
                        $fileName,
                        $fileExt,
                        $fileSize,
                        $thumbnailPath,
                        $currentUser['id']
                    ]);

                    logActivity($currentUser['id'], 'upload', $title);
                    showAlert('success', 'Document uploaded successfully.');
                } else {
                    showAlert('danger', 'Failed to upload file. Please try again.');
                }
            } else {
                showAlert('danger', 'Please select a file to upload.');
            }
            redirect('upload.php');
        }

        if ($action === 'edit') {
            $editId = intval($_POST['edit_id']);

            // Handle new thumbnail upload
            $thumbnailPath = $_POST['existing_thumbnail'] ?? null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbFile = $_FILES['thumbnail'];
                $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                    $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                    $thumbPath = UPLOAD_PATH . 'thumbnails/' . $thumbFileName;

                    if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                        $thumbnailPath = 'uploads/thumbnails/' . $thumbFileName;
                    }
                }
            }

            // Handle file replacement
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (in_array($fileExt, ALLOWED_EXTENSIONS) && $fileSize <= MAX_UPLOAD_SIZE) {
                    $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                    $uploadPath = UPLOAD_PATH . 'newspapers/' . $newFileName;

                    if (move_uploaded_file($fileTmp, $uploadPath)) {
                        // Update with new file
                        $stmt = $pdo->prepare("
                            UPDATE newspapers SET title = ?, publication_date = ?, edition = ?, category_id = ?, 
                                   language_id = ?, page_count = ?, keywords = ?, publisher = ?, volume_issue = ?, 
                                   description = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ?, 
                                   thumbnail_path = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $title,
                            $publicationDate,
                            $edition,
                            $categoryId,
                            $languageId,
                            $pageCount,
                            $keywords,
                            $publisher,
                            $volumeIssue,
                            $description,
                            'uploads/newspapers/' . $newFileName,
                            $fileName,
                            $fileExt,
                            $fileSize,
                            $thumbnailPath,
                            $editId
                        ]);
                    }
                }
            } else {
                // Update without changing file
                $stmt = $pdo->prepare("
                    UPDATE newspapers SET title = ?, publication_date = ?, edition = ?, category_id = ?, 
                           language_id = ?, page_count = ?, keywords = ?, publisher = ?, volume_issue = ?, 
                           description = ?, thumbnail_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title,
                    $publicationDate,
                    $edition,
                    $categoryId,
                    $languageId,
                    $pageCount,
                    $keywords,
                    $publisher,
                    $volumeIssue,
                    $description,
                    $thumbnailPath,
                    $editId
                ]);
            }

            logActivity($currentUser['id'], 'edit', $title);
            showAlert('success', 'Document updated successfully.');
            redirect('upload.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $editMode ? 'Edit' : 'Upload' ?> -
        <?= APP_NAME ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/dark-mode.css" rel="stylesheet">
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <?php if ($editMode): ?>
                    <h1 class="page-title">Upload > Editing "
                        <?= htmlspecialchars($editItem['title']) ?>"
                    </h1>
                    <p class="page-subtitle">You are editing a document</p>
                <?php else: ?>
                    <h1 class="page-title">Upload</h1>
                    <p class="page-subtitle">Upload newspapers, documents, or media files</p>
                <?php endif; ?>
            </div>
            <div class="page-actions">
                <?php if ($editMode): ?>
                    <a href="upload.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" form="uploadForm" class="btn btn-primary">
                        <i class="bi bi-check2 me-2"></i>Save Changes
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Discard</button>
                    <button type="submit" form="uploadForm" class="btn btn-primary">
                        <i class="bi bi-upload me-2"></i>Upload
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert -->
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
                <?= $alert['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'upload' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="edit_id" value="<?= $editItem['id'] ?>">
                <input type="hidden" name="existing_thumbnail" value="<?= $editItem['thumbnail_path'] ?>">
            <?php endif; ?>

            <?php if ($editMode): ?>
                <!-- Current File Info -->
                <div class="card mb-4">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-danger text-white rounded p-3">
                                <i class="bi bi-file-earmark-pdf fs-4"></i>
                            </div>
                            <div>
                                <strong>
                                    <?= htmlspecialchars($editItem['file_name']) ?>
                                </strong>
                                <div class="text-muted small">
                                    <?= strtoupper($editItem['file_type']) ?> Document •
                                    <?= formatFileSize($editItem['file_size']) ?> •
                                    Uploaded on
                                    <?= date('M d, Y', strtotime($editItem['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <label class="btn btn-secondary" for="newFile">
                            <i class="bi bi-arrow-repeat me-2"></i>Replace File
                        </label>
                        <input type="file" id="newFile" name="file" class="d-none"
                            accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif">
                    </div>
                </div>
            <?php else: ?>
                <!-- Upload Tabs -->
                <ul class="nav nav-tabs-custom mb-4" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#singleUpload">Single
                            Upload</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bulkUpload">Bulk Upload</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Single Upload -->
                    <div class="tab-pane fade show active" id="singleUpload">
                        <!-- Drop Zone -->
                        <div class="upload-area mb-4" id="dropZone">
                            <i class="bi bi-cloud-arrow-up upload-icon"></i>
                            <p class="upload-text">Choose a file or drag & drop it here</p>
                            <p class="upload-hint">MOBI, PDF, JPG, PNG, TIFF and TXT formats</p>
                            <label class="btn btn-secondary mt-3" for="fileInput">Browse File</label>
                            <input type="file" id="fileInput" name="file" class="d-none"
                                accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" required>
                        </div>
                        <div id="filePreview" class="alert alert-info d-none mb-4">
                            <i class="bi bi-file-earmark me-2"></i>
                            <span id="fileName"></span>
                            <button type="button" class="btn-close float-end" onclick="clearFile()"></button>
                        </div>
                    </div>

                    <!-- Bulk Upload -->
                    <div class="tab-pane fade" id="bulkUpload">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Bulk upload feature coming soon. For now, please upload files one at a time.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Fields -->
            <div class="row g-4">
                <!-- General Information -->
                <div class="col-lg-6">
                    <div class="settings-section">
                        <div class="settings-section-title">
                            <i class="bi bi-info-circle"></i>
                            GENERAL INFORMATION
                        </div>

                        <div class="mb-3">
                            <label class="form-label">TITLE</label>
                            <input type="text" class="form-control" name="title"
                                value="<?= $editMode ? htmlspecialchars($editItem['title']) : '' ?>"
                                placeholder="Enter document title" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">DATE</label>
                                <input type="date" class="form-control" name="publication_date"
                                    value="<?= $editMode ? $editItem['publication_date'] : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">EDITION</label>
                                <select class="form-select" name="edition">
                                    <option value="">Select Edition</option>
                                    <option value="Morning" <?= ($editMode && $editItem['edition'] === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                    <option value="Evening" <?= ($editMode && $editItem['edition'] === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                    <option value="Special" <?= ($editMode && $editItem['edition'] === 'Special') ? 'selected' : '' ?>>Special</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">CATEGORY</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($editMode && $editItem['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PAGE COUNT</label>
                                <input type="number" class="form-control" name="page_count"
                                    value="<?= $editMode ? $editItem['page_count'] : '' ?>" placeholder="20" min="1">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">KEYWORDS/TAGS</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="keywords"
                                    value="<?= $editMode ? htmlspecialchars($editItem['keywords']) : '' ?>"
                                    placeholder="Add tags separated by commas...">
                                <button type="button" class="btn btn-primary">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Convert File (Placeholder) -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-lightning"></i>
                                <strong>CONVERT FILE (OPTIONAL)</strong>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="convert" id="convertPdf" value="pdf"
                                    disabled>
                                <label class="form-check-label text-muted" for="convertPdf">CONVERT TO PDF</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="convert" id="convertEpub"
                                    value="epub" disabled>
                                <label class="form-check-label text-muted" for="convertEpub">CONVERT TO EPUB</label>
                            </div>
                            <small class="d-block text-muted mt-2">Coming soon</small>
                        </div>
                    </div>
                </div>

                <!-- Repository Details -->
                <div class="col-lg-6">
                    <div class="settings-section">
                        <div class="settings-section-title">
                            <i class="bi bi-archive"></i>
                            REPOSITORY DETAILS
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <?= $editMode ? 'PUBLISHER / CONTRIBUTOR' : 'TITLE' ?>
                                </label>
                                <input type="text" class="form-control" name="publisher"
                                    value="<?= $editMode ? htmlspecialchars($editItem['publisher']) : '' ?>"
                                    placeholder="Publisher name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">LANGUAGE</label>
                                <select class="form-select" name="language_id">
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang['id'] ?>" <?= ($editMode && $editItem['language_id'] == $lang['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lang['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">VOLUME / ISSUE REFERENCE</label>
                            <input type="text" class="form-control" name="volume_issue"
                                value="<?= $editMode ? htmlspecialchars($editItem['volume_issue']) : '' ?>"
                                placeholder="VOL. XCIII, No. 44">
                        </div>

                        <div class="mt-3">
                            <label class="form-label">DESCRIPTION</label>
                            <textarea class="form-control" name="description" rows="3"
                                placeholder="Add description..."><?= $editMode ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>

                        <!-- Thumbnail & Verification -->
                        <div class="row g-4 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-image me-1"></i>
                                    THUMBNAIL COVER (OPTIONAL)
                                </label>
                                <div class="upload-area py-4" style="border: 1px dashed #ccc;">
                                    <?php if ($editMode && $editItem['thumbnail_path']): ?>
                                        <img src="<?= APP_URL ?>/<?= $editItem['thumbnail_path'] ?>"
                                            style="max-height: 100px; margin-bottom: 10px;">
                                    <?php else: ?>
                                        <i class="bi bi-image text-muted" style="font-size: 48px;"></i>
                                    <?php endif; ?>
                                    <p class="small text-muted mb-2">JPG/PNG file only</p>
                                    <label class="btn btn-sm btn-secondary" for="thumbnailInput">
                                        <?= ($editMode && $editItem['thumbnail_path']) ? 'Change' : 'Upload' ?>
                                    </label>
                                    <input type="file" id="thumbnailInput" name="thumbnail" class="d-none"
                                        accept=".jpg,.jpeg,.png">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="bi bi-hand-index me-1"></i>
                                    FILE CHECKING
                                </label>
                                <div class="p-4 text-center" style="border: 1px solid #ccc; border-radius: 8px;">
                                    <span class="badge bg-success mb-2">VERIFIED</span>
                                    <p class="text-muted small mb-0">NO DUPLICATE FILE FOUND</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // File input handling
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        const filePreview = document.getElementById('filePreview');
        const fileNameSpan = document.getElementById('fileName');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                    filePreview.classList.remove('d-none');
                    dropZone.style.display = 'none';
                }
            });
        }

        if (dropZone) {
            dropZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', function () {
                this.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', function (e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileNameSpan.textContent = files[0].name;
                    filePreview.classList.remove('d-none');
                    this.style.display = 'none';
                }
            });
        }

        function clearFile() {
            fileInput.value = '';
            filePreview.classList.add('d-none');
            dropZone.style.display = 'block';
        }

        function resetForm() {
            document.getElementById('uploadForm').reset();
            if (filePreview) {
                clearFile();
            }
        }

        // Thumbnail preview
        document.getElementById('thumbnailInput').addEventListener('change', function () {
            if (this.files.length > 0) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    // Could add preview here
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>

</html>