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
$fileUrl = 'serve_file.php?file=' . urlencode($file['file_path']);
$filePath = __DIR__ . '/../' . $file['file_path'];

// Handle MOBI → EPUB
$epubUrl = null;
$conversionError = null;

if ($fileType === 'mobi') {
    $result = ensureEpubExists($filePath);
    if ($result['success']) {
        $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
        $epubUrl = 'serve_file.php?file=' . urlencode($epubRelativePath);
    } else {
        $conversionError = $result['error'];
    }
}

// Handle CBZ - Get list of images
$cbzImages = [];
if ($fileType === 'cbz') {
    $zip = new ZipArchive;
    if ($zip->open($filePath) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $cbzImages[] = $name;
            }
        }
        $zip->close();
        natsort($cbzImages); // Natural sort for 1.jpg, 2.jpg... 10.jpg
        $cbzImages = array_values($cbzImages); // Re-index
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
} elseif ($fileType === 'cbz') {
    $readerType = 'cbz';
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
        <script src="../assets/js/jszip.min.js"></script>
        <script src="../assets/js/epub.min.js"></script>
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

        <?php elseif ($readerType === 'cbz'): ?>

            <div id="cbz-viewer" class="h-100 position-relative" style="background: #1a1a1a;">
                <div class="d-flex h-100 justify-content-center align-items-center">
                    <img id="cbz-image" src="" style="max-height: 100vh; max-width: 100%; object-fit: contain;">
                </div>

                <!-- Navigation Zones -->
                <div class="position-absolute top-0 start-0 h-100 w-25" style="cursor: pointer; z-index: 10;"
                    onclick="prevPage()"></div>
                <div class="position-absolute top-0 end-0 h-100 w-25" style="cursor: pointer; z-index: 10;"
                    onclick="nextPage()"></div>

                <!-- Navigation Buttons (Visual) -->
                <button class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-3 rounded-circle p-3"
                    onclick="prevPage()" style="opacity: 0.7; z-index: 20;">
                    <i class="bi bi-chevron-left fs-4"></i>
                </button>
                <button class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-3 rounded-circle p-3"
                    onclick="nextPage()" style="opacity: 0.7; z-index: 20;">
                    <i class="bi bi-chevron-right fs-4"></i>
                </button>

                <!-- Page Counter -->
                <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3 badge bg-dark fs-6 px-3 py-2"
                    style="opacity: 0.8; z-index: 20;">
                    <span id="page-counter">1 / <?= count($cbzImages) ?></span>
                </div>
            </div>

        <?php else: ?>

            <div class="d-flex h-100 justify-content-center align-items-center">
                <div class="text-center">
                    <?php if (isset($conversionError) && $conversionError): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                showReaderError("Conversion Failed: <?= addslashes($conversionError) ?>");
                            });
                        </script>
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
                spread: "none"
            });

            // Register and select dark theme
            rendition.themes.register("dark", {
                "body": { "color": "#cfcfcf", "background": "#1a1a1a" },
                "p": { "color": "#cfcfcf" },
                "span": { "color": "#cfcfcf" },
                "div": { "color": "#cfcfcf" },
                "h1": { "color": "#ffffff" },
                "h2": { "color": "#ffffff" },
                "h3": { "color": "#ffffff" },
                "a": { "color": "#3498db" }
            });
            rendition.themes.select("dark");

            book.ready.then(function () {
                console.log("Book loaded successfully");
                return rendition.display();
            }).then(function () {
                console.log("Rendition displayed");
            }).catch(function (err) {
                console.error("Error loading book:", err);
                showReaderError("Error loading eBook. Check console for details.");
            });

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

    <?php if ($readerType === 'cbz'): ?>
        <script>
            const images = <?= json_encode($cbzImages) ?>;
            const fileId = <?= $fileId ?>;
            let currentIndex = 0;

            const imgElement = document.getElementById('cbz-image');
            const counterElement = document.getElementById('page-counter');

            function updateImage() {
                if (!images || images.length === 0) return;

                const imagePath = images[currentIndex];
                const src = `serve_cbz_image.php?file_id=${fileId}&image_path=${encodeURIComponent(imagePath)}`;

                // Preload next image
                if (currentIndex < images.length - 1) {
                    const nextPath = images[currentIndex + 1];
                    const preload = new Image();
                    preload.src = `serve_cbz_image.php?file_id=${fileId}&image_path=${encodeURIComponent(nextPath)}`;
                }

                imgElement.src = src;
                counterElement.textContent = `${currentIndex + 1} / ${images.length}`;
            }

            function nextPage() {
                if (currentIndex < images.length - 1) {
                    currentIndex++;
                    updateImage();
                }
            }

            function prevPage() {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateImage();
                }
            }

            // Keyboard navigation
            document.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === ' ') {
                    nextPage();
                } else if (e.key === 'ArrowLeft') {
                    prevPage();
                }
            });

            // Initial load
            document.addEventListener('DOMContentLoaded', function () {
                updateImage();
            });
        </script>
    <?php endif; ?>

    <!-- Error Modal -->
    <div class="modal fade" id="readerErrorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center mx-auto"
                            style="width: 64px; height: 64px;">
                            <i class="bi bi-exclamation-triangle-fill text-danger text-danger"
                                style="font-size: 32px;"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2">Reader Error</h5>
                    <p class="text-muted small mb-4" id="readerErrorMessage">An error occurred.</p>
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showReaderError(msg) {
            document.getElementById('readerErrorMessage').textContent = msg;
            new bootstrap.Modal(document.getElementById('readerErrorModal')).show();
        }
    </script>

</body>

</html>