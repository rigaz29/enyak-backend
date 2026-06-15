// Enyak Admin — minimal JS. Alpine drives the shell; Lucide renders icons.

// Alpine component for the app shell (sidebar drawer + dark mode).
function adminShell() {
  return {
    sidebarOpen: false,
    dark: (localStorage.getItem('enyak_theme') || 'dark') === 'dark',
    init() {
      this.apply();
      this.$watch('dark', () => this.apply());
    },
    apply() {
      document.documentElement.setAttribute('data-theme', this.dark ? 'dark' : 'light');
      localStorage.setItem('enyak_theme', this.dark ? 'dark' : 'light');
    },
    toggleDark() { this.dark = !this.dark; },
  };
}
window.adminShell = adminShell;

// Render Lucide icons + auto-dismiss toasts once the DOM is ready.
document.addEventListener('DOMContentLoaded', function () {
  if (window.lucide) window.lucide.createIcons();
  document.querySelectorAll('.toast').forEach(function (t) {
    setTimeout(function () {
      t.style.transition = 'opacity .3s';
      t.style.opacity = '0';
      setTimeout(function () { t.remove(); }, 320);
    }, 3500);
  });
});
