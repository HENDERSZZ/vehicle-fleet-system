<?php
// ---- includes/scripts.php ----
?>
<!-- jQuery (must come before Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;
    const desktopBtn = document.getElementById('toggleCollapseBtn');
    const desktopMQ = window.matchMedia('(min-width: 992px)');

    // Exit early if toggle button or body is missing
    if (!desktopBtn || !body) return;

    /**
     * Apply saved sidebar state (desktop only)
     */
    function applySavedState() {
      if (!desktopMQ.matches) return; // mobile: do nothing
      if (!body || !desktopBtn) return; // safety check

      const savedState = localStorage.getItem('sidebarState');
      if (savedState === 'collapsed') {
        body.classList.add('sidebar-collapsed');
      } else {
        body.classList.remove('sidebar-collapsed');
      }

      desktopBtn.setAttribute('aria-expanded', String(!body.classList.contains('sidebar-collapsed')));
    }

    /**
     * Toggle sidebar state (desktop only)
     */
    function toggleSidebar() {
      if (!desktopMQ.matches) return; // mobile: do nothing
      if (!body || !desktopBtn) return; // safety check

      body.classList.toggle('sidebar-collapsed');
      const isCollapsed = body.classList.contains('sidebar-collapsed');

      desktopBtn.setAttribute('aria-expanded', String(!isCollapsed));
      localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'open');
    }

    // Initialize on page load
    applySavedState();

    // Button click event
    desktopBtn.addEventListener('click', toggleSidebar);

    // Re-apply state if desktop breakpoint changes
    desktopMQ.addEventListener('change', applySavedState);
  });
</script>


<script>
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const modeToggle = document.getElementById('modeToggle');
    const icon = modeToggle?.querySelector('.mode-icon');
    const THEME_KEY = 'vmsMode';

    const applyMode = (mode) => {
      if (!mode || !icon) return;

      root.setAttribute('data-bs-theme', mode);
      localStorage.setItem(THEME_KEY, mode);

      icon.textContent = mode === 'dark' ? '☀️' : '🌙';
      modeToggle.setAttribute('aria-label', mode === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    };

    // Initialize
    const saved = localStorage.getItem(THEME_KEY);
    const initial = saved || root.getAttribute('data-bs-theme') || 'light';
    applyMode(initial);

    // Toggle on click
    modeToggle?.addEventListener('click', () => {
      const current = root.getAttribute('data-bs-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      applyMode(next);
    });
  });
</script>

<script>
  function updateLastActivity() {
    fetch('admin.php?page=users/ping', {
      method: 'POST',
      credentials: 'include'
    }).catch(err => console.error("Ping failed:", err));
  }

  function refreshUserStatus() {
    fetch('admin.php?page=users/get_user_status', {
      method: 'GET',
      credentials: 'include'
    })
      .then(response => {
        if (!response.ok) throw new Error("Bad response");
        return response.json();
      })
      .then(data => {
        Object.entries(data).forEach(([id, status]) => {
          const badge = document.getElementById(`status-badge-${id}`);
          if (!badge) return;

          const currentUserId = <?= json_encode($_SESSION['user']['id']) ?>;
          if (parseInt(id) === currentUserId) return; // Don't overwrite "You"

          const badgeHtml = status === 'online'
            ? '<span class="badge bg-success">Online</span>'
            : '<span class="badge bg-secondary">Offline</span>';

          badge.innerHTML = badgeHtml;
        });
      })
      .catch(err => {
        console.error("Error fetching user status:", err);
      });
  }

  let activityTimeout;
  function resetActivityTimer() {
    clearTimeout(activityTimeout);
    updateLastActivity();
    activityTimeout = setTimeout(updateLastActivity, 25000);
  }

  document.addEventListener("mousemove", resetActivityTimer);
  document.addEventListener("keypress", resetActivityTimer);

  setInterval(refreshUserStatus, 10000); // Every 10s
  refreshUserStatus(); // First load
  resetActivityTimer(); // First ping
</script>
<script>
  function getFingerprint() {
    const data = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      Intl.DateTimeFormat().resolvedOptions().timeZone
    ];
    return sha256(data.join('|')); // You can use a SHA-256 library like crypto.subtle or an external lib
  }
</script>
<!----Fuel Type Toggle Script---->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.querySelector('[name="type"]');
    const fuelGroup = document.getElementById('fuelInputGroup');
    const stationGroup = document.getElementById('stationInputGroup');

    function toggleFuelFields() {
      const isFuel = typeSelect.value === 'fuel';
      fuelGroup.style.display = isFuel ? 'block' : 'none';
      stationGroup.style.display = isFuel ? 'block' : 'none';
    }

    if (typeSelect) {
      toggleFuelFields(); // On load
      typeSelect.addEventListener('change', toggleFuelFields);
    }
  });
</script>
<!-- Select2 Initialization -->
<script>
  $(document).ready(function () {
    // Region (required, no clear)
    if ($('select[name="region_id"]').length && !$('select[name="region_id"]').hasClass("select2-hidden-accessible")) {
      $('select[name="region_id"]').select2({
        placeholder: "Select a Region",
        allowClear: false,
        ajax: {
          url: 'admin.php?page=users/populate_locations',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term,
              type: 'regions'
            };
          },
          processResults: function (data) {
            return data; // assuming data is already { results: [...] }
          },
          cache: true
        },
        width: '100%'
      });
    }

    // County (optional, depends on region)
    if ($('#countySelect').length && !$('#countySelect').hasClass("select2-hidden-accessible")) {
      $('#countySelect').select2({
        placeholder: "Select a County",
        allowClear: true,
        ajax: {
          url: 'admin.php?page=users/populate_locations',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term,
              type: 'counties',
              parent_id: $('select[name="region_id"]').val()
            };
          },
          processResults: function (data) {
            return data;
          },
          cache: true
        },
        width: '100%'
      });
    }

    // Subcounty (optional, depends on county)
    if ($('#subcountySelect').length && !$('#subcountySelect').hasClass("select2-hidden-accessible")) {
      $('#subcountySelect').select2({
        placeholder: "Select a Subcounty",
        allowClear: true,
        ajax: {
          url: 'admin.php?page=users/populate_locations',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term,
              type: 'subcounties',
              parent_id: $('#countySelect').val()
            };
          },
          processResults: function (data) {
            return data;
          },
          cache: true
        },
        width: '100%'
      });
    }

    // Location (optional, depends on subcounty)
    if ($('#locationSelect').length && !$('#locationSelect').hasClass("select2-hidden-accessible")) {
      $('#locationSelect').select2({
        placeholder: "Select a Location",
        allowClear: true,
        ajax: {
          url: 'admin.php?page=users/populate_locations',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term,
              type: 'locations',
              parent_id: $('#subcountySelect').val()
            };
          },
          processResults: function (data) {
            return data;
          },
          cache: true
        },
        width: '100%'
      });
    }

    // Clear dependent selects when parent changes
    $('select[name="region_id"]').on('change', function () {
      $('#countySelect').val(null).trigger('change');
      $('#subcountySelect').val(null).trigger('change');
      $('#locationSelect').val(null).trigger('change');
    });

    $('#countySelect').on('change', function () {
      $('#subcountySelect').val(null).trigger('change');
      $('#locationSelect').val(null).trigger('change');
    });

    $('#subcountySelect').on('change', function () {
      $('#locationSelect').val(null).trigger('change');
    });
  });
  console.log("Region select initialized");
</script>

<!-- Vehicle Select Initialization -->
<script>$(document).ready(function () {
    // Helper: prefill select2 if editing
    function prefillSelect2(selector, id, text) {
      if (id && text) {
        const newOption = new Option(text, id, true, true);
        $(selector).append(newOption).trigger('change');
      }
    }

    // County (no parent)
    $('#vehicleCountySelect').select2({
      placeholder: 'Select County',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: params => ({
          q: params.term,
          type: 'counties'
        }),
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });

    // Subcounty (depends on county)
    $('#vehicleSubcountySelect').select2({
      placeholder: 'Select Subcounty',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            q: params.term,
            type: 'subcounties',
            parent_id: $('#vehicleCountySelect').val()  // evaluated at runtime
          };
        },
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });

    // Location (depends on subcounty)
    $('#vehicleLocationSelect').select2({
      placeholder: 'Select Location',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            q: params.term,
            type: 'locations',
            parent_id: $('#vehicleSubcountySelect').val()  // evaluated at runtime
          };
        },
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });


    // Category
    $('#vehicleCategorySelect').select2({
      placeholder: 'Select Category',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: params => ({
          q: params.term,
          type: 'categories'
        }),
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });

    // Status
    $('#vehicleStatusSelect').select2({
      placeholder: 'Select Status/Condition/Ownership',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: params => ({
          q: params.term,
          type: 'statuses'
        }),
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });

    // Designation (static list from PHP)
    $('#vehicleDesignationSelect').select2({
      placeholder: 'Select Designation',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=vehicles/populate_data',
        dataType: 'json',
        delay: 250,
        data: params => ({
          q: params.term,
          type: 'designations'
        }),
        processResults: data => data,
        cache: true
      },
      width: '100%'
    });

    // Reset children when parent changes
    $('#vehicleCountySelect').on('change', function () {
      $('#vehicleSubcountySelect').val(null).trigger('change');
      $('#vehicleLocationSelect').val(null).trigger('change');
    });

    $('#vehicleSubcountySelect').on('change', function () {
      $('#vehicleLocationSelect').val(null).trigger('change');
    });

    // Prefill in edit mode (replace these values dynamically in PHP)
    prefillSelect2('#vehicleCountySelect', '<?= $vehicle["county_id"] ?? "" ?>', '<?= $vehicle["county_name"] ?? "" ?>');
    prefillSelect2('#vehicleSubcountySelect', '<?= $vehicle["subcounty_id"] ?? "" ?>', '<?= $vehicle["subcounty_name"] ?? "" ?>');
    prefillSelect2('#vehicleLocationSelect', '<?= $vehicle["location_id"] ?? "" ?>', '<?= $vehicle["location_name"] ?? "" ?>');
    prefillSelect2('#vehicleCategorySelect', '<?= $vehicle["category_id"] ?? "" ?>', '<?= $vehicle["category_name"] ?? "" ?>');
    prefillSelect2('#vehicleStatusSelect', '<?= $vehicle["status_id"] ?? "" ?>', '<?= $vehicle["status_label"] ?? "" ?>');
    prefillSelect2('#vehicleDesignationSelect', '<?= $vehicle["designation"] ?? "" ?>', '<?= $vehicle["designation"] ?? "" ?>');

  });
</script>
<!---Requisition Regno Initialize--->
<script>
  $(document).ready(function () {
    $('#vehicleSelect').select2({
      placeholder: 'Select Vehicle',
      allowClear: true,
      ajax: {
        url: 'admin.php?page=requisitions/search_regno',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            q: params.term,
            type: 'vehicles'
          };
        },
        processResults: function (data) {
          return data;
        },
        cache: true
      },
      width: '100%'
    });
  });
</script>
<!---Requisition Select2 Initialization Maintenance/Refuel--->
<script>
  $('#refuelSelect').select2({
    placeholder: 'Select Vehicle',
    allowClear: true,
    ajax: {
      url: 'admin.php?page=refuel/search_regno',
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term }),
      processResults: data => data,
      cache: true
    },
    width: '100%'
  });
</script>
<script>
  $('#maintenanceSelect').select2({
    placeholder: 'Select Vehicle',
    allowClear: true,
    ajax: {
      url: 'admin.php?page=maintenance/search_regno',
      dataType: 'json',
      delay: 250,
      data: params => ({
        q: params.term
      }),
      processResults: data => data,
      cache: true
    },
    width: '100%'
  });
</script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    function updateTableColumns() {
      const isMobile = window.innerWidth <= 768;

      document.querySelectorAll("table[data-table-type]").forEach(table => {
        const type = table.getAttribute("data-table-type");

        // Define which columns to show for each table type
        const visibleColsMap = {
          "requisitions-table": [0, 1, 2, 4, 5, 8],
          "maintenance-table": [0, 1, 2, 8], // Registration, Type, Description, Actions
          "refuel-table": [0, 2, 3, 9],         // Registration, Station, Actions
          "users-table": [0, 1, 7, 8],       // Name, Current/Online, Status, Actions
          "vehicles-table": [0, 1, 2, 9],    // Registration, Model, Designation, Actions
          "fleet-table": [0, 1, 2, 5]        // Registration, Model, Designation, Status
        };

        const visibleCols = visibleColsMap[type] || [];

        table.querySelectorAll("tr").forEach(row => {
          row.querySelectorAll("th, td").forEach((cell, index) => {
            if (isMobile) {
              cell.style.display = visibleCols.includes(index) ? "" : "none";
            } else {
              cell.style.display = "";
            }
          });
        });
      });
    }

    updateTableColumns();
    window.addEventListener("resize", updateTableColumns);
  });
</script>