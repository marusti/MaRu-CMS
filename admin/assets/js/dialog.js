let deleteUrl = null;
let deleteAction = null;
let lastFocusedEl = null;
let isModalOpen = false;

/**
 * Öffnet ein beliebiges Modal per ID
 * @param {string} id 
 */
function openDialog(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.showModal();
    modal.focus();
    isModalOpen = true;
    
document.body.style.overflow = 'hidden'; // 👈 Scroll sperren
}

/**
 * Schließt ein beliebiges Modal per ID
 * @param {string} id 
 */
function closeDialog(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.close();
    isModalOpen = false;
    
document.body.style.overflow = ''; // 👈 Scroll wieder erlauben

    if (lastFocusedEl) {
        lastFocusedEl.focus();
        lastFocusedEl = null;
    }
}

/**
 * Öffnet das zentrale Delete-Modal
 */
function openDeleteModal() {
    openDialog('deleteModal');
}

/**
 * Schließt das zentrale Delete-Modal
 */
function closeDeleteModal() {
    closeDialog('deleteModal');
    deleteUrl = null;
    deleteAction = null;
}

/**
 * Zeigt das Delete-Modal mit Titel, Nachricht, URL oder Callback
 */
function confirmModal(title, message, url = null, action = null) {
    lastFocusedEl = document.activeElement;

    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');

    if (modalTitle) modalTitle.textContent = title;
    if (modalMessage) modalMessage.textContent = message;

    deleteUrl = url;
    deleteAction = action;

    openDeleteModal();

    const modalConfirm = document.getElementById('modalConfirm');
    modalConfirm?.focus();
}

/**
 * Öffnet das Media-Modal
 */
function openMediaModal(url, onSelect) {
    const modal = document.getElementById('mediaModal');
    const content = document.getElementById('mediaModalContent');
    if (!modal || !content) return;

    fetch(url)
        .then(r => r.text())
        .then(html => {
            content.innerHTML = html;

            content.querySelectorAll('img').forEach(img => {
                img.addEventListener('click', () => {
                    if (typeof onSelect === 'function') {
                        onSelect(img.src);
                    }
                    closeDialog('mediaModal');
                });
            });

            openDialog('mediaModal');
        });
}

/*Schließen*/
document.addEventListener('DOMContentLoaded', () => {

    // Alle Close-Buttons für beliebige Dialoge
    document.querySelectorAll('.maru-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('dialog');
            if (modal) closeDialog(modal.id);
        });
    });
    
 // Alle Cancel-Buttons für beliebige Dialoge
    document.querySelectorAll('.maru-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('dialog');
            if (modal) closeDialog(modal.id);
        });
    });

    // Klick außerhalb schließt Dialog
    document.querySelectorAll('dialog').forEach(modal => {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeDialog(modal.id);
        });

        // ESC schließt Dialog
        modal.addEventListener('cancel', e => {
            e.preventDefault();
            closeDialog(modal.id);
        });
    });

    // Cancel-Button
    const modalCancel = document.getElementById('modalCancel');
    if (modalCancel) {
        modalCancel.addEventListener('click', () => {
            const modal = modalCancel.closest('dialog');
            if (modal) closeDialog(modal.id);
        });
    }

    // Delete-Modal: "Ja"-Button
    const modalConfirm = document.getElementById('modalConfirm');
    modalConfirm?.addEventListener('click', () => {
        if (deleteAction) {
            deleteAction();
            closeDeleteModal();
            return;
        }
        if (deleteUrl) {
            window.location.href = deleteUrl;
        }
        closeDeleteModal();
    });

    //  löschen
    document.querySelectorAll('.js-delete').forEach(btn => {

    btn.addEventListener('click', e => {

        e.preventDefault();

        const title = btn.dataset.title || '';
        let message = btn.dataset.message || '';

        const value = btn.dataset.value || '';
        const url = btn.dataset.url || null;
        const formId = btn.dataset.form || null;
        const inputId = btn.dataset.input || null;

        if (value) {
            message = message.replace('%s', value);
        }

        confirmModal(title, message, url, () => {

            // Redirect Delete
            if (url) {
                window.location.href = url;
                return;
            }

            // Form Delete
            if (formId && inputId) {

                const form = document.getElementById(formId);
                const input = document.getElementById(inputId);

                if (form && input) {
                    input.value = value;
                    form.submit();
                }

            }

        });

    });

});

});