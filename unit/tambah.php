<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
redirect('servis/tambah.php');

$activeNav = 'unit';
$pageTitle = 'Tambah Unit AC';
$selectedPelangganId = $_GET['pelanggan_id'] ?? '';
$error = '';

// Ambil daftar pelanggan untuk pilihan dropdown
$pelangganList = $pdo->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf($_POST['csrf_token'] ?? null);
    $pelanggan_id = trim($_POST['pelanggan_id'] ?? '');
    $units = $_POST['units'] ?? [];

    if (empty($pelanggan_id) || !is_array($units) || count($units) < 1 || count($units) > 20) {
        $error = 'Pilih pelanggan dan tambahkan antara 1 sampai 20 unit AC.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO unit_ac (pelanggan_id, nama_unit, merk, jenis, kapasitas, nomor_seri) VALUES (:pelanggan_id, :nama_unit, :merk, :jenis, :kapasitas, :nomor_seri)");
            foreach ($units as $unit) {
                $nama_unit = trim($unit['nama_unit'] ?? '');
                $merk = trim($unit['merk'] ?? '');
                $jenis = trim($unit['jenis'] ?? '');
                $kapasitas = trim($unit['kapasitas'] ?? '');
                $nomor_seri = trim($unit['nomor_seri'] ?? '');
                if ($nama_unit === '' || $merk === '' || $jenis === '' || $kapasitas === '' || mb_strlen($nama_unit) > 100 || mb_strlen($merk) > 50 || mb_strlen($jenis) > 50 || mb_strlen($kapasitas) > 20 || mb_strlen($nomor_seri) > 100) {
                    throw new InvalidArgumentException('Semua data unit wajib diisi dan tidak boleh melebihi batas karakter.');
                }
                $stmt->execute([
                    'pelanggan_id' => $pelanggan_id,
                    'nama_unit' => $nama_unit,
                    'merk' => $merk,
                    'jenis' => $jenis,
                    'kapasitas' => $kapasitas,
                    'nomor_seri' => $nomor_seri ?: null,
                ]);
            }
            $pdo->commit();

            setFlash('success', count($units) . ' unit AC berhasil didaftarkan.');
            redirect('pelanggan/detail.php?id=' . $pelanggan_id);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Gagal menyimpan unit: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">Tambah Unit AC Baru</h2>
            <p class="text-sm text-slate-500 mt-0.5">Daftarkan mesin pendingin untuk pemeliharaan terjadwal.</p>
        </div>
        <a href="<?= !empty($selectedPelangganId) ? baseUrl('pelanggan/detail.php?id=' . $selectedPelangganId) : baseUrl('unit/') ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
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
                    <option value="">-- Pilih Pelanggan --</option>
                    <?php foreach ($pelangganList as $p): ?>
                        <option value="<?= e($p['id']) ?>" <?= ($selectedPelangganId === $p['id'] || ($_POST['pelanggan_id'] ?? '') === $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nama']) ?> (<?= e($p['no_hp']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php
            $postedUnits = is_array($_POST['units'] ?? null) ? $_POST['units'] : [[]];
            $unitTypes = ['Split Wall', 'Cassette', 'Standing Floor', 'Central / Ducted', 'Portable'];
            $capacities = ['0.5 PK', '0.75 PK', '1 PK', '1.5 PK', '2 PK', '2.5 PK', 'Lainnya'];
            ?>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Daftar Unit AC</h3>
                    <p class="text-xs text-slate-500 mt-1">Tambahkan beberapa unit sekaligus untuk pelanggan ini.</p>
                </div>
                <button type="button" id="add-unit" class="px-3 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-semibold">+ Tambah Unit</button>
            </div>

            <div id="unit-list" class="space-y-5">
                <?php foreach ($postedUnits as $index => $unit): ?>
                    <div class="unit-row rounded-lg border border-slate-200 p-4" data-unit-row>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-bold text-slate-800">Unit <span data-unit-number><?= $index + 1 ?></span></span>
                            <button type="button" data-remove-unit class="text-xs font-semibold text-red-600 hover:text-red-700 <?= count($postedUnits) === 1 ? 'hidden' : '' ?>">Hapus</button>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama / Posisi Unit</label>
                            <input type="text" name="units[<?= $index ?>][nama_unit]" required maxlength="100" value="<?= e($unit['nama_unit'] ?? '') ?>" placeholder="Contoh: AC Kamar Utama" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Merk AC</label>
                                <input type="text" name="units[<?= $index ?>][merk]" required maxlength="50" value="<?= e($unit['merk'] ?? '') ?>" placeholder="Contoh: Daikin, Sharp, LG" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kapasitas (PK)</label>
                                <select name="units[<?= $index ?>][kapasitas]" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                                    <?php foreach ($capacities as $capacity): ?><option value="<?= e($capacity) ?>" <?= ($unit['kapasitas'] ?? '1 PK') === $capacity ? 'selected' : '' ?>><?= e($capacity) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Jenis / Tipe Unit</label>
                                <select name="units[<?= $index ?>][jenis]" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                                    <?php foreach ($unitTypes as $type): ?><option value="<?= e($type) ?>" <?= ($unit['jenis'] ?? 'Split Wall') === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Seri (Opsional)</label>
                                <input type="text" name="units[<?= $index ?>][nomor_seri]" maxlength="100" value="<?= e($unit['nomor_seri'] ?? '') ?>" placeholder="Contoh: DKN-992140" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="<?= baseUrl('unit/') ?>" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                    Daftarkan Unit
                </button>
            </div>
        </form>
    </div>
</div>

<template id="unit-template">
    <div class="unit-row rounded-lg border border-slate-200 p-4" data-unit-row>
        <div class="flex items-center justify-between mb-4"><span class="text-sm font-bold text-slate-800">Unit <span data-unit-number></span></span><button type="button" data-remove-unit class="text-xs font-semibold text-red-600 hover:text-red-700">Hapus</button></div>
        <div class="mb-4"><label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama / Posisi Unit</label><input type="text" data-field="nama_unit" required maxlength="100" placeholder="Contoh: AC Kamar Utama" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-semibold text-slate-700 mb-1.5">Merk AC</label><input type="text" data-field="merk" required maxlength="50" placeholder="Contoh: Daikin, Sharp, LG" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label class="block text-sm font-semibold text-slate-700 mb-1.5">Kapasitas (PK)</label><select data-field="kapasitas" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><option>0.5 PK</option><option>0.75 PK</option><option selected>1 PK</option><option>1.5 PK</option><option>2 PK</option><option>2.5 PK</option><option>Lainnya</option></select></div>
            <div><label class="block text-sm font-semibold text-slate-700 mb-1.5">Jenis / Tipe Unit</label><select data-field="jenis" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><option selected>Split Wall</option><option>Cassette</option><option>Standing Floor</option><option>Central / Ducted</option><option>Portable</option></select></div>
            <div><label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Seri (Opsional)</label><input type="text" data-field="nomor_seri" maxlength="100" placeholder="Contoh: DKN-992140" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        </div>
    </div>
</template>

<script>
    (function () {
        const list = document.getElementById('unit-list');
        const template = document.getElementById('unit-template');
        const addButton = document.getElementById('add-unit');
        function refreshRows() {
            const rows = list.querySelectorAll('[data-unit-row]');
            rows.forEach(function (row, index) {
                row.querySelector('[data-unit-number]').textContent = index + 1;
                row.querySelector('[data-remove-unit]').classList.toggle('hidden', rows.length === 1);
                row.querySelectorAll('[data-field]').forEach(function (field) { field.name = 'units[' + index + '][' + field.dataset.field + ']'; });
            });
            addButton.disabled = rows.length >= 20;
        }
        addButton.addEventListener('click', function () {
            if (list.children.length >= 20) return;
            list.appendChild(template.content.cloneNode(true));
            refreshRows();
        });
        list.addEventListener('click', function (event) {
            if (event.target.matches('[data-remove-unit]')) { event.target.closest('[data-unit-row]').remove(); refreshRows(); }
        });
        refreshRows();
    }());
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
