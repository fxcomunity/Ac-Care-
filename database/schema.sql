-- ====================================================================
-- AC CARE — SISTEM MANAJEMEN PELANGGAN & PENGINGAT PERAWATAN AC
-- Database Schema untuk Neon PostgreSQL
-- ====================================================================

-- 1. Tabel USERS (Pengguna Sistem: Admin & Teknisi)
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    no_hp VARCHAR(25),
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('owner', 'admin', 'teknisi', 'user', 'developer')) DEFAULT 'user',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS no_hp VARCHAR(25);
ALTER TABLE users ADD COLUMN IF NOT EXISTS aktif BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('owner', 'admin', 'teknisi', 'user', 'developer'));

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    first_failed_at TIMESTAMPTZ,
    blocked_until TIMESTAMPTZ,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    role VARCHAR(20),
    action VARCHAR(100) NOT NULL,
    target VARCHAR(150),
    description TEXT,
    ip_address INET,
    user_agent VARCHAR(500),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);

-- 2. Tabel PELANGGAN (Data Pemilik AC)
CREATE TABLE IF NOT EXISTS pelanggan (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(25) NOT NULL,
    email VARCHAR(150),
    alamat TEXT NOT NULL,
    catatan TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'aktif' CHECK (status IN ('aktif', 'nonaktif')),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE pelanggan ADD COLUMN IF NOT EXISTS email VARCHAR(150);
ALTER TABLE pelanggan ADD COLUMN IF NOT EXISTS catatan TEXT;
ALTER TABLE pelanggan ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'aktif';
ALTER TABLE pelanggan DROP CONSTRAINT IF EXISTS pelanggan_status_check;
ALTER TABLE pelanggan ADD CONSTRAINT pelanggan_status_check CHECK (status IN ('aktif', 'nonaktif'));

ALTER TABLE users ADD COLUMN IF NOT EXISTS pelanggan_id UUID REFERENCES pelanggan(id) ON DELETE SET NULL;

-- 2b. Tabel PENGATURAN WEBSITE (Branding yang dapat diubah owner)
CREATE TABLE IF NOT EXISTS website_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO website_settings (setting_key, setting_value)
VALUES
    ('site_name', 'PT AFE Teknik'),
    ('site_slogan', 'Manajemen & Servis'),
    ('site_logo', ''),
    ('maintenance_mode', '0'),
    ('maintenance_message', 'Website sedang dalam pemeliharaan operasional. Beberapa fitur mungkin tidak tersedia sementara.')
ON CONFLICT (setting_key) DO NOTHING;

-- 3. Tabel UNIT_AC (Data Unit AC milik Pelanggan)
CREATE TABLE IF NOT EXISTS unit_ac (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pelanggan_id UUID NOT NULL REFERENCES pelanggan(id) ON DELETE CASCADE,
    nama_unit VARCHAR(100) NOT NULL,
    merk VARCHAR(50) NOT NULL,
    jenis VARCHAR(50) NOT NULL,
    kapasitas VARCHAR(20) NOT NULL,
    nomor_seri VARCHAR(100),
    tanggal_pemasangan DATE,
    catatan TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE unit_ac ADD COLUMN IF NOT EXISTS tanggal_pemasangan DATE;
ALTER TABLE unit_ac ADD COLUMN IF NOT EXISTS catatan TEXT;

-- 4. Tabel SERVIS (Riwayat Perawatan / Servis AC)
CREATE TABLE IF NOT EXISTS servis (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pelanggan_id UUID NOT NULL REFERENCES pelanggan(id) ON DELETE CASCADE,
    unit_ac_id UUID REFERENCES unit_ac(id) ON DELETE SET NULL,
    tanggal_servis DATE NOT NULL,
    jenis_servis VARCHAR(50) NOT NULL,
    teknisi VARCHAR(100) NOT NULL,
    jumlah_unit INTEGER NOT NULL DEFAULT 1 CHECK (jumlah_unit > 0),
    detail_unit TEXT,
    kondisi_ac VARCHAR(100),
    keluhan TEXT,
    tindakan TEXT,
    sparepart TEXT,
    foto_url VARCHAR(500),
    biaya NUMERIC(12, 2) NOT NULL DEFAULT 0,
    catatan TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE servis ALTER COLUMN unit_ac_id DROP NOT NULL;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS jumlah_unit INTEGER NOT NULL DEFAULT 1;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS detail_unit TEXT;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS kondisi_ac VARCHAR(100);
ALTER TABLE servis ADD COLUMN IF NOT EXISTS keluhan TEXT;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS tindakan TEXT;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS sparepart TEXT;
ALTER TABLE servis ADD COLUMN IF NOT EXISTS foto_url VARCHAR(500);
ALTER TABLE servis DROP CONSTRAINT IF EXISTS servis_jenis_servis_check;
ALTER TABLE servis DROP CONSTRAINT IF EXISTS servis_jumlah_unit_check;
ALTER TABLE servis ADD CONSTRAINT servis_jumlah_unit_check CHECK (jumlah_unit > 0);

-- 5. Tabel NOTIFIKASI (Pencatatan Notifikasi Reminder Servis)
CREATE TABLE IF NOT EXISTS notifikasi (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pelanggan_id UUID NOT NULL REFERENCES pelanggan(id) ON DELETE CASCADE,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    tanggal DATE NOT NULL,
    status_pengiriman VARCHAR(30) NOT NULL DEFAULT 'belum_dihubungi' CHECK (status_pengiriman IN ('belum_dihubungi', 'reminder_terkirim', 'menunggu_konfirmasi', 'sudah_dijadwalkan', 'servis_selesai')),
    dikirim_pada TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL CHECK (status IN ('unread', 'read')) DEFAULT 'unread',
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE notifikasi ADD COLUMN IF NOT EXISTS status_pengiriman VARCHAR(30) NOT NULL DEFAULT 'belum_dihubungi';
ALTER TABLE notifikasi ADD COLUMN IF NOT EXISTS dikirim_pada TIMESTAMPTZ;
ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_status_pengiriman_check;
ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_status_pengiriman_check CHECK (status_pengiriman IN ('belum_dihubungi', 'reminder_terkirim', 'menunggu_konfirmasi', 'sudah_dijadwalkan', 'servis_selesai'));

-- 5b. Jadwal kunjungan servis yang dibuat setelah pelanggan mengonfirmasi reminder
CREATE TABLE IF NOT EXISTS jadwal_servis (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pelanggan_id UUID NOT NULL REFERENCES pelanggan(id) ON DELETE CASCADE,
    unit_ac_id UUID REFERENCES unit_ac(id) ON DELETE SET NULL,
    jumlah_unit INTEGER NOT NULL DEFAULT 1 CHECK (jumlah_unit > 0),
    detail_unit TEXT,
    teknisi_id UUID REFERENCES users(id) ON DELETE SET NULL,
    tanggal DATE NOT NULL,
    jam TIME,
    status VARCHAR(30) NOT NULL DEFAULT 'menunggu_konfirmasi' CHECK (status IN ('menunggu_konfirmasi', 'dijadwalkan', 'sedang_dikerjakan', 'selesai', 'dibatalkan')),
    catatan TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE jadwal_servis ADD COLUMN IF NOT EXISTS jumlah_unit INTEGER NOT NULL DEFAULT 1;
ALTER TABLE jadwal_servis ADD COLUMN IF NOT EXISTS detail_unit TEXT;
ALTER TABLE jadwal_servis DROP CONSTRAINT IF EXISTS jadwal_servis_jumlah_unit_check;
ALTER TABLE jadwal_servis ADD CONSTRAINT jadwal_servis_jumlah_unit_check CHECK (jumlah_unit > 0);

CREATE INDEX IF NOT EXISTS idx_jadwal_servis_tanggal ON jadwal_servis(tanggal);
CREATE INDEX IF NOT EXISTS idx_jadwal_servis_status ON jadwal_servis(status);

-- 6. Tabel PRODUK (Katalog produk dan informasi maintenance)
CREATE TABLE IF NOT EXISTS produk (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tipe VARCHAR(20) NOT NULL DEFAULT 'produk' CHECK (tipe IN ('produk', 'layanan')),
    nama VARCHAR(150) NOT NULL,
    kategori VARCHAR(80) NOT NULL,
    deskripsi TEXT,
    harga NUMERIC(12, 2) NOT NULL DEFAULT 0 CHECK (harga >= 0),
    maintenance TEXT,
    gambar_url VARCHAR(500),
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE produk ADD COLUMN IF NOT EXISTS tipe VARCHAR(20) NOT NULL DEFAULT 'produk';
ALTER TABLE produk DROP CONSTRAINT IF EXISTS produk_tipe_check;
ALTER TABLE produk ADD CONSTRAINT produk_tipe_check CHECK (tipe IN ('produk', 'layanan'));

-- 7. Tabel SUBSCRIPTIONS (Web Push per perangkat pengguna)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    endpoint TEXT NOT NULL UNIQUE,
    subscription_json JSONB NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- 8. Tabel TEMPLATE WHATSAPP (kalimat reminder yang dapat diedit staf)
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama VARCHAR(100) NOT NULL,
    isi TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO whatsapp_templates (nama, isi, aktif)
SELECT
    'Reminder servis berkala',
    E'Halo Bapak/Ibu {nama_pelanggan},\n\nKami dari {nama_bisnis} ingin menginformasikan bahwa unit AC {nama_unit} terakhir dilakukan servis pada {tanggal_servis}.\n\nJadwal perawatan berikutnya: {jadwal_berikutnya}. Apakah Bapak/Ibu ingin menjadwalkan servis AC?\n\nTerima kasih.\n\n{nama_bisnis}',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_templates);

-- Index untuk performa
CREATE INDEX IF NOT EXISTS idx_unit_ac_pelanggan ON unit_ac(pelanggan_id);
CREATE INDEX IF NOT EXISTS idx_servis_pelanggan ON servis(pelanggan_id);
CREATE INDEX IF NOT EXISTS idx_servis_unit ON servis(unit_ac_id);
CREATE INDEX IF NOT EXISTS idx_servis_tanggal ON servis(tanggal_servis);
CREATE INDEX IF NOT EXISTS idx_notifikasi_pelanggan ON notifikasi(pelanggan_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_produk_aktif ON produk(aktif);
 
-- Contoh Data Pelanggan Budi Santoso
INSERT INTO pelanggan (id, nama, no_hp, alamat)
VALUES (
    'a0000000-0000-0000-0000-000000000001',
    'Budi Santoso',
    '081234567890',
    'Jl. Anggrek No. 12, Bekasi'
) ON CONFLICT (id) DO NOTHING;

-- Contoh Data Unit AC Budi Santoso
INSERT INTO unit_ac (id, pelanggan_id, nama_unit, merk, jenis, kapasitas, nomor_seri)
VALUES (
    'b0000000-0000-0000-0000-000000000001',
    'a0000000-0000-0000-0000-000000000001',
    'AC Kamar Utama',
    'Daikin',
    'Split',
    '1 PK',
    'DKN-00912'
) ON CONFLICT (id) DO NOTHING;

-- Contoh Servis Terakhir: 10 Juni 2026
INSERT INTO servis (pelanggan_id, unit_ac_id, tanggal_servis, jenis_servis, teknisi, biaya, catatan)
VALUES (
    'a0000000-0000-0000-0000-000000000001',
    'b0000000-0000-0000-0000-000000000001',
    '2026-06-10',
    'Cuci AC',
    'Teknisi Varrel',
    75000,
    'Kondisi unit bersih, filter dicuci, freon normal.'
);
