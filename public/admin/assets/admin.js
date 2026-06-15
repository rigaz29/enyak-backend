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

  // Live filter forms (no Filter button): debounce text inputs, submit selects/date instantly.
  document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
    var timer;
    form.querySelectorAll('input[type="search"], input[type="text"]').forEach(function (inp) {
      inp.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 400);
      });
    });
    form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
      el.addEventListener('change', function () { form.submit(); });
    });
  });

  // After a reload, put the caret back at the end of the focused search box.
  var af = document.querySelector('input[data-autofocus]');
  if (af) { var v = af.value; af.focus(); af.value = ''; af.value = v; }

  // Instant client-side row filter: <input data-filter="#tableId">.
  document.querySelectorAll('input[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var table = document.querySelector(inp.getAttribute('data-filter'));
      if (!table) return;
      var q = inp.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = (q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
      });
    });
  });
});
