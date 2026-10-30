<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin']);

$activeNav = 'pelanggan';
$pageTitle = 'Edit Data Pelanggan';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    redirect('pelanggan/');
}

$stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    setFlash('error', 'Data pelanggan tidak ditemukan.');
    redirect('pelanggan/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($nama) || empty($no_hp) || empty($alamat) || mb_strlen($catatan) > 2000 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $error = 'Periksa kembali data pelanggan.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE pelanggan 
                SET nama = :nama, no_hp = :no_hp, email = :email, alamat = :alamat, catatan = :catatan, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'nama' => $nama,
                'no_hp' => $no_hp,
                'email' => $email ?: null,
                'alamat' => $alamat,
                'catatan' => $catatan ?: null,
                'id' => $id
            ]);

            setFlash('success', 'Perubahan data pelanggan berhasil disimpan.');
            redirect('pelanggan/detail.php?id=' . $id);
        } catch (Exception $e) {
            $error = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Perbarui Data Pelanggan</h2>
            <p class="text-sm text-slate-500 mt-0.5">Ubah informasi nama, kontak, atau alamat domisili.</p>
        </div>
        <a href="<?= baseUrl('pelanggan/detail.php?id=' . $id) ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Kembali ke Detail
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 shadow-sm">
        <form method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div>
                <label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required value="<?= e($_POST['nama'] ?? $pelanggan['nama']) ?>"
                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div>
                <label for="no_hp" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor WhatsApp / HP</label>
                <input type="text" id="no_hp" name="no_hp" required value="<?= e($_POST['no_hp'] ?? $pelanggan['no_hp']) ?>"
                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email (Opsional)</label>
                <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? ($pelanggan['email'] ?? '')) ?>" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div>
                <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1.5">Alamat Lengkap</label>
                <textarea id="alamat" name="alamat" rows="3" required
                          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all"><?= e($_POST['alamat'] ?? $pelanggan['alamat']) ?></textarea>
            </div>

            <div>
                <label for="catatan" class="block text-sm font-semibold text-slate-700 mb-1.5">Catatan Internal (Opsional)</label>
                <textarea id="catatan" name="catatan" rows="3" maxlength="2000" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all"><?= e($_POST['catatan'] ?? ($pelanggan['catatan'] ?? '')) ?></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $id) ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
