<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['owner']);

$pageTitle = 'Kelola Akun';
$activeNav = 'akun';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? '';

        if ($action === 'create_teknisi') {
            $nama = validateText($_POST['nama'] ?? '', 100, 'Nama');
            $username = strtolower(validateText($_POST['username'] ?? '', 50, 'Username'));
            $noHp = trim($_POST['no_hp'] ?? '');
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
            if ($noHp !== '') {
                $noHp = validatePhone($noHp);
            }

            $stmtCreate = $pdo->prepare('INSERT INTO users (nama, username, no_hp, password_hash, role) VALUES (:nama, :username, :no_hp, :password_hash, \'teknisi\') RETURNING id');
            $stmtCreate->execute([
                'nama' => $nama,
                'username' => $username,
                'no_hp' => $noHp ?: null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            auditLog('account_created', (string)$stmtCreate->fetchColumn(), 'Akun teknisi dibuat oleh Owner.');
            setFlash('success', 'Akun teknisi berhasil dibuat.');
        } elseif ($action === 'promote') {
            $userId = trim($_POST['user_id'] ?? '');
            $stmtPromote = $pdo->prepare("UPDATE users SET role = 'teknisi', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND role = 'user'");
            $stmtPromote->execute(['id' => $userId]);
            auditLog('role_changed', $userId, 'Role akun diubah menjadi teknisi.');
            setFlash('success', $stmtPromote->rowCount() > 0 ? 'Akun user berhasil dinaikkan menjadi teknisi.' : 'Akun user tidak ditemukan atau sudah diproses.');
        } elseif ($action === 'promote_admin') {
            $userId = trim($_POST['user_id'] ?? '');
            $stmtPromoteAdmin = $pdo->prepare("UPDATE users SET role = 'admin', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id <> :current_id AND role IN ('user', 'teknisi')");
            $stmtPromoteAdmin->execute(['id' => $userId, 'current_id' => $_SESSION['user_id']]);
            auditLog('role_changed', $userId, 'Role akun diubah menjadi admin.');
            setFlash('success', $stmtPromoteAdmin->rowCount() > 0 ? 'Akun berhasil dipindahkan menjadi admin.' : 'Akun tidak ditemukan atau tidak dapat dipindahkan.');
        } elseif ($action === 'delete') {
            $userId = trim($_POST['user_id'] ?? '');
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = :id AND id <> :current_id AND role <> 'owner'");
            $stmtDelete->execute(['id' => $userId, 'current_id' => $_SESSION['user_id']]);
            auditLog('account_deleted', $userId, 'Akun non-owner dihapus oleh Owner.');
            setFlash('success', $stmtDelete->rowCount() > 0 ? 'Akun berhasil dihapus.' : 'Akun owner atau akun yang dipilih tidak dapat dihapus.');
        } elseif ($action === 'toggle_status') {
            $userId = trim($_POST['user_id'] ?? '');
            $stmtStatus = $pdo->prepare("UPDATE users SET aktif = NOT aktif, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND id <> :current_id AND role <> 'owner' RETURNING aktif");
            $stmtStatus->execute(['id' => $userId, 'current_id' => $_SESSION['user_id']]);
            $newStatus = $stmtStatus->fetchColumn();
            $isActive = in_array($newStatus, [true, 't', '1', 1], true);
            auditLog('account_status_changed', $userId, $isActive ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
            setFlash('success', $newStatus === false ? 'Status akun tidak dapat diperbarui.' : ($isActive ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.'));
        } else {
            throw new InvalidArgumentException('Aksi akun tidak valid.');
        }
        redirect('akun/');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = $exception->getCode() === '23505' ? 'Username sudah digunakan.' : 'Perubahan akun gagal diproses.';
    }
}

$users = $pdo->query("SELECT id, nama, username, no_hp, role, aktif, created_at FROM users ORDER BY CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'teknisi' THEN 3 ELSE 4 END, nama ASC")->fetchAll();
require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Kelola Akun</h2>
            <p class="text-sm text-slate-500 mt-0.5">Owner dapat membuat, menaikkan role, dan menghapus akun non-owner.</p>
        </div>
    </div>

    <?php if ($error): ?><div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium"><?= e($error) ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <h3 class="text-base font-bold text-slate-900">Tambah Akun Teknisi</h3>
        <form method="POST" class="mt-5 grid sm:grid-cols-2 gap-4" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_teknisi">
            <div><label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama lengkap</label><input id="nama" name="nama" maxlength="100" required value="<?= e($_POST['nama'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="username" class="block text-sm font-semibold text-slate-700 mb-1.5">Username</label><input id="username" name="username" maxlength="50" pattern="[a-z0-9._-]{4,50}" required value="<?= e($_POST['username'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="no_hp" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor HP (opsional)</label><input id="no_hp" name="no_hp" maxlength="25" value="<?= e($_POST['no_hp'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password awal</label><input id="password" name="password" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1.5">Ulangi password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div class="sm:col-span-2"><button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Buat Akun Teknisi</button></div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200"><h3 class="text-base font-bold text-slate-900">Daftar Akun</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider"><tr><th class="py-3 px-6">Nama</th><th class="py-3 px-6">Username</th><th class="py-3 px-6">Role</th><th class="py-3 px-6">Status</th><th class="py-3 px-6">Terdaftar</th><th class="py-3 px-6 text-right">Tindakan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $account): ?>
                        <tr>
                            <td class="py-3.5 px-6 font-semibold text-slate-900"><?= e($account['nama']) ?></td>
                            <td class="py-3.5 px-6"><?= e($account['username']) ?></td>
                            <td class="py-3.5 px-6"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $account['role'] === 'owner' ? 'bg-amber-50 text-amber-700' : ($account['role'] === 'teknisi' ? 'bg-blue-50 text-blue-700' : ($account['role'] === 'admin' ? 'bg-purple-50 text-purple-700' : 'bg-slate-100 text-slate-700')) ?>"><?= e($account['role']) ?></span></td>
                            <td class="py-3.5 px-6"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $account['aktif'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' ?>"><?= $account['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td class="py-3.5 px-6 text-xs text-slate-500"><?= formatTanggal($account['created_at']) ?></td>
                            <td class="py-3.5 px-6 text-right">
                                <?php if ($account['role'] === 'user'): ?>
                                    <form method="POST" class="inline mr-3" onsubmit="return confirm('Naikkan akun ini menjadi teknisi?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="promote"><input type="hidden" name="user_id" value="<?= e($account['id']) ?>"><button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Jadikan teknisi</button></form>
                                <?php endif; ?>
                                <?php if (in_array($account['role'], ['user', 'teknisi'], true)): ?>
                                    <form method="POST" class="inline mr-3" onsubmit="return confirm('Pindahkan akun ini menjadi admin?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="promote_admin"><input type="hidden" name="user_id" value="<?= e($account['id']) ?>"><button type="submit" class="text-xs font-semibold text-purple-600 hover:text-purple-800">Jadikan admin</button></form>
                                <?php endif; ?>
                                <?php if ($account['role'] !== 'owner' && $account['id'] !== $_SESSION['user_id']): ?><form method="POST" class="inline mr-3"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= e($account['id']) ?>"><button type="submit" class="text-xs font-semibold text-amber-600 hover:text-amber-800"><?= $account['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><?php endif; ?>
                                <?php if ($account['role'] !== 'owner' && $account['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Hapus akun ini secara permanen?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= e($account['id']) ?>"><button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800">Hapus</button></form>
                                <?php endif; ?>
                                <?php if ($account['role'] === 'owner' || $account['id'] === $_SESSION['user_id']): ?><span class="text-xs text-slate-400">-</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
