// core/assets/js/menu.js
function toggleSubmenu(button) {
    const submenu = button.nextElementSibling;

    // Robustheitsprüfung
    if (!submenu || !submenu.classList.contains('submenu')) {
        console.warn("Kein gültiges Submenü gefunden.");
        return;
    }

    const isHidden = submenu.hasAttribute('hidden');
    if (isHidden) {
        submenu.removeAttribute('hidden');
        button.setAttribute('aria-expanded', 'true');
    } else {
        submenu.setAttribute('hidden', '');
        button.setAttribute('aria-expanded', 'false');
    }
}
