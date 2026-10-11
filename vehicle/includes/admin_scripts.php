<!-- Flash-prevention: set theme early -->
<script>
  (function () {
    const savedMode = localStorage.getItem('vmsMode');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const mode = (savedMode === 'dark' || savedMode === 'light')
      ? savedMode
      : (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', mode);
  })();
</script>

<script>
  (function () {
    const body = document.body;
    const saved = localStorage.getItem('sidebarState');

    // Apply saved sidebar state safely
    if (saved === 'collapsed') {
      body.classList.add('sidebar-collapsed');
      body.classList.remove('sidebar-open');
    } else {
      body.classList.add('sidebar-open');
      body.classList.remove('sidebar-collapsed');
    }

    // Ensure transitions don’t run on first paint
    if (body.classList.contains('no-transition')) {
      window.requestAnimationFrame(() => {
        body.classList.remove('no-transition');
      });
    }

    // Optional: add a safety check if localStorage is unavailable
    // (for very old browsers or private mode)
  })();
</script>