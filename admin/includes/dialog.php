<!-- Vorschau-Dialog -->
<dialog id="imagePreviewDialog"
        class="modal"
        aria-labelledby="previewModalTitle"
        aria-describedby="previewModalMessage">

    <h2 id="previewModalTitle"><?= __('preview') ?></h2>

    <div class="dialog-content">
        <div class="image-container">
            <img src="" alt="" id="previewImage" class="preview-img">
            <pre id="previewText"></pre>
        </div>

        <div class="info-panel">
            <p id="previewModalMessage" aria-live="polite">
                <strong><?= __('file_info') ?>:</strong>
            </p>
            <p><strong><?= __('name') ?>:</strong> <span id="fileName"></span></p>
            <p><strong><?= __('size') ?>:</strong> <span id="fileSize"></span></p>
            <p><strong><?= __('dimensions') ?>:</strong> <span id="fileDimensions"></span></p>

            <div id="imageMeta">
                <label for="altTextInput"><strong><?= __('alt_text') ?>:</strong></label>
                <input type="text" id="altTextInput" placeholder="<?= __('alt_text_placeholder') ?>">

                <label for="captionInput"><strong><?= __('caption') ?>:</strong></label>
                <input type="text" id="captionInput" placeholder="<?= __('caption_placeholder') ?>">

                <button type="button" id="saveMetaBtn"><?= __('save') ?></button>
            </div>

            <p id="metaStatus" class="alt-status" aria-live="polite"></p>
        </div>
    </div>

    <button id="modalClose"
            type="button"
            class="close-btn"
            aria-label="<?= __('close') ?>">
        ×
    </button>
</dialog>

<!-- Lösch-Bestätigungsdialog -->
<dialog id="deleteModal"
        class="modal"
        aria-labelledby="modalTitle"
        aria-describedby="modalMessage">

    <h2 id="modalTitle"><?= __('confirm') ?></h2>

    <div class="dialog-content">
        <p id="modalMessage"><?= __('delete_confirm_generic') ?></p>
    </div>

    <button id="modalConfirm" type="button"><?= __('yes') ?></button>
    <button id="modalCancel" type="button"><?= __('no') ?></button>

    <button id="modalClose"
            type="button"
            class="close-btn"
            aria-label="<?= __('close') ?>">
        ×
    </button>
</dialog>
