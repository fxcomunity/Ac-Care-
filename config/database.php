<?php
/**
 * ============================================
 * AC Care - Database Connection
 * ============================================
 * 
 * File ini menghubungkan aplikasi ke database Neon PostgreSQL.
 * 
 * CARA KERJA:
 * 1. Membaca DATABASE_URL dari file .env
 * 2. Memecah URL menjadi bagian-bagian (host, user, password, database)
 * 3. Membuat koneksi menggunakan PDO (PHP Data Objects)
 * 
 * APA ITU PDO?
 * PDO adalah cara PHP untuk berkomunikasi dengan database.
 * PDO lebih aman daripada cara lama karena mendukung "prepared statements"
 * yang mencegah serangan SQL Injection.
 * ============================================
 */

// ============================================
// LANGKAH 1: Membaca file .env
// ============================================
// __DIR__ artinya "folder tempat file ini berada" (yaitu folder config/)
// '..' artinya "naik satu folder ke atas" (yaitu folder ac-care/)
// Jadi kita mencari file .env di folder ac-care/

$envFile = __DIR__ . '/../.env';

// Cek apakah file .env ada
if (!file_exists($envFile)) {
    die('Error: File .env tidak ditemukan. Buat file .env di direktori root.');
}

// Baca file .env baris per baris
$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($envLines as $line) {
    // Lewati baris yang dimulai dengan # (itu komentar)
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    
    // Cari tanda = untuk memisahkan nama dan nilai
    // Contoh: DATABASE_URL="postgresql://..." 
    //   -> nama: DATABASE_URL
    //   -> nilai: postgresql://...
    if (str_contains($line, '=')) {
        $parts = explode('=', $line, 2); // Pecah jadi 2 bagian saja
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        
        // Hapus tanda kutip di awal dan akhir jika ada
        $value = trim($value, '"\'');
        
        // Simpan sebagai environment variable
        // putenv() membuat variabel bisa diakses oleh getenv()
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// ============================================
// LANGKAH 2: Ambil DATABASE_URL
// ============================================
$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die('Error: DATABASE_URL tidak ditemukan di file .env.');
}

// ============================================
// LANGKAH 3: Pecah DATABASE_URL menjadi bagian-bagian
// ============================================
// DATABASE_URL biasanya berbentuk:
// postgresql://username:password@host/database?sslmode=require
//
// parse_url() akan memecah URL ini menjadi:
// - scheme: postgresql
// - host: ep-xxx.region.aws.neon.tech
// - user: username
// - pass: password
// - path: /database (nama database)
// - query: sslmode=require

$dbParts = parse_url($databaseUrl);

if ($dbParts === false) {
    die('Error: Format DATABASE_URL tidak valid.');
}

$host     = $dbParts['host'] ?? '';
$port     = $dbParts['port'] ?? 5432;        // Port default PostgreSQL: 5432
$dbname   = ltrim($dbParts['path'] ?? '', '/'); // Hapus / di depan nama database
$user     = $dbParts['user'] ?? '';
$password = $dbParts['pass'] ?? '';

// ============================================
// LANGKAH 4: Buat koneksi PDO ke PostgreSQL
// ============================================
// DSN (Data Source Name) adalah string yang memberitahu PDO:
// - Jenis database apa (pgsql = PostgreSQL)
// - Di mana database-nya (host)
// - Port berapa
// - Nama database-nya apa

// Ambil endpoint ID dari hostname Neon
// Hostname Neon berbentuk: ep-cool-name-12345.region.aws.neon.tech
// Atau untuk pooler: ep-cool-name-12345-pooler.region.aws.neon.tech
$endpoint = explode('.', $host)[0];
$endpoint = str_replace('-pooler', '', $endpoint);

// Solusi resmi Neon untuk client tanpa SNI di Windows:
// Tambahkan options='endpoint=ep-xyz' di DSN
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require;options='endpoint=$endpoint'";

try {
    // Buat koneksi baru
    $pdo = new PDO($dsn, $user, $password);
    
    // Set mode error: jika ada error SQL, langsung lempar Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set mode fetch default: hasil query dikembalikan sebagai array asosiatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Matikan emulated prepared statements untuk keamanan lebih
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    error_log('AC Care database connection failed: ' . $e->getMessage());
    http_response_code(503);
    die('Terjadi gangguan pada sistem. Silakan coba lagi nanti.');
}

// ============================================
// SELESAI!
// ============================================
// Variabel $pdo sekarang bisa digunakan di file lain
// untuk menjalankan query ke database.
//
// Contoh penggunaan:
// require_once 'config/database.php';
// $stmt = $pdo->query("SELECT * FROM pelanggan");
// $data = $stmt->fetchAll();
