<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
redirect('servis/');

$activeNav = 'unit';
$pageTitle = 'Edit Unit AC';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    redirect('unit/');
}

$stmt = $pdo->prepare("SELECT * FROM unit_ac WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$unit = $stmt->fetch();

if (!$unit) {
    setFlash('error', 'Unit AC tidak ditemukan.');
    redirect('unit/');
}

$pelangganList = $pdo->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $pelanggan_id = trim($_POST['pelanggan_id'] ?? '');
    $nama_unit = trim($_POST['nama_unit'] ?? '');
    $merk = trim($_POST['merk'] ?? '');
    $jenis = trim($_POST['jenis'] ?? '');
    $kapasitas = trim($_POST['kapasitas'] ?? '');
    $nomor_seri = trim($_POST['nomor_seri'] ?? '');

    if (empty($pelanggan_id) || empty($nama_unit) || empty($merk) || empty($jenis) || empty($kapasitas)) {
        $error = 'Harap lengkapi semua kolom yang wajib diisi.';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE unit_ac 
                SET pelanggan_id = :pelanggan_id,
                    nama_unit = :nama_unit,
                    merk = :merk,
                    jenis = :jenis,
                    kapasitas = :kapasitas,
                    nomor_seri = :nomor_seri
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'pelanggan_id' => $pelanggan_id,
                'nama_unit'    => $nama_unit,
                'merk'         => $merk,
                'jenis'        => $jenis,
                'kapasitas'    => $kapasitas,
                'nomor_seri'   => $nomor_seri ?: null,
                'id'           => $id
            ]);

            setFlash('success', 'Data unit AC berhasil diperbarui.');
            redirect('pelanggan/detail.php?id=' . $pelanggan_id);
        } catch (Exception $e) {
            $error = 'Gagal menyimpan pembaruan: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Edit Data Unit AC</h2>
            <p class="text-sm text-slate-500 mt-0.5">Ubah spesifikasi atau lokasi unit mesin.</p>
        </div>
        <a href="<?= baseUrl('unit/') ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Kembali
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
                <label for="pelanggan_id" class="block text-sm font-semibold text-slate-700 mb-1.5">Pemilik (Pelanggan)</label>
                <select id="pelanggan_id" name="pelanggan_id" required
                        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                    <?php foreach ($pelangganList as $p): ?>
                        <option value="<?= e($p['id']) ?>" <?= (($unit['pelanggan_id'] ?? '') === $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nama']) ?> (<?= e($p['no_hp']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="nama_unit" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama / Posisi Unit</label>
                <input type="text" id="nama_unit" name="nama_unit" required value="<?= e($_POST['nama_unit'] ?? $unit['nama_unit']) ?>"
                       class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="merk" class="block text-sm font-semibold text-slate-700 mb-1.5">Merk AC</label>
                    <input type="text" id="merk" name="merk" required value="<?= e($_POST['merk'] ?? $unit['merk']) ?>"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                </div>

                <div>
                    <label for="kapasitas" class="block text-sm font-semibold text-slate-700 mb-1.5">Kapasitas (PK)</label>
                    <select id="kapasitas" name="kapasitas" required
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                        <?php foreach (['0.5 PK', '0.75 PK', '1 PK', '1.5 PK', '2 PK', '2.5 PK', 'Lainnya'] as $pk): ?>
                            <option value="<?= $pk ?>" <?= (($unit['kapasitas'] ?? '') === $pk) ? 'selected' : '' ?>><?= $pk ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="jenis" class="block text-sm font-semibold text-slate-700 mb-1.5">Jenis / Tipe Unit</label>
                    <select id="jenis" name="jenis" required
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                        <?php foreach (['Split Wall', 'Split', 'Cassette', 'Standing Floor', 'Central / Ducted', 'Portable'] as $tipe): ?>
                            <option value="<?= $tipe ?>" <?= (($unit['jenis'] ?? '') === $tipe) ? 'selected' : '' ?>><?= $tipe ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="nomor_seri" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Seri (Opsional)</label>
                    <input type="text" id="nomor_seri" name="nomor_seri" value="<?= e($_POST['nomor_seri'] ?? ($unit['nomor_seri'] ?? '')) ?>"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= baseUrl('unit/') ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
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
