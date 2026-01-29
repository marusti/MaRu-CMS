// Markdown zu HTML Konvertierungsfunktion
function convertMarkdown(md) {
    let html = md
        // Überschriften
        .replace(/\[h1\|(.*?)\]/g, '<h1 class="heading1">$1</h1>')
        .replace(/\[h2\|(.*?)\]/g, '<h2 class="heading2">$1</h2>')
        .replace(/\[h3\|(.*?)\]/g, '<h3 class="heading3">$1</h3>')
        .replace(/\[h4\|(.*?)\]/g, '<h4 class="heading4">$1</h4>')
        .replace(/\[h5\|(.*?)\]/g, '<h5 class="heading5">$1</h5>')
        .replace(/\[h6\|(.*?)\]/g, '<h6 class="heading6">$1</h6>')

        // Textformatierungen
        .replace(/\[bold\|(.*?)\]/g, '<b>$1</b>')
        .replace(/\[italic\|(.*?)\]/g, '<i>$1</i>')
        .replace(/\[underline\|(.*?)\]/g, '<u>$1</u>')

        // Blockquote
        .replace(/\[quote\|(.*?)\]/g, '<blockquote>$1</blockquote>')

        // Codeblock
        .replace(/\[codeblock\|(.*?)\]/gs, '<pre><code>$1</code></pre>')

        // Listen
        .replace(/\[listunordered\|([\s\S]*?)\]/g, (m, p) => {
            const items = p.split('\n').filter(l => l.trim()).map(l => `<li>${l.replace(/^\- /, '')}</li>`).join('');
            return `<ul class="listunordered">${items}</ul>`;
        })
        .replace(/\[listordered\|([\s\S]*?)\]/g, (m, p) => {
            const items = p.split('\n').filter(l => l.trim()).map(l => `<li>${l.replace(/^\d+\. /, '')}</li>`).join('');
            return `<ol class="listordered">${items}</ol>`;
        })

        // Links
        .replace(/\[link\|(.*?)\|(.*?)\]/g, '<a href="$1">$2</a>')

        // Bilder
        .replace(/\[image\|(.*?)\|(.*?)\]/g, '<img src="$1" alt="$2"/>');

    html = html.replace(/\n/g, '<br>');
    return html;
}

// Exportiere die Funktion, um sie in anderen Dateien zu verwenden
if (typeof module !== "undefined" && module.exports) {
    module.exports = { convertMarkdown };
}

