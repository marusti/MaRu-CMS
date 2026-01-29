class CMS_Editor {
  constructor(id){
    this.el = document.getElementById(id);
    this.history = [];
    this.future = [];
    this.previewFrame = null;
    this.saveHistory();
  }

  saveHistory(){
    this.history.push(this.el.value);
    if(this.history.length > 100) this.history.shift();
  }

  undo(){
    if(this.history.length > 1){
      this.future.push(this.history.pop());
      this.el.value = this.history[this.history.length-1];
      this.updatePreview();
    }
  }

  redo(){
    if(this.future.length){
      const v = this.future.pop();
      this.history.push(v);
      this.el.value = v;
      this.updatePreview();
    }
  }

  getSelection(){
    return {
      start: this.el.selectionStart,
      end: this.el.selectionEnd,
      value: this.el.value
    };
  }

  replace(start,end,text){
    const v = this.el.value;
    this.el.value = v.substring(0,start) + text + v.substring(end);
    this.el.focus();
    this.el.selectionStart = start;
    this.el.selectionEnd = start + text.length;
    this.saveHistory();
    this.updatePreview();
  }

  // Haupt-Wrap-Methode für [key|Text]
  // Haupt-Wrap-Methode für [key|Text]
wrap(key, inner = '') {
  const s = this.getSelection();  // Auswahl des Textes im Editor

  // Einfügen des Markups
  const formattedText = `[${key}|${inner}]`;
  this.replace(s.start, s.end, formattedText);  // Ersetze den markierten Text oder füge das Markup ein
}



  linePrefix(prefix){
    const s = this.getSelection();
    let sel = s.value.substring(s.start,s.end);

    if(!sel){
      const value = s.value;
      const start = value.lastIndexOf('\n', s.start - 1) + 1;
      const end = value.indexOf('\n', s.start);
      const lineEnd = end === -1 ? value.length : end;
      sel = value.substring(start, lineEnd);
      const newLine = sel.startsWith(prefix) ? sel : prefix + sel;
      this.replace(start, lineEnd, newLine);
      return;
    }

    const lines = sel.split('\n').map(l => l.startsWith(prefix) ? l : prefix + l).join('\n');
    this.replace(s.start,s.end, lines);
  }

  link(url){
    const s = this.getSelection();
    const sel = s.value.substring(s.start,s.end) || 'Link';
    this.replace(s.start,s.end, `[link|${url}|${sel}]`);
  }

  image(url, alt=''){
    const s = this.getSelection();
    this.replace(s.start,s.end, `[image|${url}|${alt}]`);
  }

  codeblock(){
    const s = this.getSelection();
    const sel = s.value.substring(s.start,s.end) || '';
    this.replace(s.start,s.end, `[codeblock|${sel}]`);
  }

  quote(){
    const s = this.getSelection();
    const sel = s.value.substring(s.start,s.end) || '';
    this.replace(s.start,s.end, `[quote|${sel}]`);
  }

  getMarkdown(){
    return this.el.value;
  }

  enableLivePreview(frameId){
    this.previewFrame = document.getElementById(frameId);
    this.el.addEventListener('input', ()=>{
      this.updatePreview();
    });
    this.updatePreview();
  }

  updatePreview(){
    if(!this.previewFrame) return;
    this.previewFrame.srcdoc = this.convertMarkdown(this.el.value);
  }

  convertMarkdown(md){
  let html = md
    // Headings
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
    .replace(/\[listunordered\|([\s\S]*?)\]/g, (m,p) => {
      const items = p.split('\n').filter(l=>l.trim()).map(l=>`<li>${l.replace(/^\- /,'')}</li>`).join('');
      return `<ul class="listunordered">${items}</ul>`;
    })
    .replace(/\[listordered\|([\s\S]*?)\]/g, (m,p) => {
      const items = p.split('\n').filter(l=>l.trim()).map(l=>`<li>${l.replace(/^\d+\. /,'')}</li>`).join('');
      return `<ol class="listordered">${items}</ol>`;
    })

    // Links
    .replace(/\[link\|(.*?)\|(.*?)\]/g, '<a href="$1">$2</a>')

    // Bilder
    .replace(/\[image\|(.*?)\|(.*?)\]/g, '<img src="$1" alt="$2"/>')

    // Neue div- und p-Tags
    .replace(/\[div\|(.*?)\]/g, '<div>$1</div>')
    .replace(/\[p\|(.*?)\]/g, '<p>$1</p>');

  html = html.replace(/\n/g,'<br>');
  return html;
}


  // Plugins / Gallery / Page Links
  plugin(id){ this.replace(this.el.selectionStart,this.el.selectionEnd, `[plugin:${id}]`); }
  gallery(id){ this.replace(this.el.selectionStart,this.el.selectionEnd, `[gallery:${id}]`); }
  pageLink(url,text){ this.replace(this.el.selectionStart,this.el.selectionEnd, `[${text}](${url})`); }
}

/* Toolbar Binding */
document.addEventListener('DOMContentLoaded', ()=>{
  const editorEl = document.getElementById('editor');
  if(!editorEl) return;

  const ED = new CMS_Editor('editor');
  ED.enableLivePreview('livePreviewFrame');
  window.ED = ED;

  document.querySelectorAll('#toolbar [data-cmd]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const c = btn.dataset.cmd;
      switch(c){
        case 'bold': ED.wrap('bold'); break;
        case 'italic': ED.wrap('italic'); break;
        case 'underline': ED.wrap('underline'); break;

        case 'h1': ED.wrap('h1'); break;
        case 'h2': ED.wrap('h2'); break;
        case 'h3': ED.wrap('h3'); break;
        case 'h4': ED.wrap('h4'); break;
        case 'h5': ED.wrap('h5'); break;
        case 'h6': ED.wrap('h6'); break;

        case 'ul': ED.wrap('listunordered'); break;
        case 'ol': ED.wrap('listordered'); break;
        case 'quote': ED.wrap('quote'); break;
        case 'code': ED.wrap('codeblock'); break;

        case 'link': {
  // Direkt das Markdown im wrap-Format einfügen, ohne const
  ED.wrap('link|');
  break;
}




        case 'image': {
          const url = prompt('Image URL:');
          if(url){
            const alt = prompt('Alt Text:') || '';
            ED.wrap(`image|${url}`, alt);
          }
          break;
        }

        case 'undo': ED.undo(); break;
        case 'redo': ED.redo(); break;
      }
    });
  });
});
