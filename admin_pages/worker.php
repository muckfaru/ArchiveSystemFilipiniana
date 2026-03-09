<?php
/**
 * Background Worker for Upload Jobs
 * 
 * Usage: php worker.php <job_id>
 */

// Increase limits for this script
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../backend/core/config.php';
// We need to manually include DB connection if not in config
if (!isset($pdo)) {
    require_once __DIR__ . '/includes/db_connection.php'; // Adjust if needed
    // Or just manually connect if needed, but config usually has it.
    // Let's assume config.php sets up $pdo. 
    // If config.php relies on session, we might need to suppress session start if CLI
    // But config.php usually just defines constants and connects DB.
}

// Check arguments
if ($argc < 2) {
    die("Usage: php worker.php <job_id>\n");
}

$jobId = intval($argv[1]);

// 1. Fetch Job
$stmt = $pdo->prepare("SELECT * FROM upload_jobs WHERE id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job ID $jobId not found.\n");
}

// Update status to processing
updateJobStatus($jobId, 'processing');

try {
    // 2. Fetch Items
    $stmt = $pdo->prepare("SELECT * FROM upload_job_items WHERE job_id = ? ORDER BY id ASC");
    $stmt->execute([$jobId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalItems = count($items);
    updateJobProgress($jobId, 0, $totalItems);

    if ($job['job_type'] === 'bulk_image_cbz') {
        processBulkImageJob($job, $items);
    } elseif ($job['job_type'] === 'bulk_document_import') {
        processBulkDocumentJob($job, $items);
    } else {
        throw new Exception("Unknown job type: " . $job['job_type']);
    }

    // Success
    updateJobStatus($jobId, 'completed', 'Job processed successfully.');

} catch (Exception $e) {
    // Fail
    updateJobStatus($jobId, 'failed', $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

// ================= FUNCTIONS =================

function processBulkImageJob($job, $items)
{
    global $pdo;

    // Use metadata from the job (we might need to store it in job table or first item)
    // Actually, create_job.php should store metadata in a separate table or column.
    // For now, let's assume metadata is passed in the first item's metadata JSON or similar.
    // Or we query the 'newspapers' table entry if we created a placeholder?
    // Let's assume create_job.php created a DRAFT record in 'newspapers' or 
    // we need to create it now.
    // Proposal said: "Move Zip to final storage and Create Newspaper Record..."

    // We need the metadata. Let's assume it's stored in the first item's metadata field for simplicity
    // or passed as a JSON file in the job items?
    // Let's assume the first item contains the metadata in 'metadata' column
    $metaItem = $items[0] ?? null;
    if (!$metaItem || empty($metaItem['metadata'])) {
        throw new Exception("Missing metadata for job.");
    }

    $metadata = json_decode($metaItem['metadata'], true);

    // Create Zip
    $files = [];
    $thumbnailFile = null;

    // Separate thumbnail if marked
    // In our new architecture, we just treat all items as files to be zipped unless flagged.

    // Create unique filename
    $zipFilename = uniqid('archive_') . '.cbz';
    $zipPath = UPLOAD_PATH . 'newspapers/' . $zipFilename;

    // Ensure directory exists
    if (!is_dir(dirname($zipPath))) {
        mkdir(dirname($zipPath), 0777, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Cannot create zip file at $zipPath");
    }

    $processedCount = 0;

    foreach ($items as $item) {
        $tempPath = $item['temp_file_path'];

        // Check if thumbnail
        $itemMeta = json_decode($item['metadata'], true);
        if (isset($itemMeta['is_thumbnail']) && $itemMeta['is_thumbnail']) {
            $thumbnailFile = $tempPath;
            // Also add to zip? Usually yes for archives.
        }

        if (file_exists($tempPath)) {
            $zip->addFile($tempPath, $item['original_name']);
        } else {
            // Log warning?
        }

        $processedCount++;
        if ($processedCount % 5 === 0) {
            updateJobProgress($job['id'], $processedCount, count($items));
        }
    }

    $zip->close();

    // Create Thumbnail
    $thumbPath = '';
    if ($thumbnailFile && file_exists($thumbnailFile)) {
        $thumbFilename = uniqid('thumb_') . '.jpg'; // Assume jpg/png
        $thumbPathReal = UPLOAD_PATH . 'thumbnails/' . $thumbFilename;
        if (!is_dir(dirname($thumbPathReal)))
            mkdir(dirname($thumbPathReal), 0777, true);
        copy($thumbnailFile, $thumbPathReal);
        $thumbPath = 'uploads/thumbnails/' . $thumbFilename;
    }

    // Insert into newspapers
    $stmt = $pdo->prepare("INSERT INTO newspapers (
        title, publication_date, edition, category_id, language_id, 
        page_count, keywords, publisher, volume_issue, description,
        file_path, file_name, file_type, file_size, thumbnail_path, 
        uploaded_by, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    $stmt->execute([
        $metadata['title'],
        $metadata['publication_date'] ?: null,
        $metadata['edition'],
        $metadata['category_id'] ?: null,
        $metadata['language_id'] ?: null,
        $metadata['page_count'] ?: 0,
        $metadata['keywords'],
        $metadata['publisher'],
        $metadata['volume_issue'],
        $metadata['description'],
        'uploads/newspapers/' . $zipFilename,
        $zipFilename,
        'cbz',
        filesize($zipPath),
        $thumbPath,
        $job['user_id']
    ]);

    $newId = $pdo->lastInsertId();

    // Clean up temp files
    foreach ($items as $item) {
        if (file_exists($item['temp_file_path'])) {
            unlink($item['temp_file_path']);
        }
    }
}

function processBulkDocumentJob($job, $items)
{
    global $pdo;

    $processedCount = 0;

    foreach ($items as $item) {
        $meta = json_decode($item['metadata'], true);
        $tempPath = $item['temp_file_path'];

        if (!file_exists($tempPath)) {
            $processedCount++;
            continue;
        }

        try {
            // Move file to final location
            $ext = pathinfo($item['original_name'], PATHINFO_EXTENSION);
            $newFilename = uniqid('doc_') . '.' . $ext;
            $finalPath = UPLOAD_PATH . 'newspapers/' . $newFilename;

            if (!is_dir(dirname($finalPath)))
                mkdir(dirname($finalPath), 0777, true);

            rename($tempPath, $finalPath);

            // Handle thumbnail
            $thumbPath = '';
            // If item has a specific thumbnail attached (might be passed as another item or encoded?)
            // For now assume no thumbnail or standard generation (future)

            $stmt = $pdo->prepare("INSERT INTO newspapers (
                title, publication_date, edition, category_id, language_id, 
                page_count, keywords, publisher, volume_issue, description,
                file_path, file_name, file_type, file_size, thumbnail_path, 
                uploaded_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmt->execute([
                $meta['title'],
                $meta['publication_date'] ?: null,
                $meta['edition'],
                $meta['category_id'] ?: null,
                $meta['language_id'] ?: null,
                $meta['page_count'] ?: 0,
                $meta['keywords'],
                $meta['publisher'],
                $meta['volume_issue'],
                $meta['description'],
                'uploads/newspapers/' . $newFilename,
                $newFilename,
                strtolower($ext),
                filesize($finalPath),
                $thumbPath,
                $job['user_id']
            ]);

            // Log success for item?

        } catch (Exception $e) {
            // Log error for item
            // We could add an error column to upload_job_items
        }

        $processedCount++;
        if ($processedCount % 2 === 0) { // Update frequency
            updateJobProgress($job['id'], $processedCount, count($items));
        }
    }
}

function updateJobStatus($id, $status, $msg = null)
{
    global $pdo;
    $sql = "UPDATE upload_jobs SET status = ?";
    $params = [$status];
    if ($msg) {
        $sql .= ", result_message = ?";
        $params[] = $msg;
    }
    $sql .= " WHERE id = ?";
    $params[] = $id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function updateJobProgress($id, $processed, $total)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE upload_jobs SET processed_files = ?, total_files = ? WHERE id = ?");
    $stmt->execute([$processed, $total, $id]);
}
