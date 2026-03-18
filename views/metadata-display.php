<?php
/**
 * Metadata Display Configuration View
 * Archive System - Quezon City Public Library
 */

include __DIR__ . '/layouts/header.php';
?>

<style>
    /* ── Page Layout ──────────────────────────── */
    .md-page-header {
        margin-top: 0 !important;
        padding-top: 0 !important;
        margin-bottom: 20px;
    }

    .md-page-header h4 {
        font-size: 22px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 4px;
    }

    .md-page-header p {
        color: #6B7280;
        font-size: 13.5px;
        margin: 0;
    }

    /* Nav tabs */
    .md-tabs {
        margin-bottom: 28px;
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        gap: 24px;
        border-bottom: 1px solid #E5E7EB;
    }

    .md-tabs .nav-item {
        width: auto !important;
        margin: 0;
        padding: 0;
    }

    .md-tabs .nav-link {
        font-size: 16px;
        font-weight: 700;
        color: #6B7280;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 10px 0;
        margin-bottom: -1px;
        border-radius: 0;
        transition: color .2s, border-color .2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .md-tabs .nav-link i {
        font-size: 18px;
    }

    .md-tabs .nav-link.active {
        color: #3A9AFF;
        border-bottom-color: #3A9AFF;
        background: none;
    }

    .md-tabs .nav-link:hover:not(.active) {
        color: #4B5563;
    }

    /* Two-panel layout */
    .md-layout {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 28px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .md-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Config panel */
    .md-config-panel {
        background: #FFFFFF;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
    }

    .md-config-header {
        padding: 20px 22px 14px;
        border-bottom: 1px solid #F3F4F6;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .md-config-header h6 {
        font-size: 14px;
        font-weight: 700;
        color: #1F2937;
        margin: 0;
    }

    .md-autosave-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        color: #059669;
        background: #D1FAE5;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .md-autosave-badge .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #059669;
        animation: pulse-dot 2s infinite;
    }

    @keyframes pulse-dot {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: .4;
        }
    }

    /* Field rows */
    .md-field-list {
        padding: 8px 0;
    }

    .md-field-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px 22px;
        border-bottom: 1px solid #F9FAFB;
        transition: background .15s;
    }

    .md-field-row:last-child {
        border-bottom: none;
    }

    .md-field-row:hover {
        background: #FAFAFA;
    }

    .md-field-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: #F3F4F6;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6B7280;
        font-size: 15px;
        flex-shrink: 0;
    }

    .md-field-meta {
        flex: 1;
        min-width: 0;
    }

    .md-field-label {
        font-size: 13.5px;
        font-weight: 600;
        color: #1F2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .md-field-desc {
        font-size: 11.5px;
        color: #9CA3AF;
        margin-top: 1px;
    }

    /* Toggle */
    .md-toggle {
        position: relative;
        width: 42px;
        height: 24px;
        flex-shrink: 0;
    }

    .md-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .md-toggle .slider {
        position: absolute;
        inset: 0;
        background: #D1D5DB;
        border-radius: 24px;
        cursor: pointer;
        transition: background .2s;
    }

    .md-toggle .slider::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        left: 3px;
        top: 3px;
        background: #FFFFFF;
        border-radius: 50%;
        transition: transform .2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
    }

    .md-toggle input:checked+.slider {
        background: #3A9AFF;
    }

    .md-toggle input:checked+.slider::before {
        transform: translateX(18px);
    }

    /* Preview panel */
    .md-preview-panel {
        position: sticky;
        top: 24px;
    }

    .md-preview-label {
        font-size: 15px;
        font-weight: 700;
        color: #1F2937;
        margin-bottom: 18px;
    }

    .md-preview-container {
        background: #F3F4F6;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #E5E7EB;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .08);
    }

    /* Card preview */
    .md-preview-card {
        background: #FFFFFF;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #F3F4F6;
        max-width: 220px;
        margin: 0 auto;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
    }

    .md-preview-thumb {
        width: 100%;
        aspect-ratio: 3/4;
        background: linear-gradient(135deg, #E5E7EB, #D1D5DB);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9CA3AF;
        font-size: 36px;
        overflow: hidden;
    }

    .md-preview-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .md-preview-card-body {
        padding: 12px 14px 14px;
    }

    .md-preview-cat {
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .7px;
        color: #6B7280;
        background: #F3F4F6;
        padding: 3px 8px;
        border-radius: 5px;
        display: inline-block;
        margin-bottom: 6px;
    }

    .md-preview-title {
        font-size: 13.5px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 8px;
        line-height: 1.35;
    }

    .md-preview-meta-row {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        padding: 3px 0;
    }

    .md-preview-meta-label {
        font-weight: 600;
        color: #6B7280;
    }

    .md-preview-meta-value {
        color: #374151;
        text-align: right;
        max-width: 55%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Modal preview */
    .md-preview-modal {
        background: #FFFFFF;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #E5E7EB;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
    }

    .md-preview-modal-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 24px;
        border-bottom: 1px solid #E5E7EB;
    }

    .md-preview-modal-thumb {
        width: 64px;
        height: 64px;
        border-radius: 8px;
        background: #E5E7EB;
        flex-shrink: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9CA3AF;
        font-size: 24px;
    }

    .md-preview-modal-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .file-cat {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #6B7280;
        margin-bottom: 4px;
    }

    .file-title {
        font-size: 16px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 0;
    }

    .md-preview-modal-fields {
        padding: 8px 24px 24px;
    }

    .md-modal-field-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #F3F4F6;
    }

    .md-modal-field-row:last-child {
        border-bottom: none;
    }

    .md-modal-field-label {
        color: #9CA3AF;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        font-size: 11px;
    }

    .md-modal-field-value {
        color: #1F2937;
        font-weight: 500;
        font-size: 13px;
        text-align: right;
        max-width: 60%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .md-no-fields-hint {
        text-align: center;
        padding: 28px 16px;
        color: #9CA3AF;
        font-size: 13px;
    }

    /* Toast */
    #mdSaveToast {
        position: fixed;
        bottom: 28px;
        right: 28px;
        background: #1F2937;
        color: #FFFFFF;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
        z-index: 9999;
        transform: translateY(20px);
        opacity: 0;
        transition: all .3s ease;
        pointer-events: none;
    }

    #mdSaveToast.show {
        transform: translateY(0);
        opacity: 1;
    }

    #mdSaveToast.error {
        background: #EF4444;
    }
</style>

<div class="container-fluid py-4">

    <div class="md-page-header mb-3" style="margin-top: 0 !important; padding-top: 0 !important;">
        <h1 class="fw-bold m-0" style="font-size: 32px; color: #000; font-family: 'Poppins', sans-serif;">Metadata
            Display Configuration</h1>
        <div class="text-dark" style="font-size: 14px; margin-top: 4px;">Configure how metadata fields are displayed
            across the library management system.</div>
    </div>

    <!-- Tabs -->
    <ul class="nav md-tabs" id="mdTabs">
        <li class="nav-item">
            <a class="nav-link active" href="#" data-tab="card">
                <i class="bi bi-eye-fill"></i>Basic Viewing
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" data-tab="modal">
                <i class="bi bi-layers-fill"></i>Detailed Modal
            </a>
        </li>
    </ul>

    <?php if (empty($fields)): ?>
        <div class="alert alert-info d-flex align-items-center gap-3" style="border-radius:12px">
            <i class="bi bi-info-circle-fill" style="font-size:1.4rem"></i>
            <div>
                <strong>No custom metadata fields found.</strong><br>
                <span style="font-size:13px">Go to <a href="form-builder.php" class="fw-semibold">Custom Metadata</a> and
                    create some fields first — they will automatically appear here.</span>
            </div>
        </div>
    <?php else: ?>

        <div class="md-layout">

            <!-- LEFT: Field Configuration -->
            <div class="md-config-panel">
                <div class="md-config-header">
                    <h6>Field Configuration</h6>
                    <span class="md-autosave-badge"><span class="dot"></span>Auto-saving</span>
                </div>

                <div class="md-field-list" id="mdFieldList">
                    <?php foreach ($fields as $field): ?>
                        <?php
                        $icon = match ($field['field_type']) {
                            'date' => 'bi-calendar3',
                            'number' => 'bi-hash',
                            'textarea' => 'bi-text-left',
                            'select', 'radio' => 'bi-list-ul',
                            'checkbox' => 'bi-check2-square',
                            'tags' => 'bi-tags',
                            default => 'bi-tag',
                        };
                        ?>
                        <div class="md-field-row" data-field-id="<?= $field['id'] ?>"
                            data-card="<?= (int) $field['show_on_card'] ?>" data-modal="<?= (int) $field['show_in_modal'] ?>"
                            data-label="<?= htmlspecialchars($field['field_label']) ?>"
                            data-type="<?= htmlspecialchars($field['field_type']) ?>">

                            <div class="md-field-icon"><i class="bi <?= $icon ?>"></i></div>
                            <div class="md-field-meta">
                                <div class="md-field-label"><?= htmlspecialchars($field['field_label']) ?></div>
                                <div class="md-field-desc"><?= ucfirst($field['field_type']) ?>
                                    field<?= $field['is_required'] ? ' · Required' : '' ?><?= $field['form_name'] ? ' · ' . htmlspecialchars($field['form_name']) : '' ?>
                                </div>
                            </div>
                            <label class="md-toggle">
                                <input type="checkbox" class="md-field-toggle" data-field-id="<?= $field['id'] ?>"
                                    <?= $field['show_on_card'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RIGHT: Live Preview -->
            <div class="md-preview-panel">
                <div class="md-preview-label">Live Preview</div>
                <div class="md-preview-container">

                    <!-- Basic Viewing preview -->
                    <div id="previewCard">
                        <div class="md-preview-card">
                                <div class="md-preview-thumb">
                                    <?php if ($sampleFile && $sampleFile['thumbnail_path']): ?>
                                        <img src="<?= APP_URL . '/' . htmlspecialchars($sampleFile['thumbnail_path']) ?>"
                                            alt="Preview">
                                    <?php else: ?>
                                        <i class="bi bi-file-earmark-text"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="md-preview-card-body">
                                    <div class="md-preview-cat">Document</div>
                                    <div class="md-preview-title">
                                        <?= $sampleFile ? htmlspecialchars($sampleFile['title']) : 'Sample Document Title' ?>
                                    </div>
                                    <div id="previewCardMeta"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Detailed Modal preview -->
                        <div id="previewModal" style="display:none">
                            <div class="md-preview-modal">
                                <div class="md-preview-modal-header">
                                    <div class="md-preview-modal-thumb">
                                        <?php if ($sampleFile && $sampleFile['thumbnail_path']): ?>
                                            <img src="<?= APP_URL . '/' . htmlspecialchars($sampleFile['thumbnail_path']) ?>"
                                                alt="Thumb">
                                        <?php else: ?>
                                            <i class="bi bi-file-earmark-text"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="file-cat">DOCUMENT</div>
                                        <div class="file-title">
                                            <?= $sampleFile ? htmlspecialchars($sampleFile['title']) : 'Sample Document Title' ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="md-preview-modal-fields" id="previewModalMeta"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<!-- Toast -->
<div id="mdSaveToast">
    <i class="bi bi-check-circle-fill"></i>
    <span id="mdSaveToastMsg">Configuration saved</span>
</div>

<script>
    const MD_FIELDS = <?= json_encode($fields) ?>;
    const MD_SAMPLE_META = <?= json_encode($sampleMeta) ?>;
    const MD_API_URL = '<?= APP_URL ?>/backend/api/metadata-display-config.php';
</script>
<script src="<?= APP_URL ?>/assets/js/admin_pages/metadata-display.js"></script>

<?php include __DIR__ . '/layouts/footer.php'; ?>