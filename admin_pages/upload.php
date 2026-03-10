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

// Get active form template and its fields (with fallback if tables don't exist)
$activeForm = null;
$formFields = [];
$customFields = [];

try {
    $stmt = $pdo->query("SELECT * FROM form_templates WHERE is_active = 1");
    $activeForm = $stmt->fetch();

    if ($activeForm) {
        // Load form fields
        $stmt = $pdo->prepare("
            SELECT * FROM form_fields 
            WHERE form_id = ? 
            ORDER BY display_order ASC
        ");
        $stmt->execute([$activeForm['id']]);
        $formFields = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Tables don't exist yet - fall back to legacy custom fields
    $activeForm = null;
    $formFields = [];
}

// Keep backward compatibility: also load old custom fields if no active form
if (empty($formFields)) {
    $stmt = $pdo->query("
        SELECT * FROM custom_metadata_fields 
        WHERE is_enabled = 1 
        ORDER BY display_order ASC
    ");
    $customFields = $stmt->fetchAll();
}

// Check if editing
$editMode = isset($_GET['edit']) && intval($_GET['edit']) > 0;
$editItem = null;
$customMetadataValues = [];

if ($editMode) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM newspapers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();

    if (!$editItem) {
        redirect('upload.php?error=' . urlencode('Document not found.'));
    }

    // Get existing custom metadata values for this file
    $stmt = $pdo->prepare("
        SELECT field_id, field_value 
        FROM custom_metadata_values 
        WHERE file_id = ?
    ");
    $stmt->execute([$editItem['id']]);
    $values = $stmt->fetchAll();
    foreach ($values as $value) {
        $customMetadataValues[$value['field_id']] = $value['field_value'];
    }

    // Auto-fill title from core data if the custom metadata value is empty
    $fieldsToCheck = !empty($formFields) ? $formFields : $customFields;
    foreach ($fieldsToCheck as $field) {
        $fieldId = (isset($field['id']) && $field['id']) ? $field['id'] : null;
        if (!$fieldId)
            continue;

        $val = $customMetadataValues[$fieldId] ?? '';

        if (empty($val)) {
            $label = strtolower(trim($field['field_label'] ?? $field['field_name'] ?? ''));

            if ($label === 'title') {
                // Use core title, or fall back to filename without extension
                $coreTitle = $editItem['title'] ?? '';
                if (empty($coreTitle)) {
                    $fileName = $editItem['file_name'] ?? '';
                    $coreTitle = pathinfo($fileName, PATHINFO_FILENAME);
                    // Clean up auto-generated filenames (timestamps + random strings)
                    $coreTitle = preg_replace('/^\d+_[a-f0-9]+_?/', '', $coreTitle);
                    $coreTitle = str_replace(['_', '-'], ' ', $coreTitle);
                    $coreTitle = ucwords(trim($coreTitle));
                }
                $customMetadataValues[$fieldId] = $coreTitle;
            }
        }
    }
}

function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function normalizePublicationDate($rawDate)
{
    $rawDate = trim((string) $rawDate);
    if ($rawDate === '') {
        return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $match)) {
        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $rawDate, $match)) {
        $year = (int) $match[1];
        $month = (int) $match[2];
        if ($month >= 1 && $month <= 12) {
            return sprintf('%04d-%02d', $year, $month);
        }
    }

    return null;
}

function rejectInvalidPublicationDate()
{
    $message = 'Publication date must use a 4-digit year in YYYY-MM-DD format.';

    if (isAjaxRequest()) {
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    redirect('upload.php?error=' . urlencode($message));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');

        // Validate custom required fields (form templates or legacy custom fields)
        $fieldsToValidate = !empty($formFields) ? $formFields : $customFields;

        // Auto-extract core fields from custom fields if standard inputs are missing
        if (!empty($fieldsToValidate)) {
            foreach ($fieldsToValidate as $field) {
                $fieldKey = !empty($formFields) ? 'field_' . $field['id'] : 'custom_' . $field['field_name'];
                $fieldValue = $_POST[$fieldKey] ?? null;

                if ($fieldValue !== null && $fieldValue !== '') {
                    $label = strtolower(trim($field['field_label'] ?? $field['field_name'] ?? ''));
                    if ($label === 'title' && empty($title)) {
                        if (is_array($fieldValue)) {
                            $title = sanitize($fieldValue[0] ?? '');
                        } else {
                            $title = sanitize($fieldValue);
                        }
                    }
                }
            }
        }

        // Validate custom required fields (form templates or legacy custom fields)
        $fieldsToValidate = !empty($formFields) ? $formFields : $customFields;
        $customFieldErrors = [];

        // Only validate if there are actually fields to validate
        if (!empty($fieldsToValidate)) {
            foreach ($fieldsToValidate as $field) {
                if ($field['is_required']) {
                    // For form fields, use field ID; for legacy custom fields, use field_name
                    $fieldKey = !empty($formFields) ? 'field_' . $field['id'] : 'custom_' . $field['field_name'];
                    $fieldValue = $_POST[$fieldKey] ?? null;

                    if ($field['field_type'] === 'checkbox') {
                        // Checkbox must have at least one value
                        if (empty($fieldValue) || !is_array($fieldValue)) {
                            $customFieldErrors[] = $field['field_label'] . ' is required';
                        }
                    } else {
                        // Other field types must not be empty
                        if (empty($fieldValue) || trim($fieldValue) === '') {
                            $customFieldErrors[] = $field['field_label'] . ' is required';
                        }
                    }

                    // Type-specific validation
                    if (!empty($fieldValue)) {
                        if ($field['field_type'] === 'number' && !is_numeric($fieldValue)) {
                            $customFieldErrors[] = $field['field_label'] . ' must be a number';
                        }
                        if ($field['field_type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fieldValue)) {
                            $customFieldErrors[] = $field['field_label'] . ' must be a valid date';
                        }
                    }
                }
            }
        }

        // If there are custom field validation errors, return them
        if (!empty($customFieldErrors)) {
            if (isAjaxRequest()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed: ' . implode(', ', $customFieldErrors)
                ]);
                exit;
            }
            redirect('upload.php?error=' . urlencode('Validation failed: ' . implode(', ', $customFieldErrors)));
        }



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
                    // ... existing error handling ...

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)]);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('File type not allowed.'));
                }

                // Check file size
                if ($fileSize > MAX_UPLOAD_SIZE) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: ' . formatFileSize(MAX_UPLOAD_SIZE)]);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('File too large.'));
                }

                // Check for duplicates
                if (checkDuplicateFile($fileName)) {
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'A file with this name already exists.']);
                        exit;
                    }
                    redirect('upload.php?error=' . urlencode('A file with this name already exists.'));
                }

                // Generate organized path based on date and file type
                // Format: uploads/newspapers/YYYY/MM/filetype/filename.ext
                $currentDate = date('Y-m-d');
                list($year, $month) = explode('-', $currentDate);

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

                // Create directory structure: uploads/newspapers/YYYY/MM/filetype/
                $uploadDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$typeFolder}/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                $relativeFilePath = "uploads/newspapers/{$year}/{$month}/{$typeFolder}/" . $newFileName;

                // Move file
                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    // Handle thumbnail upload - organized by year/month
                    $thumbnailPath = null;
                    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                        $thumbFile = $_FILES['thumbnail'];
                        $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

                        if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                            // Create thumbnail directory: uploads/newspapers/YYYY/MM/thumbnails/
                            $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/thumbnails/";
                            if (!is_dir($thumbDir)) {
                                mkdir($thumbDir, 0777, true);
                            }

                            $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                            $thumbPath = $thumbDir . $thumbFileName;

                            if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                                $thumbnailPath = "uploads/newspapers/{$year}/{$month}/thumbnails/" . $thumbFileName;
                            }
                        }
                    }

                    // Insert into database (only core file fields, metadata goes to custom_metadata_values)
                    $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, file_path, file_name, file_type, file_size, thumbnail_path, uploaded_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (
                        $stmt->execute([
                            $title,
                            $relativeFilePath,
                            $fileName,
                            $fileExt,
                            $fileSize,
                            $thumbnailPath,
                            $currentUser['id']
                        ])
                    ) {
                        $newId = $pdo->lastInsertId();

                        // Insert custom metadata values (form templates or legacy custom fields)
                        $fieldsToSave = !empty($formFields) ? $formFields : $customFields;

                        if (!empty($fieldsToSave)) {
                            $metaStmt = $pdo->prepare("
                                INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                                VALUES (?, ?, ?)
                            ");

                            foreach ($fieldsToSave as $field) {
                                // For form fields, use field ID; for legacy custom fields, use field_name
                                $fieldKey = !empty($formFields) ? 'field_' . $field['id'] : 'custom_' . $field['field_name'];
                                $fieldValue = null;

                                if (isset($_POST[$fieldKey])) {
                                    if ($field['field_type'] === 'checkbox') {
                                        // Checkbox values come as array
                                        if (is_array($_POST[$fieldKey])) {
                                            $fieldValue = json_encode($_POST[$fieldKey]);
                                        }
                                    } else {
                                        $fieldValue = sanitize($_POST[$fieldKey]);
                                    }
                                }

                                // Only insert if value is not empty
                                if ($fieldValue !== null && $fieldValue !== '') {
                                    $metaStmt->execute([$newId, $field['id'], $fieldValue]);
                                }
                            }
                        }

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

                        redirect(APP_URL . '/admin_pages/dashboard.php?success=upload');
                    } else {
                        // DB Insert Failed
                        $errorInfo = $stmt->errorInfo();
                        error_log("Database Insert Error: " . print_r($errorInfo, true));

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

            // Get form data — $title already extracted from custom fields above
            // Keep as-is; don't overwrite from $_POST['title'] which doesn't exist

            $fileCount = count($_FILES['files']['name']);

            if ($fileCount > 0) {
                $totalSize = 0;
                $savedPaths = [];
                $thumbnailPath = null;

                // Get current date for folder organization
                $currentDate = date('Y-m-d');
                list($year, $month) = explode('-', $currentDate);

                // Create directory structure: uploads/newspapers/YYYY/MM/images/
                $bulkDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/images";

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
                                $relativePath = "uploads/newspapers/{$year}/{$month}/images/" . $cleanName;
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
                        // Create thumbnail directory: uploads/newspapers/YYYY/MM/thumbnails/
                        $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/thumbnails/";
                        if (!is_dir($thumbDir)) {
                            mkdir($thumbDir, 0777, true);
                        }

                        $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                        $thumbPath = $thumbDir . $thumbFileName;
                        if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                            $thumbnailPath = "uploads/newspapers/{$year}/{$month}/thumbnails/" . $thumbFileName;
                        }
                    }
                } else {
                    $thumbnailPath = $savedPaths[0]; // Fallback to first uploaded image in sequence
                }

                // FIX: Encode image paths as JSON for storage
                $imagePathsJson = json_encode($savedPaths);

                // Insert into Database (only core file fields, metadata goes to custom_metadata_values)
                $stmt = $pdo->prepare("
                        INSERT INTO newspapers (title, file_path, file_name, file_type, file_size, thumbnail_path, uploaded_by, created_at, is_bulk_image, image_paths)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, ?)
                    ");

                // Use the title from form, fallback to auto-generated if empty
                $finalTitle = !empty($title) ? $title : 'Bulk_Image_Gallery_' . time();

                if (
                    $stmt->execute([
                        $finalTitle,
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

                    // Insert custom metadata values
                    if (!empty($customFields)) {
                        $metaStmt = $pdo->prepare("
                            INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                            VALUES (?, ?, ?)
                        ");

                        foreach ($customFields as $field) {
                            // Use field ID for form template fields
                            $fieldKey = 'field_' . $field['id'];
                            $fieldValue = null;

                            if (isset($_POST[$fieldKey])) {
                                if ($field['field_type'] === 'checkbox') {
                                    // Checkbox values come as array
                                    if (is_array($_POST[$fieldKey])) {
                                        $fieldValue = json_encode($_POST[$fieldKey]);
                                    }
                                } else {
                                    $fieldValue = sanitize($_POST[$fieldKey]);
                                }
                            }

                            // Only insert if value is not empty
                            if ($fieldValue !== null && $fieldValue !== '') {
                                $metaStmt->execute([$newId, $field['id'], $fieldValue]);
                            }
                        }
                    }

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
        // $title was already extracted from custom fields (field_17) above
        // Fallback to empty if somehow still not set
        if (empty($title)) {
            $title = '';
        }

        $thumbnailPath = $_POST['existing_thumbnail'] ?? null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));

            if (in_array($thumbExt, ['jpg', 'jpeg', 'png'])) {
                // Get current date for folder organization
                $currentDate = date('Y-m-d');
                list($year, $month) = explode('-', $currentDate);

                // Create thumbnail directory: uploads/newspapers/YYYY/MM/thumbnails/
                $thumbDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/thumbnails/";
                if (!is_dir($thumbDir)) {
                    mkdir($thumbDir, 0777, true);
                }

                $thumbFileName = time() . '_thumb_' . generateRandomString(8) . '.' . $thumbExt;
                $thumbPath = $thumbDir . $thumbFileName;

                if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                    $thumbnailPath = "uploads/newspapers/{$year}/{$month}/thumbnails/" . $thumbFileName;
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
                // Get current date for folder organization
                $currentDate = date('Y-m-d');
                list($year, $month) = explode('-', $currentDate);

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

                // Create directory structure: uploads/newspapers/YYYY/MM/filetype/
                $uploadDir = UPLOAD_PATH . "newspapers/{$year}/{$month}/{$typeFolder}/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newFileName = time() . '_' . generateRandomString(8) . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                $relativeFilePath = "uploads/newspapers/{$year}/{$month}/{$typeFolder}/" . $newFileName;

                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    // Update with new file (only core file fields, metadata goes to custom_metadata_values)
                    $stmt = $pdo->prepare("
                            UPDATE newspapers SET title = ?, file_path = ?, file_name = ?, file_type = ?, file_size = ?, thumbnail_path = ?
                            WHERE id = ?
                        ");
                    $stmt->execute([
                        $title,
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
            // Update without changing file (only core file fields, metadata goes to custom_metadata_values)
            $stmt = $pdo->prepare("
                    UPDATE newspapers SET title = ?, thumbnail_path = ?
                    WHERE id = ?
                ");
            $stmt->execute([
                $title,
                $thumbnailPath,
                $editId
            ]);
        }

        // Update custom metadata values
        $fieldsToSave = !empty($formFields) ? $formFields : $customFields;
        if (!empty($fieldsToSave)) {
            foreach ($fieldsToSave as $field) {
                $fieldKey = !empty($formFields) ? 'field_' . $field['id'] : 'custom_' . $field['field_name'];
                $fieldValue = null;

                if (isset($_POST[$fieldKey])) {
                    if ($field['field_type'] === 'checkbox') {
                        // Checkbox values come as array
                        if (is_array($_POST[$fieldKey])) {
                            $fieldValue = json_encode($_POST[$fieldKey]);
                        }
                    } else {
                        $fieldValue = sanitize($_POST[$fieldKey]);
                    }
                }

                // Check if value already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM custom_metadata_values 
                    WHERE file_id = ? AND field_id = ?
                ");
                $stmt->execute([$editId, $field['id']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update existing value
                    if ($fieldValue !== null && $fieldValue !== '') {
                        $stmt = $pdo->prepare("
                            UPDATE custom_metadata_values 
                            SET field_value = ? 
                            WHERE file_id = ? AND field_id = ?
                        ");
                        $stmt->execute([$fieldValue, $editId, $field['id']]);
                    } else {
                        // Delete if value is empty
                        $stmt = $pdo->prepare("
                            DELETE FROM custom_metadata_values 
                            WHERE file_id = ? AND field_id = ?
                        ");
                        $stmt->execute([$editId, $field['id']]);
                    }
                } else if ($fieldValue !== null && $fieldValue !== '') {
                    // Insert new value
                    $stmt = $pdo->prepare("
                        INSERT INTO custom_metadata_values (file_id, field_id, field_value)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$editId, $field['id'], $fieldValue]);
                }
            }
        }

        logActivity($currentUser['id'], 'edit', $title, $editId);

        if (isAjaxRequest()) {
            echo json_encode(['success' => true, 'message' => 'Document updated successfully']);
            exit;
        }
        redirect('upload.php?success=edit&edit=' . $editId);
    }
}

// Load View
include __DIR__ . '/../views/upload.php';
