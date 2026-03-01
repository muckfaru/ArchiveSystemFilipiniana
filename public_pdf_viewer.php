<?php
/**
 * Public PDF Viewer
 * Archive System - Quezon City Public Library
 * No login required. Embeds in an iframe inside reader.php.
 */

require_once __DIR__ . '/backend/core/config.php';
require_once __DIR__ . '/backend/core/functions.php';

$file = $_GET['file'] ?? '';
$file = str_replace(['..', '\\'], '', $file);
$fileUrl = APP_URL . '/serve_file.php?file=' . urlencode($file);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Viewer</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            background: #1a1a1a;
            overflow: hidden;
        }

        #viewerContainer {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0 80px;
            gap: 12px;
            scroll-behavior: smooth;
        }

        .pdf-page-canvas {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
            border-radius: 4px;
            display: block;
            max-width: calc(100vw - 32px);
        }

        #loadMsg {
            color: #888;
            font-family: Inter, sans-serif;
            font-size: 14px;
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .spin {
            width: 36px;
            height: 36px;
            border: 3px solid rgba(58, 154, 255, 0.3);
            border-top-color: #3A9AFF;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div id="viewerContainer">
        <div id="loadMsg">
            <div class="spin"></div>Loading PDF…
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const PDF_URL = '<?= addslashes($fileUrl) ?>';
        const container = document.getElementById('viewerContainer');
        const loadMsg = document.getElementById('loadMsg');

        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.5;
        let isRendering = false;

        // Render all pages (continuous scroll)
        async function renderAll() {
            if (isRendering) return;
            isRendering = true;

            pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
            const total = pdfDoc.numPages;

            // Tell parent
            window.parent.postMessage({ type: 'pdfInfo', total }, '*');

            loadMsg.remove();

            for (let i = 1; i <= total; i++) {
                const page = await pdfDoc.getPage(i);
                const vp = page.getViewport({ scale });
                const canvas = document.createElement('canvas');
                canvas.className = 'pdf-page-canvas';
                canvas.dataset.pageNum = i;
                canvas.width = vp.width;
                canvas.height = vp.height;
                container.appendChild(canvas);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
            }

            // IntersectionObserver to track current page
            const observer = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        const n = parseInt(e.target.dataset.pageNum);
                        if (n !== currentPage) {
                            currentPage = n;
                            window.parent.postMessage({ type: 'pdfPage', page: currentPage, total }, '*');
                        }
                    }
                });
            }, { root: container, threshold: 0.5 });

            container.querySelectorAll('.pdf-page-canvas').forEach(c => observer.observe(c));
        }

        renderAll().catch(err => {
            loadMsg.innerHTML = `<p style="color:#f44">Error loading PDF: ${err.message}</p>`;
            container.appendChild(loadMsg);
        });

        // Listen for page jump from parent
        window.addEventListener('message', e => {
            if (e.data?.type === 'gotoPage') {
                const p = parseInt(e.data.page);
                const canvas = container.querySelector(`[data-page-num="${p}"]`);
                if (canvas) {
                    canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    currentPage = p;
                }
            }
        });
    </script>
</body>

</html>