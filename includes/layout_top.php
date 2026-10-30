<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
sendSecurityHeaders();

$activeNav = $activeNav ?? 'dashboard';
$pageTitle = $pageTitle ?? 'AC Care System';
$currentUser = $_SESSION['user_nama'] ?? 'Administrator';
$currentRole = $_SESSION['user_role'] ?? 'admin';
$dashboardUrl = $currentRole === 'user' ? 'user/' : 'dashboard/';
$flash = getFlash();
$push = pushConfig();
$website = websiteSettings();
$websiteLogo = $website['site_logo'] !== '' ? websiteAssetUrl($website['site_logo']) : '';
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($website['site_name']) ?></title>
    <?php if ($websiteLogo): ?><link rel="icon" href="<?= e($websiteLogo) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(baseUrl('assets/css/app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>
<body class="h-full antialiased text-slate-800">
    <div class="min-h-full flex flex-col lg:flex-row">
        
        <!-- Sidebar Navigation -->
        <aside id="app-sidebar" class="hidden w-full lg:w-64 bg-slate-900 text-slate-300 flex-shrink-0 lg:flex flex-col justify-between">
            <div>
                <!-- Brand Header -->
                <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
                    <div class="afe-logo">
                        <?php if ($websiteLogo): ?><img src="<?= e($websiteLogo) ?>" alt="Logo <?= e($website['site_name']) ?>" class="afe-logo-mark object-contain bg-white p-1"><?php else: ?><div class="afe-logo-mark">AF</div><?php endif; ?>
                        <div>
                            <span class="afe-logo-name text-white text-base block"><?= e($website['site_name']) ?></span>
                            <span class="text-xs text-slate-400 block -mt-0.5"><?= e($website['site_slogan']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Nav Links -->
                <nav class="p-4 space-y-1">
                    <a href="<?= baseUrl('home.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'home' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 9.75-9 9.75 9M4.5 10.5v9.75h15V10.5M9 20.25v-6h6v6" /></svg>
                        <span>Home</span>
                    </a>

                    <a href="<?= baseUrl($dashboardUrl) ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'dashboard' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        <span><?= $currentRole === 'user' ? 'Dashboard Saya' : 'Dashboard' ?></span>
                    </a>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('produk/') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'produk' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-8.25-4.125L3.75 7.5m16.5 0v9L12 20.625m8.25-13.125L12 11.625M3.75 7.5v9L12 20.625m0-9v9" /></svg>
                        <span>Produk &amp; Layanan</span>
                    </a>
                    <?php endif; ?>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('pelanggan/') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'pelanggan' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Pelanggan</span>
                    </a>
                    <?php endif; ?>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('servis/') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'servis' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233l2.802-2.801a2.121 2.121 0 00-3-3l-2.802 2.802" />
                        </svg>
                        <span>Riwayat Servis</span>
                    </a>
                    <?php endif; ?>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('pengingat/') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'pengingat' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span>Pengingat Servis</span>
                    </a>
                    <?php endif; ?>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('profile.php') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'pengaturan' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.06c.9-.35 1.912.087 2.262.987l.192.493a1.5 1.5 0 001.395.96h.517a1.5 1.5 0 001.395-.96l.192-.493a1.5 1.5 0 012.262-.987l.43.252a1.5 1.5 0 01.548 2.052l-.26.45a1.5 1.5 0 00.001 1.5l.258.45a1.5 1.5 0 01-.548 2.052l-.43.252a1.5 1.5 0 01-2.262-.987l-.192-.493a1.5 1.5 0 00-1.395-.96h-.517a1.5 1.5 0 00-1.395.96l-.192.493a1.5 1.5 0 01-2.262.987l-.43-.252a1.5 1.5 0 01-.548-2.052l.26-.45a1.5 1.5 0 00-.001-1.5l-.258-.45A1.5 1.5 0 019.164 3.312l.43-.252z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" /></svg>
                        <span>Pengaturan</span>
                    </a>
                    <?php endif; ?>

                    <?php if (isStaffRole($currentRole)): ?>
                    <a href="<?= baseUrl('jadwal/') ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeNav === 'jadwal' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 9.75h18M5.25 5.25h13.5A2.25 2.25 0 0121 7.5v11.25A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V7.5a2.25 2.25 0 012.25-2.25z" /></svg>
                        <span>Jadwal Servis</span>
                    </a>
                    <?php endif; ?>

                </nav>
            </div>

            <!-- User Footer in Sidebar -->
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center justify-between">
                    <div class="min-w-0 pr-2">
                        <p class="text-sm font-semibold text-white truncate"><?= e($currentUser) ?></p>
                        <p class="text-xs text-slate-400 capitalize"><?= e($currentRole) ?></p>
                    </div>
                    <a href="<?= baseUrl('auth/logout.php') ?>" title="Keluar dari akun" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Topbar Header -->
            <header class="h-16 bg-white border-b border-slate-200 px-6 sm:px-8 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="Buka navigasi">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15m-15 5.25h15" /></svg>
                    </button>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight"><?= e($pageTitle) ?></h1>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" data-enable-notifications hidden class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700">Aktifkan notifikasi</button>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Database Terhubung
                    </span>
                </div>
            </header>

            <!-- Page Body -->
            <main class="flex-1 p-6 sm:p-8 overflow-y-auto">
                <?php if ($flash): ?>
                    <div data-app-flash="<?= e($flash['type']) ?>" class="mb-6 rounded-lg p-4 text-sm font-medium border <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($flash['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-blue-50 text-blue-800 border-blue-200') ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>
