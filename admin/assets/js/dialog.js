// Zentraler Dialog-Handler
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

document.addEventListener('DOMContentLoaded', () => {
    // Alle Close-Buttons für beliebige Dialoge
    document.querySelectorAll('.maru-close').forEach(btn => {
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
    
 // Hinzufügen eines Event Listeners für den 'modalCancel'-Button
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

    /**
     * EventListener für Buttons mit data-Attributen (Kategorie, Seite, Template, Plugin)
     */
    document.querySelectorAll('[data-template]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const filePath = btn.dataset.template;

            confirmModal(
                btn.dataset.title || 'Bestätigung',
                btn.dataset.message || LANG['delete_confirm_generic'],
                null,
                () => {
                    const form = document.getElementById('deleteTemplateForm');
                    const input = document.getElementById('deleteTemplateInput');
                    if (form && input) {
                        input.value = filePath;
                        form.submit();
                    }
                }
            );
        });
    });

    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const username = btn.dataset.name;

            confirmModal(
                LANG['delete'],
                LANG['delete_confirm_user'].replace('%s', username),
                null,
                () => {
                    const form = document.getElementById('deleteUserForm');
                    const input = document.getElementById('deleteUserInput');
                    if (form && input) {
                        input.value = username;
                        form.submit();
                    }
                }
            );
        });
    });
});

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