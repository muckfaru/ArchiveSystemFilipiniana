<?php
/**
 * Public Document Reader
 * Archive System - Quezon City Public Library
 * No authentication required - public access.
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/functions.php';
require_once __DIR__ . '/../backend/core/analytics.php';

$rawId = isset($_GET['id']) ? $_GET['id'] : '';

// Attempt to decrypt if it looks like an encrypted string (not purely numeric)
if (!is_numeric($rawId) && !empty($rawId)) {
    $decryptedId = url_decrypt($rawId);
    $fileId = $decryptedId !== null ? intval($decryptedId) : 0;
} else {
    // Fallback for already numeric IDs (e.g. older links)
    $fileId = intval($rawId);
}

if (!$fileId) {
    header('Location: ' . route_url('home'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT n.*
    FROM newspapers n
    WHERE n.id = ? AND n.deleted_at IS NULL
");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: ' . route_url('home'));
    exit;
}

// Record view for analytics after the response is sent so public reading opens faster.
recordNewspaperViewDeferred($pdo, $fileId);

// Fetch custom metadata for this file
$customMetadata = getCustomMetadataValues($fileId);

// Log activity (only if user is authenticated)
if (isset($currentUser) && $currentUser) {
    logActivity($currentUser['id'], 'read', $file['title']);
}

if (session_status() === PHP_SESSION_ACTIVE && function_exists('session_write_close')) {
    session_write_close();
}

$fileType = strtolower($file['file_type']);
$fileUrl = route_url('serve-file', ['file' => $file['file_path']]);
$filePath = __DIR__ . '/../' . $file['file_path'];

// Handle MOBI → EPUB conversion (admin only)
$epubUrl = null;
$conversionError = null;
$needsConversion = false;

if ($fileType === 'mobi') {
    require_once __DIR__ . '/../backend/core/calibre.php';
    
    // Check if EPUB already exists (fast check, <1 second)
    $existingEpub = getConvertedEpubPath($filePath);
    
    if ($existingEpub) {
        // EPUB already exists, use it immediately (instant load)
        $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
        $epubUrl = route_url('serve-file', ['file' => $epubRelativePath]);
    } elseif (isCalibreAvailable()) {
        // Calibre is installed, mark for conversion
        $needsConversion = true;
    } else {
        // No EPUB and no Calibre — will show download fallback
        $conversionError = 'Calibre ebook-convert is not installed. MOBI files cannot be converted to EPUB for web reading. You can download the file instead.';
    }
}

// Determine reader type
$readerType = 'unknown';
$pdfViewerUrl = '';

if ($fileType === 'pdf') {
    $readerType = 'pdf';
    $pdfViewerUrl = route_url('serve-file', ['file' => $file['file_path']]);
} elseif ($fileType === 'epub' || ($fileType === 'mobi' && $epubUrl)) {
    $readerType = 'epub';
} elseif ($fileType === 'mobi' && $needsConversion) {
    $readerType = 'mobi-converting';
} elseif ($fileType === 'gallery') {
    $readerType = 'gallery';
    $galleryImages = json_decode($file['image_paths'] ?? '[]', true) ?: [];
} elseif ($fileType === 'cbz') {
    // CBZ = Comic Book Zip — extract image list from the archive
    $readerType = 'gallery';
    $galleryImages = [];
    $cbzPath = __DIR__ . '/../' . $file['file_path'];
    if (file_exists($cbzPath)) {
        $zip = new ZipArchive();
        if ($zip->open($cbzPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $galleryImages[] = $name;
                }
            }
            $zip->close();
            sort($galleryImages);
        }
    }
} elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'tiff', 'tif', 'webp'])) {
    $readerType = 'image';
}

// Human-readable format label
$formatLabel = match (true) {
    $fileType === 'gallery' || $fileType === 'cbz' => 'Images',
    $fileType === 'pdf' => 'PDF',
    $fileType === 'epub' => 'EPUB',
    $fileType === 'mobi' => 'MOBI',
    default => strtoupper($fileType),
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($file['title']) ?> — <?= APP_NAME ?>
    </title>

    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@300;400;700&family=Lora:wght@400;500;600&family=Open+Sans:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <?php if ($readerType === 'epub'): ?>
        <script src="<?= APP_URL ?>/assets/js/jszip.min.js"></script>
        <script src="<?= APP_URL ?>/assets/js/epub.min.js"></script>
    <?php endif; ?>

    <style>
        /* ======================================================
           ADMIN READER  – Full Featured (Based on Public Reader Design)
        ====================================================== */
        :root {
            --bg: #1a1a1a;
            --surface: #2a2a2a;
            --text: #e0e0e0;
            --muted: #888;
            --accent: #3A9AFF;
            --chrome-h: 56px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        /* ── Themes ── */
        body.theme-light {
            --bg: #fafafa;
            --surface: #fff;
            --text: #1a1a1a;
            --muted: #666;
        }

        body.theme-sepia {
            --bg: #f4ead2;
            --surface: #ece1c4;
            --text: #4a3728;
            --muted: #8a7060;
        }

        body.theme-dark {
            --bg: #1a1a1a;
            --surface: #2a2a2a;
            --text: #e0e0e0;
            --muted: #888;
        }

        body.theme-contrast {
            --bg: #000;
            --surface: #111;
            --text: #fff;
            --muted: #aaa;
        }

        /* ── Top Chrome ── */
        .reader-top {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--chrome-h);
            background: var(--surface);
            border-bottom: 1px solid rgba(128, 128, 128, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1000;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .reader-top.hidden {
            transform: translateY(-100%);
            opacity: 0;
            pointer-events: none;
        }

        .chrome-left,
        .chrome-right {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .chrome-center {
            flex: 1;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 12px;
        }

        .chrome-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            transition: background 0.15s;
        }

        .chrome-btn:hover {
            background: rgba(128, 128, 128, 0.2);
        }

        .chrome-btn.active {
            background: var(--accent);
            color: #fff;
        }

        /* ── Reading Area ── */
        .reader-area {
            position: fixed;
            top: var(--chrome-h);
            bottom: 48px;
            left: 0;
            right: 0;
            background: var(--bg);
            overflow: hidden;
        }

        .reader-area.no-bottom {
            bottom: 0;
        }

        /* PDF iframe */
        .pdf-frame {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* EPUB viewer */
        #epub-viewer {
            width: 100%;
            height: 100%;
        }

        /* Gallery / Image viewer */
        .gallery-viewer {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: var(--bg);
        }

        .gallery-viewer img {
            max-width: 92%;
            max-height: 92%;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            transform-origin: center center;
            transition: transform 0.15s ease;
            cursor: zoom-in;
            user-select: none;
            -webkit-user-drag: none;
        }

        .gal-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 10;
            transition: background 0.2s;
        }

        .gal-arrow:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .gal-arrow.left {
            left: 16px;
        }

        .gal-arrow.right {
            right: 16px;
        }

        /* EPUB click zones */
        .zone {
            position: absolute;
            top: 0;
            bottom: 0;
            z-index: 5;
            cursor: pointer;
        }

        .zone-prev {
            left: 0;
            width: 20%;
        }

        .zone-next {
            right: 0;
            width: 20%;
        }

        /* Loading */
        .epub-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            gap: 16px;
            z-index: 20;
        }

        .epub-loading.gone {
            display: none;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(58, 154, 255, 0.3);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Bottom Bar ── */
        .reader-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 48px;
            background: var(--surface);
            border-top: 1px solid rgba(128, 128, 128, 0.15);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .reader-bottom.hidden {
            transform: translateY(100%);
        }

        .progress-bar-outer {
            flex: 1;
            height: 4px;
            background: rgba(128, 128, 128, 0.25);
            border-radius: 2px;
            overflow: hidden;
            cursor: pointer;
        }

        .progress-bar-inner {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s;
        }

        .progress-label {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            min-width: 80px;
            text-align: right;
        }

        /* ── Panels (Settings / Info) ── */
        .panel {
            position: fixed;
            right: 0;
            top: var(--chrome-h);
            bottom: 48px;
            width: 300px;
            background: var(--surface);
            border-left: 1px solid rgba(128, 128, 128, 0.15);
            z-index: 900;
            transform: translateX(100%);
            transition: transform 0.25s ease;
            overflow-y: auto;
            padding: 20px;
        }

        .panel.open {
            transform: translateX(0);
        }

        .panel h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        /* Panel close button */
        .panel-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(128, 128, 128, 0.1);
            border: none;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .panel-close-btn:hover {
            background: rgba(128, 128, 128, 0.2);
            transform: scale(1.05);
        }

        .panel-close-btn i {
            font-size: 14px;
        }

        .setting-row {
            margin-bottom: 20px;
        }

        .setting-row label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Theme buttons */
        .theme-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .theme-btn {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            border: 2.5px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.15s;
        }

        .theme-btn.active {
            border-color: var(--accent);
        }

        .theme-btn.t-light {
            background: #fafafa;
            color: #1a1a1a;
        }

        .theme-btn.t-sepia {
            background: #f4ead2;
            color: #4a3728;
        }

        .theme-btn.t-dark {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        .theme-btn.t-contrast {
            background: #000;
            color: #fff;
        }

        /* Font + size selects */
        .panel select,
        .panel input[type="range"] {
            width: 100%;
        }

        .panel select {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid rgba(128, 128, 128, 0.3);
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
        }

        .size-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .size-row span {
            font-size: 12px;
            color: var(--muted);
            min-width: 28px;
        }

        .size-row input {
            flex: 1;
            accent-color: var(--accent);
        }

        /* Info panel */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(128, 128, 128, 0.12);
            font-size: 13px;
            gap: 8px;
            align-items: flex-start;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--muted);
            flex-shrink: 0;
        }

        .info-val {
            font-weight: 600;
            color: var(--text);
            text-align: right;
            max-width: 60%;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background 0.15s;
        }

        .back-link:hover {
            background: rgba(128, 128, 128, 0.2);
        }

        /* Fullscreen */
        body.fullscreen .reader-top {
            display: none;
        }

        body.fullscreen .reader-area {
            top: 0;
            bottom: 0;
        }

        body.fullscreen .reader-bottom {
            display: none;
        }

        body.fullscreen .panel {
            top: 0;
            bottom: 0;
        }
        
        /* Ensure PDF iframe fills completely in fullscreen */
        body.fullscreen .pdf-frame {
            height: 100vh;
        }

        /* Page nav for PDFs */
        .page-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text);
        }

        .page-nav button {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid rgba(128, 128, 128, 0.3);
            background: transparent;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-nav button:hover {
            background: rgba(128, 128, 128, 0.2);
        }

        .page-nav input[type="number"] {
            width: 52px;
            text-align: center;
            background: var(--bg);
            color: var(--text);
            border: 1px solid rgba(128, 128, 128, 0.3);
            border-radius: 6px;
            padding: 4px 6px;
            font-size: 13px;
        }

        /* Fallback */
        .fallback {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 16px;
            color: var(--muted);
        }

        .fallback i {
            font-size: 48px;
        }

        .fallback h3 {
            font-size: 18px;
            color: var(--text);
        }

        /* Conversion Screen */
        .conversion-screen {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: var(--bg);
        }

        .conversion-content {
            text-align: center;
            max-width: 400px;
            padding: 40px;
        }

        .conversion-icon {
            font-size: 64px;
            color: var(--accent);
            margin-bottom: 20px;
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .conversion-content h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }

        .conversion-content p {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .conversion-progress {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 20px;
            background: var(--panel-bg);
            border-radius: 8px;
        }

        #conversionStatus {
            font-size: 13px;
            color: var(--muted);
        }

        .btn-dl {
            padding: 10px 24px;
            background: var(--accent);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .panel {
                width: 100%;
            }

            .chrome-center {
                font-size: 12px;
            }
        }
    </style>
</head>

<body class="theme-dark">

    <!-- ======= TOP CHROME ======= -->
    <div class="reader-top" id="readerTop">
        <div class="chrome-left">
                    <a href="<?= route_url('home') ?>" class="back-link" title="Back">
                <i class="bi bi-arrow-left"></i>
                <span class="d-none d-sm-inline">Back</span>
            </a>
        </div>
        <div class="chrome-center">
            <?= htmlspecialchars($file['title']) ?>
        </div>
        <div class="chrome-right">
            <button class="chrome-btn" id="btnInfo" title="File Info">
                <i class="bi bi-info-circle"></i>
            </button>
            <?php if ($readerType !== 'pdf'): ?>
                <button class="chrome-btn" id="btnTheme" title="Color Theme">
                    <i class="bi bi-palette"></i>
                </button>
                <button class="chrome-btn" id="btnText" title="Reading Settings">
                    <i class="bi bi-type"></i>
                </button>
            <?php endif; ?>
            <button class="chrome-btn" id="btnFullscreen" title="Fullscreen">
                <i class="bi bi-fullscreen"></i>
            </button>
        </div>
    </div>

    <!-- ======= READING AREA ======= -->
    <div class="reader-area" id="readerArea">

        <?php if ($readerType === 'pdf'): ?>
            <iframe id="pdfFrame" class="pdf-frame"
                        src="<?= htmlspecialchars($pdfViewerUrl) ?>"></iframe>

        <?php elseif ($readerType === 'mobi-converting'): ?>
            <div class="conversion-screen" id="conversionScreen">
                <div class="conversion-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>

        <?php elseif ($readerType === 'epub'): ?>
            <div id="epub-viewer"></div>
            <div class="zone zone-prev" id="zonePrev"></div>
            <div class="zone zone-next" id="zoneNext"></div>
            <div class="epub-loading" id="epubLoading">
                <div class="spinner"></div>
                <span style="font-size:13px;color:var(--muted);">Opening book…</span>
            </div>

        <?php elseif ($readerType === 'gallery'): ?>
            <div class="gallery-viewer" id="galleryViewer">
                <img id="galleryImg" src="" alt="">
                <button class="gal-arrow left" id="galPrev"><i class="bi bi-chevron-left"></i></button>
                <button class="gal-arrow right" id="galNext"><i class="bi bi-chevron-right"></i></button>
            </div>

        <?php elseif ($readerType === 'image'): ?>
            <div class="gallery-viewer">
                <img src="<?= $fileUrl ?>" alt="<?= htmlspecialchars($file['title']) ?>">
            </div>

        <?php else: ?>
            <div class="fallback">
                <?php if ($fileType === 'mobi' && $conversionError): ?>
                    <i class="bi bi-book" style="font-size:48px;color:#64748B;margin-bottom:16px;"></i>
                    <h3>MOBI Web Preview Unavailable</h3>
                    <p style="font-size:14px;color:#94a3b8;margin:12px 0;max-width:420px;line-height:1.6;">
                        This MOBI file needs Calibre to convert it to EPUB for web reading. 
                        Calibre is not currently installed on the server. You can download the file and read it with a local e-book reader instead.
                    </p>
                <?php else: ?>
                    <i class="bi bi-file-earmark-break"></i>
                    <h3>Cannot Preview This File</h3>
                <?php endif; ?>
                <p style="font-size:14px;">Format:
                    <?= strtoupper(htmlspecialchars($fileType)) ?>
                </p>
                <a href="<?= $fileUrl ?>" download class="btn-dl">
                    <i class="bi bi-download me-2"></i>Download File
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- ======= BOTTOM BAR ======= -->
    <div class="reader-bottom" id="readerBottom">
        <?php if ($readerType === 'pdf'): ?>
            <div class="page-nav">
                <button onclick="pdfPage(-1)"><i class="bi bi-chevron-left"></i></button>
                <span>Page</span>
                <input type="number" id="pdfPageInput" value="1" min="1">
                <span id="pdfPageTotal">/ —</span>
                <button onclick="pdfPage(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        <?php endif; ?>
        <div class="progress-bar-outer" id="progressOuter">
            <div class="progress-bar-inner" id="progressBar"></div>
        </div>
        <div class="progress-label" id="progressLabel">—</div>
    </div>

    <!-- ======= PANEL: COLOR THEME ======= -->
    <?php if ($readerType !== 'pdf'): ?>
        <div class="panel" id="panelTheme">
            <button class="panel-close-btn" onclick="closeAllPanels()">
                <i class="bi bi-x-lg"></i>
            </button>
            <h4>Color Theme</h4>
            <div class="theme-btns">
                <button class="theme-btn t-light" data-theme="theme-light">Aa</button>
                <button class="theme-btn t-sepia" data-theme="theme-sepia">Aa</button>
                <button class="theme-btn t-dark  active" data-theme="theme-dark">Aa</button>
                <button class="theme-btn t-contrast" data-theme="theme-contrast">Aa</button>
            </div>
        </div>
    <?php else: ?>
        <div class="panel" id="panelTheme" style="display:none"></div>
    <?php endif; ?>

    <!-- ======= PANEL: TYPOGRAPHY ======= -->
    <?php if ($readerType !== 'pdf'): ?>
        <div class="panel" id="panelText">
            <button class="panel-close-btn" onclick="closeAllPanels()">
                <i class="bi bi-x-lg"></i>
            </button>
            <h4>Reading Settings</h4>
            <div class="setting-row">
                <label>Font</label>
                <select id="fontSelect">
                    <option value="'Merriweather', serif">Merriweather</option>
                    <option value="'Open Sans', sans-serif">Open Sans</option>
                </select>
            </div>
            <div class="setting-row">
                <label>Font Size <span id="fontSizeVal" style="float:right;color:var(--accent)">18px</span></label>
                <div class="size-row">
                    <span>A</span>
                    <input type="range" id="fontSizeRange" min="13" max="32" value="18">
                    <span style="font-size:20px">A</span>
                </div>
            </div>
            <div class="setting-row">
                <label>Line Spacing</label>
                <div class="size-row">
                    <span><i class="bi bi-text-paragraph" style="font-size:11px"></i></span>
                    <input type="range" id="lineHeightRange" min="1.2" max="2.6" step="0.1" value="1.7">
                    <span><i class="bi bi-text-paragraph" style="font-size:17px"></i></span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="panel" id="panelText" style="display:none"></div>
    <?php endif; ?>

    <!-- ======= PANEL: FILE INFO ======= -->
    <div class="panel" id="panelInfo">
        <button class="panel-close-btn" onclick="closeAllPanels()">
            <i class="bi bi-x-lg"></i>
        </button>
        <h4>File Details</h4>
        <?php
        // Build metadata rows from custom metadata
        $metaIconMap = [
            'category' => 'bi-bookmark',
            'publication date' => 'bi-calendar3', 'date published' => 'bi-calendar3', 'date issued' => 'bi-calendar3', 'published' => 'bi-calendar3',
            'publisher' => 'bi-building',
            'language' => 'bi-translate',
            'pages' => 'bi-book', 'page count' => 'bi-book',
            'volume' => 'bi-layers', 'issue' => 'bi-layers', 'volume/issue' => 'bi-layers',
            'edition' => 'bi-sun',
            'keywords' => 'bi-tag', 'tags' => 'bi-tag',
            'description' => 'bi-text-paragraph',
        ];

        // Always show Format
        $metaRows = [['bi-file-earmark', 'Format', $formatLabel]];

        if (!empty($customMetadata)):
            foreach ($customMetadata as $meta):
                if (empty($meta['field_value'])) continue;
                $labelLower = strtolower(trim($meta['field_label']));
                $icon = $metaIconMap[$labelLower] ?? 'bi-info-circle';
                $val = $meta['field_value'];
                // Format date fields
                if ($meta['field_type'] === 'date' && !empty($val)) {
                    $ts = strtotime($val);
                    $val = $ts ? date('F j, Y', $ts) : $val;
                }
                // Format checkbox fields (JSON arrays)
                if ($meta['field_type'] === 'checkbox') {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) $val = implode(', ', $decoded);
                }
                // Format tags fields
                if ($meta['field_type'] === 'tags') {
                    $val = implode(', ', array_filter(array_map('trim', explode(',', $val))));
                }
                $metaRows[] = [$icon, $meta['field_label'], $val];
            endforeach;
        endif;

        foreach ($metaRows as [$icon, $label, $val]):
            if (!$val) continue; ?>
            <div class="info-row">
                <span class="info-label"><i class="bi <?= $icon ?> me-1"></i>
                    <?= htmlspecialchars($label) ?>
                </span>
                <span class="info-val">
                    <?= htmlspecialchars($val) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ======= SCRIPTS ======= -->
    <script>
        const READER_TYPE = '<?= $readerType ?>';
        const FILE_URL = '<?= addslashes($fileUrl) ?>';
        const APP_URL = '<?= APP_URL ?>';
        const STORAGE_KEY = 'pub_reader_v1';
        const FILE_ID = <?= $file['id'] ?>;
        <?php if ($readerType === 'gallery'): ?>
            const GAL_IMAGES = <?= json_encode($galleryImages ?? []) ?>;
        <?php endif; ?>
        <?php if ($readerType === 'epub'): ?>
            const EPUB_URL = '<?= $fileType === 'mobi' && $epubUrl ? addslashes($epubUrl) : addslashes($fileUrl) ?>';
        <?php endif; ?>

        // ── MOBI Conversion Handler ──────────────────────────────────────────────────
        <?php if ($readerType === 'mobi-converting'): ?>
        async function convertMobiFile() {
            try {
                    const response = await fetch(APP_URL + `/convert-mobi?file_id=${FILE_ID}`);
                const data = await response.json();
                
                if (data.success) {
                    // Reload the page to show the EPUB reader
                    window.location.reload();
                } else {
                    // On error, show alert and go back
                    alert('Conversion failed: ' + data.error);
                            window.location.href = '<?= route_url('home') ?>';
                }
            } catch (error) {
                // On error, show alert and go back
                alert('Error: ' + error.message);
                        window.location.href = '<?= route_url('home') ?>';
            }
        }
        
        // Start conversion when page loads
        convertMobiFile();
        <?php endif; ?>

        // ── Load persisted settings ──────────────────────────────────────────────────
        let settings = {
            theme: 'theme-dark',
            font: "'Merriweather', serif",
            fontSize: 18,
            lineHeight: 1.7
        };
        try {
            const s = localStorage.getItem(STORAGE_KEY);
            if (s) settings = { ...settings, ...JSON.parse(s) };
        } catch (e) { }

        function saveSettings() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
        }

        // ── Apply theme to body ──────────────────────────────────────────────────────
        function applyTheme(t) {
            document.body.classList.remove('theme-light', 'theme-sepia', 'theme-dark', 'theme-contrast');
            document.body.classList.add(t);
            document.querySelectorAll('.theme-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.theme === t);
            });
        }
        applyTheme(settings.theme);

        // ── DOM refs ─────────────────────────────────────────────────────────────────
        const $ = id => document.getElementById(id);
        const readerTop = $('readerTop');
        const readerArea = $('readerArea');
        const readerBottom = $('readerBottom');
        const progressBar = $('progressBar');
        const progressLabel = $('progressLabel');

        // ── Panels ───────────────────────────────────────────────────────────────────
        const panels = { theme: $('panelTheme'), text: $('panelText'), info: $('panelInfo') };

        function openPanel(key) {
            Object.entries(panels).forEach(([k, el]) => {
                el.classList.toggle('open', k === key && !el.classList.contains('open'));
                if (k !== key) el.classList.remove('open');
            });
        }

        function closeAllPanels() {
            Object.values(panels).forEach(el => el.classList.remove('open'));
        }

        // ESC key to close panels
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const anyPanelOpen = Object.values(panels).some(el => el.classList.contains('open'));
                if (anyPanelOpen) {
                    closeAllPanels();
                    e.preventDefault();
                    e.stopPropagation();
                }
            }
        });

        $('btnInfo').addEventListener('click', () => openPanel('info'));
        if ($('btnTheme')) $('btnTheme').addEventListener('click', () => openPanel('theme'));
        if ($('btnText')) $('btnText').addEventListener('click', () => openPanel('text'));

        // ── Fullscreen ───────────────────────────────────────────────────────────────
        const btnFS = $('btnFullscreen');
        btnFS.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen?.();
                btnFS.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
            } else {
                document.exitFullscreen?.();
                btnFS.innerHTML = '<i class="bi bi-fullscreen"></i>';
            }
        });
        document.addEventListener('fullscreenchange', () => {
            const isFS = !!document.fullscreenElement;
            document.body.classList.toggle('fullscreen', isFS);
            btnFS.innerHTML = isFS ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-fullscreen"></i>';
        });

        // ── Theme buttons ────────────────────────────────────────────────────────────
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                settings.theme = btn.dataset.theme;
                applyTheme(settings.theme);
                saveSettings();
                if (typeof updateEpubLayout === 'function') updateEpubLayout();
            });
        });

        // ── Typography controls ──────────────────────────────────────────────────────
        const fontSelect = $('fontSelect');
        const fontSizeRange = $('fontSizeRange');
        const fontSizeVal = $('fontSizeVal');
        const lineHeightRange = $('lineHeightRange');

        if (fontSelect) fontSelect.value = settings.font;
        if (fontSizeRange) fontSizeRange.value = settings.fontSize;
        if (fontSizeVal) fontSizeVal.textContent = settings.fontSize + 'px';
        if (lineHeightRange) lineHeightRange.value = settings.lineHeight;

        if (fontSelect) fontSelect.addEventListener('change', () => {
            settings.font = fontSelect.value;
            saveSettings();
            if (typeof updateEpubLayout === 'function') updateEpubLayout();
        });
        if (fontSizeRange) fontSizeRange.addEventListener('input', () => {
            settings.fontSize = parseInt(fontSizeRange.value);
            if (fontSizeVal) fontSizeVal.textContent = settings.fontSize + 'px';
            saveSettings();
            if (typeof updateEpubLayout === 'function') updateEpubLayout();
        });
        if (lineHeightRange) lineHeightRange.addEventListener('input', () => {
            settings.lineHeight = parseFloat(lineHeightRange.value);
            saveSettings();
            if (typeof updateEpubLayout === 'function') updateEpubLayout();
        });

        // ── Auto-hide chrome on PDF/gallery ─────────────────────────────────────────
        let chromeTimer;
        function showChrome() {
            readerTop.classList.remove('hidden');
            readerBottom.classList.remove('hidden');
            clearTimeout(chromeTimer);
            if (READER_TYPE === 'epub' || READER_TYPE === 'gallery' || READER_TYPE === 'image') {
                chromeTimer = setTimeout(() => {
                    if (!document.querySelector('.panel.open')) {
                        readerTop.classList.add('hidden');
                        readerBottom.classList.add('hidden');
                    }
                }, 3000);
            }
        }
        document.addEventListener('mousemove', showChrome);
        document.addEventListener('touchstart', showChrome);
        showChrome();

        // ── Keyboard ─────────────────────────────────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                Object.values(panels).forEach(p => p.classList.remove('open'));
                if (document.fullscreenElement) document.exitFullscreen();
            }
            if (e.key === 'f' || e.key === 'F') btnFS.click();
        });

        // ═══════════════════════════════════════════════════════════════
        //  GALLERY READER
        // ═══════════════════════════════════════════════════════════════
        <?php if ($readerType === 'gallery'): ?>
            let galIndex = 0;
            const galImg = $('galleryImg');
            const galImages = GAL_IMAGES;
            const IS_CBZ = <?= json_encode($fileType === 'cbz') ?>;

            function renderGalImg() {
                if (!galImages.length) return;
                const src = galImages[galIndex];
                if (IS_CBZ) {
                        galImg.src = APP_URL + '/serve-cbz-image?file_id=' + FILE_ID + '&image_path=' + encodeURIComponent(src);
                } else {
                        galImg.src = '<?= route_url('serve-file') ?>?file=' + encodeURIComponent(src);
                }
                const pct = Math.round(((galIndex + 1) / galImages.length) * 100);
                progressBar.style.width = pct + '%';
                progressLabel.textContent = `Image ${galIndex + 1} / ${galImages.length}`;
            }

            document.addEventListener('keydown', e => {
                if (e.key === 'ArrowLeft') { galIndex = (galIndex - 1 + galImages.length) % galImages.length; renderGalImg(); }
                if (e.key === 'ArrowRight') { galIndex = (galIndex + 1) % galImages.length; renderGalImg(); }
            });
            renderGalImg();

            // ── Reset zoom when navigating ───────────────────────────────
            function galNav(dir) {
                galIndex = (galIndex + dir + galImages.length) % galImages.length;
                zoomScale = 1;
                zoomX = 0; zoomY = 0;
                applyZoom(galImg);
                renderGalImg();
            }
            $('galPrev').addEventListener('click', () => galNav(-1));
            $('galNext').addEventListener('click', () => galNav(1));
        <?php endif; ?>

        // ═══════════════════════════════════════════════════════════════
        //  ZOOM for gallery & single image
        // ═══════════════════════════════════════════════════════════════
        <?php if (in_array($readerType, ['gallery', 'image'])): ?>
            let zoomScale = 1;
            let zoomX = 0, zoomY = 0;
            const MIN_ZOOM = 0.5, MAX_ZOOM = 5;

            function applyZoom(img) {
                img.style.transform = `scale(${zoomScale}) translate(${zoomX}px, ${zoomY}px)`;
                img.style.cursor = zoomScale > 1 ? 'move' : 'zoom-in';
            }

            function getZoomTarget() {
                <?php if ($readerType === 'gallery'): ?>
                    return $('galleryImg');
                <?php else: ?>
                    return document.querySelector('.gallery-viewer img');
                <?php endif; ?>
            }

            // ── Mouse wheel zoom ──
            const viewerEl = <?php if ($readerType === 'gallery'): ?>$('galleryViewer')<?php else: ?>document.querySelector('.gallery-viewer')<?php endif; ?>;
            if (viewerEl) {
                viewerEl.addEventListener('wheel', e => {
                    e.preventDefault();
                    const img = getZoomTarget();
                    if (!img) return;
                    const delta = e.deltaY > 0 ? -0.15 : 0.15;
                    zoomScale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoomScale + delta));
                    if (zoomScale <= 1) { zoomX = 0; zoomY = 0; }
                    applyZoom(img);
                }, { passive: false });

                // ── Double-click to reset ──
                viewerEl.addEventListener('dblclick', () => {
                    const img = getZoomTarget();
                    zoomScale = 1; zoomX = 0; zoomY = 0;
                    if (img) applyZoom(img);
                });

                // ── Drag to pan when zoomed ──
                let isDragging = false, dragStartX = 0, dragStartY = 0;
                viewerEl.addEventListener('mousedown', e => {
                    if (zoomScale <= 1) return;
                    isDragging = true;
                    dragStartX = e.clientX - zoomX;
                    dragStartY = e.clientY - zoomY;
                    viewerEl.style.cursor = 'grabbing';
                });
                document.addEventListener('mousemove', e => {
                    if (!isDragging) return;
                    const img = getZoomTarget();
                    zoomX = e.clientX - dragStartX;
                    zoomY = e.clientY - dragStartY;
                    if (img) applyZoom(img);
                });
                document.addEventListener('mouseup', () => {
                    isDragging = false;
                    viewerEl.style.cursor = '';
                });

                // ── Touch pinch-to-zoom ──
                let lastPinchDist = null;
                viewerEl.addEventListener('touchmove', e => {
                    if (e.touches.length === 2) {
                        e.preventDefault();
                        const dx = e.touches[0].clientX - e.touches[1].clientX;
                        const dy = e.touches[0].clientY - e.touches[1].clientY;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (lastPinchDist !== null) {
                            const ratio = dist / lastPinchDist;
                            zoomScale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoomScale * ratio));
                            if (zoomScale <= 1) { zoomX = 0; zoomY = 0; }
                            const img = getZoomTarget();
                            if (img) applyZoom(img);
                        }
                        lastPinchDist = dist;
                    }
                }, { passive: false });
                viewerEl.addEventListener('touchend', () => { lastPinchDist = null; });
            }
        <?php endif; ?>

        // ═══════════════════════════════════════════════════════════════
        //  PDF PAGE NAVIGATION (using postMessage to viewer iframe)
        // ═══════════════════════════════════════════════════════════════
        <?php if ($readerType === 'pdf'): ?>
            const pdfFrame = $('pdfFrame');

            function pdfPage(delta) {
                const input = $('pdfPageInput');
                let p = parseInt(input.value || 1) + delta;
                const total = parseInt($('pdfPageTotal').textContent.replace('/ ', '')) || 9999;
                p = Math.max(1, Math.min(p, total));
                input.value = p;
                pdfFrame.contentWindow?.postMessage({ type: 'gotoPage', page: p }, '*');
                const pct = total > 1 ? Math.round(((p) / total) * 100) : 0;
                progressBar.style.width = pct + '%';
                progressLabel.textContent = `Page ${p} / ${total}`;
            }

            $('pdfPageInput').addEventListener('change', () => {
                const p = parseInt($('pdfPageInput').value) || 1;
                pdfFrame.contentWindow?.postMessage({ type: 'gotoPage', page: p }, '*');
            });

            // Receive messages from PDF viewer
            window.addEventListener('message', e => {
                if (e.data?.type === 'pdfInfo') {
                    $('pdfPageTotal').textContent = '/ ' + e.data.total;
                    $('pdfPageInput').max = e.data.total;
                }
                if (e.data?.type === 'pdfPage') {
                    $('pdfPageInput').value = e.data.page;
                    const total = e.data.total || 1;
                    const pct = Math.round((e.data.page / total) * 100);
                    progressBar.style.width = pct + '%';
                    progressLabel.textContent = `Page ${e.data.page} / ${total}`;
                }
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'ArrowLeft') pdfPage(-1);
                if (e.key === 'ArrowRight') pdfPage(1);
            });

            // No separate PDF pdfFrame resize for now; viewer handles it
        <?php endif; ?>

        // ═══════════════════════════════════════════════════════════════
        //  EPUB READER
        // ═══════════════════════════════════════════════════════════════
        <?php if ($readerType === 'epub'): ?>
            const book = ePub(EPUB_URL);
            const rendition = book.renderTo('epub-viewer', {
                width: '100%', height: '100%',
                flow: 'paginated', spread: 'none'
            });

            const LOC_KEY = 'pub_epub_loc_' + encodeURIComponent(EPUB_URL);

            function updateEpubLayout() {
                const themeColors = {
                    'theme-light': ['#fafafa', '#1a1a1a'],
                    'theme-sepia': ['#f4ead2', '#4a3728'],
                    'theme-dark': ['#1a1a1a', '#e0e0e0'],
                    'theme-contrast': ['#000000', '#ffffff'],
                };
                const [bg, fg] = themeColors[settings.theme] || themeColors['theme-dark'];

                rendition.themes.default({
                    'body': {
                        'background': `${bg} !important`,
                        'color': `${fg} !important`,
                        'font-family': `${settings.font} !important`,
                        'font-size': `${settings.fontSize}px !important`,
                        'line-height': `${settings.lineHeight} !important`,
                        'padding': '48px 60px !important',
                    },
                    'p': { 
                        'color': `${fg} !important`, 
                        'margin-bottom': '1em !important',
                        'line-height': `${settings.lineHeight} !important`,
                        'font-family': `${settings.font} !important`,
                        'font-size': `${settings.fontSize}px !important`
                    },
                    'div': { 
                        'color': `${fg} !important`,
                        'line-height': `${settings.lineHeight} !important`,
                        'font-family': `${settings.font} !important`
                    },
                    'span': { 
                        'color': `${fg} !important`,
                        'font-family': `${settings.font} !important`
                    },
                    'h1,h2,h3': { 
                        'color': `${fg} !important`, 
                        'font-family': `${settings.font} !important`,
                        'line-height': `${settings.lineHeight} !important`
                    },
                    'img': { 'max-width': '100% !important', 'height': 'auto !important' }
                });
            }

            book.ready.then(() => {
                const saved = localStorage.getItem(LOC_KEY);
                return rendition.display(saved || undefined);
            }).then(() => {
                $('epubLoading').classList.add('gone');
                updateEpubLayout();

                const cacheKey = 'pub_epub_locs_' + encodeURIComponent(EPUB_URL);
                const cached = localStorage.getItem(cacheKey);
                if (cached) {
                    book.locations.load(cached);
                } else {
                    book.locations.generate(1000).then(() => {
                        localStorage.setItem(cacheKey, book.locations.save());
                    });
                }
            }).catch(err => {
                $('epubLoading').classList.add('gone');
                console.error('EPUB load error:', err);
            });

            rendition.on('relocated', loc => {
                localStorage.setItem(LOC_KEY, loc.start.cfi);
                if (book.locations.length() > 0) {
                    const pct = Math.round(book.locations.percentageFromCfi(loc.start.cfi) * 100);
                    progressBar.style.width = pct + '%';
                    progressLabel.textContent = pct + '% read';
                }
            });

            $('zonePrev').addEventListener('click', () => rendition.prev());
            $('zoneNext').addEventListener('click', () => rendition.next());
            document.addEventListener('keydown', e => {
                if (e.key === 'ArrowLeft') rendition.prev();
                if (e.key === 'ArrowRight') rendition.next();
                if (e.key === ' ') rendition.next();
            });
        <?php endif; ?>

    </script>

</body>

</html>
