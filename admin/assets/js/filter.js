document.addEventListener('DOMContentLoaded', () => {

    const filter = document.getElementById('filter');
    if (!filter) return;

    filter.addEventListener('input', e => {
        const search= e.target.value.toLowerCase().trim();

        document.querySelectorAll('.entry-block').forEach(result => {
            const name = result.querySelector('.entry-name')?.textContent.toLowerCase() || '';
            result.style.display = name.includes(search) ? '' : 'none';
        });
    });

});