<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
if (($_SESSION['user_role'] ?? '') !== 'user') {
    redirect('dashboard/');
}
redirect('profile.php');

$userId = $_SESSION['user_id'];
$error = '';
$success = '';
$stmtUser = $pdo->prepare('SELECT nama, username, created_at FROM users WHERE id = :id LIMIT 1');
$stmtUser->execute(['id' => $userId]);
$user = $stmtUser->fetch();
if (!$user) {
    redirect('auth/logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? '';
        if ($action === 'profile') {
            $nama = validateText($_POST['nama'] ?? '', 100, 'Nama');
            $stmtUpdate = $pdo->prepare('UPDATE users SET nama = :nama, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtUpdate->execute(['nama' => $nama, 'id' => $userId]);
            $_SESSION['user_nama'] = $nama;
            $success = 'Nama akun berhasil diperbarui.';
            $user['nama'] = $nama;
        } elseif ($action === 'password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmation = $_POST['password_confirmation'] ?? '';
            $stmtHash = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $stmtHash->execute(['id' => $userId]);
            if (!password_verify($currentPassword, (string)$stmtHash->fetchColumn())) {
                throw new InvalidArgumentException('Password saat ini tidak benar.');
            }
            if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                throw new InvalidArgumentException('Password baru minimal 8 karakter dan harus berisi huruf serta angka.');
            }
            if (!hash_equals($newPassword, $confirmation)) {
                throw new InvalidArgumentException('Konfirmasi password baru tidak sama.');
            }
            $stmtPassword = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtPassword->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
            $success = 'Password berhasil direset.';
        } else {
            throw new InvalidArgumentException('Aksi pengaturan tidak valid.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Pengaturan Akun';
$activeNav = 'pengaturan-user';
require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6"><h2 class="text-xl font-bold text-slate-900">Pengaturan Akun</h2><p class="text-sm text-slate-500 mt-1">Kelola nama akun dan keamanan password Anda.</p></div>
    <?php if ($error): ?><div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-700"><?= e($success) ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Identitas Akun</h3>
        <form method="POST" class="mt-5 space-y-4"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="profile">
            <div><label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama</label><input id="nama" name="nama" maxlength="100" required value="<?= e($user['nama']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label><p class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600"><?= e($user['username']) ?></p></div>
            <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Simpan Nama</button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm mt-6">
        <h3 class="text-base font-bold text-slate-900">Reset Password</h3>
        <p class="text-sm text-slate-500 mt-1">Masukkan password saat ini sebelum membuat password baru.</p>
        <form method="POST" class="mt-5 space-y-4" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="password">
            <div><label for="current_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password saat ini</label><input id="current_password" name="current_password" type="password" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div class="grid sm:grid-cols-2 gap-4"><div><label for="new_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password baru</label><input id="new_password" name="new_password" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1.5">Ulangi password baru</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div></div>
            <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-lg">Simpan Password Baru</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
