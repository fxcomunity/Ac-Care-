<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
requireLogin();

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT nama, username, no_hp, role, created_at FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    redirect('auth/logout.php');
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? 'profile';
        if ($action === 'profile') {
            $nama = validateText($_POST['nama'] ?? '', 100, 'Nama');
            $username = strtolower(validateText($_POST['username'] ?? '', 50, 'Username'));
            $noHp = trim($_POST['no_hp'] ?? '');
            if (!preg_match('/^[a-z0-9._-]{4,50}$/', $username)) {
                throw new InvalidArgumentException('Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.');
            }
            if ($noHp !== '') {
                $noHp = validatePhone($noHp);
            }
            $stmtUpdate = $pdo->prepare('UPDATE users SET nama = :nama, username = :username, no_hp = :no_hp, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            try {
                $stmtUpdate->execute(['nama' => $nama, 'username' => $username, 'no_hp' => $noHp ?: null, 'id' => $userId]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23505') {
                    throw new InvalidArgumentException('Username sudah digunakan oleh akun lain.');
                }
                throw $exception;
            }
            $_SESSION['user_nama'] = $nama;
            $_SESSION['user_username'] = $username;
            $user['nama'] = $nama;
            $user['username'] = $username;
            $user['no_hp'] = $noHp;
            auditLog('profile_updated', (string)$userId, 'Profil akun diperbarui.');
            $success = 'Profil berhasil diperbarui.';
        } elseif ($action === 'password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $passwordConfirmation = $_POST['password_confirmation'] ?? '';
            $stmtPassword = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
            $stmtPassword->execute(['id' => $userId]);
            $passwordHash = $stmtPassword->fetchColumn();
            if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
                throw new InvalidArgumentException('Password saat ini tidak benar.');
            }
            if (strlen($newPassword) < 8 || !preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                throw new InvalidArgumentException('Password baru minimal 8 karakter dan harus berisi huruf serta angka.');
            }
            if (!hash_equals($newPassword, $passwordConfirmation)) {
                throw new InvalidArgumentException('Konfirmasi password baru tidak sama.');
            }
            $stmtUpdatePassword = $pdo->prepare('UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmtUpdatePassword->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]);
            auditLog('password_changed', (string)$userId, 'Password akun diubah oleh pemilik akun.');
            $success = 'Password berhasil diperbarui.';
        } else {
            throw new InvalidArgumentException('Aksi pengaturan tidak valid.');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = 'Pengaturan akun gagal disimpan.';
    }
}

$pageTitle = 'Pengaturan';
$activeNav = 'pengaturan';
require_once __DIR__ . '/includes/layout_top.php';
?>
<div class="max-w-3xl mx-auto">
    <div class="mb-6"><h2 class="text-xl font-bold text-slate-900">Pengaturan</h2><p class="text-sm text-slate-500 mt-1">Kelola profil dan keamanan akun Anda.</p></div>
    <?php if ($error): ?><div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-700"><?= e($success) ?></div><?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <div class="flex items-center gap-4 pb-6 border-b border-slate-100"><div class="w-14 h-14 rounded-xl bg-blue-600 text-white flex items-center justify-center text-xl font-bold"><?= e(strtoupper(substr($user['nama'], 0, 2))) ?></div><div><h3 class="text-lg font-bold text-slate-900"><?= e($user['nama']) ?></h3><p class="text-sm text-slate-500 capitalize"><?= e($user['role']) ?></p></div></div>
        <form method="POST" class="mt-6 space-y-4"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="profile">
            <div><label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama</label><input id="nama" name="nama" maxlength="100" required value="<?= e($user['nama']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label><input id="username" name="username" maxlength="50" pattern="[a-z0-9._-]{4,50}" required value="<?= e($user['username']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="no_hp" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor HP</label><input id="no_hp" name="no_hp" maxlength="25" value="<?= e($user['no_hp'] ?? '') ?>" placeholder="Contoh: 081234567890" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div class="grid sm:grid-cols-2 gap-4 text-sm"><div><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Role</span><span class="block mt-1 font-medium capitalize text-slate-800"><?= e($user['role']) ?></span></div><div><span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Tanggal bergabung</span><span class="block mt-1 font-medium text-slate-800"><?= formatTanggal($user['created_at']) ?></span></div></div>
            <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Simpan Profil</button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm mt-6">
        <h3 class="text-base font-bold text-slate-900">Keamanan</h3>
        <p class="text-sm text-slate-500 mt-1 mb-5">Gunakan password saat ini untuk membuat password baru.</p>
        <form method="POST" class="space-y-4" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="password">
            <div><label for="current_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password saat ini</label><input id="current_password" name="current_password" type="password" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label for="new_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password baru</label><input id="new_password" name="new_password" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <div><label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1.5">Ulangi password baru</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>
            <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Simpan Password Baru</button>
        </form>
    </div>

    <div class="grid sm:grid-cols-2 gap-4 mt-6">
        <?php if (isStaffRole()): ?><a href="<?= baseUrl('pengaturan/whatsapp.php') ?>" class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:border-blue-300"><span class="text-sm font-bold text-slate-900">Template WhatsApp</span><span class="block mt-1 text-xs text-slate-500">Atur pesan pengingat servis.</span></a><?php endif; ?>
        <?php if (isOwner()): ?><a href="<?= baseUrl('akun/') ?>" class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:border-blue-300"><span class="text-sm font-bold text-slate-900">Kelola Akun</span><span class="block mt-1 text-xs text-slate-500">Atur pengguna dan role sistem.</span></a><a href="<?= baseUrl('pengaturan/website.php') ?>" class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:border-blue-300"><span class="text-sm font-bold text-slate-900">Website</span><span class="block mt-1 text-xs text-slate-500">Atur branding AFe Teknik.</span></a><?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>