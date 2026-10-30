<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
sendSecurityHeaders();
$website = websiteSettings();
$websiteLogo = $website['site_logo'] !== '' ? websiteAssetUrl($website['site_logo']) : '';

// Jika sudah login, langsung ke dashboard
if (isLoggedIn()) {
    redirect(($_SESSION['user_role'] ?? '') === 'user' ? 'user/' : 'dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $attemptKey = loginRateLimitKey($username);
    $genericError = 'Username atau password tidak sesuai.';

    if (loginIsBlocked($attemptKey)) {
        $error = $genericError;
    } elseif (empty($username) || empty($password)) {
        $error = 'Harap masukkan username dan password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && !empty($user['aktif']) && password_verify($password, $user['password_hash'])) {
            clearLoginFailures($attemptKey);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            auditLog('login_success', (string)$user['id'], 'Login berhasil.');
            setFlash('success', 'Selamat datang kembali, ' . $user['nama']);
            redirect($user['role'] === 'user' ? 'user/' : ($user['role'] === 'developer' ? 'developer/' : 'dashboard/'));
        } else {
            recordLoginFailure($attemptKey);
            auditLog('login_failed', null, 'Percobaan login gagal.');
            $error = $genericError;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?= e($website['site_name']) ?></title>
    <?php if ($websiteLogo): ?><link rel="icon" href="<?= e($websiteLogo) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(baseUrl('assets/css/app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="afe-login-shell min-h-full text-slate-800">
    <main class="afe-login-layout w-full min-h-screen grid lg:grid-cols-[1fr_0.9fr]">
        <section class="afe-login-intro hidden lg:flex flex-col justify-between p-12 xl:p-20">
            <div>
                <div class="afe-logo">
                    <?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1"><?php else: ?><span class="afe-logo-mark">AF</span><?php endif; ?>
                    <span>
                        <strong class="afe-logo-name block text-lg text-slate-950"><?= e($website['site_name']) ?></strong>
                        <small class="block text-sm text-slate-500">Manajemen &amp; Servis</small>
                    </span>
                </div>
                <div class="mt-20 max-w-lg">
                    <p class="text-sm font-bold uppercase tracking-[0.2em] text-blue-600">AC Care</p>
                    <h1 class="mt-4 text-4xl xl:text-5xl font-bold leading-tight tracking-tight text-slate-950">Kelola Pelanggan, Jadwal &amp; Servis AC dengan Mudah.</h1>
                    <p class="mt-6 max-w-md text-base leading-7 text-slate-600">Satu tempat untuk memantau unit AC, mencatat riwayat servis, dan memastikan pengingat perawatan tidak terlewat.</p>
                </div>
            </div>
            <div class="afe-login-illustration" aria-hidden="true">
                <div class="afe-login-illustration-unit"><span></span><i></i><i></i><i></i></div>
                <div class="afe-login-illustration-air afe-login-illustration-air-one"></div>
                <div class="afe-login-illustration-air afe-login-illustration-air-two"></div>
            </div>
        </section>

        <section class="flex items-center justify-center p-6 sm:p-10">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center lg:hidden">
                    <?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1 mx-auto mb-3"><?php else: ?><div class="afe-logo-mark mx-auto mb-3">AF</div><?php endif; ?>
                    <p class="text-sm font-semibold text-slate-500">AC Care &middot; Manajemen &amp; Servis</p>
                </div>

        <div class="afe-login-card bg-white border border-slate-200 rounded-2xl p-7 sm:p-9 shadow-xl shadow-slate-200/60">
            <div class="mb-7">
                <p class="text-sm font-semibold text-blue-600">Selamat datang kembali</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Masuk ke AC Care</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan akun Anda untuk mengelola operasional servis AC.</p>
            </div>
            <?php if (!empty($error)): ?>
                <div class="mb-5 flex items-start gap-3 p-3.5 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium" role="alert">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12V15.75z" /></svg>
                    <span>
                    <?= e($error) ?>
                    </span>
                </div>
            <?php endif; ?>

            <form id="login-form" action="<?= baseUrl('auth/login.php') ?>" method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0" /></svg>
                        <input type="text" id="username" name="username" placeholder="Masukkan username" autocomplete="username" required
                               class="w-full pl-11 pr-3.5 py-3 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5"><label for="password" class="block text-sm font-semibold text-slate-700">Password</label></div>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21h-10.5a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 014.5 10.5z" /></svg>
                        <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required
                               class="w-full pl-11 pr-12 py-3 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition-all">
                        <button type="button" id="toggle-password" class="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 text-slate-400 hover:text-slate-700" aria-label="Tampilkan password" title="Tampilkan password">
                            <svg id="show-password-icon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6 9.75-6 9.75 6 9.75 6-3.5 6-9.75 6-9.75-6-9.75-6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 12a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                            <svg id="hide-password-icon" class="hidden w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2.25 2.25 0 003.23 3.23M9.88 4.24A10.8 10.8 0 0112 4c6.25 0 9.75 6 9.75 6a17.4 17.4 0 01-3.18 3.89M6.61 6.61C3.84 8.2 2.25 10 2.25 10s3.5 6 9.75 6c1.02 0 1.96-.16 2.81-.42" /></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="login-submit"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:bg-blue-400 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-xl shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                    <span id="login-submit-label">Masuk ke Sistem</span>
                    <span id="login-submit-loading" class="hidden items-center justify-center gap-2"><span class="afe-login-spinner"></span>Memproses...</span>
                </button>
            </form>

            <p class="mt-6 pt-5 border-t border-slate-100 text-center text-sm text-slate-500">Belum memiliki akun? <a href="<?= baseUrl('auth/register.php') ?>" class="font-semibold text-blue-600 hover:text-blue-700">Daftar sekarang</a></p>
            <p class="mt-5 text-center text-xs text-slate-400">Sistem Manajemen &amp; Pengingat Servis AC</p>
        </div>
            </div>
        </section>
    </main>
    <script>
        (function () {
            const password = document.getElementById('password');
            const togglePassword = document.getElementById('toggle-password');
            const showIcon = document.getElementById('show-password-icon');
            const hideIcon = document.getElementById('hide-password-icon');
            const form = document.getElementById('login-form');
            const submit = document.getElementById('login-submit');
            const submitLabel = document.getElementById('login-submit-label');
            const submitLoading = document.getElementById('login-submit-loading');

            togglePassword.addEventListener('click', function () {
                const visible = password.type === 'text';
                password.type = visible ? 'password' : 'text';
                showIcon.classList.toggle('hidden', !visible);
                hideIcon.classList.toggle('hidden', visible);
                togglePassword.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
                togglePassword.setAttribute('title', visible ? 'Tampilkan password' : 'Sembunyikan password');
            });

            form.addEventListener('submit', function () {
                if (!form.checkValidity()) return;
                submit.disabled = true;
                submitLabel.classList.add('hidden');
                submitLoading.classList.remove('hidden');
                submitLoading.classList.add('inline-flex');
            });
        }());
    </script>
</body>
</html>
