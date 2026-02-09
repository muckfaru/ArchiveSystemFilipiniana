<?php
/**
 * Universal Document Reader
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/calibre.php';

// Get file ID
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$fileId) {
    header('Location: dashboard.php');
    exit;
}

// Fetch file data
$stmt = $pdo->prepare("
    SELECT n.*, c.name AS category_name
    FROM newspapers n
    LEFT JOIN categories c ON n.category_id = c.id
    WHERE n.id = ?
      AND n.deleted_at IS NULL
");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: dashboard.php');
    exit;
}

// File info
$fileType = strtolower($file['file_type']);
$fileUrl = APP_URL . '/' . $file['file_path'];
$filePath = __DIR__ . '/../' . $file['file_path'];

// Handle MOBI → EPUB
$epubUrl = null;
$conversionError = null;

if ($fileType === 'mobi') {
    $result = ensureEpubExists($filePath);
    if ($result['success']) {
        $epubUrl = APP_URL . '/uploads/newspapers/' . basename($result['epub_path']);
    } else {
        $conversionError = $result['error'];
    }
}

// Reader type determination
$readerType = 'unknown';
$pdfViewerUrl = '';

if ($fileType === 'pdf') {
    $readerType = 'pdf';
    // Use pdf_viewer.php to serve content content directly (native browser viewer)
    $pdfViewerUrl = 'pdf_viewer.php?file=' . urlencode($file['file_path']);

} elseif ($fileType === 'epub' || ($fileType === 'mobi' && $epubUrl)) {
    $readerType = 'epub';
} elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'tiff', 'tif'])) {
    $readerType = 'image';
}

// Log activity
logActivity($currentUser['id'], 'read', $file['title']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($file['title']) ?> - <?= APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <?php if ($readerType === 'epub'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>
    <?php endif; ?>

    <style>
        body {
            margin: 0;
            background: #1a1a1a;
            color: #fff;
            height: 100vh;
            overflow: hidden;
        }

        .reader-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }

        .reader-content {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
        }

        iframe,
        #epub-viewer {
            width: 100%;
            height: 100%;
            border: none;
            background: #1a1a1a;
        }

        /* EPUB Navigation */
        .epub-navigation {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
            z-index: 100;
        }

        .epub-navigation:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        .epub-navigation.prev {
            left: 0;
            border-radius: 0 8px 8px 0;
        }

        .epub-navigation.next {
            right: 0;
            border-radius: 8px 0 0 8px;
        }

        .image-viewer {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-viewer img {
            max-width: 90%;
            max-height: 100%;
        }
    </style>
</head>

<body>

    <div class="reader-header">
        <a href="dashboard.php" class="text-white text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back
        </a>

        <strong><?= htmlspecialchars($file['title']) ?></strong>

        <div>
            <?php if ($readerType === 'pdf'): ?>
                <a href="<?= $pdfViewerUrl ?>" download class="btn btn-sm btn-outline-light">
                    <i class="bi bi-download"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="reader-content">

        <?php if ($readerType === 'pdf'): ?>

            <!-- Native PDF Viewer (via pdf_viewer.php) -->
            <iframe src="<?= $pdfViewerUrl ?>" class="pdf-viewer"></iframe>

        <?php elseif ($readerType === 'epub'): ?>

            <div id="epub-viewer"></div>
            <div class="epub-navigation prev" onclick="rendition.prev()">
                <i class="bi bi-chevron-left"></i>
            </div>
            <div class="epub-navigation next" onclick="rendition.next()">
                <i class="bi bi-chevron-right"></i>
            </div>

        <?php elseif ($readerType === 'image'): ?>

            <div class="image-viewer">
                <img src="<?= $fileUrl ?>" alt="">
            </div>

        <?php else: ?>

            <div class="d-flex h-100 justify-content-center align-items-center">
                <div class="text-center">
                    <?php if (isset($conversionError) && $conversionError): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Conversion Failed: <?= htmlspecialchars($conversionError) ?>
                        </div>
                    <?php endif; ?>

                    <p class="mb-4">
                        <?php if ($fileType === 'mobi'): ?>
                            Unable to convert MOBI file for web viewing.
                        <?php else: ?>
                            Unsupported file format: <?= strtoupper(htmlspecialchars($fileType)) ?>
                        <?php endif; ?>
                    </p>
                    <a href="<?= $fileUrl ?>" download class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download File
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($readerType === 'epub'): ?>
        <script>
            var book = ePub("<?= $fileType === 'mobi' ? $epubUrl : $fileUrl ?>");
            var rendition = book.renderTo("epub-viewer", {
                width: "100%",
                height: "100%",
                spread: "auto"
            });
            rendition.display();

            // Keyboard navigation
            document.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') {
                    rendition.prev();
                } else if (e.key === 'ArrowRight') {
                    rendition.next();
                }
            });
        </script>
    <?php endif; ?>

</body>

</html>