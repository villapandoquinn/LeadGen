    <footer class="app-footer">
      <span>&copy; 2026 Primovative Co. &bull; LeadFlow</span>
      <span>Lead Generation Platform Foundation</span>
    </footer>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  'use strict';
  const shell = document.querySelector('.app-shell');
  const toggle = document.querySelector('.desktop-sidebar-toggle');
  const sidebar = document.getElementById('mobileSidebar');
  if (!shell || !toggle || !sidebar) return;

  toggle.addEventListener('click', () => {
    shell.classList.toggle('sidebar-collapsed');
    const collapsed = shell.classList.contains('sidebar-collapsed');
    toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
  });

  const mediaQuery = window.matchMedia('(max-width: 991.98px)');
  const handleBreakpoint = event => {
    if (event.matches) {
      shell.classList.remove('sidebar-collapsed');
    }
  };
  mediaQuery.addEventListener('change', handleBreakpoint);
})();
</script>
</body>
</html>
