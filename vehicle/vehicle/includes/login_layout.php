<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle ?? 'Login - VMS') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

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

  <style>
    html,
    body {
      height: 100%;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bs-body-bg);
    }

    .form-signin {
      max-width: 330px;
      padding: 1rem;
    }

    .form-signin .form-floating:focus-within {
      z-index: 2;
    }

    .form-signin input[type="text"],
    .form-signin input[type="email"] {
      margin-bottom: -1px;
      border-bottom-right-radius: 0;
      border-bottom-left-radius: 0;
    }

    .form-signin input[type="password"] {
      margin-bottom: 10px;
      border-top-left-radius: 0;
      border-top-right-radius: 0;
    }

    .bd-mode-toggle {
      z-index: 1500;
    }
  </style>
</head>

<body class="bg-body-tertiary">

  <!-- Theme Toggle -->
  <button id="modeToggle" class="btn btn-sm btn-outline-light position-fixed bottom-0 end-0 mb-3 me-3"
    title="Toggle Dark Mode" aria-label="Switch theme">
    <span class="mode-icon" aria-hidden="true">🌙</span>
  </button>

  <!-- Login Form -->
  <main class="form-signin w-100 m-auto">
    <form method="POST" novalidate>
      <h1 class="h3 mb-3 fw-normal text-center">
        <?= htmlspecialchars($formHeading ?? '🔐 Please sign in') ?>
      </h1>

      <?php if (!empty($message)): ?>
        <div class="alert alert-danger text-center py-2"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <div class="form-floating">
        <input type="text" name="username" id="floatingUsername" class="form-control" placeholder="Username" required autofocus>
        <label for="floatingUsername">Username</label>
      </div>

      <div class="form-floating">
        <input type="password" name="password" id="floatingPassword" class="form-control" placeholder="Password" required>
        <label for="floatingPassword">Password</label>
      </div>

      <div class="form-check text-start my-3">
        <input class="form-check-input" type="checkbox" value="remember-me" id="rememberMe">
        <label class="form-check-label" for="rememberMe">Remember me</label>
      </div>

      <button class="btn btn-primary w-100 py-2" type="submit">
        <?= htmlspecialchars($buttonText ?? 'Sign in') ?>
      </button>
      <p class="mt-5 mb-3 text-body-secondary text-center">&copy; VMS 2025</p>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://getbootstrap.com/docs/5.3/examples/assets/js/color-modes.js"></script>

  <!--Mode Toggle-->
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
        modeToggle.setAttribute('aria-label', mode === 'dark'
          ? 'Switch to light mode'
          : 'Switch to dark mode');
      };

      const saved = localStorage.getItem(THEME_KEY);
      const initial = saved || root.getAttribute('data-bs-theme') || 'light';
      applyMode(initial);

      modeToggle?.addEventListener('click', () => {
        const current = root.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        applyMode(next);
      });
    });
  </script>
</body>
</html>
