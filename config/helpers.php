<?php
/**
 * AC Care - Helper Functions
 * Kumpulan fungsi pendukung umum sistem manajemen AC Care
 */

/**
 * Mendapatkan base URL aplikasi secara otomatis atau fallback ke /Developer
 */
function baseUrl(string $path = ''): string 
{
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        
        // Cari posisi root project Developer
        if (preg_match('#^(/[^/]+)#', $dir, $matches)) {
            $base = rtrim($matches[1], '/');
        } else {
            $base = '/Developer';
        }
    }
    
    $cleanPath = ltrim($path, '/');
    return $cleanPath ? $base . '/' . $cleanPath : $base;
}

function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $sessionTimeout = 1800;
    if (!empty($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $sessionTimeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

function envValue(string $name, string $fallback = ''): string
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return trim($value, " \t\n\r\0\x0B\\\"'");
    }

    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $envValue] = explode('=', $line, 2);
            if (trim($key) === $name) {
                return trim(trim($envValue), " \t\n\r\0\x0B\\\"'");
            }
        }
    }
    return $fallback;
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (envValue('APP_ENV', 'development') === 'production' && !$isHttps) {
        $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $target, true, 308);
        exit;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; frame-src 'self' https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Membersihkan input teks agar aman ditampilkan di HTML (XSS prevention)
 */
function e(?string $string): string 
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka ke mata uang Rupiah
 */
function formatRupiah($angka): string 
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format tanggal Y-m-d ke format Indonesia (contoh: 23 September 2026)
 */
function formatTanggal(?string $tanggal): string 
{
    if (!$tanggal) {
        return '-';
    }
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    if (!$timestamp) {
        return $tanggal;
    }
    
    $hari  = date('d', $timestamp);
    $bln   = (int) date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return "$hari " . ($bulan[$bln] ?? '') . " $tahun";
}

/**
 * Menghitung status pengingat perawatan AC (siklus 3 bulan)
 */
function hitungStatusReminder(string $tanggalServisTerakhir): array 
{
    $tanggalServis = new DateTime($tanggalServisTerakhir);
    $jadwalBerikutnya = clone $tanggalServis;
    $jadwalBerikutnya->modify('+3 months');
    
    $hariIni = new DateTime('today');
    $selisih = $hariIni->diff($jadwalBerikutnya);
    $sisaHari = (int) $selisih->format('%r%a');
    
    if ($sisaHari < 0) {
        return [
            'status' => 'terlambat',
            'label'  => 'Terlambat',
            'badge'  => 'danger',
            'badge_class' => 'bg-red-50 text-red-700 ring-1 ring-red-600/10',
            'dot_class'   => 'bg-red-500',
            'jadwal_berikutnya' => $jadwalBerikutnya->format('Y-m-d'),
            'sisa_hari' => $sisaHari,
        ];
    } elseif ($sisaHari === 0) {
        return [
            'status' => 'jatuh_tempo',
            'label'  => 'Jatuh Tempo',
            'badge'  => 'danger',
            'badge_class' => 'bg-red-50 text-red-700 ring-1 ring-red-600/10',
            'dot_class'   => 'bg-red-500',
            'jadwal_berikutnya' => $jadwalBerikutnya->format('Y-m-d'),
            'sisa_hari' => $sisaHari,
        ];
    } elseif ($sisaHari <= 14) {
        return [
            'status' => 'segera',
            'label'  => 'Segera',
            'badge'  => 'warning',
            'badge_class' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/10',
            'dot_class'   => 'bg-amber-500',
            'jadwal_berikutnya' => $jadwalBerikutnya->format('Y-m-d'),
            'sisa_hari' => $sisaHari,
        ];
    } else {
        return [
            'status' => 'aman',
            'label'  => 'Aman',
            'badge'  => 'success',
            'badge_class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10',
            'dot_class'   => 'bg-emerald-500',
            'jadwal_berikutnya' => $jadwalBerikutnya->format('Y-m-d'),
            'sisa_hari' => $sisaHari,
        ];
    }
}

/**
 * Redirect ke path tujuan
 */
function redirect(string $path): void 
{
    $url = (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))
        ? $path
        : baseUrl($path);
        
    header("Location: $url");
    exit;
}

/**
 * Validasi otentikasi sesi pengguna
 */
function requireLogin(): void 
{
    startSecureSession();
    
    if (empty($_SESSION['user_id'])) {
        redirect('auth/login.php');
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    $currentRole = $_SESSION['user_role'] ?? '';
    $hasAccess = $currentRole === 'owner'
        || in_array($currentRole, $roles, true);
    if (!$hasAccess) {
        http_response_code(403);
        exit('Akses ditolak. Role akun ini tidak memiliki izin untuk halaman tersebut.');
    }
}

function isDeveloper(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'developer';
}

function auditLog(string $action, ?string $target = null, string $description = ''): void
{
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, role, action, target, description, ip_address, user_agent) VALUES (:user_id, :role, :action, :target, :description, :ip_address, :user_agent)');
        $stmt->execute([
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'action' => $action,
            'target' => $target,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('AC Care audit log failed: ' . $exception->getMessage());
    }
}

function loginRateLimitKey(string $username): string
{
    return hash('sha256', strtolower(trim($username)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function loginIsBlocked(string $key): bool
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT blocked_until FROM login_attempts WHERE attempt_key = :attempt_key AND blocked_until > CURRENT_TIMESTAMP');
        $stmt->execute(['attempt_key' => $key]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('AC Care login rate limit check failed: ' . $exception->getMessage());
        return false;
    }
}

function recordLoginFailure(string $key): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (attempt_key, failed_attempts, first_failed_at, blocked_until, updated_at) VALUES (:attempt_key, 1, CURRENT_TIMESTAMP, NULL, CURRENT_TIMESTAMP) ON CONFLICT (attempt_key) DO UPDATE SET failed_attempts = login_attempts.failed_attempts + 1, blocked_until = CASE WHEN login_attempts.failed_attempts + 1 >= 5 THEN CURRENT_TIMESTAMP + INTERVAL \'15 minutes\' ELSE login_attempts.blocked_until END, updated_at = CURRENT_TIMESTAMP');
        $stmt->execute(['attempt_key' => $key]);
    } catch (Throwable $exception) {
        error_log('AC Care login rate limit write failed: ' . $exception->getMessage());
    }
}

function clearLoginFailures(string $key): void
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key');
        $stmt->execute(['attempt_key' => $key]);
    } catch (Throwable $exception) {
        error_log('AC Care login rate limit cleanup failed: ' . $exception->getMessage());
    }
}

function isStaffRole(?string $role = null): bool
{
    $role = $role ?? ($_SESSION['user_role'] ?? '');
    return in_array($role, ['owner', 'admin', 'teknisi'], true);
}

function isOwner(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'owner';
}

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): void
{
    startSecureSession();
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

function validateText(string $value, int $maxLength, string $label): string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' wajib diisi dan maksimal ' . $maxLength . ' karakter.');
    }
    return $value;
}

function validatePhone(string $value): string
{
    $value = trim($value);
    if (!preg_match('/^\+?[0-9][0-9\s-]{7,20}$/', $value)) {
        throw new InvalidArgumentException('Nomor HP tidak valid.');
    }
    return $value;
}

/**
 * Cek apakah user sedang login
 */
function isLoggedIn(): bool 
{
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void 
{
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $_SESSION['flash'] = [
        'type' => $type, // success, error, info
        'message' => $message
    ];
}

/**
 * Ambil flash message jika ada
 */
function getFlash(): ?array 
{
    startSecureSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate pesan WhatsApp reminder servis
 */
function generatePesanWhatsApp(string $nama, string $namaUnit, string $tanggalServis, string $noHp = '', string $namaTeknisi = ''): string 
{
    $tanggalFormatted = formatTanggal($tanggalServis);
    $jadwalBerikutnya = formatTanggal((new DateTime($tanggalServis))->modify('+3 months')->format('Y-m-d'));

    $siteName = websiteSettings()['site_name'];
    $template = "Halo Bapak/Ibu {nama},\n\nKami dari {$siteName} ingin mengingatkan bahwa unit AC {unit} terakhir diservis pada {tanggal}.\n\nJadwal servis berkala berikutnya sudah dekat. Apakah berkenan kami jadwalkan kunjungan teknisi?\n\nSalam,\n{$siteName}";
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            static $savedTemplate = null;
            if ($savedTemplate === null) {
                $savedTemplate = $pdo->query('SELECT isi FROM whatsapp_templates WHERE aktif = TRUE ORDER BY updated_at DESC LIMIT 1')->fetchColumn() ?: $template;
            }
            $template = $savedTemplate;
        } catch (Throwable $exception) {
            // Gunakan template bawaan saat migrasi belum dijalankan.
        }
    }

    $pesan = strtr($template, [
        '{nama}' => trim($nama),
        '{nama_pelanggan}' => trim($nama),
        '{unit}' => trim($namaUnit),
        '{nama_unit}' => trim($namaUnit),
        '{tanggal}' => $tanggalFormatted,
        '{tanggal_servis}' => $tanggalFormatted,
        '{jadwal_berikutnya}' => $jadwalBerikutnya,
        '{nama_teknisi}' => trim($namaTeknisi),
        '{perusahaan}' => $siteName,
        '{nama_bisnis}' => $siteName,
        '{nomor_telepon}' => trim($noHp),
    ]);
    
    return urlencode($pesan);
}

/**
 * Format link langsung WhatsApp
 */
function buatLinkWhatsApp(string $noHp, string $nama, string $namaUnit, string $tanggalServis): string 
{
    $noHp = preg_replace('/^0/', '62', trim($noHp));
    $noHp = preg_replace('/[^0-9]/', '', $noHp);
    
    $pesan = generatePesanWhatsApp($nama, $namaUnit, $tanggalServis, $noHp);
    return "https://wa.me/" . $noHp . "?text=" . $pesan;
}

function pushConfig(): array
{
    $configFile = __DIR__ . '/push.php';
    return file_exists($configFile) ? require $configFile : [];
}

function websiteSettings(): array
{
    $defaults = [
        'site_name' => 'PT AFE Teknik',
        'site_slogan' => 'Manajemen & Servis',
        'site_logo' => '',
        'maintenance_mode' => '0',
        'maintenance_message' => 'Website sedang dalam pemeliharaan operasional. Beberapa fitur mungkin tidak tersedia sementara.',
    ];
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        return $defaults;
    }

    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll();
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $defaults[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
    } catch (Throwable $exception) {
        // Fallback dipakai saat schema branding belum dijalankan.
    }
    return $defaults;
}

function websiteAssetUrl(string $value): string
{
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : baseUrl($value);
}
