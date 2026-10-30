<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
sendSecurityHeaders();
$website = websiteSettings();
$websiteLogo = $website['site_logo'] !== '' ? websiteAssetUrl($website['site_logo']) : '';

if (isLoggedIn()) {
    redirect('dashboard/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $nama = validateText($_POST['nama'] ?? '', 100, 'Nama');
        $username = strtolower(validateText($_POST['username'] ?? '', 50, 'Username'));
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        if (!preg_match('/^[a-z0-9._-]{4,50}$/', $username)) {
            throw new InvalidArgumentException('Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.');
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('Password minimal 8 karakter dan harus berisi huruf serta angka.');
        }
        if (!hash_equals($password, $passwordConfirmation)) {
            throw new InvalidArgumentException('Konfirmasi password tidak sama.');
        }

        $stmt = $pdo->prepare('INSERT INTO users (nama, username, password_hash, role) VALUES (:nama, :username, :password_hash, \'user\')');
        $stmt->execute([
            'nama' => $nama,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        setFlash('success', 'Akun berhasil dibuat. Silakan masuk.');
        redirect('auth/login.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = $e->getCode() === '23505' ? 'Username sudah digunakan.' : 'Registrasi gagal diproses.';
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - <?= e($website['site_name']) ?></title>
    <?php if ($websiteLogo): ?><link rel="icon" href="<?= e($websiteLogo) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(baseUrl('assets/css/app.css')) ?>">
</head>
<body class="h-full flex items-center justify-center p-6 text-slate-800">
    <main class="w-full max-w-md">
        <div class="text-center mb-8">
            <?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1 mx-auto mb-3"><?php else: ?><div class="afe-logo-mark mx-auto mb-3">AF</div><?php endif; ?>
            <h1 class="text-2xl font-bold text-slate-900">Buat akun pengguna</h1>
            <p class="text-sm text-slate-500 mt-1"><?= e($website['site_name']) ?></p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
            <?php if ($error): ?><div class="mb-5 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
            <form method="POST" class="space-y-5" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div><label for="nama" class="block text-sm font-semibold mb-1.5">Nama lengkap</label><input id="nama" name="nama" maxlength="100" required value="<?= e($_POST['nama'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <div><label for="username" class="block text-sm font-semibold mb-1.5">Username</label><input id="username" name="username" pattern="[a-z0-9._-]{4,50}" maxlength="50" required value="<?= e($_POST['username'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <div><label for="password" class="block text-sm font-semibold mb-1.5">Password</label><input id="password" name="password" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <div><label for="password_confirmation" class="block text-sm font-semibold mb-1.5">Ulangi password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <button class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Daftar</button>
            </form>
            <a href="<?= baseUrl('auth/login.php') ?>" class="block mt-5 text-center text-sm font-semibold text-blue-600">Kembali ke login</a>
        </div>
    </main>
</body>
</html>