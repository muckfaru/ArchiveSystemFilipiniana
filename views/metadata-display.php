<?php
/**
 * Metadata Display Configuration View
 * Archive System - Quezon City Public Library
 */

include __DIR__ . '/layouts/header.php';
?>

<div class="container-fluid md-page">

    <div class="page-header admin-page-header">
        <div>
            <h1 class="page-title">Metadata Display Configuration</h1>
            <div class="page-subtitle">Configure how metadata fields are displayed across the library management
                system.</div>
        </div>
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
        <div class="md-empty-state">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>No custom metadata fields found.</strong>
                <span class="md-empty-state-text">Go to <a href="<?= route_url('form-builder') ?>" class="fw-semibold">Custom
                        Metadata</a> and create some fields first. They will automatically appear here.</span>
            </div>
        </div>
    <?php else: ?>

        <div class="md-layout">

            <!-- LEFT: Field Configuration -->
            <div class="md-config-panel">
                <div class="md-config-header">
                    <div>
                        <h6 class="md-section-title">Field Configuration</h6>
                        <p class="md-section-subtitle">Choose which metadata fields appear in basic cards and in the
                            detailed modal view.</p>
                    </div>
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
                <div class="md-preview-surface">
                    <div class="md-preview-header">
                        <div>
                            <h6 class="md-section-title">Live Preview</h6>
                            <p class="md-section-subtitle">Preview how the selected metadata will appear to users before
                                it is saved.</p>
                        </div>
                    </div>
                    <div class="md-preview-body">

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
