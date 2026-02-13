<?php
/**
 * Upload Page
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/calibre.php';

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
        // showAlert('danger', 'Document not found.');
        redirect('upload.php?error=' . urlencode('Document not found.'));
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
                    // showAlert('danger', 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS));
                    redirect('upload.php?error=' . urlencode('File type not allowed.'));
                }

                // Check file size
                if ($fileSize > MAX_UPLOAD_SIZE) {
                    // showAlert('danger', 'File too large. Maximum size: ' . formatFileSize(MAX_UPLOAD_SIZE));
                    redirect('upload.php?error=' . urlencode('File too large.'));
                }

                // Check for duplicates
                if (checkDuplicateFile($fileName)) {
                    // showAlert('danger', 'A file with this name already exists.');
                    redirect('upload.php?error=' . urlencode('A file with this name already exists.'));
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

                    $newId = $pdo->lastInsertId();

                    // Convert MOBI to EPUB for web reading
                    if ($fileExt === 'mobi' && isCalibreAvailable()) {
                        $result = convertMobiToEpub($uploadPath);
                        if ($result['success']) {
                            // Log conversion success
                            error_log("MOBI converted to EPUB: " . $result['epub_path']);
                        } else {
                            // Log conversion failure but don't block upload
                            error_log("MOBI conversion failed: " . $result['error']);
                        }
                    }

                    logActivity($currentUser['id'], 'upload', $title);

                    // Respond with JSON if AJAX, else redirect
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'File uploaded successfully']);
                        exit;
                    }

                    // showAlert('success', 'Document uploaded successfully.');
                } else {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
                        exit;
                    }
                    // showAlert('danger', 'Failed to upload file. Please try again.');
                    redirect('upload.php?error=' . urlencode('Failed to upload file.'));
                }
            } else {
                // showAlert('danger', 'Please select a file to upload.');
                redirect('upload.php?error=' . urlencode('Please select a file to upload.'));
            }
            redirect('dashboard.php?success=upload');
        }

        if ($action === 'bulk_image_upload') {
            // Handle bulk image upload -> Create CBZ
            if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
                $fileCount = count($_FILES['files']['name']);

                if ($fileCount > 0) {
                    // Create minimal filename for the archive based on title
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
                    $cbzFileName = time() . '_' . ($slug ?: 'archive') . '.cbz';
                    $cbzPath = UPLOAD_PATH . 'newspapers/' . $cbzFileName;

                    $zip = new ZipArchive();
                    if ($zip->open($cbzPath, ZipArchive::CREATE) === TRUE) {

                        $totalSize = 0;

                        // Add each file to the zip
                        for ($i = 0; $i < $fileCount; $i++) {
                            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmpName = $_FILES['files']['tmp_name'][$i];
                                $name = $_FILES['files']['name'][$i];
                                $totalSize += $_FILES['files']['size'][$i];

                                // Clean filename to ensure order and compatibility
                                // We prefix with index to ensure sort order in reader: 001_image.jpg
                                $ext = pathinfo($name, PATHINFO_EXTENSION);
                                $cleanName = sprintf("%03d_%s", $i + 1, $name);

                                $zip->addFile($tmpName, $cleanName);
                            }
                        }

                        $zip->close();

                        // Handle thumbnail (use first image if no thumbnail uploaded)
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

                        // Insert into Database
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
                            'uploads/newspapers/' . $cbzFileName,
                            $cbzFileName,
                            'cbz', // Custom type for our Comic Book Zip
                            $totalSize,
                            $thumbnailPath,
                            $currentUser['id']
                        ]);

                        $newId = $pdo->lastInsertId();
                        logActivity($currentUser['id'], 'upload', $title);

                        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Bulk upload compiled to book successfully']);
                        exit;

                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to create archive file']);
                        exit;
                    }
                }
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No files received']);
            exit;
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
            // showAlert('success', 'Document updated successfully.');
            redirect('upload.php?success=edit');
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

    <!-- Custom CSS for Upload Page -->
    <style>
        /* Bulk Upload Tab Styling */
        .bulk-file-tab {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #eee;
            border-radius: 12px;
            background: #fff;
            position: relative;
            margin-right: 12px;
            min-width: 180px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            overflow: visible;
            /* Allow close button to overlap if needed */
        }

        .bulk-file-tab:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            border-color: #ddd;
        }

        .bulk-file-tab.active {
            border: 2px solid #4C3939 !important;
            box-shadow: 0 4px 12px rgba(76, 57, 57, 0.1);
            background: #fff !important;
        }

        .bulk-tab-close-btn {
            position: absolute;
            top: 6px;
            right: 8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            transition: all 0.2s;
            cursor: pointer;
            z-index: 10;
        }

        .bulk-tab-close-btn:hover {
            background-color: #ffebee;
            color: #dc3545;
        }

        .bulk-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Page Order Grid */
        .page-order-item {
            transition: all 0.2s ease;
            cursor: move;
            position: relative;
            overflow: hidden;
        }

        .page-order-item img {
            transition: transform 0.2s ease;
        }

        .page-order-item:hover img {
            transform: scale(1.05);
        }

        .page-order-item.active-item {
            box-shadow: 0 6px 20px rgba(192, 139, 92, 0.5) !important;
            transform: scale(1.02);
            border-color: #C08B5C !important;
        }

        .page-order-item:not(.active-item):hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            border-color: #C08B5C;
        }

        .page-order-item.drag-over {
            background-color: rgba(192, 139, 92, 0.15) !important;
            border: 2px dashed #C08B5C !important;
            opacity: 0.9;
        }

        .page-order-item .position-absolute.top-0 {
            z-index: 10;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .page-order-item:hover .position-absolute.top-0 {
            opacity: 1;
        }

        /* Primary Thumbnail Badge */
        .primary-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #4C3939;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
            z-index: 15;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }

        /* Set Cover Button */
        .set-cover-btn {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.9);
            color: #4C3939;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid #4C3939;
            opacity: 0;
            transition: all 0.2s ease;
            white-space: nowrap;
            z-index: 15;
            cursor: pointer;
        }

        .page-order-item:hover .set-cover-btn {
            opacity: 1;
            bottom: 12px;
        }

        .set-cover-btn:hover {
            background: #4C3939;
            color: white;
        }

        /* Upload Drop Zone */
        .upload-drop-zone {
            border: 2px dashed #E6E8EB;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #F9FAFB;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-drop-zone:hover {
            border-color: #C08B5C;
            background-color: #FFF8F6;
        }

        .upload-drop-zone.dragover {
            border-color: #C08B5C;
            background-color: #FFF8F6;
            box-shadow: 0 4px 16px rgba(192, 139, 92, 0.2);
        }

        /* Disabled Button State */
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Tab Bar */
        #bulkFileTabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: thin;
            scrollbar-color: #C08B5C #f5f5f5;
        }

        #bulkFileTabs::-webkit-scrollbar {
            height: 6px;
        }

        #bulkFileTabs::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 3px;
        }

        #bulkFileTabs::-webkit-scrollbar-thumb {
            background: #C08B5C;
            border-radius: 3px;
        }

        #bulkFileTabs>div {
            white-space: nowrap;
            flex-shrink: 0;
        }
    </style>
</head>

<body class="<?= getSetting('dark_mode') === '1' ? 'dark-mode' : '' ?>">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <?php if ($editMode): ?>
                    <h1
                        style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif; margin: 0;">
                        Upload Archive</h1>
                    <p style="color: #888; margin: 5px 0 0;">Editing "<?= htmlspecialchars($editItem['title']) ?>"</p>
                <?php else: ?>
                    <h1
                        style="font-size: 28px; font-weight: 600; color: #2C1810; font-family: 'Playfair Display', Georgia, serif; margin: 0;">
                        Upload Archive</h1>
                    <p style="color: #888; margin: 5px 0 0;">Populate the repository with newspapers, documents, or media
                        files</p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($editMode): ?>
                    <button type="button" onclick="window.location.href='upload.php'"
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; font-weight: 500;">
                        Discard
                    </button>
                    <button type="submit" form="uploadForm"
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload
                    </button>
                <?php else: ?>
                    <button type="button" id="discardBtn" onclick="resetForm()" disabled
                        style="padding: 10px 20px; background: #f5f5f5; border: none; border-radius: 8px; color: #666; font-weight: 500; cursor: pointer;">
                        Discard
                    </button>
                    <button type="submit" form="uploadForm" id="uploadBtn" disabled
                        style="padding: 10px 20px; background: #4C3939; border: none; border-radius: 8px; color: white; font-weight: 500; cursor: pointer;">
                        <i class="bi bi-cloud-upload me-2"></i>Upload
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Real-time File Checking Indicator -->
        <div id="fileCheckingBar"
            style="background: #e8f5e9; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div
                        style="width: 36px; height: 36px; background: #4caf50; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-lg" style="color: white; font-size: 18px;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #2e7d32; font-size: 14px;">Real-time File Checking</div>
                        <div id="fileCheckText" style="color: #4caf50; font-size: 13px;">Duplicate detection complete.
                            No duplicate archives found for "filename"</div>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('fileCheckingBar').style.display='none'"
                    style="background: #2e7d32; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    DECLINE
                </button>
            </div>
        </div>

        <!-- Alerts replaced by Modals -->

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
                <div id="singleUploadContent">
                    <!-- Drop Zone Card (Mockup Style) -->
                    <div class="upload-drop-zone text-center p-5 mb-4 border-2 border-dashed rounded-3 bg-white"
                        style="border-style: dashed !important; border-color: #E0E0E0; border-width: 2px; cursor: pointer;"
                        onclick="document.getElementById('fileInput').click()"
                        ondragover="event.preventDefault(); this.style.borderColor = '#4C3939';"
                        ondragleave="this.style.borderColor = '#E0E0E0';" ondrop="handleDrop(event)">
                        <div class="mb-3">
                            <span style="display: inline-block; padding: 15px; background: #F5F5F5; border-radius: 12px;">
                                <i class="bi bi-cloud-arrow-up-fill" style="font-size: 24px; color: #5D4037;"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">Drag & Drop Files</h5>

                        <label class="btn px-4 py-2" for="fileInput"
                            style="background: #E0E0E0; color: #5D4037; font-weight: 700; font-size: 11px; letter-spacing: 1px; border-radius: 20px; text-transform: uppercase;"
                            onclick="event.stopPropagation()">
                            SELECT FILE
                        </label>
                        <input type="file" id="fileInput" name="file" class="d-none"
                            accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" multiple required>
                    </div>

                    <!-- File Preview (Moved here) -->
                    <div id="filePreview" class="d-none"
                        style="background: #F9F5F2; border: 2px solid #C08B5C; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(76, 57, 57, 0.1);">
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                            <div style="display: flex; align-items: center; gap: 15px; flex: 1; min-width: 0;">
                                <div
                                    style="width: 48px; height: 48px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 1px solid #E6D5C9; flex-shrink: 0;">
                                    <i class="bi bi-file-earmark-text-fill" style="font-size: 24px; color: #C08B5C;"></i>
                                </div>
                                <div style="overflow: hidden;">
                                    <div
                                        style="font-size: 11px; font-weight: 700; color: #8D6E63; letter-spacing: 0.5px; text-transform: uppercase;">
                                        SELECTED FILE</div>
                                    <span id="fileName"
                                        style="font-weight: 700; color: #4C3939; font-size: 16px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <!-- Duplicate Check Status -->
                                <div id="duplicateCheckStatus" style="display: block;">
                                    <span id="statusWaiting" style="display: none; color: #888; font-size: 13px;">
                                        <i class="bi bi-hourglass me-1"></i> Waiting...
                                    </span>
                                    <span id="statusChecking" style="display: none; color: #C08B5C; font-size: 13px;">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Checking...
                                    </span>
                                    <span id="statusReady" style="display: none; color: #22C55E; font-size: 13px;">
                                        <i class="bi bi-check-circle-fill me-1"></i> Ready to upload
                                    </span>
                                    <span id="statusDuplicate" style="display: none; color: #D32F2F; font-size: 13px;">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        <span id="duplicateMessage">Duplicate found!</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Upload Container -->
                <div id="bulkUploadContent" style="display: none;">

                    <!-- Bulk Header Statistics (Document Mode) - Moved to Top -->
                    <div id="bulkStatsBar"
                        class="d-none d-flex align-items-center justify-content-between p-4 mb-4 bg-white rounded-3 shadow-sm border">

                        <div class="d-flex align-items-center w-100 pe-4" style="gap: 20px;">
                            <div class="flex-grow-1 text-center border-end">
                                <span class="text-muted d-block text-uppercase"
                                    style="font-size: 10px; letter-spacing: 1px;">Total Files</span>
                                <span class="fs-4 fw-bold text-dark" id="totalFilesCount">0</span>
                            </div>
                            <div class="flex-grow-1 text-center border-end">
                                <span class="d-block text-uppercase"
                                    style="font-size: 10px; letter-spacing: 1px; color: #2e7d32;">Ready</span>
                                <span class="fs-4 fw-bold" style="color: #2e7d32;" id="readyFilesCount">0</span>
                            </div>
                            <div class="flex-grow-1 text-center">
                                <span class="d-block text-uppercase"
                                    style="font-size: 10px; letter-spacing: 1px; color: #ed6c02;">Pending</span>
                                <span class="fs-4 fw-bold" style="color: #ed6c02;" id="pendingFilesCount">0</span>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-dark fw-bold text-uppercase px-4 py-2"
                            style="font-size: 11px; letter-spacing: 0.5px; white-space: nowrap; border-radius: 20px;"
                            onclick="document.getElementById('bulkFileInput').click()">
                            <i class="bi bi-plus-lg me-1"></i> Add Files
                        </button>
                    </div>



                    <!-- Bulk Document Tabs/List - Moved below File Preview -->
                    <div id="bulkDocumentList" class="d-none mb-4">
                        <div class="d-flex gap-2 overflow-auto pb-2 mb-3" id="bulkFileTabs"
                            style="border-bottom: 2px solid #E0E0E0;">
                            <!-- Tabs will be injected here via JS -->
                        </div>
                    </div>

                    <!-- Bulk Image Page Order -->
                    <div id="bulkPageOrder" class="d-none mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-title m-0">
                                <i class="bi bi-grid-3x3-gap-fill"></i> PAGE ORDER MANAGEMENT
                            </div>
                            <span class="badge bg-light text-secondary border">DRAG AND DROP TO REORDER</span>
                        </div>

                        <div class="row g-3" id="pageOrderGrid">
                            <!-- Images will be injected here via JS -->
                        </div>
                    </div>

                    <!-- Bulk File Input (Hidden) -->
                    <input type="file" id="bulkFileInput" class="d-none"
                        accept=".pdf,.mobi,.epub,.txt,.jpg,.jpeg,.png,.tiff,.tif" multiple>

                    <!-- Edit Success Modal -->
                    <div class="modal fade" id="editSuccessModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                                <div class="modal-body text-center p-4">
                                    <div class="mb-3">
                                        <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center mx-auto"
                                            style="width: 64px; height: 64px;">
                                            <i class="bi bi-check-lg text-success" style="font-size: 32px;"></i>
                                        </div>
                                    </div>
                                    <h5 class="fw-bold mb-2">Update Successful!</h5>
                                    <p class="text-muted small mb-4">The document has been successfully updated.</p>
                                    <button type="button" class="btn btn-light rounded-pill px-4"
                                        data-bs-dismiss="modal">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Modal -->
                    <div class="modal fade" id="uploadErrorModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                                <div class="modal-body text-center p-4">
                                    <div class="mb-3">
                                        <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center mx-auto"
                                            style="width: 64px; height: 64px;">
                                            <i class="bi bi-exclamation-triangle-fill text-danger"
                                                style="font-size: 32px;"></i>
                                        </div>
                                    </div>
                                    <h5 class="fw-bold mb-2">Error</h5>
                                    <p class="text-muted small mb-4" id="uploadErrorMessage">
                                        <?= $alert ? $alert['message'] : '' ?></p>
                                    <button type="button" class="btn btn-light rounded-pill px-4"
                                        data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Script to handle modals -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const urlParams = new URLSearchParams(window.location.search);

                            if (urlParams.get('success') === 'edit') {
                                new bootstrap.Modal(document.getElementById('editSuccessModal')).show();
                                // Clean URL
                                window.history.replaceState({}, document.title, window.location.pathname);
                            }

                            // Check for error param or PHP alert
                            const errorMsg = urlParams.get('error');
                            if (errorMsg) {
                                document.getElementById('uploadErrorMessage').textContent = decodeURIComponent(errorMsg);
                                new bootstrap.Modal(document.getElementById('uploadErrorModal')).show();
                                window.history.replaceState({}, document.title, window.location.pathname);
                            }

                            <?php if ($alert && $alert['type'] === 'danger'): ?>
                                new bootstrap.Modal(document.getElementById('uploadErrorModal')).show();
                            <?php endif; ?>
                        });
                    </script>
                </div>
                </div>
                </div>
            <?php endif; ?>

            <!-- Form Fields -->
            <div class="row g-4">
                <!-- General Information -->
                <div class="col-lg-8">
                    <div class="content-card shadow-sm"
                        style="border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;">
                        <div class="section-title">
                            <i class="bi bi-info-circle-fill"></i>
                            GENERAL INFORMATION
                        </div>

                        <div class="mb-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 700; color: #374151; letter-spacing: 0.5px;">ARCHIVE
                                TITLE</label>
                            <input type="text" class="form-control form-control-custom" name="title"
                                value="<?= $editMode ? htmlspecialchars($editItem['title']) : '' ?>"
                                placeholder="e.g. Stranger Things: A Case Study" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom" style="font-weight: 700; color: #374151;">DATE
                                    PUBLISHED</label>
                                <div style="position: relative;">
                                    <input type="date" class="form-control form-control-custom" name="publication_date"
                                        value="<?= $editMode ? $editItem['publication_date'] : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom"
                                    style="font-weight: 700; color: #374151;">EDITION</label>
                                <select class="form-select form-control-custom" name="edition">
                                    <option value="">Select Edition</option>
                                    <option value="Morning" <?= ($editMode && $editItem['edition'] === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                    <option value="Evening" <?= ($editMode && $editItem['edition'] === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                    <option value="Special" <?= ($editMode && $editItem['edition'] === 'Special') ? 'selected' : '' ?>>Special</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label-custom"
                                    style="font-weight: 700; color: #374151;">CATEGORY</label>
                                <select class="form-select form-control-custom" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($editMode && $editItem['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom" style="font-weight: 700; color: #374151;">PAGE
                                    COUNT</label>
                                <input type="number" class="form-control form-control-custom" name="page_count"
                                    value="<?= $editMode ? $editItem['page_count'] : '' ?>" placeholder="20" min="1">
                            </div>
                        </div>





                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label-custom"
                                    style="font-weight: 700; color: #374151;">LANGUAGE</label>
                                <select class="form-select form-control-custom" name="language_id">
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?= $lang['id'] ?>" <?= ($editMode && $editItem['language_id'] == $lang['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lang['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom" style="font-weight: 700; color: #374151;">VOLUME /
                                    ISSUE REFERENCE</label>
                                <input type="text" class="form-control form-control-custom" name="volume_issue"
                                    value="<?= $editMode ? htmlspecialchars($editItem['volume_issue']) : '' ?>"
                                    placeholder="VOL. XCIII, No. 44">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label"
                                style="font-size: 11px; font-weight: 700; color: #374151; letter-spacing: 0.5px;">KEYWORDS
                                / TAGS</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-custom" id="keywordInput"
                                    placeholder="Type a tag and press add..." style="border-radius: 8px 0 0 8px;">
                                <button type="button" class="btn" id="addTagBtn"
                                    style="background: #4E342E; color: white; border-radius: 0 8px 8px 0; padding: 0 15px;">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <input type="hidden" name="keywords" id="hiddenKeywords"
                                value="<?= $editMode ? htmlspecialchars($editItem['keywords']) : '' ?>">
                            <div class="mt-2 d-flex gap-2 flex-wrap" id="tagsContainer">
                                <!-- Tags will be added here dynamically -->
                            </div>

                        </div>

                        <div class="mt-3">
                            <label class="form-label-custom"
                                style="font-weight: 700; color: #374151;">DESCRIPTION</label>
                            <textarea class="form-control form-control-custom" name="description" rows="3"
                                placeholder="Enter a comprehensive description of the archive content..."
                                style="min-height: 100px;"><?= $editMode ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>


                    </div>
                </div>

                <!-- Cover Preview / Thumbnail -->
                <div class="col-lg-4"> <!-- Adjusted column width to match layout -->
                    <div class="content-card h-100 shadow-sm"
                        style="display: flex; flex-direction: column; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-image me-2"></i>THUMBNAIL PREVIEW
                            </span>
                        </div>

                        <!-- Thumbnail Preview Area -->
                        <div class="flex-grow-1 d-flex flex-col align-items-center justify-content-center"
                            id="thumbnailArea"
                            style="background: #ffffff; border: 2px dashed #E6E8EB; border-radius: 12px; margin: 20px auto; width: 100%; max-width: 280px; aspect-ratio: 3/4; position: relative; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">

                            <div id="previewPlaceholder" class="text-center p-4">
                                <div
                                    style="width: 50px; height: 50px; background: #E6E8EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="bi bi-image" style="color: #9CA3AF; font-size: 24px;"></i>
                                </div>
                                <h6
                                    style="color: #374151; font-weight: 700; letter-spacing: 0.5px; font-size: 12px; margin-bottom: 8px;">
                                    UPLOAD THUMBNAIL</h6>
                                <p
                                    style="color: #6B7280; font-size: 10px; max-width: 180px; margin: 0 auto 15px; line-height: 1.5;">
                                    Recommended aspect ratio 3:4 for vertical archive cover images.
                                </p>
                                <button type="button" class="btn btn-outline-dark btn-sm shadow-sm"
                                    onclick="event.stopPropagation(); document.getElementById('thumbnailInput').click()"
                                    style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 6px 12px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                                    BROWSE
                                </button>
                            </div>

                            <!-- Thumbnail Container with Remove Button -->
                            <div id="thumbnailContainer"
                                style="display: none; position: relative; width: 100%; height: 100%; border-radius: 12px; overflow: hidden;">
                                <img id="thumbnailPreview"
                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                                <button type="button" id="removeThumbnailBtn"
                                    onclick="event.stopPropagation(); removeThumbnail();"
                                    class="btn btn-danger rounded-circle position-absolute shadow-sm"
                                    style="top: 8px; right: 8px; width: 28px; height: 28px; padding: 0; display: flex; align-items: center; justify-content: center; z-index: 10; border: 2px solid white;">
                                    <i class="bi bi-x-lg" style="font-size: 12px;"></i>
                                </button>
                            </div>

                            <!-- Dynamic Label Container (for Change Thumbnail button) -->
                            <div id="dynamicPreviewLabel" class="text-center" style="display: none; width: 100%;">
                                <button type="button" class="btn btn-outline-dark btn-sm"
                                    onclick="event.stopPropagation(); document.getElementById('thumbnailInput').click()"
                                    style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 8px 16px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                                    CHANGE THUMBNAIL
                                </button>
                            </div>

                            <input type="file" id="thumbnailInput" name="thumbnail" class="d-none"
                                accept=".jpg,.jpeg,.png,.tiff,.tif">
                        </div>
                    </div>
                </div>

                <!-- End Form Fields Row -->
            </div>
        </form>
    </main>

    <?php include __DIR__ . '/../layouts/footer.php'; ?>

    <script>
        // Add form submission handler for single file uploads
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('uploadForm');
            form.addEventListener('submit', function (e) {
                // Only prevent default for single file uploads (not bulk)
                if (!isBulkMode && fileInput && fileInput.files.length > 0) {
                    // For single files, allow normal submission since backend handles it
                    // The success modal will be triggered on page return via query parameter
                    uploadBtn.disabled = true;
                    uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Uploading...`;
                }
            });
        });
    </script>

    <script>
        // Button references
        const discardBtn = document.getElementById('discardBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // File input handling
        const fileInput = document.getElementById('fileInput');

        // Drop zone handling
        const dropZone = document.querySelector('.upload-area'); // Main drop zone

        const filePreview = document.getElementById('filePreview');
        const fileNameSpan = document.getElementById('fileName');

        // Function to check if form has input
        function checkFormInput() {
            // For bulk upload
            if (bulkFiles.length > 0) {
                if (discardBtn) discardBtn.disabled = false;
                // Check if all files have required fields
                const allFilesReady = bulkFiles.every(file => hasRequiredFields(file));

                if (uploadBtn) {
                    uploadBtn.disabled = !allFilesReady;
                    // Optional: Update button text to indicate status
                    if (!allFilesReady) {
                        uploadBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Complete Metadata';
                    } else {
                        uploadBtn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i>Finalize Upload';
                    }
                }
                return;
            }

            // For single upload
            const title = document.querySelector('input[name="title"]')?.value.trim();
            const hasFile = fileInput && fileInput.files.length > 0;
            const hasInput = title || hasFile; // Allow discard if title is typed

            if (discardBtn) discardBtn.disabled = !hasInput;
            if (uploadBtn) uploadBtn.disabled = !(title && hasFile); // Require both for upload
        }

        // Add input listeners to all form fields
        // Add input listeners to all form fields
        const formInputs = document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea');
        formInputs.forEach(el => {
            const updateHandler = function (e) {
                // Update bulk file data if in bulk mode
                if (bulkFiles.length > 0) {
                    updateCurrentBulkFileData(e.target.name, e.target.value);
                    // Update UI to reflect readiness (green checkmark)
                    // We only want to update the tabs part to avoid heavy re-renders, but for now updateBulkUI is safest
                    updateBulkUI();
                }
                checkFormInput();
            };

            el.addEventListener('input', updateHandler);
            el.addEventListener('change', updateHandler);
        });

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                    filePreview.classList.remove('d-none');
                    filePreview.style.display = 'flex'; // Ensure flex layout
                    // Hide drop zone content
                    document.querySelector('.upload-area').style.display = 'none';

                    checkFormInput();

                    // Trigger duplicate check when file is selected
                    const title = document.querySelector('input[name="title"]')?.value.trim();
                    if (title) {
                        checkDuplicate(title, this.files[0].name);
                    }
                }
            });
        }

        // Status display elements
        const statusWaiting = document.getElementById('statusWaiting');
        const statusChecking = document.getElementById('statusChecking');
        const statusReady = document.getElementById('statusReady');
        const statusDuplicate = document.getElementById('statusDuplicate');
        const duplicateMessage = document.getElementById('duplicateMessage');

        // Track duplicate check status
        let isDuplicateCheckPassed = false;
        let duplicateCheckTimeout = null;

        function showStatus(status, message = '') {
            // Hide all status states
            if (statusWaiting) statusWaiting.style.display = 'none';
            if (statusChecking) statusChecking.style.display = 'none';
            if (statusReady) statusReady.style.display = 'none';
            if (statusDuplicate) statusDuplicate.style.display = 'none';

            // Show the requested status
            switch (status) {
                case 'waiting':
                    if (statusWaiting) statusWaiting.style.display = 'block';
                    break;
                case 'checking':
                    if (statusChecking) statusChecking.style.display = 'block';
                    break;
                case 'ready':
                    if (statusReady) statusReady.style.display = 'block';
                    isDuplicateCheckPassed = true;
                    break;
                case 'duplicate':
                    if (statusDuplicate) statusDuplicate.style.display = 'block';
                    if (duplicateMessage && message) duplicateMessage.textContent = message;
                    isDuplicateCheckPassed = false;
                    break;
            }

            // Update upload button state
            updateUploadButtonState();
        }

        // Helper to check if a file has required fields
        function hasRequiredFields(file) {
            // Required: title, category_id, publication_date, publisher
            // Optional: description, keywords, thumbnail, edition, page_count, volume_issue, language_id

            return file.title &&
                file.category_id &&
                file.publication_date && // Assuming date is required
                file.publisher;          // Assuming publisher is required
        }

        function updateUploadButtonState() {
            const title = document.querySelector('input[name="title"]')?.value.trim();
            const hasFile = fileInput && fileInput.files.length > 0;

            if (uploadBtn) {
                // Enable upload button only if we have title, file, and passed duplicate check
                uploadBtn.disabled = !(title && hasFile && isDuplicateCheckPassed);
            }
        }

        async function checkDuplicate(title, fileName) {
            if (!title && !fileName) {
                showStatus('waiting');
                return;
            }

            showStatus('checking');

            try {
                const formData = new FormData();
                formData.append('title', title || '');
                formData.append('file_name', fileName || '');

                const response = await fetch('<?= APP_URL ?>/api/check_duplicate.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.is_duplicate) {
                    showStatus('duplicate', data.message);
                } else {
                    showStatus('ready');
                }
            } catch (error) {
                console.error('Duplicate check error:', error);
                // On error, allow upload (fail open)
                showStatus('ready');
            }
        }

        // Title input change handler - trigger duplicate check
        const titleInput = document.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.addEventListener('input', function () {
                // Debounce the duplicate check
                clearTimeout(duplicateCheckTimeout);

                const title = this.value.trim();
                const fileName = fileInput?.files[0]?.name || '';

                if (title && fileName) {
                    duplicateCheckTimeout = setTimeout(() => {
                        checkDuplicate(title, fileName);
                    }, 500); // Wait 500ms after user stops typing
                } else if (title || fileName) {
                    showStatus('waiting');
                    isDuplicateCheckPassed = false;
                    updateUploadButtonState();
                }
            });
        }

        function clearFile() {
            // Reset file input
            fileInput.value = '';

            // Reset UI for single upload
            filePreview.classList.add('d-none');
            filePreview.style.display = 'none';

            // Show drop zone again
            const dropZone = document.querySelector('.upload-drop-zone');
            if (dropZone) dropZone.style.display = 'block';

            // Reset bulk UI if in bulk mode
            if (isBulkMode) {
                bulkFiles = [];
                activeFileIndex = 0;
                isBulkMode = false;
                document.getElementById('bulkUploadContent').style.display = 'none';
                document.getElementById('singleUploadContent').style.display = 'block'; // Show single upload container
                updateBulkUI();
            }

            // Reset status indicators
            showStatus('waiting');
            isDuplicateCheckPassed = false;

            checkFormInput();
        }

        function resetForm() {
            // If in bulk mode, clear bulk files
            if (bulkFiles.length > 0) {
                bulkFiles = [];
                activeFileIndex = 0;
                updateBulkUI();
            }

            // Reset form fields
            document.getElementById('uploadForm').reset();
            clearFile();

            // Clear thumbnails
            const thumbnailPreview = document.getElementById('thumbnailPreview');
            const thumbnailIcon = document.getElementById('thumbnailIcon');
            if (thumbnailPreview) thumbnailPreview.style.display = 'none';
            if (thumbnailIcon) thumbnailIcon.style.display = 'flex';

            checkFormInput();
        }

        // Thumbnail preview
        const thumbnailInput = document.getElementById('thumbnailInput');
        const thumbnailPreview = document.getElementById('thumbnailPreview');
        const thumbnailIcon = document.getElementById('thumbnailIcon');
        const thumbnailArea = document.getElementById('thumbnailArea');

        // Add click handler to thumbnail area to trigger file input
        if (thumbnailArea && thumbnailInput) {
            thumbnailArea.addEventListener('click', function () {
                thumbnailInput.click();
            });
        }

        // Handle Drop for Single Upload
        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            document.querySelector('.upload-drop-zone').style.borderColor = '#E0E0E0'; // Reset border

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                const files = e.dataTransfer.files;
                const fileInput = document.getElementById('fileInput');
                fileInput.files = files; // Set input files

                // Trigger change event
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        }

        if (thumbnailInput) {
            thumbnailInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (thumbnailPreview) {
                            thumbnailPreview.src = e.target.result;
                            // thumbnailPreview.style.display = 'block'; // Removed individual display
                        }
                        const thumbnailContainer = document.getElementById('thumbnailContainer');
                        if (thumbnailContainer) thumbnailContainer.style.display = 'inline-block';

                        const removeBtn = document.getElementById('removeThumbnailBtn');
                        if (removeBtn) removeBtn.style.display = 'flex';

                        const previewPlaceholder = document.getElementById('previewPlaceholder');
                        if (previewPlaceholder) previewPlaceholder.style.display = 'none';

                        // Mark current file as edited in bulk mode
                        if (isBulkMode && bulkFiles[activeFileIndex]) {
                            bulkFiles[activeFileIndex].thumbnail = this.files[0];
                            bulkFiles[activeFileIndex].isEdited = true;
                            bulkFiles[activeFileIndex].lastEditTime = Date.now();
                            updateBulkUI();

                            // Update cover preview
                            updateCoverPreview(activeFileIndex);
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // ========== BULK UPLOAD FUNCTIONALITY ==========
        // Auto-detect bulk mode based on number of files selected

        let isBulkMode = false;
        let isEditingBulkFile = false; // Track if user is currently editing bulk file metadata

        // Store file data
        let bulkFiles = [];
        let activeFileIndex = 0;
        let primaryThumbnailId = null; // Track ID of the primary thumbnail

        // Generate unique ID for files
        function generateFileId() {
            return 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        // Helper to validate file extension
        function isValidExtension(file) {
            const allowedExtensions = ['pdf', 'mobi', 'epub', 'txt', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
            const ext = file.name.split('.').pop().toLowerCase();
            return allowedExtensions.includes(ext);
        }

        // Listen for file selection - auto-detect bulk mode based on file count
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                // Filter files first
                const rawFiles = Array.from(this.files);
                const files = rawFiles.filter(isValidExtension);

                if (rawFiles.length > files.length) {
                    alert(`Skipped ${rawFiles.length - files.length} unsupported file(s). Allowed: PDF, MOBI, EPUB, TXT, JPG, PNG, TIFF`);
                }

                if (files.length === 0) {
                    this.value = ''; // Reset if all invalid
                    return;
                }

                // Auto-detect bulk mode based on file count
                if (files.length > 1) {
                    isBulkMode = true;

                    files.forEach(file => {
                        const fileId = generateFileId();
                        // Initial metadata for each file
                        bulkFiles.push({
                            id: fileId,
                            file: file,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            ext: file.name.split('.').pop().toLowerCase(),
                            status: 'waiting',
                            isEdited: false, // Track if metadata has been filled in
                            lastEditTime: 0, // Track when last edited
                            // Metadata fields
                            title: file.name.split('.')[0], // Default title is filename
                            publication_date: '',
                            edition: '',
                            category_id: '',
                            page_count: '',
                            keywords: '',
                            publisher: '',
                            volume_issue: '',
                            description: '',
                            language_id: '',
                            thumbnail: null
                        });
                    });

                    // Set first file as default primary thumbnail if not set
                    if (!primaryThumbnailId && bulkFiles.length > 0) {
                        primaryThumbnailId = bulkFiles[0].id;
                    }

                    // Hide drop zone, show bulk UI
                    document.querySelector('.upload-drop-zone').style.display = 'none';

                    // Show bulk upload content
                    document.getElementById('singleUploadContent').style.display = 'none';
                    document.getElementById('bulkUploadContent').style.display = 'block';

                    // Explicitly hide single file preview
                    const filePreview = document.getElementById('filePreview');
                    if (filePreview) filePreview.classList.add('d-none');
                    if (filePreview) filePreview.style.display = 'none'; // Force hide

                    updateBulkUI();
                } else if (files.length === 1) {
                    // ... (Single upload logic - unchanged) ...
                    isBulkMode = false;
                    fileNameSpan.textContent = files[0].name;
                    filePreview.classList.remove('d-none');
                    filePreview.style.display = 'block';
                    document.getElementById('singleUploadContent').style.display = 'block'; // Ensure container is visible
                    document.querySelector('.upload-drop-zone').style.display = 'none';

                    // Auto-fill title with filename
                    const titleInput = document.querySelector('input[name="title"]');
                    if (titleInput && !titleInput.value) {
                        titleInput.value = files[0].name.split('.')[0];
                    }

                    // Check for duplicates
                    const title = document.querySelector('input[name="title"]').value;
                    checkDuplicate(title, files[0].name);

                    checkFormInput();
                }
            });
        }

        // Add File Handler for Bulk Mode
        const bulkFileInput = document.getElementById('bulkFileInput');
        if (bulkFileInput) {
            bulkFileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    const rawFiles = Array.from(this.files);
                    const newFiles = rawFiles.filter(isValidExtension);

                    if (rawFiles.length > newFiles.length) {
                        alert(`Skipped ${rawFiles.length - newFiles.length} unsupported file(s).`);
                    }

                    let addedCount = 0;

                    newFiles.forEach(file => {
                        // Check if file already exists in bulkFiles array
                        if (!bulkFiles.some(f => f.name === file.name && f.size === file.size)) {
                            const fileId = generateFileId();
                            bulkFiles.push({
                                id: fileId,
                                file: file,
                                name: file.name,
                                size: file.size,
                                type: file.type,
                                ext: file.name.split('.').pop().toLowerCase(),
                                status: 'waiting',
                                isEdited: false,
                                lastEditTime: 0,
                                title: file.name.split('.')[0],
                                publication_date: '',
                                edition: '',
                                category_id: '',
                                page_count: '',
                                keywords: '',
                                publisher: '',
                                volume_issue: '',
                                description: '',
                                language_id: '',
                                thumbnail: null
                            });
                            addedCount++;
                        }
                    });

                    if (addedCount > 0) {
                        // Set default primary if none exists
                        if (!primaryThumbnailId && bulkFiles.length > 0) {
                            primaryThumbnailId = bulkFiles[0].id;
                        }
                        updateBulkUI();
                        // Automatically switch to the last added file to ensure it's editable
                        setActiveFile(bulkFiles.length - 1);
                    }

                    // Reset input so change event fires again for same files if needed
                    this.value = '';
                }
            });
        }

        function updateCurrentBulkFileData(field, value) {
            if (bulkFiles[activeFileIndex]) {
                bulkFiles[activeFileIndex][field] = value;
            }
        }

        function loadBulkFileData(index) {
            // ... (unchanged) ...
            const fileData = bulkFiles[index];
            if (!fileData) return;

            // Populate form fields
            const fields = ['title', 'publication_date', 'edition', 'category_id', 'page_count', 'keywords', 'publisher', 'volume_issue', 'description', 'language_id'];
            fields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (input) input.value = fileData[field] || '';
            });

            // Update Cover Preview
            updateCoverPreview(index);

            // ... (rest of function) ...
            // Update Selected File Preview Text (Only show for Single Upload)
            const filePreview = document.getElementById('filePreview');
            const fileNameSpan = document.getElementById('fileName');

            if (filePreview) {
                if (!isBulkMode) {
                    filePreview.classList.remove('d-none');
                    if (fileNameSpan) fileNameSpan.textContent = fileData.name;
                } else {
                    filePreview.classList.add('d-none');
                }
            }

            // Determine active tab/ui
            // Update styling for active tab in bulk list
            // Update styling for active tab in bulk list
            document.querySelectorAll('.bulk-file-tab').forEach((el, idx) => {
                if (idx === index) {
                    el.classList.add('active');
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    el.classList.remove('active');
                }
            });
        }

        function setPrimaryThumbnail(id) {
            if (!id) return;
            primaryThumbnailId = id;
            updateBulkUI();
        }

        function updateBulkUI() {
            const bulkContainer = document.getElementById('bulkUploadContent');
            // const bulkDropZone = document.getElementById('bulkDropZone'); // Removed
            const bulkPageOrder = document.getElementById('bulkPageOrder');
            const bulkDocumentList = document.getElementById('bulkDocumentList');
            const bulkStatsBar = document.getElementById('bulkStatsBar');

            if (bulkFiles.length > 0) {
                // bulkDropZone.style.display = 'none'; // Removed

                // Determine mode: Image or Document
                // If any file is PDF/MOBI/EPUB/TXT, defaults to Document mode.
                // If all are images, Image mode.
                const hasDocuments = bulkFiles.some(f => ['pdf', 'mobi', 'epub', 'txt'].includes(f.ext));
                const mode = hasDocuments ? 'document' : 'image';

                // Update Counts for Document Mode
                // Update Counts for Document Mode (Whole Numbers)
                document.getElementById('totalFilesCount').textContent = bulkFiles.length;
                document.getElementById('readyFilesCount').textContent = bulkFiles.filter(f => f.isEdited === true).length;
                document.getElementById('pendingFilesCount').textContent = bulkFiles.filter(f => f.isEdited !== true).length;


                if (mode === 'image') {
                    // ... (Image Mode UI - unchanged) ...
                    bulkPageOrder.classList.remove('d-none');
                    bulkDocumentList.classList.add('d-none');
                    bulkStatsBar.classList.add('d-none'); // Don't show stats for images

                    const grid = document.getElementById('pageOrderGrid');
                    grid.innerHTML = '';

                    bulkFiles.forEach((file, idx) => {
                        // ... (Image Grid Logic - unchanged) ...
                        const col = document.createElement('div');
                        col.className = 'col-md-2 col-4'; // Grid layout

                        const isPrimary = file.id === primaryThumbnailId;

                        // Create thumbnail URL
                        const MAX_PREVIEW_SIZE = 20 * 1024 * 1024; // 20MB

                        if (file.file.size > MAX_PREVIEW_SIZE) {
                            // Large file handling
                        } else {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                const img = col.querySelector('img');
                                if (img) img.src = e.target.result;
                            };
                            reader.readAsDataURL(file.file);
                        }

                        col.innerHTML = `
                            <div class="position-relative page-order-item ${idx === activeFileIndex ? 'active-item' : ''}" onclick="setActiveFile(${idx})" 
                                 draggable="true"
                                 ondragstart="handleDragStart(event, ${idx})"
                                 ondragover="handleDragOver(event)"
                                 ondrop="handleDrop(event, ${idx})"
                                 ondragenter="handleDragEnter(event)"
                                 ondragleave="handleDragLeave(event)"
                                 style="background: #E0E0E0; border-radius: 8px; aspect-ratio: 3/4; overflow: hidden; cursor: pointer; border: ${idx === activeFileIndex ? '3px solid #C08B5C' : '1px solid #ddd'}; transition: all 0.2s;">
                                
                                ${file.file.size > (20 * 1024 * 1024) ?
                                `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #eee; color: #777; font-size: 10px; font-weight: bold; text-align: center; padding: 5px;">Large File<br>Preview N/A</div>` :
                                `<img src="" style="width: 100%; height: 100%; object-fit: cover; opacity: ${isPrimary ? '1' : '0.8'};" alt="Page ${idx + 1}" draggable="false">`
                            }
                                
                                <div class="position-absolute d-flex align-items-center justify-content-center" 
                                     style="top: 50%; left: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; font-weight: bold; font-size: 12px; pointer-events: none;">
                                    ${idx + 1}
                                </div>

                                ${isPrimary ?
                                `<div class="primary-badge"><i class="bi bi-star-fill me-1" style="font-size: 8px;"></i>COVER</div>` :
                                `<button type="button" class="set-cover-btn shadow-sm" onclick="event.stopPropagation(); setPrimaryThumbnail('${file.id}')">Set as Cover</button>`
                            }

                                <div class="position-absolute top-0 end-0 p-1" onclick="event.stopPropagation(); removeBulkFile(${idx});">
                                    <i class="bi bi-x-circle-fill text-danger bg-white rounded-circle" style="font-size: 14px; cursor: pointer;"></i>
                                </div>
                            </div>
                        `;
                        grid.appendChild(col);
                    });

                } else {
                    // Document Mode UI - Show stats and tabs
                    bulkPageOrder.classList.add('d-none');
                    bulkDocumentList.classList.remove('d-none');
                    bulkDocumentList.style.display = 'block'; // Force display
                    bulkStatsBar.classList.remove('d-none');
                    bulkStatsBar.style.display = 'flex'; // Force display flex for stats bar

                    // Update Counts based on validation
                    const readyCount = bulkFiles.filter(f => hasRequiredFields(f)).length;
                    const pendingCount = bulkFiles.length - readyCount;

                    document.getElementById('totalFilesCount').textContent = bulkFiles.length;
                    document.getElementById('readyFilesCount').textContent = readyCount;
                    document.getElementById('pendingFilesCount').textContent = pendingCount;

                    const tabsContainer = document.getElementById('bulkFileTabs');
                    tabsContainer.innerHTML = '';

                    bulkFiles.forEach((file, idx) => {
                        const isActive = idx === activeFileIndex;
                        const isReady = hasRequiredFields(file);

                        // Update the isEdited status based on required fields
                        file.isEdited = isReady;

                        const tab = document.createElement('div');
                        tab.className = `bulk-file-tab px-3 py-3 ${isActive ? 'active' : ''}`;

                        // Icons
                        let icon = 'bi-file-earmark-text';
                        if (file.ext === 'pdf') icon = 'bi-file-earmark-pdf';
                        if (file.ext === 'mobi' || file.ext === 'epub') icon = 'bi-book';

                        // Status Dot Color
                        const dotColor = isReady ? '#2e7d32' : '#ed6c02'; // Green or Orange

                        tab.innerHTML = `
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi ${icon} me-2" style="font-size: 16px; color: #4C3939;"></i>
                                <span class="fw-bold text-dark text-truncate" style="font-size: 12px; max-width: 120px;">${file.name}</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2">
                                <span class="text-muted" style="font-size: 10px;">${isReady ? 'Ready' : 'Pending Info'}</span>
                                <span class="bulk-status-dot" style="background-color: ${dotColor};"></span>
                            </div>
                            <div class="bulk-tab-close-btn" onclick="event.stopPropagation(); removeBulkFile(${idx});" title="Remove File">
                                <i class="bi bi-x" style="font-size: 14px;"></i>
                            </div>
                            <div class="position-absolute w-100 h-100 top-0 start-0" onclick="setActiveFile(${idx})" style="z-index: 5;"></div>
                        `;

                        // We need the close button to be clickable, so z-index of overlay should be lower than close button
                        // Close button z-index is 10 in CSS. Overlay z-index is 5.

                        tabsContainer.appendChild(tab);
                    });
                }

                // Load data for active file
                loadBulkFileData(activeFileIndex);

                // Update upload button
                if (uploadBtn) {
                    // For Documents "Publish All Files", for Images "Finalize Upload"
                    const btnText = mode === 'image' ? 'Finalize Upload' : 'Publish All Files';
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>${btnText}`;
                    uploadBtn.disabled = false;
                    uploadBtn.onclick = processBulkUpload;
                    uploadBtn.type = 'button';
                }

                // Add form field change listeners for bulk mode sync
                addBulkFormListeners();

            } else {
                // No bulk files, reset UI
                // ... (reset UI logic - unchanged) ...
                // bulkDropZone.style.display = 'block'; // Removed
                bulkPageOrder.classList.add('d-none');
                bulkDocumentList.classList.add('d-none');
                bulkStatsBar.classList.add('d-none');
                document.getElementById('bulkUploadContent').style.display = 'none'; // Hide container if empty
                document.getElementById('bulkUploadContent').style.display = 'none'; // Hide container if empty
                // document.getElementById('filePreview').classList.add('d-none'); // Hide preview (Already handled by logic)

                // Show drop zone
                document.querySelector('.upload-drop-zone').style.display = 'block'; // Show MAIN drop zone if resetting completely? 
                // Wait, if we are in bulk mode tab but no files, show BULK drop zone.
                // But if we reset logic... 

                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload`;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
                removeBulkFormListeners();
            }
        }

        // Function to remove a single file from bulk list
        function removeBulkFile(idx) {
            const removedFile = bulkFiles[idx];
            bulkFiles.splice(idx, 1);

            // Check if removed file was primary
            if (removedFile.id === primaryThumbnailId && bulkFiles.length > 0) {
                // Assign new primary (first available)
                primaryThumbnailId = bulkFiles[0].id;
            }

            // If all files removed, reset everything
            if (bulkFiles.length === 0) {
                fileInput.value = '';
                isBulkMode = false;
                activeFileIndex = 0;
                primaryThumbnailId = null;
                isEditingBulkFile = false;

                // Reset UI
                // ... (unchanged) ...
                document.getElementById('bulkUploadContent').style.display = 'none';
                document.getElementById('singleUploadContent').style.display = 'block'; // Show single upload container
                document.querySelector('.upload-drop-zone').style.display = 'block';

                // Reset buttons
                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload`;
                    uploadBtn.disabled = true;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
                document.querySelector('.upload-drop-zone').style.display = 'block';

                // Reset buttons
                if (uploadBtn) {
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload`;
                    uploadBtn.disabled = true;
                    uploadBtn.type = 'submit';
                    uploadBtn.onclick = null;
                }
                if (discardBtn) discardBtn.disabled = true;

                // Clear Single File Preview if any
                const filePreview = document.getElementById('filePreview');
                if (filePreview) filePreview.classList.add('d-none');
                const fileNameSpan = document.getElementById('fileName');
                if (fileNameSpan) fileNameSpan.textContent = '';

                checkFormInput();
            } else {
                // Some files remain, adjust active index
                if (activeFileIndex >= bulkFiles.length) {
                    activeFileIndex = Math.max(0, bulkFiles.length - 1);
                }
                updateBulkUI();
            }
        }

        // Form field listeners for bulk mode
        let bulkFormListenersAdded = false;
        const formFieldNames = ['title', 'publication_date', 'edition', 'category_id', 'page_count', 'keywords', 'publisher', 'volume_issue', 'description', 'language_id'];

        function handleFormFieldChange(e) {
            if (isBulkMode && bulkFiles.length > 0) {
                const fieldName = e.target.name;
                const value = e.target.value;
                updateCurrentBulkFileData(fieldName, value);

                // Mark as edited and set timestamp
                if (bulkFiles[activeFileIndex]) {
                    bulkFiles[activeFileIndex].isEdited = true;
                    bulkFiles[activeFileIndex].lastEditTime = Date.now();
                }

                // Set editing flag and disable upload button
                isEditingBulkFile = true;
                if (uploadBtn) uploadBtn.disabled = true;

                // Update UI to show file status
                updateBulkUI();
            }
        }

        function addBulkFormListeners() {
            if (bulkFormListenersAdded) return;

            formFieldNames.forEach(name => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) {
                    input.addEventListener('change', handleFormFieldChange);
                    input.addEventListener('input', handleFormFieldChange);
                }
            });
            bulkFormListenersAdded = true;
        }

        function removeBulkFormListeners() {
            formFieldNames.forEach(name => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input) {
                    input.removeEventListener('change', handleFormFieldChange);
                    input.removeEventListener('input', handleFormFieldChange);
                }
            });
            bulkFormListenersAdded = false;
        }

        function setActiveFile(idx) {
            isEditingBulkFile = false; // Reset editing flag when switching files
            if (uploadBtn) uploadBtn.disabled = false; // Re-enable upload button when switching away
            activeFileIndex = idx;
            loadBulkFileData(idx);
        }

        async function processBulkUpload() {
            if (bulkFiles.length === 0) return;

            uploadBtn.disabled = true;
            uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Uploading...`;

            // Determine Mode
            const hasDocuments = bulkFiles.some(f => ['pdf', 'mobi', 'epub', 'txt'].includes(f.ext));
            const mode = hasDocuments ? 'document' : 'image';

            if (mode === 'image') {
                // ========== IMAGE MODE: SINGLE UPLOAD (CBZ) ==========

                // Use metadata from active file (or first file) as the shared metadata
                const metaData = bulkFiles[0]; // Logic: user sets metadata 

                const formData = new FormData();
                formData.append('action', 'bulk_image_upload');

                // Append all files
                bulkFiles.forEach((f, index) => {
                    formData.append('files[]', f.file);
                });

                // Append shared metadata
                formData.append('title', document.querySelector('[name="title"]').value);
                formData.append('publication_date', document.querySelector('[name="publication_date"]').value);
                formData.append('edition', document.querySelector('[name="edition"]').value);
                formData.append('category_id', document.querySelector('[name="category_id"]').value);
                formData.append('language_id', document.querySelector('[name="language_id"]').value);
                formData.append('page_count', document.querySelector('[name="page_count"]').value);
                formData.append('keywords', document.querySelector('[name="keywords"]').value);
                formData.append('publisher', document.querySelector('[name="publisher"]').value);
                formData.append('volume_issue', document.querySelector('[name="volume_issue"]').value);
                formData.append('description', document.querySelector('[name="description"]').value);

                // Handle thumbnail
                // If the user selected a primary thumbnail from the grid, send that file separate as 'thumbnail'
                // Or if they uploaded a custom one via the form input
                const thumbInput = document.getElementById('thumbnailInput');

                if (thumbInput && thumbInput.files.length > 0) {
                    // Custom uploaded thumbnail takes precedence
                    formData.append('thumbnail', thumbInput.files[0]);
                } else if (primaryThumbnailId) {
                    // Find the file marked as primary
                    const primaryFile = bulkFiles.find(f => f.id === primaryThumbnailId);
                    if (primaryFile) {
                        // Send it as the thumbnail file
                        // Note: Backend expects 'thumbnail' file input
                        // We append it with a unique name to avoid conflict if needed, or just as 'thumbnail'
                        // Since we are creating a CBZ from all files, this file is ALSO inside the CBZ.
                        // But we want it as the cover in DB.
                        formData.append('thumbnail', primaryFile.file);
                    }
                }

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showUploadSuccessModal(result.id, true);
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 2000);
                    } else {
                        alert('Upload failed: ' + result.message);
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Finalize Upload`;
                    }

                } catch (e) {
                    console.error(e);
                    alert('An error occurred during upload.');
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Finalize Upload`;
                }

            } else {
                // ========== DOCUMENT MODE: ORIGINAL LOGIC ==========

                let uploadedCount = 0;
                let errorCount = 0;
                let firstUploadedId = null;

                for (let i = 0; i < bulkFiles.length; i++) {
                    const fileData = bulkFiles[i];
                    if (fileData.status === 'success') {
                        uploadedCount++;
                        continue; // Skip already uploaded
                    }

                    // Update UI to show processing
                    setActiveFile(i);

                    const formData = new FormData();
                    formData.append('action', 'upload');
                    formData.append('file', fileData.file);

                    // Append metadata
                    Object.keys(fileData).forEach(key => {
                        if (key !== 'file' && key !== 'status' && key !== 'thumbnail') {
                            formData.append(key, fileData[key]);
                        }
                    });

                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        // Since the PHP redirects, we might get a redirected response
                        // Ideally check response text or status
                        let text = await response.text();
                        let json = null;
                        try { json = JSON.parse(text); } catch (e) { }

                        if ((json && json.success) || text.includes('uploaded successfully') || response.ok) {
                            fileData.status = 'success';
                            uploadedCount++;

                            // Extract ID from response if available
                            if (json && json.id) {
                                if (!firstUploadedId) firstUploadedId = json.id;
                            }
                        } else {
                            fileData.status = 'error';
                            errorCount++;
                        }
                    } catch (e) {
                        console.error(e);
                        fileData.status = 'error';
                        errorCount++;
                    }

                    updateBulkUI();
                }

                uploadBtn.disabled = false;
                uploadBtn.innerHTML = `<i class="bi bi-cloud-upload me-2"></i>Upload Completed`;

                if (errorCount === 0) {
                    // Show success modal
                    showUploadSuccessModal(firstUploadedId, true);
                    // Redirect after a delay
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 3000);
                } else {
                    alert(`Upload complete with ${errorCount} errors.`);
                }
            }
        }

        // ========== KEYWORDS / TAGS FUNCTIONALITY ==========
        const keywordInput = document.getElementById('keywordInput');
        const addTagBtn = document.getElementById('addTagBtn');
        const tagsContainer = document.getElementById('tagsContainer');
        const hiddenKeywords = document.getElementById('hiddenKeywords');

        // Initialize tags from hidden input (if editing)
        let tags = [];
        if (hiddenKeywords.value) {
            tags = hiddenKeywords.value.split(',').map(t => t.trim()).filter(t => t);
            renderTags();
        }

        function renderTags() {
            tagsContainer.innerHTML = '';
            tags.forEach((tag, index) => {
                const badge = document.createElement('span');
                badge.className = 'badge';
                badge.style.cssText = 'background: #4C3939; color: white; padding: 6px 10px; border-radius: 6px; margin-right: 5px; font-weight: 500; font-size: 11px; margin-bottom: 5px; display: inline-flex; align-items: center;';
                badge.innerHTML = `${tag} <i class="bi bi-x ms-2" style="cursor: pointer;" onclick="removeTag(${index})"></i>`;
                tagsContainer.appendChild(badge);
            });
            hiddenKeywords.value = tags.join(', ');
        }

        function addTag() {
            const val = keywordInput.value.trim();
            if (val && !tags.includes(val)) {
                tags.push(val);
                renderTags();
                keywordInput.value = '';
            }
        }

        function removeTag(index) {
            tags.splice(index, 1);
            renderTags();
        }

        if (addTagBtn) {
            addTagBtn.addEventListener('click', addTag);
        }

        if (keywordInput) {
            keywordInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    addTag();
                }
            });
        }
        // ========== COVER PREVIEW & DRAG-DROP LOGIC ==========

        function updateCoverPreview(index) {
            if (!bulkFiles[index]) return;

            const mode = bulkFiles.some(f => ['pdf', 'mobi', 'epub', 'txt'].includes(f.ext)) ? 'document' : 'image';
            const file = bulkFiles[index];

            const previewImg = document.getElementById('thumbnailPreview');
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            const dynamicLabel = document.getElementById('dynamicPreviewLabel');

            // Helper to show image and hide placeholder
            const showImage = (src) => {
                if (previewImg) {
                    previewImg.src = src;
                    // previewImg.style.display = 'block';
                }
                const thumbnailContainer = document.getElementById('thumbnailContainer');
                if (thumbnailContainer) thumbnailContainer.style.display = 'inline-block';

                if (previewPlaceholder) previewPlaceholder.style.display = 'none';
                if (dynamicLabel) dynamicLabel.style.display = 'block';
            };

            // Helper to show placeholder and hide image
            const showPlaceholder = () => {
                // if (previewImg) previewImg.style.display = 'none';
                const thumbnailContainer = document.getElementById('thumbnailContainer');
                if (thumbnailContainer) thumbnailContainer.style.display = 'none';

                if (previewPlaceholder) previewPlaceholder.style.display = 'block';
                if (dynamicLabel) dynamicLabel.style.display = 'none';
            };

            // Helper to update dynamic label text
            const updateDynamicLabel = (mainText, buttonText) => {
                if (dynamicLabel) {
                    dynamicLabel.innerHTML = `
                         <h6 style="color: #4B5563; font-weight: 700; letter-spacing: 0.5px; font-size: 11px; margin-bottom: 8px; text-transform: uppercase;">${mainText}</h6>
                         <button type="button" class="btn btn-outline-dark btn-sm" onclick="event.stopPropagation(); document.getElementById('thumbnailInput').click()" 
                               style="border-radius: 4px; font-size: 10px; font-weight: 700; padding: 8px 16px; letter-spacing: 0.5px; border-color: #4C3939; color: #4C3939;">
                            ${buttonText}
                         </button>
                    `;
                }
            };

            const isImageFile = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file.ext);
            const isDocumentFile = ['pdf', 'mobi', 'epub', 'txt'].includes(file.ext);

            const removeBtn = document.getElementById('removeThumbnailBtn');

            if (isImageFile) {
                // If the file is an image, display it directly
                const reader = new FileReader();
                reader.onload = (e) => showImage(e.target.result);
                reader.readAsDataURL(file.file);
                updateDynamicLabel('IMAGE PREVIEW', 'Change Image');
                // Hide remove button for main images as they are the content
                if (removeBtn) removeBtn.style.display = 'none';
            } else if (isDocumentFile) {
                // If the file is a document
                if (file.thumbnail) {
                    // If a custom thumbnail is provided for the document
                    const reader = new FileReader();
                    reader.onload = (e) => showImage(e.target.result);
                    reader.readAsDataURL(file.thumbnail);
                    updateDynamicLabel('DOCUMENT COVER', 'Change Cover');
                    // Show remove button for custom thumbnails
                    if (removeBtn) removeBtn.style.display = 'flex';
                } else {
                    // No custom thumbnail, show placeholder
                    showPlaceholder();
                    updateDynamicLabel('DOCUMENT COVER', 'Upload Cover');
                }
            } else {
                // Fallback for other file types or if no file
                showPlaceholder();
                updateDynamicLabel('PREVIEW', 'No Preview Available');
            }
        }

        // Removed redundant updatePreviewLabel function as logic is now handled in updateCoverPreview and HTML structure

        // Drag and Drop Logic
        let draggedItem = null;

        function handleDragStart(e, index) {
            draggedItem = index;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', index);
            e.target.closest('.page-order-item').style.opacity = '0.4';
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            e.target.closest('.page-order-item')?.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.target.closest('.page-order-item')?.classList.remove('drag-over');
        }

        function handleDrop(e, index) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            // Remove style
            document.querySelectorAll('.page-order-item').forEach(el => {
                el.style.opacity = '1';
                el.classList.remove('drag-over');
            });

            if (draggedItem !== null && draggedItem !== index) {
                // Reorder array
                const item = bulkFiles.splice(draggedItem, 1)[0];
                bulkFiles.splice(index, 0, item);

                // Update active index if needed
                if (activeFileIndex === draggedItem) activeFileIndex = index;

                updateBulkUI();
            }

            return false;
        }
        function removeThumbnail() {
            // Clear input
            if (thumbnailInput) thumbnailInput.value = '';

            // Hide preview container
            const thumbnailContainer = document.getElementById('thumbnailContainer');
            if (thumbnailContainer) thumbnailContainer.style.display = 'none';

            // Show placeholder
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            if (previewPlaceholder) previewPlaceholder.style.display = 'block';

            // Hide dynamic label
            const dynamicPreviewLabel = document.getElementById('dynamicPreviewLabel');
            if (dynamicPreviewLabel) dynamicPreviewLabel.style.display = 'none';

            // Handle Bulk Mode
            if (isBulkMode && bulkFiles[activeFileIndex]) {
                bulkFiles[activeFileIndex].thumbnail = null;
                bulkFiles[activeFileIndex].isEdited = true; // Metadata change
                bulkFiles[activeFileIndex].lastEditTime = Date.now();
                updateBulkUI();

                // Update active file thumbnail reference
                // If it was a file object, we removed it. 
            }
        }
    </script>

    <!-- Upload Success Modal -->
    <div class="modal fade" id="uploadSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden;">
                <div class="modal-body text-center" style="padding: 40px 30px;">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="position: absolute; top: 15px; right: 15px;"></button>
                    <div
                        style="width: 80px; height: 80px; background: #22C55E; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
                        <i class="bi bi-check-lg" style="font-size: 40px; color: white;"></i>
                    </div>
                    <h4 style="font-weight: 700; color: #333; margin-bottom: 10px;">Upload Success</h4>
                    <p style="color: #888; font-size: 14px; margin-bottom: 30px;">Item added successfully and is now
                        available for viewing</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn" id="viewUploadedBtn"
                            style="background: #f5f5f5; color: #666; padding: 12px 30px; border-radius: 10px; font-weight: 600; border: none;">
                            View
                        </button>
                        <button type="button" class="btn" data-bs-dismiss="modal"
                            style="background: #4C3939; color: white; padding: 12px 30px; border-radius: 10px; font-weight: 600; border: none;">
                            Okay
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Upload success modal handling
        let lastUploadedId = null;
        let isBulkUploadSuccess = false;

        function showUploadSuccessModal(uploadedId, isBulk = false) {
            lastUploadedId = uploadedId;
            isBulkUploadSuccess = isBulk;

            const modal = document.getElementById('uploadSuccessModal');
            const modalTitle = modal.querySelector('h4');
            const modalText = modal.querySelector('p');

            if (isBulk) {
                modalTitle.textContent = 'Bulk Upload Complete';
                modalText.textContent = `${bulkFiles.length} item(s) successfully added and are now available for viewing.`;
            } else {
                modalTitle.textContent = 'Upload Success';
                modalText.textContent = 'Item added successfully and is now available for viewing';
            }

            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        document.getElementById('viewUploadedBtn')?.addEventListener('click', function () {
            if (lastUploadedId) {
                window.location.href = 'dashboard.php?view=' + lastUploadedId;
            } else {
                window.location.href = 'dashboard.php';
            }
        });

        // Show success modal if page reload after POST
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('success') === 'upload') {
                // Single file upload success
                const modalTitle = document.querySelector('#uploadSuccessModal h4');
                const modalText = document.querySelector('#uploadSuccessModal p');
                if (modalTitle) modalTitle.textContent = 'Upload Success';
                if (modalText) modalText.textContent = 'Document uploaded successfully and is now available for viewing.';

                const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
                modal.show();

                // Clean up URL
                history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('success') === 'edit') {
                // Update modal text for edit success
                const modalTitle = document.querySelector('#uploadSuccessModal h4');
                const modalText = document.querySelector('#uploadSuccessModal p');
                if (modalTitle) modalTitle.textContent = 'Update Success';
                if (modalText) modalText.textContent = 'Document updated successfully and is now available for viewing.';

                const modal = new bootstrap.Modal(document.getElementById('uploadSuccessModal'));
                modal.show();

                // Clean up URL
                history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>

</html>