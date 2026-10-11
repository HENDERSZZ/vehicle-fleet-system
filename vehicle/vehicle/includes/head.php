<?php
// ---- includes/head.php ----
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  /* -----------------------------------------------
   1. Layout & Navbar
----------------------------------------------- */
  main#mainContent {
    flex: 1 1 auto;
    top: 56px;
    min-height: 100vh;
    overflow-x: hidden;
    padding-top: 56px;
    margin-left: 0 !important;
    transition: margin-left 0.3s ease;
  }

  .navbar.fixed-top {
    z-index: 1050;
  }

  /* -----------------------------------------------
   2. Sidebar Styles (Desktop Only)
----------------------------------------------- */
  #vmsSidebar {
    position: fixed;
    top: 56px;
    left: 0;
    width: 250px;
    height: calc(100vh - 56px);
    background-color: #212529;
    color: #fff;
    transform: translateX(0);
    /* Always visible on desktop unless collapsed */
    transition: transform 0.3s ease;
    z-index: 1040;
    overflow-y: auto;
    padding-top: 5px !important;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  #vmsSidebar::-webkit-scrollbar {
    display: none;
  }

  body.sidebar-collapsed #vmsSidebar {
    transform: translateX(-250px);
    pointer-events: none;
    user-select: none;
    opacity: 0.6;
  }

  #vmsSidebar .nav-link {
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    transition: background-color 0.2s ease;
  }

  #vmsSidebar .nav-link:hover {
    background-color: #343a40;
  }

  #vmsSidebar .nav-link.active,
  #vmsSidebar .nav-link.fw-bold {
    background-color: #495057;
    font-weight: 600;
  }

  #vmsSidebar .nav-link i {
    width: 20px;
    text-align: center;
  }

  .btn-toggle-nav a {
    color: #adb5bd;
    padding: 0.25rem 1.5rem;
    display: block;
  }

  .btn-toggle-nav a:hover {
    color: #fff;
    background-color: #343a40;
  }

  /* -----------------------------------------------
   3. Desktop Layout
----------------------------------------------- */
  @media (min-width: 992px) {
    body:not(.sidebar-collapsed) main#mainContent {
      margin-left: 250px !important;
    }
  }

  /* Desktop only: hide links when sidebar is open */
  @media (min-width: 992px) {

    /* adjust breakpoint if needed */
    .show-on-collapsed {
      display: none;
    }

    body.sidebar-collapsed .show-on-collapsed {
      display: block;
    }
  }

  /* Mobile: always show links (or handle separately if needed) */
  @media (max-width: 991.98px) {
    .show-on-collapsed {
      display: block;
      /* visible by default on mobile */
    }
  }


  /* -----------------------------------------------
   4. Mobile View — Hide Sidebar, Use Off-Canvas
----------------------------------------------- */
  @media (max-width: 991.98px) {
    #vmsSidebar {
      display: none !important;
    }

    main#mainContent {
      padding-top: 70px !important;
      /* Extra breathing room under navbar */
    }
  }
</style>
<style>
  /* 🌙 Base dark mode */
  [data-bs-theme="dark"] {
    background-color: #252424ff;
    color: #f0f0f0;
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  [data-bs-theme="dark"] * {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
  }

  .mode-icon {
    display: inline-block;
    width: 1.2em;
    /* keep width constant so icon swap doesn't shift content */
    text-align: center;
  }

  /* 🧭 Navbar and sidebar */
  [data-bs-theme="dark"] .navbar,
  [data-bs-theme="dark"] .sidebar {
    background-color: #615a5aff;
    color: #f0f0f0;
  }

  /* 🧱 Cards and tables */
  [data-bs-theme="dark"] .card,
  [data-bs-theme="dark"] .table {
    background-color: #1a1a1a;
    color: #ddd;
    border-color: #333;
  }

  [data-bs-theme="dark"] .table th,
  [data-bs-theme="dark"] .table td {
    background-color: #1a1a1a;
    color: #ddd;
    border-color: #444;
  }

  /* 🏷️ Headings and labels */
  [data-bs-theme="dark"] h1,
  [data-bs-theme="dark"] h2,
  [data-bs-theme="dark"] h3,
  [data-bs-theme="dark"] h4,
  [data-bs-theme="dark"] h5,
  [data-bs-theme="dark"] h6,
  [data-bs-theme="dark"] label {
    color: #f0f0f0;
  }

  /* 📂 Dropdowns */
  [data-bs-theme="dark"] .dropdown-menu {
    background-color: #3c3333ff;
    border-color: #444;
    color: #f0f0f0;
  }

  [data-bs-theme="dark"] .dropdown-item {
    color: #f0f0f0;
    background-color: transparent;
  }

  [data-bs-theme="dark"] .dropdown-item:hover,
  [data-bs-theme="dark"] .dropdown-item:focus {
    background-color: #3a3a3a;
    color: #ffffff;
  }

  /* 🔽 Dropdown toggle arrow */
  [data-bs-theme="dark"] .dropdown-toggle::after {
    filter: brightness(0.8);
  }

  /* ✅ Native <select> dropdowns */
  [data-bs-theme="dark"] select,
  [data-bs-theme="dark"] select.form-control {
    background-color: #1e1e1e;
    color: #f0f0f0;
    border: 1px solid #444;
  }

  [data-bs-theme="dark"] select option {
    background-color: #2a2a2a;
    color: #f0f0f0;
  }

  /* 🌙 Select2 container */
  [data-bs-theme="dark"] .select2-container .select2-selection--single {
    background-color: #1e1e1e;
    border: 1px solid #444;
    color: #f0f0f0;
  }

  [data-bs-theme="dark"] .select2-container .select2-selection__arrow {
    background-color: #1e1e1e;
  }

  [data-bs-theme="dark"] .select2-container .select2-selection__rendered {
    color: #f0f0f0;
  }

  [data-bs-theme="dark"] .select2-container .select2-dropdown {
    background-color: #2a2a2a;
    border: 1px solid #444;
    color: #f0f0f0;
  }

  [data-bs-theme="dark"] .select2-results__option {
    background-color: #2a2a2a;
    color: #f0f0f0;
  }

  [data-bs-theme="dark"] .select2-results__option--highlighted {
    background-color: #3a3a3a;
    color: #ffffff;
  }

  /* 🧼 Buttons and inputs */
  [data-bs-theme="dark"] .btn,
  [data-bs-theme="dark"] .form-control {
    background-color: inherit;
    color: inherit;
    border-color: inherit;
  }

  [data-bs-theme="dark"] .btn:hover,
  [data-bs-theme="dark"] .btn:focus {
    background-color: #444;
    color: #fff;
    border-color: #666;
  }

  [data-bs-theme="dark"] .form-control:focus,
  [data-bs-theme="dark"] .form-select:focus {
    background-color: #2a2a2a;
    border-color: #888;
    color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
  }

  /* 📅 Date inputs */
  [data-bs-theme="dark"] input[type="date"] {
    background-color: #1e1e1e;
    color: #f0f0f0;
    border: 1px solid #444;
  }

  /* 📋 Alerts */
  [data-bs-theme="dark"] .alert {
    background-color: #2a2a2a;
    color: #f0f0f0;
    border-color: #444;
  }

  [data-bs-theme="dark"] .alert-info {
    background-color: #2e3b4e;
    color: #d0e6ff;
    border-color: #3c4f66;
  }

  /* 🏷️ Status badges */
  [data-bs-theme="dark"] .badge.bg-success {
    background-color: #28a745;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-primary {
    background-color: #007bff;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-secondary {
    background-color: #6c757d;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-warning {
    background-color: #f0ad4e;
    color: #000;
  }

  [data-bs-theme="dark"] .badge.bg-danger {
    background-color: #dc3545;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-dark {
    background-color: #343a40;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-primary-subtle {
    background-color: #375a7f;
    color: #fff;
  }

  [data-bs-theme="dark"] .badge.bg-danger-subtle {
    background-color: #a94442;
    color: #fff;
  }

  /* 📁 Receipt thumbnails */
  [data-bs-theme="dark"] a[target="_blank"] img {
    border: 1px solid #555;
    background-color: #1a1a1a;
    padding: 2px;
  }

  /* 🔢 Pagination */
  [data-bs-theme="dark"] .pagination .page-link {
    background-color: #1e1e1e;
    color: #f0f0f0;
    border-color: #444;
  }

  [data-bs-theme="dark"] .pagination .page-item.active .page-link {
    background-color: #444;
    border-color: #666;
    color: #fff;
  }

  [data-bs-theme="dark"] .pagination .page-link:hover {
    background-color: #3a3a3a;
    color: #fff;
  }

  /* 🖱️ Scrollbar (optional) */
  [data-bs-theme="dark"] ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  [data-bs-theme="dark"] ::-webkit-scrollbar-track {
    background: #1a1a1a;
  }

  [data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
    background-color: #444;
    border-radius: 4px;
  }

  /* 🔲 Topnav separation */
  [data-bs-theme="dark"] .navbar {
    border-bottom: 1px solid #444;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
  }

  /* 🔳 Sidebar separation */
  [data-bs-theme="dark"] .sidebar {
    border-right: 1px solid #444;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
  }

  /* 📦 Content container padding */
  [data-bs-theme="dark"] .container,
  [data-bs-theme="dark"] .container-fluid {
    padding-top: 1rem;
    padding-left: 1rem;
    padding-right: 1rem;
  }

  /* 🧱 Optional: content card separation */
  [data-bs-theme="dark"] .card {
    box-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
    border: 1px solid #333;
  }

  [data-bs-theme="dark"] .chart-container {
    background-color: #1a1a1a;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
  }
</style>