<?php
/**
 * Helper Functions
 * Archive System - Quezon City Public Library
 */

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 */
function getCurrentUser()
{
    global $pdo;
    if (!isLoggedIn())
        return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Redirect to a URL
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $targetTitle = null, $referenceId = null)
{
    global $pdo;
    // Simply use the raw targetTitle for logging so the ID doesn't pollute the history view.
    // The reference_id column should be added to the DB if the ID is needed programmatically,
    // but the user expressly wants the ID hidden from the front-end string.
    $logTitle = $targetTitle;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, target_title) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $logTitle]);
}

/**
 * Format file size
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d h:i A')
{
    return date($format, strtotime($date));
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random string
 */
function generateRandomString($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get setting value
 */
function getSetting($key, $default = null)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting($key, $value)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
    return $stmt->execute([$value, $key]);
}

/**
 * Get all categories
 */
function getCategories()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get all languages (prioritize English, Filipino, Tagalog first)
 */
function getLanguages()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT * FROM languages 
        ORDER BY 
            CASE 
                WHEN name = 'English' THEN 1
                WHEN name = 'Filipino' THEN 2
                WHEN name = 'Tagalog' THEN 3
                ELSE 4
            END,
            name ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Count total archives (not deleted)
 */
function countArchives()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM newspapers WHERE deleted_at IS NULL");
    return $stmt->fetch()['total'];
}

/**
 * Count total issues (pages) - from custom metadata
 */
function countIssues()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(CAST(cmv.field_value AS UNSIGNED)), 0) as total 
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON cmv.field_id = ff.id
        INNER JOIN newspapers n ON cmv.file_id = n.id
        WHERE LOWER(ff.field_label) IN ('page count', 'pages')
        AND n.deleted_at IS NULL
        AND cmv.field_value REGEXP '^[0-9]+$'
    ");
    return $stmt->fetch()['total'];
}

/**
 * Get years covered - from custom metadata
 */
function getYearsCovered()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT 
            MIN(CAST(LEFT(cmv.field_value, 4) AS UNSIGNED)) as min_year, 
            MAX(CAST(LEFT(cmv.field_value, 4) AS UNSIGNED)) as max_year 
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON cmv.field_id = ff.id
        INNER JOIN newspapers n ON cmv.file_id = n.id
        WHERE LOWER(ff.field_label) IN ('publication date', 'date published', 'date')
        AND n.deleted_at IS NULL 
        AND cmv.field_value REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])(-([0-2][0-9]|3[0-1]))?$'
    ");
    $result = $stmt->fetch();
    if ($result['min_year'] && $result['max_year']) {
        // If min and max year are the same, show only one year
        if ($result['min_year'] === $result['max_year']) {
            return $result['min_year'];
        }
        return $result['min_year'] . '-' . $result['max_year'];
    }
    return 'N/A';
}

function formatPublicationDate($publicationDate, $long = true)
{
    $value = trim((string) ($publicationDate ?? ''));
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $value)) {
        $timestamp = strtotime($value . '-01');
        return $timestamp ? date($long ? 'F Y' : 'M Y', $timestamp) : $value;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
        $timestamp = strtotime($value);
        return $timestamp ? date($long ? 'F j, Y' : 'M Y', $timestamp) : $value;
    }

    return $value;
}

/**
 * Count categories that are actually used by newspapers - now from custom metadata
 */
function countCategories()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT cmv.field_value) as total 
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON cmv.field_id = ff.id
        INNER JOIN newspapers n ON cmv.file_id = n.id
        WHERE LOWER(ff.field_label) IN ('category', 'categories')
        AND n.deleted_at IS NULL
        AND cmv.field_value IS NOT NULL 
        AND cmv.field_value != ''
    ");
    return $stmt->fetch()['total'];
}

/**
 * Count active admins
 */
function countActiveAdmins()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    return $stmt->fetch()['total'];
}

/**
 * Count total admins
 */
function countTotalAdmins()
{
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    return $stmt->fetch()['total'];
}

/**
 * Get recent newspapers - now without hardcoded metadata columns
 */
function getRecentNewspapers($limit = 10)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT n.*
        FROM newspapers n 
        WHERE n.deleted_at IS NULL 
        ORDER BY n.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $newspapers = $stmt->fetchAll();

    // Fetch custom metadata for all newspapers in a single query
    if (!empty($newspapers)) {
        $fileIds = array_column($newspapers, 'id');
        $customMetadata = getCustomMetadataValuesForFiles($fileIds);

        // Attach custom metadata to each newspaper
        foreach ($newspapers as &$newspaper) {
            $newspaper['custom_metadata'] = $customMetadata[$newspaper['id']] ?? [];
        }
    }

    return $newspapers;
}

/**
 * Check if file already exists (duplicate check)
 */
function checkDuplicateFile($fileName)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM newspapers WHERE file_name = ? AND deleted_at IS NULL");
    $stmt->execute([$fileName]);
    return $stmt->fetch() ? true : false;
}

/**
 * Get pagination data
 */
function getPagination($totalItems, $currentPage, $itemsPerPage)
{
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;

    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Display alert message
 */
function showAlert($type, $message)
{
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get organized upload path based on Year/Month/FileType structure
 * Uses publication date for folder organization (falls back to current date)
 * 
 * @param string $fileExt File extension or type (pdf, epub, mobi, thumbnail, etc.)
 * @param string $fileName The filename to use
 * @param string|null $publicationDate Publication date (Y-m-d format) to determine year/month folders
 * @return array ['dir' => full dir path, 'relative_dir' => relative dir, 'full_path' => full file path, 'relative_path' => relative file path]
 */
function getOrganizedUploadPath($fileExt, $fileName, $publicationDate = null)
{
    // Use publication date if provided, otherwise current date
    if ($publicationDate && strtotime($publicationDate)) {
        $timestamp = strtotime($publicationDate);
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
    } else {
        $year = date('Y');
        $month = date('m');
    }

    // Map file extension to subfolder
    $ext = strtolower($fileExt);
    if ($ext === 'pdf') {
        $typeFolder = 'pdf';
    } elseif ($ext === 'epub') {
        $typeFolder = 'epub';
    } elseif ($ext === 'mobi') {
        $typeFolder = 'mobi';
    } elseif ($ext === 'thumbnail') {
        $typeFolder = 'thumbnail';
    } else {
        $typeFolder = 'images'; // jpg, jpeg, png, webp, cbz, gallery, etc.
    }

    $relativeDir = "uploads/newspapers/$year/$month/$typeFolder";
    $fullDir = UPLOAD_PATH . "newspapers/$year/$month/$typeFolder";

    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0777, true);
    }

    return [
        'dir' => $fullDir,
        'relative_dir' => $relativeDir,
        'full_path' => $fullDir . '/' . $fileName,
        'relative_path' => $relativeDir . '/' . $fileName
    ];
}

/**
 * Get and clear alert message
 */
function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// ==================== Custom Metadata Helper Functions ====================

/**
 * Get custom metadata values for a specific file
 * 
 * @param int $fileId The file ID (newspapers.id)
 * @return array Associative array with field_id as key and field_value as value
 */
function getCustomMetadataValues($fileId)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT cmv.field_id, cmv.field_value, 
               ff.field_label as field_label, 
               ff.field_type  as field_type
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON cmv.field_id = ff.id
        WHERE cmv.file_id = ?
    ");
    $stmt->execute([$fileId]);

    $values = [];
    while ($row = $stmt->fetch()) {
        $values[$row['field_id']] = [
            'field_name'  => $row['field_label'],
            'field_label' => $row['field_label'],
            'field_type'  => $row['field_type'],
            'field_value' => $row['field_value']
        ];
    }

    return $values;
}

/**
 * Get custom metadata values for multiple files (optimized for dashboard/browse)
 * 
 * @param array $fileIds Array of file IDs
 * @return array Nested array: [file_id => [field_id => value_data]]
 */
function getCustomMetadataValuesForFiles($fileIds)
{
    global $pdo;

    if (empty($fileIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));

    $stmt = $pdo->prepare("
        SELECT cmv.file_id, cmv.field_id, cmv.field_value, 
               ff.field_label as field_label, 
               ff.field_type  as field_type
        FROM custom_metadata_values cmv
        INNER JOIN form_fields ff ON cmv.field_id = ff.id
        WHERE cmv.file_id IN ($placeholders)
        ORDER BY ff.display_order ASC
    ");
    $stmt->execute($fileIds);

    $values = [];
    while ($row = $stmt->fetch()) {
        if (!isset($values[$row['file_id']])) {
            $values[$row['file_id']] = [];
        }
        $values[$row['file_id']][$row['field_id']] = $row['field_value'];
    }

    return $values;
}

/**
 * Save custom metadata values for a file
 * 
 * @param int $fileId The file ID (newspapers.id)
 * @param array $values Associative array with field_id as key and value as value
 * @param PDO $pdo Optional PDO instance (for transaction support)
 * @return bool True on success, false on failure
 */
function saveCustomMetadataValues($fileId, $values, $pdo = null)
{
    global $pdo;
    $db = $pdo ?? $GLOBALS['pdo'];

    try {
        $stmt = $db->prepare("
            INSERT INTO custom_metadata_values (file_id, field_id, field_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)
        ");

        foreach ($values as $fieldId => $value) {
            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            $stmt->execute([$fileId, $fieldId, $value]);
        }

        return true;
    } catch (Exception $e) {
        error_log("Error saving custom metadata: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate custom field value
 * 
 * @param array $field Field definition from form_fields
 * @param mixed $value The value to validate
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateCustomField($field, $value)
{
    // Check required fields
    if ($field['is_required'] && ($value === null || $value === '')) {
        return [
            'valid' => false,
            'error' => $field['field_label'] . ' is required'
        ];
    }

    // Skip validation if value is empty and field is not required
    if ($value === null || $value === '') {
        return ['valid' => true, 'error' => null];
    }

    // Type-specific validation
    switch ($field['field_type']) {
        case 'number':
            if (!is_numeric($value)) {
                return [
                    'valid' => false,
                    'error' => $field['field_label'] . ' must be a number'
                ];
            }
            break;

        case 'date':
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return [
                    'valid' => false,
                    'error' => $field['field_label'] . ' must be a valid date'
                ];
            }
            break;

        case 'select':
        case 'radio':
            $options = json_decode($field['field_options'], true);
            if (!in_array($value, $options)) {
                return [
                    'valid' => false,
                    'error' => $field['field_label'] . ' contains an invalid option'
                ];
            }
            break;

        case 'checkbox':
            $options = json_decode($field['field_options'], true);
            $selectedValues = is_array($value) ? $value : json_decode($value, true);

            if (!is_array($selectedValues)) {
                return [
                    'valid' => false,
                    'error' => $field['field_label'] . ' must be an array'
                ];
            }

            foreach ($selectedValues as $selected) {
                if (!in_array($selected, $options)) {
                    return [
                        'valid' => false,
                        'error' => $field['field_label'] . ' contains an invalid option'
                    ];
                }
            }
            break;
    }

    // Validation rules (if defined)
    if (!empty($field['validation_rules'])) {
        $rules = json_decode($field['validation_rules'], true);

        // Regex validation
        if (isset($rules['regex']) && !preg_match($rules['regex'], $value)) {
            $errorMsg = $rules['regex_error'] ?? $field['field_label'] . ' format is invalid';
            return ['valid' => false, 'error' => $errorMsg];
        }

        // Min length validation
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            return [
                'valid' => false,
                'error' => $field['field_label'] . ' must be at least ' . $rules['min_length'] . ' characters'
            ];
        }

        // Max length validation
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return [
                'valid' => false,
                'error' => $field['field_label'] . ' must not exceed ' . $rules['max_length'] . ' characters'
            ];
        }

        // Min value validation (for numbers)
        if (isset($rules['min_value']) && is_numeric($value) && $value < $rules['min_value']) {
            return [
                'valid' => false,
                'error' => $field['field_label'] . ' must be at least ' . $rules['min_value']
            ];
        }

        // Max value validation (for numbers)
        if (isset($rules['max_value']) && is_numeric($value) && $value > $rules['max_value']) {
            return [
                'valid' => false,
                'error' => $field['field_label'] . ' must not exceed ' . $rules['max_value']
            ];
        }
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Format custom metadata value for display
 * 
 * @param string $fieldType The field type
 * @param mixed $value The raw value
 * @return string Formatted value for display
 */
function formatCustomMetadataValue($fieldType, $value)
{
    if ($value === null || $value === '') {
        return '';
    }

    switch ($fieldType) {
        case 'date':
            return formatPublicationDate($value, true);

        case 'checkbox':
            $values = is_array($value) ? $value : json_decode($value, true);
            return is_array($values) ? implode(', ', $values) : $value;

        case 'tags':
            // Tags stored as comma-separated string
            $tagArr = array_filter(array_map('trim', explode(',', $value)));
            return implode(', ', $tagArr);

        case 'textarea':
            return nl2br(htmlspecialchars($value));

        default:
            return htmlspecialchars($value);
    }
}

/**
 * Delete custom metadata values for a file
 * 
 * @param int $fileId The file ID
 * @return bool True on success
 */
function deleteCustomMetadataValues($fileId)
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM custom_metadata_values WHERE file_id = ?");
    return $stmt->execute([$fileId]);
}

/**
 * Render custom metadata for display
 * @param array $customMetadata Array of custom metadata
 * @param int $maxDisplay Maximum number of items to display
 * @return string HTML output
 */
function renderCustomMetadata($customMetadata, $maxDisplay = 3)
{
    if (empty($customMetadata)) {
        return '';
    }

    $html = '<div class="dashboard-card-custom-meta">';
    $displayCount = 0;

    foreach ($customMetadata as $meta) {
        if ($displayCount >= $maxDisplay)
            break;
        if (empty($meta['field_value']))
            continue;

        // Format value based on field type
        $displayValue = $meta['field_value'];
        if ($meta['field_type'] === 'checkbox') {
            $values = json_decode($meta['field_value'], true);
            if (is_array($values)) {
                $displayValue = implode(', ', $values);
            }
        } elseif ($meta['field_type'] === 'date') {
            $displayValue = date('M j, Y', strtotime($meta['field_value']));
        }

        $html .= '<div class="custom-meta-item">';
        $html .= '<span class="meta-label">' . htmlspecialchars($meta['field_label']) . ':</span> ';
        $html .= '<span class="meta-value">' . htmlspecialchars($displayValue) . '</span>';
        $html .= '</div>';

        $displayCount++;
    }

    if (count($customMetadata) > $maxDisplay) {
        $html .= '<div class="custom-meta-more">+' . (count($customMetadata) - $maxDisplay) . ' more</div>';
    }

    $html .= '</div>';

    return $html;
}

// ═══════════════════════════════════════════════════════════════════════════
// Metadata Display Configuration Functions
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get display configuration for all custom metadata fields
 * Applies default behavior for fields without explicit configuration
 * 
 * @param PDO $pdo Database connection
 * @param string $context 'card', 'modal', or 'both'
 * @return array Filtered and ordered fields
 * 
 * Requirements: 1.5, 7.1, 7.2, 9.4, 9.5
 */
function getDisplayConfig($pdo, $context = 'both')
{
    static $cache = [];

    // Return cached result if available
    $cacheKey = "display_config_{$context}";
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'metadata_display_config'");
        $tableExists = $tableCheck->rowCount() > 0;
    } catch (Exception $e) {
        $tableExists = false;
    }

    $result = [];

    if ($tableExists) {
        // Query form_fields with metadata_display_config
        $query = "
            SELECT 
                ff.id as field_id,
                ff.field_label as field_name,
                ff.field_label,
                ff.field_type,
                COALESCE(mdc.show_on_card, 1) as show_on_card,
                COALESCE(mdc.show_in_modal, 1) as show_in_modal,
                ff.display_order as card_display_order,
                ff.display_order as modal_display_order
            FROM form_fields ff
            INNER JOIN form_templates ft ON ff.form_id = ft.id AND ft.is_active = 1
            LEFT JOIN metadata_display_config mdc ON ff.id = mdc.form_field_id
        ";

        if ($context === 'card') {
            $query .= " WHERE COALESCE(mdc.show_on_card, 1) = 1 ORDER BY ff.display_order ASC";
        } elseif ($context === 'modal') {
            $query .= " WHERE COALESCE(mdc.show_in_modal, 1) = 1 ORDER BY ff.display_order ASC";
        } else {
            $query .= " ORDER BY ff.display_order ASC";
        }

        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fallback: form_fields without active template constraint
    if (empty($result)) {
        $query = "
            SELECT 
                ff.id as field_id,
                ff.field_label as field_name,
                ff.field_label,
                ff.field_type,
                COALESCE(mdc.show_on_card, 1) as show_on_card,
                COALESCE(mdc.show_in_modal, 1) as show_in_modal,
                ff.display_order as card_display_order,
                ff.display_order as modal_display_order
            FROM form_fields ff
            INNER JOIN form_templates ft ON ff.form_id = ft.id
            LEFT JOIN metadata_display_config mdc ON ff.id = mdc.form_field_id
        ";

        if ($context === 'card') {
            $query .= " WHERE COALESCE(mdc.show_on_card, 1) = 1 ORDER BY ff.display_order ASC";
        } elseif ($context === 'modal') {
            $query .= " WHERE COALESCE(mdc.show_in_modal, 1) = 1 ORDER BY ff.display_order ASC";
        } else {
            $query .= " ORDER BY ff.display_order ASC";
        }

        $stmt = $pdo->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $cache[$cacheKey] = $result;
    return $result;
}

/**
 * Get visible custom metadata fields for a specific context
 */
function getVisibleFields($pdo, $context)
{
    if (!in_array($context, ['card', 'modal'])) {
        throw new InvalidArgumentException("Context must be 'card' or 'modal'");
    }
    return getDisplayConfig($pdo, $context);
}

/**
 * Get custom metadata values for a file with display configuration applied
 */
function getFileMetadataForDisplay($pdo, $fileId, $context)
{
    // Get visible fields for context
    $visibleFields = getVisibleFields($pdo, $context);

    if (empty($visibleFields)) {
        return [];
    }

    // Get field IDs
    $fieldIds = array_column($visibleFields, 'field_id');
    $placeholders = implode(',', array_fill(0, count($fieldIds), '?'));

    // Get values from custom_metadata_values
    $stmt = $pdo->prepare("
        SELECT field_id, field_value
        FROM custom_metadata_values
        WHERE file_id = ? AND field_id IN ($placeholders)
    ");
    // PDO requires array values array_merge is breaking string placeholders 
    $params = [$fileId];
    foreach ($fieldIds as $fid)
        $params[] = $fid;
    $stmt->execute($params);
    $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch core newspaper details for auto-fill (title, file_name)
    $coreStmt = $pdo->prepare("
        SELECT n.title, n.file_name
        FROM newspapers n 
        WHERE n.id = ?
    ");
    $coreStmt->execute([$fileId]);
    $coreData = $coreStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Combine fields with values, maintaining order
    $result = [];
    foreach ($visibleFields as $field) {
        $fieldId = $field['field_id'];
        $label = strtolower(trim($field['field_label']));

        $val = isset($values[$fieldId]) ? $values[$fieldId] : '';

        // Auto-fill title from core newspaper data if custom metadata value is empty
        if (empty($val) && !empty($coreData)) {
            if ($label === 'title') {
                $val = !empty($coreData['title']) ? $coreData['title'] : ($coreData['file_name'] ?? '');
            }
        }

        // Normalize field_name to standard core field names for renderer styling
        $normalizedName = $field['field_label'];
        if ($label === 'publication date' || $label === 'date published') {
            $normalizedName = 'publication_date';
        } elseif ($label === 'publisher') {
            $normalizedName = 'publisher';
        }

        $result[] = [
            'field_id' => $fieldId,
            'field_name' => $normalizedName,
            'field_label' => $field['field_label'],
            'field_type' => $field['field_type'],
            'field_value' => $val
        ];
    }

    return $result;
}

/**
 * Get custom metadata values for multiple files with display configuration applied
 * Optimized for bulk operations like ranking lists or browse grids
 * 
 * @param PDO $pdo Database connection
 * @param array $fileIds Array of file IDs
 * @param string $context 'card' or 'modal'
 * @return array Nested array: [file_id => metadata_array_for_display]
 */
function getFilesMetadataForDisplay($pdo, $fileIds, $context)
{
    if (empty($fileIds)) {
        return [];
    }

    // Get visible fields for context
    $visibleFields = getVisibleFields($pdo, $context);
    if (empty($visibleFields)) {
        return array_fill_keys($fileIds, []);
    }

    // Get field IDs
    $fieldIds = array_column($visibleFields, 'field_id');
    $fieldPlaceholders = implode(',', array_fill(0, count($fieldIds), '?'));
    $filePlaceholders = implode(',', array_fill(0, count($fileIds), '?'));

    // Get values from custom_metadata_values for all files at once
    $stmt = $pdo->prepare("
        SELECT file_id, field_id, field_value
        FROM custom_metadata_values
        WHERE field_id IN ($fieldPlaceholders) AND file_id IN ($filePlaceholders)
    ");
    
    $params = array_merge($fieldIds, $fileIds);
    $stmt->execute($params);
    
    // Group values by file_id [file_id => [field_id => value]]
    $rawValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($rawValues[$row['file_id']])) {
            $rawValues[$row['file_id']] = [];
        }
        $rawValues[$row['file_id']][$row['field_id']] = $row['field_value'];
    }

    // Fetch core newspaper details for auto-fill (title, file_name)
    $stmt = $pdo->prepare("SELECT id, title, file_name FROM newspapers WHERE id IN ($filePlaceholders)");
    $stmt->execute($fileIds);
    $coreDataMap = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // Combine fields with values for each file, maintaining field order
    $result = [];
    foreach ($fileIds as $fileId) {
        $fileResult = [];
        $fileValues = $rawValues[$fileId] ?? [];
        $fileCore = $coreDataMap[$fileId] ?? [];

        foreach ($visibleFields as $field) {
            $fieldId = $field['field_id'];
            $label = strtolower(trim($field['field_label']));
            $val = $fileValues[$fieldId] ?? '';

            // Auto-fill title from core newspaper data if custom metadata value is empty
            if (empty($val) && !empty($fileCore)) {
                if ($label === 'title') {
                    $val = !empty($fileCore['title']) ? $fileCore['title'] : ($fileCore['file_name'] ?? '');
                }
            }

            // Normalize field_name for renderer styling
            $normalizedName = $field['field_label'];
            if ($label === 'publication date' || $label === 'date published') {
                $normalizedName = 'publication_date';
            } elseif ($label === 'publisher') {
                $normalizedName = 'publisher';
            }

            $fileResult[] = [
                'field_id' => $fieldId,
                'field_name' => $normalizedName,
                'field_label' => $field['field_label'],
                'field_type' => $field['field_type'],
                'field_value' => $val
            ];
        }
        $result[$fileId] = $fileResult;
    }

    return $result;
}


/**
 * Validate display configuration data
 * 
 * @param array $config Configuration data
 * @return array Array of validation errors (empty if valid)
 * 
 * Requirements: 3.1, 3.4, 12.1, 12.2, 12.5
 */
function validateDisplayConfig($config)
{
    $errors = [];

    if (!isset($config['field_id']) || !is_numeric($config['field_id'])) {
        $errors['field_id'] = 'Field ID must be a valid number';
    }

    if (isset($config['show_on_card']) && !in_array($config['show_on_card'], [0, 1, '0', '1', true, false], true)) {
        $errors['show_on_card'] = 'Show on card must be 0 or 1';
    }

    if (isset($config['show_in_modal']) && !in_array($config['show_in_modal'], [0, 1, '0', '1', true, false], true)) {
        $errors['show_in_modal'] = 'Show in modal must be 0 or 1';
    }

    if (isset($config['card_display_order'])) {
        if (!is_numeric($config['card_display_order']) || $config['card_display_order'] < 0) {
            $errors['card_display_order'] = 'Card display order must be a non-negative integer';
        }
    }

    if (isset($config['modal_display_order'])) {
        if (!is_numeric($config['modal_display_order']) || $config['modal_display_order'] < 0) {
            $errors['modal_display_order'] = 'Modal display order must be a non-negative integer';
        }
    }

    return $errors;
}

/**
 * Render custom metadata for file cards
 * Handles special formatting for common fields like publication_date, publisher, and tags
 * 
 * @param array $customMetadata Array of metadata from getFileMetadataForDisplay()
 * @return string HTML output
 */
function renderCardMetadata($customMetadata)
{
    if (empty($customMetadata)) {
        return '';
    }

    $html = '';

    foreach ($customMetadata as $meta) {
        $val = $meta['field_value'] ?? '';
        $label = strtolower(trim($meta['field_label'] ?? ''));

        // Skip the Title field — it's already shown as the card's main title
        if ($label === 'title') {
            continue;
        }

        // Format the display value based on field type
        $displayVal = '—'; // default dash for empty

        if (!empty($val)) {
            if ($meta['field_type'] === 'date') {
                $displayVal = htmlspecialchars(formatPublicationDate($val, true));
            } elseif ($meta['field_type'] === 'checkbox') {
                $values = json_decode($val, true);
                if (is_array($values) && !empty($values)) {
                    $displayVal = htmlspecialchars(implode(', ', array_slice($values, 0, 3)));
                    if (count($values) > 3) {
                        $displayVal .= ' +' . (count($values) - 3);
                    }
                }
            } elseif ($meta['field_type'] === 'tags') {
                $tagArr = array_filter(array_map('trim', explode(',', $val)));
                if (!empty($tagArr)) {
                    $displayVal = htmlspecialchars(implode(', ', array_slice($tagArr, 0, 3)));
                    if (count($tagArr) > 3) {
                        $displayVal .= ' +' . (count($tagArr) - 3);
                    }
                }
            } else {
                $displayVal = htmlspecialchars($val);
            }
        }

        $html .= '<div class="dashboard-card-meta-row">';
        $html .= '<span class="dashboard-card-meta-label">' . htmlspecialchars($meta['field_label']) . '</span>';
        $html .= '<span class="dashboard-card-meta-value">' . $displayVal . '</span>';
        $html .= '</div>';
    }

    return $html;
}

/**
 * Extract category value from custom metadata
 * 
 * @param array $customMetadata Array of custom metadata
 * @return string Category value or 'Uncategorized'
 */
function getCategoryFromMetadata($customMetadata)
{
    if (empty($customMetadata)) {
        return 'Uncategorized';
    }

    foreach ($customMetadata as $meta) {
        $label = strtolower(trim($meta['field_label'] ?? ''));
        if ($label === 'category') {
            return $meta['field_value'] ?? 'Uncategorized';
        }
    }

    return 'Uncategorized';
}

/**
 * Extract a metadata value by label from custom metadata array
 * 
 * @param array $customMetadata Array of metadata from getFileMetadataForDisplay
 * @param string|array $labels Label(s) to search for (case-insensitive)
 * @param string $default Default value if not found
 * @return string The metadata value or default
 */
function getMetadataValueByLabel($customMetadata, $labels, $default = '')
{
    if (empty($customMetadata)) {
        return $default;
    }

    if (!is_array($labels)) {
        $labels = [$labels];
    }

    $labels = array_map(function ($l) {
        return strtolower(trim($l));
    }, $labels);

    foreach ($customMetadata as $meta) {
        $fieldLabel = strtolower(trim($meta['field_label'] ?? ''));
        if (in_array($fieldLabel, $labels) && !empty($meta['field_value'])) {
            return $meta['field_value'];
        }
    }

    return $default;
}

/**
 * Override newspapers.title with the custom metadata "Title" field value.
 * 
 * The newspapers.title column may contain the original filename or an
 * auto-extracted title from file metadata. The user-entered custom
 * metadata "Title" field should take priority when it has a value.
 *
 * @param array &$documents Array of document rows (by reference)
 * @param PDO $pdo Database connection
 */
function applyTitleOverrides(&$documents, $pdo)
{
    if (empty($documents)) return;

    $fileIds = array_column($documents, 'id');

    // Find the "Title" field ID from form_fields
    $titleFieldId = null;
    try {
        $stmt = $pdo->query("
            SELECT ff.id FROM form_fields ff
            JOIN form_templates ft ON ff.form_id = ft.id AND ft.is_active = 1
            WHERE LOWER(TRIM(ff.field_label)) = 'title'
            LIMIT 1
        ");
        $row = $stmt->fetch();
        if ($row) {
            $titleFieldId = $row['id'];
        }
    } catch (PDOException $e) {
        // Silently ignore if tables don't exist
    }

    if (!$titleFieldId) return;

    // Fetch title values for all documents in one query
    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
    $stmt = $pdo->prepare("
        SELECT file_id, field_value
        FROM custom_metadata_values
        WHERE field_id = ? AND file_id IN ($placeholders)
        AND field_value IS NOT NULL AND field_value != ''
    ");
    $params = array_merge([$titleFieldId], $fileIds);
    $stmt->execute($params);
    $titleValues = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // file_id => title

    // Override
    foreach ($documents as &$doc) {
        if (isset($titleValues[$doc['id']]) && !empty($titleValues[$doc['id']])) {
            $doc['title'] = $titleValues[$doc['id']];
        }
    }
    unset($doc);
}
