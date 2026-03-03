<?php
/**
 * Upload Page Controller
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/calibre.php';
require_once __DIR__ . '/../backend/core/functions.php';

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

        // Debug Logging
        $logFile = 'C:/xampp/htdocs/qcpl/ArchiveSystemFilipiniana/pages/debug_upload.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request received. Action: $action\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - FILES Data: " . print_r($_FILES, true) . "\n", FILE_APPEND);

        // Log Raw Input (for debugging JSON/Streams)
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Raw Input Length: " . strlen($rawInput) . "\n", FILE_APPEND);
        }

        if ($action === 'upload') {
            // Check for upload mode (Single vs Bulk/Bind)
            // The frontend sends 'upload' action for simple uploads.
            // For Bulk Image Upload, the action might be different or handled here.
            // The original code had: if ($action === 'upload') { ... } 

            // Handle file upload
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];

                // Debug Logging
                $logFile = __DIR__ . '/debug_upload.log';
                $logMsg = date('Y-m-d H:i:s') . " - Processing upload: " . $fileName . " (" . $fileSize . " bytes)\n";
                file_put_contents($logFile, $logMsg, FILE_APPEND);

                // Get file extension
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Check allowed extensions
                if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Invalid Extension ($fileExt)\n", FILE_APPEND);
                    // ... existing error handling ...

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)]);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('File type not allowed.'));
                }

                // Check file size
                if ($fileSize > MAX_UPLOAD_SIZE) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: File too large (" . formatFileSize($fileSize) . ")\n", FILE_APPEND);
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: ' . formatFileSize(MAX_UPLOAD_SIZE)]);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('File too large.'));
                }

                // Check for duplicates
                if (checkDuplicateFile($fileName)) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Duplicate file ($fileName)\n", FILE_APPEND);
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'A file with this name already exists.']);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('A file with this name already exists.'));
                }

                // Generate organized path based on publication date, category, and file type
                // Format: uploads/newspapers/YYYY/MM/category/filetype/filename.ext
                $pubDate = $publicationDate ?: date('Y-m-d');
                list($year, $month) = explode('-', $pubDate);
                
                // Get category name for folder
                $categoryFolder = 'uncategorized';
                if ($categoryId) {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    $cat = $stmt->fetch();
                    if ($cat) {
                        // Sanitize category name for folder (remove special chars, spaces to underscores)
                        $categoryFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($cat['name']));
                    }
                }
                
                // Determine file type folder - each format gets its own folder
                $typeFolder = '';
                if ($fileExt === 'epub') {
                    $typeFolder = 'epub';
                } elseif ($fileExt === 'mobi') {
                    $typeFolder = 'mobi';
                } elseif ($fileExt === 'pdf') {
                    $typeFolder = 'pdf';
                } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'tiff', 'tif'])) {
                    $typeFolder = 'images';
                } else {
                    $typeFolder = 'documents';
                }
                
                // Create directory structure: uploads/newspapers/YYYY/MM/category/filetype/
                $uploadDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/{$typeFolder}/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                $relativeFilePath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/{$typeFolder}/" . $newFileName;

                // Move file
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - File moved successfully to $uploadPath\n", FILE_APPEND);

                    // Handle thumbnail upload - organized by year/month/category
                    $thumbnailPath = null;
                    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                        $thumbFile = $_FILES['thumbnail'];
                        $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                        if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                            // Create thumbnail directory: uploads/newspapers/YYYY/MM/category/thumbnails/
                            $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/";
                            if (!is_dir($thumbDir)) {
                                mkdir($thumbDir, 0777, true);
                            }
                            
                            $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                            $thumbPath = $thumbDir . $thumbFileName;

                            if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                                $thumbnailPath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/" . $thumbFileName;
                            }
                        }
                    }

                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, publication_date, edition, category_id, language_id, page_count, 
                                               keywords, publisher, volume_issue, description, file_path, file_name, 
                                               file_type, file_size, thumbnail_path, uploaded_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (
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
                            $relativeFilePath,
                            $fileName,
                            $fileExt,
                            $fileSize,
                            $thumbnailPath,
                            $currentUser['id']
                        ])
                    ) {
                        $newId = $pdo->lastInsertId();
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - DB Insert Success. New ID: $newId\n", FILE_APPEND);

                        // Convert MOBI to EPUB for web reading
                        if ($fileExt === 'mobi' && isCalibreAvailable()) {
                            $result = convertMobiToEpub($uploadPath);
                            if ($result['success']) {
                                error_log("MOBI converted to EPUB: " . $result['epub_path']);
                            } else {
                                error_log("MOBI conversion failed: " . $result['error']);
                            }
                        }

                        logActivity($currentUser['id'], 'upload', $title, $newId);

                        // Respond with JSON if AJAX, else redirect
                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'File uploaded successfully']);
                            exit;
                        }

                        redirect(APP_URL . '/dashboard.php?success=upload');
                    } else {
                        // DB Insert Failed
                        $errorInfo = $stmt->errorInfo();
                        error_log("Database Insert Error: " . print_r($errorInfo, true));
                        file_put_contents($logFile, date('Y-m-d H:i:s') . " - DB Insert Error: " . $errorInfo[2] . "\n", FILE_APPEND);

                        // Delete uploaded file to cleanup
                        if (file_exists($uploadPath))
                            unlink($uploadPath);
                        if ($thumbnailPath && file_exists(UPLOAD_PATH . 'thumbnails/' . basename($thumbnailPath)))
                            unlink(UPLOAD_PATH . 'thumbnails/' . basename($thumbnailPath));

                        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                            echo json_encode(['success' => false, 'message' => 'Database error: ' . $errorInfo[2]]);
                            exit;
                        }
                        redirect('upload.php?error=' . urlencode('Database error: ' . $errorInfo[2]));
                    }
                } else {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: Failed to move uploaded file\n", FILE_APPEND);
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('Failed to upload file.'));
                }
            } else {
                // File missing or upload error
                $errorCode = $_FILES['file']['error'] ?? 'No file sent';
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: check FILES['file'] failed. Error: $errorCode\n", FILE_APPEND);

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['success' => false, 'message' => 'No file selected or file too large (Server Limit).']);
                    exit;
                }
                redirect('upload.php?error=' . urlencode('No file selected or file too large.'));
            }
        }
    }

    // Bulk Image Upload (or Bind Mode)
    // The JS might send 'action=bulk_image_upload' or similar. 
    // In my previous read, it was: if ($action === 'bulk_image_upload')

    if ($action === 'bulk_image_upload') {
        // Handle bulk image upload -> Create CBZ
        if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
            // Increase limits for bulk processing
            set_time_limit(0);
            ini_set('memory_limit', '1024M');
            ignore_user_abort(true); // Continue processing even if client disconnects
            session_write_close(); // Prevent session locking during long process

            // Get form data (re-fetching here as it's outside the previous block)
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

            $fileCount = count($_FILES['files']['name']);

            if ($fileCount > 0) {
                $totalSize = 0;
                $savedPaths = [];
                $thumbnailPath = null;
                
                // Get publication date for folder organization
                $pubDate = $publicationDate ?: date('Y-m-d');
                list($year, $month) = explode('-', $pubDate);
                
                // Get category name for folder
                $categoryFolder = 'uncategorized';
                if ($categoryId) {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    $cat = $stmt->fetch();
                    if ($cat) {
                        // Sanitize category name for folder (remove special chars, spaces to underscores)
                        $categoryFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($cat['name']));
                    }
                }
                
                // Create directory structure: uploads/newspapers/YYYY/MM/category/images/
                $bulkDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/images";

                if (!is_dir($bulkDir)) {
                    mkdir($bulkDir, 0777, true);
                }

                // Process each file sequentially to maintain order
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['files']['tmp_name'][$i];
                        $name = $_FILES['files']['name'][$i];
                        $size = $_FILES['files']['size'][$i];
                        $totalSize += $size;

                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            // Generate safe sequential filename
                            $cleanName = sprintf("%03d_%s_%s", $i + 1, time(), generateRandomString(5)) . '.' . $ext;
                            $destination = $bulkDir . '/' . $cleanName;

                            if (move_uploaded_file($tmpName, $destination)) {
                                $relativePath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/images/" . $cleanName;
                                $savedPaths[] = $relativePath;
                            }
                        }
                    }
                }

                if (empty($savedPaths)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to upload any valid images']);
                    exit;
                }

                // Handle Custom Thumbnail (if selected) or use first image as fallback
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $thumbFile = $_FILES['thumbnail'];
                    $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
                    if (in_array($thumbExt, ['jpg', 'jpeg', 'png', 'webp'])) {
                        // Create thumbnail directory: uploads/newspapers/YYYY/MM/category/thumbnails/
                        $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/";
                        if (!is_dir($thumbDir)) {
                            mkdir($thumbDir, 0777, true);
                        }
                        
                        $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                        $thumbPath = $thumbDir . $thumbFileName;
                        if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                            $thumbnailPath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/" . $thumbFileName;
                        }
                    }
                } else {
                    $thumbnailPath = $savedPaths[0]; // Fallback to first uploaded image in sequence
                }

                $imagePathsJson = json_encode($savedPaths);

                // Insert into Database
                $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, publication_date, edition, category_id, language_id, page_count, 
                                               keywords, publisher, volume_issue, description, file_path, file_name, 
                                               file_type, file_size, thumbnail_path, uploaded_by, created_at,
                                               is_bulk_image, image_paths)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, ?)
                    ");

                if (
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
                        $savedPaths[0], // Main file path fallback string
                        'Bulk_Image_Gallery_' . time(),
                        'gallery',
                        $totalSize,
                        $thumbnailPath,
                        $currentUser['id'],
                        $imagePathsJson
                    ])
                ) {
                    $newId = $pdo->lastInsertId();
                    logActivity($currentUser['id'], 'upload', $title, $newId);

                    echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Bulk image sequence saved successfully']);
                    exit;
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Database Bulk Gallery Insert Error: " . print_r($errorInfo, true));

                    // Cleanup physical files if DB fails
                    foreach ($savedPaths as $path) {
                        $full = dirname(dirname(__DIR__)) . '/' . $path;
                        if (file_exists($full))
                            unlink($full);
                    }

                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $errorInfo[2]]);
                    exit;
                }
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No files received']);
        exit;
    }

    // Edit Action
    if ($action === 'edit') {
        $editId = intval($_POST['edit_id']);
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

        // Handle new thumbnail upload - organized by year/month/category
        $thumbnailPath = $_POST['existing_thumbnail'] ?? null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

            if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                // Get publication date for folder organization
                $pubDate = $publicationDate ?: date('Y-m-d');
                list($year, $month) = explode('-', $pubDate);
                
                // Get category name for folder
                $categoryFolder = 'uncategorized';
                if ($categoryId) {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    $cat = $stmt->fetch();
                    if ($cat) {
                        // Sanitize category name for folder (remove special chars, spaces to underscores)
                        $categoryFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($cat['name']));
                    }
                }
                
                // Create thumbnail directory: uploads/newspapers/YYYY/MM/category/thumbnails/
                $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/";
                if (!is_dir($thumbDir)) {
                    mkdir($thumbDir, 0777, true);
                }
                
                $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                $thumbPath = $thumbDir . $thumbFileName;

                if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                    $thumbnailPath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/thumbnails/" . $thumbFileName;
                }
            }
        }

        // Handle file replacement - organized by year/month/type
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmp = $file['tmp_name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExt, ALLOWED_EXTENSIONS) && $fileSize <= MAX_UPLOAD_SIZE) {
                // Get publication date for folder organization
                $pubDate = $publicationDate ?: date('Y-m-d');
                list($year, $month) = explode('-', $pubDate);
                
                // Get category name for folder
                $categoryFolder = 'uncategorized';
                if ($categoryId) {
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$categoryId]);
                    $cat = $stmt->fetch();
                    if ($cat) {
                        // Sanitize category name for folder (remove special chars, spaces to underscores)
                        $categoryFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($cat['name']));
                    }
                }
                
                // Determine file type folder - each format gets its own folder
                $typeFolder = '';
                if ($fileExt === 'epub') {
                    $typeFolder = 'epub';
                } elseif ($fileExt === 'mobi') {
                    $typeFolder = 'mobi';
                } elseif ($fileExt === 'pdf') {
                    $typeFolder = 'pdf';
                } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'tiff', 'tif'])) {
                    $typeFolder = 'images';
                } else {
                    $typeFolder = 'documents';
                }
                
                // Create directory structure: uploads/newspapers/YYYY/MM/category/filetype/
                $uploadDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$categoryFolder}/{$typeFolder}/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                $relativeFilePath = "uploads/newspapers/{$year}/{$month}/{$categoryFolder}/{$typeFolder}/" . $newFileName;

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
                        $relativeFilePath,
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

        logActivity($currentUser['id'], 'edit', $title, $editId);
        redirect('upload.php?success=edit&edit=' . $editId);
    }
}

// Load View
include __DIR__ . '/../views/upload.php';
