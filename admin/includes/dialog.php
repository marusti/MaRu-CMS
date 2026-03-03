<!-- Vorschau-Dialog -->
<dialog id="imagePreviewDialog"
        class="modal"
        aria-labelledby="modalTitle"  <!-- Change to match the id -->
        aria-describedby="previewModalMessage">

    <div id="modalTitle"><?= __('preview') ?></div>  <!-- Ensure this matches -->

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

    <button type="button" class="maru-close" aria-label="<?= __('close') ?>">x</button>
</dialog>

<!-- Lösch-Bestätigungsdialog -->
<dialog id="deleteModal"
        class="modal"
        aria-labelledby="modalTitle"
        aria-describedby="modalMessage">

    <div id="modalTitle"><?= __('delete') ?></div>

    <div class="dialog-content">
        <p id="modalMessage"><?= __('delete_confirm_generic') ?></p>
    </div>

    <button id="modalConfirm" type="button"><?= __('yes') ?></button>
    <button id="modalCancel" type="button"><?= __('no') ?></button>

    <button type="button" class="maru-close" aria-label="<?= __('close') ?>">x</button>
</dialog>

<!-- Generischer Formular-Dialog -->
<dialog id="formModal"
        class="modal"
        aria-labelledby="modalTitle">

    <div id="modalTitle"><?= __('form_title') ?></div>  <!-- Add meaningful content here -->

    <div class="dialog-content" id="formModalContent">
        <!-- Wird dynamisch gefüllt -->
    </div>

    <button type="button" class="maru-close" aria-label="<?= __('close') ?>">×</button>
</dialog>

<!-- Media-Dialog -->
<dialog id="mediaModal" class="modal" aria-labelledby="modalTitle" aria-describedby="mediaModalMessage">
    <div id="modalTitle"><?= __('select_image') ?></div>

    <!-- Neue Beschreibung für den Dialog -->
    <div id="mediaModalMessage" class="sr-only"><?= __('select_image_description') ?></div>

    <div class="dialog-content" id="mediaModalContent">
        <!-- Der Inhalt von uploads_list.php wird hier geladen -->
    </div>
    
    <button id="modalCancel" type="button" class="maru-cancel"><?= __('cancel') ?></button>

    <button type="button" class="maru-close" aria-label="<?= __('close') ?>">×</button>
</dialog>

<script>
    const LANG = <?= json_encode(include __DIR__ . '/../lang/de.php'); ?>;
</script>