<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin']);

$activeNav = 'pelanggan';
$pageTitle = 'Tambah Pelanggan Baru';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $nama = trim($_POST['nama'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($nama) || empty($no_hp) || empty($alamat) || mb_strlen($nama) > 100 || mb_strlen($alamat) > 500 || mb_strlen($catatan) > 2000 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || !preg_match('/^\+?[0-9][0-9\s-]{7,20}$/', $no_hp)) {
        $error = 'Periksa kembali nama, nomor WhatsApp, email, alamat, dan catatan pelanggan.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pelanggan (nama, no_hp, email, alamat, catatan) 
                VALUES (:nama, :no_hp, :email, :alamat, :catatan) 
                RETURNING id
            ");
            $stmt->execute([
                'nama' => $nama,
                'no_hp' => $no_hp,
                'email' => $email ?: null,
                'alamat' => $alamat,
                'catatan' => $catatan ?: null,
            ]);
            $newId = $stmt->fetchColumn();

            setFlash('success', 'Data pelanggan berhasil ditambahkan.');
            redirect('pelanggan/detail.php?id=' . $newId);
        } catch (Exception $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Tambah Pelanggan Baru</h2>
            <p class="text-sm text-slate-500 mt-0.5">Daftarkan pelanggan baru pemilik unit AC.</p>
        </div>
        <a href="<?= baseUrl('pelanggan/') ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Kembali ke Daftar
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
                <input type="text" id="nama" name="nama" required value="<?= e($_POST['nama'] ?? '') ?>"
                       placeholder="Contoh: Budi Santoso"
                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div>
                <label for="no_hp" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor WhatsApp / HP</label>
                <input type="text" id="no_hp" name="no_hp" required value="<?= e($_POST['no_hp'] ?? '') ?>"
                       placeholder="Contoh: 081234567890"
                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                <p class="text-xs text-slate-500 mt-1">Nomor ini akan digunakan untuk mengirimkan reminder via tautan WhatsApp.</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email (Opsional)</label>
                <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div>
                <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1.5">Alamat Lengkap</label>
                <textarea id="alamat" name="alamat" rows="3" required
                          placeholder="Contoh: Jl. Anggrek No. 12, RT 02/05, Bekasi Barat"
                          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all"><?= e($_POST['alamat'] ?? '') ?></textarea>
            </div>

            <div>
                <label for="catatan" class="block text-sm font-semibold text-slate-700 mb-1.5">Catatan Internal (Opsional)</label>
                <textarea id="catatan" name="catatan" rows="3" maxlength="2000" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all"><?= e($_POST['catatan'] ?? '') ?></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= baseUrl('pelanggan/') ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                    Simpan Pelanggan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
