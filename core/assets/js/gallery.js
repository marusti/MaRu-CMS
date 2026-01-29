document.addEventListener('DOMContentLoaded', function () {
    const listContainer = document.getElementById('gallery-list');
    const imagesContainer = document.getElementById('gallery-container');

    if (!listContainer || !imagesContainer) return;

    // settings.json laden
    fetch('/dev/chatgp/flatfile/config/settings.json')
        .then(res => res.json())
        .then(config => {
            const baseUrl = config.base_url.replace(/\/$/, '');
            console.log('Base URL aus settings.json:', baseUrl);

            // Galerien laden
            fetch(`${baseUrl}/admin/get_galleries.php`)
                .then(res => res.json())
                .then(galleries => {
                    console.log('Geladene Galerien:', galleries);

                    galleries.forEach(gallery => {
                        const btn = document.createElement('button');
                        btn.textContent = gallery.name;
                        btn.className = 'gallery-button';
                        btn.style.margin = '5px';
                        btn.style.padding = '6px 10px';
                        btn.style.cursor = 'pointer';

                        btn.addEventListener('click', () => {
                            const requestUrl = `${baseUrl}/admin/get_gallery_images.php?gallery=${encodeURIComponent(gallery.id || gallery.name)}`;
                            console.log('Lade Bilder von:', requestUrl);

                            imagesContainer.innerHTML = '<p>Wird geladen...</p>';

                            fetch(requestUrl)
                                .then(res => res.json())
                                .then(data => {
                                    imagesContainer.innerHTML = '';

                                    if (data.error) {
                                        imagesContainer.innerHTML = `<p><em>${data.error}</em></p>`;
                                        return;
                                    }

                                    if (!data.images || data.images.length === 0) {
                                        imagesContainer.innerHTML = '<p><em>Keine Bilder in dieser Galerie.</em></p>';
                                        return;
                                    }

                                    data.images.forEach(src => {
                                        const img = document.createElement('img');
                                        img.src = src;
                                        img.alt = gallery.name;
                                        img.style.maxWidth = '150px';
                                        img.style.margin = '5px';
                                        img.style.borderRadius = '8px';
                                        img.style.boxShadow = '0 0 5px rgba(0,0,0,0.2)';
                                        imagesContainer.appendChild(img);
                                    });
                                })
                                .catch(err => {
                                    imagesContainer.innerHTML = '<p><em>Fehler beim Laden der Bilder.</em></p>';
                                    console.error(err);
                                });
                        });

                        listContainer.appendChild(btn);
                    });
                });
        })
        .catch(err => {
            listContainer.innerHTML = '<p style="color:red;">Fehler beim Laden der Galerie-Daten.</p>';
            console.error('Fehler beim Laden:', err);
        });

    // Lightbox
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');

    document.getElementById('gallery-container').addEventListener('click', e => {
        if (e.target.tagName === 'IMG') {
            lightboxImg.src = e.target.src;
            lightbox.style.display = 'flex';
        }
    });

    lightbox.addEventListener('click', () => {
        lightbox.style.display = 'none';
        lightboxImg.src = '';
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && lightbox.style.display === 'flex') {
            lightbox.style.display = 'none';
            lightboxImg.src = '';
        }
    });
});
