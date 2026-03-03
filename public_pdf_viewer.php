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
            width: 100%;
            background: #1a1a1a;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        #viewerContainer {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0 16px;
            gap: 12px;
            scroll-behavior: smooth;
            background: #1a1a1a;
        }
        
        /* Fullscreen mode - remove bottom padding */
        @media (display-mode: fullscreen) {
            #viewerContainer {
                padding: 16px 0 0;
            }
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

            try {
                pdfDoc = await pdfjsLib.getDocument(PDF_URL).promise;
                const total = pdfDoc.numPages;

                // Tell parent
                window.parent.postMessage({ type: 'pdfInfo', total }, '*');

                loadMsg.remove();

                // Clear any existing canvases to prevent duplicates
                const existingCanvases = container.querySelectorAll('.pdf-page-canvas');
                existingCanvases.forEach(c => c.remove());

                // Render each page sequentially
                for (let pageNum = 1; pageNum <= total; pageNum++) {
                    const page = await pdfDoc.getPage(pageNum);
                    const vp = page.getViewport({ scale });
                    
                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-page-canvas';
                    canvas.dataset.pageNum = pageNum;
                    canvas.width = vp.width;
                    canvas.height = vp.height;
                    
                    container.appendChild(canvas);
                    
                    const ctx = canvas.getContext('2d');
                    const renderContext = {
                        canvasContext: ctx,
                        viewport: vp
                    };
                    
                    await page.render(renderContext).promise;
                }
            } catch (err) {
                console.error('PDF rendering error:', err);
                throw err;
            }

            // IntersectionObserver to track current page
            const observer = new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        const n = parseInt(e.target.dataset.pageNum, 10);
                        if (!isNaN(n) && n !== currentPage) {
                            currentPage = n;
                            window.parent.postMessage({ type: 'pdfPage', page: currentPage, total }, '*');
                        }
                    }
                });
            }, { root: null, threshold: 0.5 });

            // Observe all rendered canvases
            const allCanvases = container.querySelectorAll('.pdf-page-canvas');
            allCanvases.forEach(c => observer.observe(c));
            
            isRendering = false;
        }

        renderAll().catch(err => {
            loadMsg.innerHTML = `<p style="color:#f44">Error loading PDF: ${err.message}</p>`;
            container.appendChild(loadMsg);
        });

        // Listen for page jump from parent
        window.addEventListener('message', e => {
            if (e.data?.type === 'gotoPage') {
                const p = parseInt(e.data.page, 10);
                if (!isNaN(p) && p >= 1 && pdfDoc && p <= pdfDoc.numPages) {
                    const canvas = container.querySelector(`[data-page-num="${p}"]`);
                    if (canvas) {
                        canvas.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        currentPage = p;
                        window.parent.postMessage({ 
                            type: 'pdfPage', 
                            page: currentPage, 
                            total: pdfDoc.numPages 
                        }, '*');
                    }
                }
            }
        });
    </script>
</body>

</html>