document.addEventListener('DOMContentLoaded', function () {
  // Sidebar toggle
    const toggle = document.getElementById('toggleSidebar');

  if (toggle) {
    toggle.addEventListener('click', () => {

      document.documentElement.classList.toggle('sidebar-collapsed');

      localStorage.setItem(
        'sidebar-collapsed',
        document.documentElement.classList.contains('sidebar-collapsed')
      );

    });
  }
   
  // Theme select
  const themeSelect = document.getElementById('theme-select');
  const root = document.documentElement;

  function applyTheme(theme) {
    root.classList.remove('dark-mode', 'glass-mode');
    if (theme !== 'light') root.classList.add(theme);
    localStorage.setItem('theme', theme);
    if (themeSelect) themeSelect.value = theme;
  }

  if (themeSelect) {
    const saved = localStorage.getItem('theme') || 'light';
    applyTheme(saved);

    themeSelect.addEventListener('change', () => {
      applyTheme(themeSelect.value);
    });
  }
});