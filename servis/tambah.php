<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'servis';
$pageTitle = 'Buat Invoice Servis';
$selectedPelangganId = $_GET['pelanggan_id'] ?? '';
$error = '';

$pelangganList = $pdo->query("SELECT id, nama, no_hp, alamat FROM pelanggan ORDER BY nama ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $pelanggan_id = trim($_POST['pelanggan_id'] ?? '');
    $tanggal_servis = trim($_POST['tanggal_servis'] ?? '');
    $jenis_servis = trim($_POST['jenis_servis'] ?? '');
    $teknisi = trim($_POST['teknisi'] ?? '');
    $jumlah_unit = (int)($_POST['jumlah_unit'] ?? 0);
    $detail_unit = trim($_POST['detail_unit'] ?? '');
    $biaya = (float)($_POST['biaya'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    if (empty($pelanggan_id) || empty($tanggal_servis) || empty($jenis_servis) || mb_strlen($jenis_servis) > 50 || empty($teknisi) || $jumlah_unit < 1 || $jumlah_unit > 50 || $detail_unit === '' || mb_strlen($detail_unit) > 2000) {
        $error = 'Harap isi seluruh kolom yang bertanda wajib.';
    } else {
        $stmtFind = $pdo->prepare("SELECT id FROM pelanggan WHERE id = :id LIMIT 1");
        $stmtFind->execute(['id' => $pelanggan_id]);
        $pelanggan_id = $stmtFind->fetchColumn();

        if (!$pelanggan_id) {
            $error = 'Pelanggan tidak valid.';
        } else {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO servis (pelanggan_id, tanggal_servis, jenis_servis, teknisi, jumlah_unit, detail_unit, biaya, catatan)
                    VALUES (:pelanggan_id, :tanggal_servis, :jenis_servis, :teknisi, :jumlah_unit, :detail_unit, :biaya, :catatan)
                ");
                $stmtInsert->execute([
                    'pelanggan_id'   => $pelanggan_id,
                    'tanggal_servis' => $tanggal_servis,
                    'jenis_servis'   => $jenis_servis,
                    'teknisi'        => $teknisi,
                    'jumlah_unit'    => $jumlah_unit,
                    'detail_unit'    => $detail_unit,
                    'biaya'          => $biaya,
                    'catatan'        => $catatan ?: null
                ]);

                setFlash('success', 'Invoice servis berhasil dibuat.');
                redirect('pelanggan/detail.php?id=' . $pelanggan_id);
            } catch (Exception $e) {
                $error = 'Gagal menyimpan catatan servis: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Buat Invoice Servis</h2>
            <p class="text-sm text-slate-500 mt-0.5">Satu invoice dapat memuat beberapa AC yang dikerjakan bersamaan.</p>
        </div>
        <a href="<?= baseUrl('servis/') ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
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
                <label for="pelanggan_id" class="block text-sm font-semibold text-slate-700 mb-1.5">Pelanggan</label>
                <select id="pelanggan_id" name="pelanggan_id" required
                        class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                    <option value="">-- Pilih Pelanggan --</option>
                    <?php foreach ($pelangganList as $p): ?>
                        <option value="<?= e($p['id']) ?>" <?= ($selectedPelangganId === $p['id'] || ($_POST['pelanggan_id'] ?? '') === $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nama']) ?> (<?= e($p['no_hp']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="jumlah_unit" class="block text-sm font-semibold text-slate-700 mb-1.5">Jumlah AC Dikerjakan</label>
                    <input type="number" id="jumlah_unit" name="jumlah_unit" min="1" max="50" required value="<?= e($_POST['jumlah_unit'] ?? '1') ?>"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label for="detail_unit" class="block text-sm font-semibold text-slate-700 mb-1.5">Detail AC</label>
                          <textarea id="detail_unit" name="detail_unit" required maxlength="2000" rows="3"
                              placeholder="Contoh: AC kamar utama, ruang tamu, kamar anak; Daikin 1 PK" 
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm">
                           <?= e($_POST['detail_unit'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="tanggal_servis" class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Servis</label>
                    <input type="date" id="tanggal_servis" name="tanggal_servis" required 
                           value="<?= e($_POST['tanggal_servis'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                </div>

                <div>
                    <label for="jenis_servis" class="block text-sm font-semibold text-slate-700 mb-1.5">Jenis Pekerjaan</label>
                    <input type="text" id="jenis_servis" name="jenis_servis" required maxlength="50"
                           value="<?= e($_POST['jenis_servis'] ?? 'Cuci AC') ?>"
                           placeholder="Contoh: Cuci AC + isi freon"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                    <p class="mt-1 text-xs text-slate-500">Tulis jenis pekerjaan sesuai tindakan teknisi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="teknisi" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Teknisi</label>
                    <input type="text" id="teknisi" name="teknisi" required
                           value="<?= e($_POST['teknisi'] ?? ($_SESSION['user_nama'] ?? 'Teknisi AC Care')) ?>"
                           placeholder="Contoh: Farrel, Budi, Tim Servis"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                </div>

                <div>
                    <label for="biaya" class="block text-sm font-semibold text-slate-700 mb-1.5">Biaya Servis (Rp)</label>
                    <input type="number" id="biaya" name="biaya" min="0" step="5000" required
                           value="<?= e($_POST['biaya'] ?? '75000') ?>"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
                </div>
            </div>

            <div>
                    <label for="catatan" class="block text-sm font-semibold text-slate-700 mb-1.5">Keterangan Pengerjaan</label>
                <textarea id="catatan" name="catatan" rows="3"
                          placeholder="Contoh: Kondisi filter dibersihkan, tekanan freon normal (70 psi), pembuangan air lancar."
                          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all"><?= e($_POST['catatan'] ?? '') ?></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= baseUrl('servis/') ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                    Simpan Catatan Servis
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
