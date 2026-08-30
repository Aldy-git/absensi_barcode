<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Jika pengguna sudah login, redirect langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
if (!empty($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $error = 'Username dan password wajib diisi.';
  } else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $storedPassword = $user['password'] ?? '';
    $isValidPassword = false;

    // Validasi aturan password: min 8 karakter, huruf A-Z/a-z dan angka 0-9, tanpa simbol/spasi
    [$isPolicyValid, $policyError] = validatePasswordPolicy($password);
    if (!$isPolicyValid) {
      $error = $policyError;
    } else {
      if ($user) {
        $isValidPassword = password_verify($password, $storedPassword)
          || strcasecmp(md5($password), $storedPassword) === 0
          || $storedPassword === $password;
      }

      if ($user && $isValidPassword) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: dashboard.php');
        exit;
      } else {
        $error = 'Username atau password yang Anda masukkan salah.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Absensi Barcode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=2.1" rel="stylesheet">
  <style>
    body {
      background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0f172a 60%, #020617 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 12px;
    }

    .auth-container {
      width: 100%;
      max-width: 420px;
    }

    .auth-card-modern {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .auth-header {
      background: #0f1724;
      color: #ffffff;
      padding: 28px 24px 24px;
      text-align: center;
      position: relative;
    }

    .auth-header .logo-circle {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: #0ea5a0;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 22px;
      margin-bottom: 12px;
      box-shadow: 0 8px 16px rgba(14, 165, 160, 0.3);
    }

    .auth-body {
      padding: 28px 24px;
      background: #ffffff;
    }

    .form-floating-custom {
      position: relative;
      margin-bottom: 18px;
    }

    .form-floating-custom label {
      font-weight: 600;
      font-size: 13px;
      color: #334155;
      margin-bottom: 6px;
      display: block;
    }

    .form-floating-custom .input-group-text {
      background: #f8fafc;
      border-right: 0;
      color: #64748b;
    }

    .form-floating-custom .form-control {
      border-left: 0;
      font-size: 14px;
      padding: 10px 12px;
    }

    .form-floating-custom .form-control:focus {
      border-color: #dee2e6;
      box-shadow: none;
    }

    .form-floating-custom:focus-within .input-group {
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
      border-radius: 8px;
    }

    .form-floating-custom:focus-within .input-group-text,
    .form-floating-custom:focus-within .form-control {
      border-color: #2563eb;
    }

    .btn-login-submit {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      border: none;
      color: #ffffff;
      font-weight: 600;
      font-size: 15px;
      padding: 12px;
      border-radius: 8px;
      width: 100%;
      transition: all 0.2s ease;
      margin-top: 8px;
    }

    .btn-login-submit:hover {
      background: linear-gradient(135deg, #1d4ed8, #1e40af);
      transform: translateY(-1px);
      box-shadow: 0 8px 16px rgba(37, 99, 235, 0.25);
    }

    .btn-login-submit:active {
      transform: translateY(0);
    }

    .auth-footer {
      text-align: center;
      color: rgba(255, 255, 255, 0.5);
      font-size: 12px;
      margin-top: 18px;
    }
  </style>
</head>

<body>
  <div class="auth-container">
    <div class="auth-card-modern">
      <!-- Header Card -->
      <div class="auth-header">
        <div class="logo-circle">BC</div>
        <div style="font-weight: 700; font-size: 20px; letter-spacing: -0.3px">AbsensiBarcode</div>
        <div style="font-size: 13px; color: rgba(255, 255, 255, 0.65); margin-top: 2px">Sistem Absensi Sekolah Berbasis Barcode</div>
      </div>

      <!-- Body Card -->
      <div class="auth-body">
        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert" style="font-size: 13px; border-radius: 8px; margin-bottom: 18px">
            <div style="display: flex; align-items: center; gap: 8px">
              <span>⚠️</span>
              <div><?= htmlspecialchars($error) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 12px"></button>
          </div>
        <?php endif; ?>

        <form method="post" id="loginForm" autocomplete="off">
          <div class="form-floating-custom">
            <label for="usernameInput">Username</label>
            <div class="input-group">
              <span class="input-group-text">👤</span>
              <input type="text" name="username" id="usernameInput" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>
          </div>

          <div class="form-floating-custom">
            <label for="passwordInput">Password</label>
            <div class="input-group">
              <span class="input-group-text">🔒</span>
              <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Min. 8 karakter (huruf & angka)" required autocomplete="current-password">
              <button class="btn btn-outline-secondary" type="button" id="btnTogglePassword" style="border-color: #dee2e6; background: #f8fafc; font-size: 13px" title="Lihat Password">
                👁️
              </button>
            </div>
            <div id="passwordPolicyFeedback" style="font-size: 11.5px; margin-top: 6px; display: none; line-height: 1.3;"></div>
            <div class="form-text" style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
              🛡️ Minimal 8 karakter, gabungan huruf (A-Z/a-z) dan angka (0-9) tanpa simbol.
            </div>
          </div>

          <button type="submit" class="btn btn-login-submit">Masuk</button>
        </form>
      </div>
    </div>

    <div class="auth-footer">
      &copy; <?= date('Y') ?> AbsensiBarcode · All rights reserved.
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle Password Visibility
    const btnToggle = document.getElementById('btnTogglePassword');
    const pwdInput = document.getElementById('passwordInput');
    const feedback = document.getElementById('passwordPolicyFeedback');
    const form = document.getElementById('loginForm');

    if (btnToggle && pwdInput) {
      btnToggle.addEventListener('click', function() {
        if (pwdInput.type === 'password') {
          pwdInput.type = 'text';
          btnToggle.textContent = '🙈';
        } else {
          pwdInput.type = 'password';
          btnToggle.textContent = '👁️';
        }
      });
    }

    // Real-time Validation for Password Policy
    function validatePasswordInput(val) {
      if (!val) return { valid: false, msg: '' };
      if (/[^a-zA-Z0-9]/.test(val)) {
        return { valid: false, msg: '❌ Password tidak valid: Mengandung simbol atau spasi. Hanya huruf (A-Z, a-z) dan angka (0-9) yang diperbolehkan.' };
      }
      if (val.length < 8) {
        return { valid: false, msg: '⚠️ Panjang password saat ini ' + val.length + ' karakter (minimal 8 karakter).' };
      }
      if (!/[a-zA-Z]/.test(val) || !/[0-9]/.test(val)) {
        return { valid: false, msg: '⚠️ Password harus mengandung gabungan huruf (A-Z/a-z) dan angka (0-9).' };
      }
      return { valid: true, msg: '✅ Format password valid.' };
    }

    if (pwdInput && feedback) {
      pwdInput.addEventListener('input', function() {
        const val = this.value;
        if (!val) {
          feedback.style.display = 'none';
          return;
        }
        const res = validatePasswordInput(val);
        feedback.style.display = 'block';
        feedback.textContent = res.msg;
        feedback.style.color = res.valid ? '#16a34a' : (/[^a-zA-Z0-9]/.test(val) ? '#dc2626' : '#d97706');
      });
    }

    if (form && pwdInput) {
      form.addEventListener('submit', function(e) {
        const val = pwdInput.value;
        const res = validatePasswordInput(val);
        if (!res.valid) {
          e.preventDefault();
          feedback.style.display = 'block';
          feedback.textContent = res.msg;
          feedback.style.color = '#dc2626';
          pwdInput.focus();
        }
      });
    }
  </script>
</body>

</html>