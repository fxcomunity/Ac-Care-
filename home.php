<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
startSecureSession();
sendSecurityHeaders();
$loggedIn = isLoggedIn();
$website = websiteSettings();
$websiteLogo = $website['site_logo'] !== '' ? websiteAssetUrl($website['site_logo']) : '';
$userName = $_SESSION['user_nama'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$dashboardPath = $userRole === 'user' ? 'user/' : 'dashboard/';
$companyAddress = envValue('COMPANY_ADDRESS', $website['site_name'] . ' Blok B3, Jalan Raya Citra Garden 5 No.18, RT.1/RW.9, Prepedan, Kalideres, Jakarta Barat, DKI Jakarta 11820');
$mapQuery = rawurlencode($companyAddress);
$products = [];
try {
    $products = $pdo->query("SELECT nama, kategori, deskripsi, harga, maintenance, gambar_url FROM produk WHERE aktif = TRUE ORDER BY created_at DESC LIMIT 6")->fetchAll();
} catch (Throwable $exception) {
    $products = [];
}
$initials = '';
if ($userName !== '') {
    $nameParts = preg_split('/\s+/', trim($userName));
    $initials = strtoupper(substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?= e($website['site_name']) ?></title>
    <?php if ($websiteLogo): ?><link rel="icon" href="<?= e($websiteLogo) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(baseUrl('assets/css/app.css')) ?>">
</head>
<body class="min-h-screen bg-slate-950 text-white afe-home-grid">
    <header class="border-b border-white/10 bg-slate-950/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="<?= baseUrl('home.php') ?>" class="afe-logo">
                <?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1"><?php else: ?><span class="afe-logo-mark">AF</span><?php endif; ?>
                <span>
                    <strong class="afe-logo-name block text-sm tracking-wide"><?= e($website['site_name']) ?></strong>
                    <small class="block text-xs text-slate-400"><?= e($website['site_slogan']) ?></small>
                </span>
            </a>

            <div class="relative">
                <?php if ($loggedIn): ?>
                    <button id="account-button" type="button" aria-expanded="false" class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 hover:bg-white/10 transition-colors">
                        <span class="w-9 h-9 rounded-lg bg-blue-500/20 text-blue-300 flex items-center justify-center text-sm font-bold"><?= e($initials) ?></span>
                        <span class="hidden sm:block text-left"><strong class="block text-sm"><?= e($userName) ?></strong><small class="block text-xs text-slate-400 capitalize"><?= e($userRole) ?></small></span>
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div id="account-menu" hidden class="absolute right-0 z-20 mt-3 w-56 rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
                        <div class="px-3 py-2 border-b border-slate-700 mb-1"><p class="text-xs text-slate-400">Akun aktif</p><p class="text-sm font-semibold truncate"><?= e($userName) ?></p></div>
                        <a href="<?= baseUrl($dashboardPath) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-200 hover:bg-slate-800">Dashboard</a>
                        <a href="<?= baseUrl('profile.php') ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-200 hover:bg-slate-800">Profile</a>
                        <a href="<?= baseUrl('auth/logout.php') ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-red-300 hover:bg-red-500/10">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-3"><a href="<?= baseUrl('auth/login.php') ?>" class="text-sm font-semibold text-slate-300 hover:text-white">Masuk</a><a href="<?= baseUrl('auth/register.php') ?>" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold hover:bg-blue-500">Daftar</a></div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main class="min-h-[calc(100vh-5rem)] flex items-center px-6 py-16">
        <section class="max-w-7xl mx-auto w-full grid lg:grid-cols-[1.1fr_0.9fr] gap-12 items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-blue-400">Manajemen teknisi modern</p>
                <h1 class="mt-5 max-w-3xl text-4xl sm:text-6xl font-bold tracking-tight"><?= e($website['site_slogan']) ?></h1>
                <p class="mt-6 max-w-2xl text-lg text-slate-300">Kelola pelanggan, banyak unit AC, riwayat servis, pengingat berkala, dan notifikasi teknisi dari satu sistem.</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="<?= baseUrl($loggedIn ? $dashboardPath : 'auth/login.php') ?>" class="px-5 py-3 rounded-lg bg-blue-600 hover:bg-blue-500 font-semibold"><?= $loggedIn ? 'Buka dashboard' : 'Masuk ke sistem' ?></a>
                    <?php if (!$loggedIn): ?><a href="<?= baseUrl('auth/register.php') ?>" class="px-5 py-3 rounded-lg border border-slate-700 hover:bg-slate-800 font-semibold">Daftar akun</a><?php endif; ?>
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-8 shadow-2xl shadow-blue-950/30">
                <div class="flex items-center justify-between"><span class="text-sm font-semibold text-slate-300">Operasional hari ini</span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span></div>
                <div class="mt-8 grid grid-cols-2 gap-4"><div class="rounded-xl bg-slate-900/70 p-5"><p class="text-3xl font-bold">24/7</p><p class="mt-2 text-xs text-slate-400">Data servis tersimpan</p></div><div class="rounded-xl bg-blue-600/20 p-5"><p class="text-3xl font-bold text-blue-300">3 bln</p><p class="mt-2 text-xs text-slate-400">Siklus pengingat</p></div></div>
                <p class="mt-6 text-sm leading-6 text-slate-400">Pantau pekerjaan teknisi dan jadwal perawatan tanpa spreadsheet yang tercecer.</p>
            </div>
        </section>
    </main>

    <section class="bg-slate-950 px-6 py-20">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8"><div><p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-400">Produk &amp; layanan</p><h2 class="mt-3 text-3xl font-bold">Solusi untuk kebutuhan AC Anda</h2></div><p class="max-w-md text-sm leading-6 text-slate-400">Informasi produk dan jadwal maintenance diperbarui oleh admin atau teknisi.</p></div>
            <?php if ($products): ?>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($products as $product): ?>
                        <article class="rounded-2xl border border-white/10 bg-white/[0.06] overflow-hidden">
                            <?php if (!empty($product['gambar_url'])): ?><img src="<?= e($product['gambar_url']) ?>" alt="<?= e($product['nama']) ?>" class="h-40 w-full object-cover"><?php else: ?><div class="h-40 bg-gradient-to-br from-blue-600/40 to-slate-900 flex items-center justify-center text-4xl font-black text-blue-200">AC</div><?php endif; ?>
                            <div class="p-5"><span class="text-xs font-semibold uppercase tracking-wider text-blue-300"><?= e($product['kategori']) ?></span><h3 class="mt-2 text-lg font-bold"><?= e($product['nama']) ?></h3><p class="mt-2 text-sm leading-6 text-slate-400"><?= e($product['deskripsi'] ?: 'Produk dan layanan perawatan dari PT AFE Teknik.') ?></p><div class="mt-4 pt-4 border-t border-white/10"><p class="text-xs text-slate-500">Maintenance</p><p class="mt-1 text-sm text-slate-300"><?= e($product['maintenance'] ?: 'Hubungi teknisi untuk jadwal maintenance.') ?></p><?php if ((float)$product['harga'] > 0): ?><p class="mt-3 font-bold text-blue-300"><?= formatRupiah($product['harga']) ?></p><?php endif; ?></div></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><div class="rounded-2xl border border-dashed border-white/15 p-10 text-center text-sm text-slate-400">Produk dan layanan akan tampil di sini setelah ditambahkan oleh admin atau teknisi.</div><?php endif; ?>
        </div>
    </section>

    <section class="bg-slate-900/80 border-t border-white/10 px-6 py-20">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-[.75fr_1.25fr] gap-8 items-stretch">
            <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-8 flex flex-col justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-400">Lokasi kantor</p>
                    <h2 class="mt-3 text-3xl font-bold">Kunjungi <?= e($website['site_name']) ?></h2>
                    <p class="mt-4 text-sm leading-6 text-slate-300"><?= e($companyAddress) ?></p>
                </div>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= e($mapQuery) ?>" target="_blank" rel="noopener noreferrer" class="mt-8 inline-flex w-fit rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold hover:bg-blue-500">Buka petunjuk arah</a>
            </div>
            <iframe class="afe-map w-full rounded-2xl" title="Peta lokasi <?= e($website['site_name']) ?>" src="https://www.google.com/maps?q=<?= e($mapQuery) ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>

    <footer class="border-t border-white/10 bg-slate-950 px-6 py-10">
        <div class="max-w-7xl mx-auto grid sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
            <div class="lg:col-span-2"><div class="afe-logo"><?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1"><?php else: ?><span class="afe-logo-mark">AF</span><?php endif; ?><span class="afe-logo-name"><?= e($website['site_name']) ?></span></div><p class="mt-4 max-w-sm text-slate-400"><?= e($website['site_slogan']) ?></p></div>
            <div><h3 class="font-semibold text-white">Akses cepat</h3><div class="mt-4 space-y-2 text-slate-400"><a class="block hover:text-white" href="<?= baseUrl('home.php') ?>">Home</a><a class="block hover:text-white" href="<?= baseUrl($loggedIn ? $dashboardPath : 'auth/login.php') ?>">Dashboard</a><a class="block hover:text-white" href="<?= baseUrl('auth/register.php') ?>">Daftar akun</a></div></div>
            <div><h3 class="font-semibold text-white">Peran sistem</h3><p class="mt-4 leading-6 text-slate-400">Admin mengelola data dan akses. Teknisi menangani unit serta servis. User memantau data pelanggan miliknya.</p></div>
        </div>
        <div class="max-w-7xl mx-auto mt-10 pt-5 border-t border-white/10 text-xs text-slate-500">&copy; <?= date('Y') ?> <?= e($website['site_name']) ?>. Semua hak dilindungi.</div>
    </footer>
    <?php if ($loggedIn): ?>
    <script>
        (function () { const button = document.getElementById('account-button'); const menu = document.getElementById('account-menu'); button.addEventListener('click', function () { const open = !menu.hidden; menu.hidden = open; button.setAttribute('aria-expanded', String(!open)); }); document.addEventListener('click', function (event) { if (!button.contains(event.target) && !menu.contains(event.target)) { menu.hidden = true; button.setAttribute('aria-expanded', 'false'); } }); }());
    </script>
    <?php endif; ?>
</body>
</html>