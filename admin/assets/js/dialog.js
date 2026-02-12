let deleteUrl = null;      // für GET-Löschen (Kategorie/Seite)
let deleteAction = null;   // für POST-Löschen (Template/Plugin)
let lastFocusedEl = null;
let isModalOpen = false;

function openModal() {
    if (isModalOpen) return;

    const modal = document.getElementById('deleteModal');
    if (!modal) return;

    modal.showModal();
    modal.focus();
    isModalOpen = true;
}

function closeModal() {
    if (!isModalOpen) return;

    const modal = document.getElementById('deleteModal');
    if (!modal) return;

    modal.close();
    deleteUrl = null;
    deleteAction = null;

    if (lastFocusedEl) {
        lastFocusedEl.focus();
        lastFocusedEl = null;
    }

    isModalOpen = false;
}

function confirmModal(title, message, url = null, action = null) {
    lastFocusedEl = document.activeElement;

    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirm = document.getElementById('modalConfirm');

    if (typeof title === 'string' && modalTitle) {
        modalTitle.textContent = title;
    }
    if (typeof message === 'string' && modalMessage) {
        modalMessage.textContent = message;
    }

    deleteUrl = url;
    deleteAction = action;

    openModal();

    modalConfirm?.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('deleteModal');
    const modalCancel = document.getElementById('modalCancel');
    const modalClose = document.getElementById('modalClose');
    const modalConfirm = document.getElementById('modalConfirm');

    // Klick auf "Abbrechen" oder "X"
    modalCancel?.addEventListener('click', closeModal);
    modalClose?.addEventListener('click', closeModal);

    // Klick außerhalb des Dialogs schließt Modal
    modal?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeModal();
    });

    // ESC-Key schließt Modal
    modal?.addEventListener('cancel', e => {
        e.preventDefault();
        closeModal();
    });

    // Klick auf "Ja"
    modalConfirm?.addEventListener('click', () => {
        if (deleteAction) {
            deleteAction();
            closeModal();
            return;
        }

        if (deleteUrl) {
            const allowed = [
                'delete_category.php',
                'delete_page.php'
            ];
            if (allowed.some(prefix => deleteUrl.startsWith(prefix))) {
                window.location.href = deleteUrl;
            }
        }

        closeModal();
    });

    // EventListener für Buttons mit data-Attributen
    document.querySelectorAll('[data-url], [data-template], [data-plugin]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();

            if (btn.dataset.url) {
                // Kategorie/Seite GET
                confirmModal(
                    btn.dataset.title,
                    btn.dataset.message,
                    btn.dataset.url
                );
            } else if (btn.dataset.template) {
                // Template POST via verstecktes Formular
                confirmModal(
                    btn.dataset.title,
                    btn.dataset.message,
                    null,
                    () => {
                        const form = document.getElementById('deleteTemplateForm');
                        const input = document.getElementById('deleteTemplateInput');
                        if (form && input) {
                            input.value = btn.dataset.template;
                            form.submit();
                        }
                    }
                );
            } else if (btn.dataset.plugin) {
                // Plugin POST via verstecktes Formular
                confirmModal(
                    btn.dataset.title,
                    btn.dataset.message,
                    null,
                    () => {
                        const form = document.getElementById('deletePluginForm');
                        const input = document.getElementById('deletePluginInput');
                        if (form && input) {
                            input.value = btn.dataset.plugin;
                            form.submit();
                        }
                    }
                );
            }
        });
    });
});
