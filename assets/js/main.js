(() => {
  const root = document.documentElement;
  
  // Siempre forzar tema claro
  function applyTheme() {
    root.setAttribute('data-theme', 'light');
    localStorage.setItem('pos-theme', 'light');
  }

  applyTheme();
})();
