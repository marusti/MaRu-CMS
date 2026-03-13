document.addEventListener('DOMContentLoaded', () => {

    const filter = document.getElementById('filter');
    if (!filter) return;

    filter.addEventListener('input', e => {
    const search = e.target.value.toLowerCase().trim();

    document.querySelectorAll('.entry-block').forEach(item => {
        const name = item.querySelector('.entry-name')?.textContent.toLowerCase() || '';

        // Prüfe eigene Kategorie
        const ownMatch = name.includes(search);

        // Prüfe Sub-Categories
        const childMatch = Array.from(item.querySelectorAll('.entry-name'))
            .some(el => el.textContent.toLowerCase().includes(search));

        if (ownMatch || childMatch) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});
    
// Allgemeine Filterfunktion für alle Listenelemente
function applyFilter(selectElement, dataAttr) {
    const items = document.querySelectorAll('.list-item');

    selectElement.addEventListener('change', () => {
        const selected = selectElement.value;

        items.forEach(item => {
            const value = item.dataset[dataAttr];
            if (!value) return; // falls das Attribut fehlt, überspringen
            item.style.display = (selected === 'all' || value === selected) ? '' : 'none';
        });
    });
}

// Folder-Filter
const folderFilter = document.getElementById('folderFilter');
if (folderFilter) {
    applyFilter(folderFilter, 'folder'); // filtert nach data-folder
}

// Role-Filter
const roleFilter = document.getElementById('roleFilter');
if (roleFilter) {
    applyFilter(roleFilter, 'role'); // filtert nach data-role
}

// Category-Filter
const categoryFilter = document.getElementById('categoryFilter');
if (categoryFilter) {
    applyFilter(categoryFilter, 'category'); // filtert nach data-category
}

});