import { createHash } from "node:crypto";
import {
	createSession,
	csrfFor,
	currentUser,
	destroySession,
	hashPassword,
	HttpError,
	requireRoles,
	userCanSeeCustomer,
	verifyCsrf,
	verifyPassword,
} from "./auth.js";
import { withDatabase } from "./db.js";
import { sendWebPush } from "./push.js";
import { deleteForm, escapeHtml, link, page, renderForm, renderTable } from "./views.js";

const CUSTOMER_FIELDS = [
	{ name: "nama", label: "Nama pelanggan", required: true, maxLength: 100 },
	{ name: "no_hp", label: "Nomor HP / WhatsApp", required: true, maxLength: 25 },
	{ name: "email", label: "Email", type: "email", maxLength: 150 },
	{ name: "alamat", label: "Alamat", required: true, type: "textarea", maxLength: 2000 },
	{ name: "catatan", label: "Catatan", type: "textarea", maxLength: 2000 },
];
const UNIT_FIELDS = [
	{ name: "pelanggan_id", label: "Pelanggan", type: "select", required: true },
	{ name: "nama_unit", label: "Nama unit", required: true, maxLength: 100 },
	{ name: "merk", label: "Merek", required: true, maxLength: 50 },
	{ name: "jenis", label: "Jenis AC", required: true, maxLength: 50 },
	{ name: "kapasitas", label: "Kapasitas", required: true, maxLength: 20 },
	{ name: "nomor_seri", label: "Nomor seri", maxLength: 100 },
	{ name: "tanggal_pemasangan", label: "Tanggal pemasangan", type: "date" },
	{ name: "catatan", label: "Catatan", type: "textarea", maxLength: 2000 },
];
const SERVICE_FIELDS = [
	{ name: "pelanggan_id", label: "Pelanggan", type: "select", required: true },
	{ name: "unit_ac_id", label: "Unit AC (opsional)", type: "select" },
	{ name: "tanggal_servis", label: "Tanggal servis", type: "date", required: true },
	{ name: "jenis_servis", label: "Jenis servis", required: true, maxLength: 50 },
	{ name: "teknisi", label: "Teknisi", required: true, maxLength: 100 },
	{ name: "jumlah_unit", label: "Jumlah unit", type: "number", min: 1, required: true },
	{ name: "detail_unit", label: "Detail unit", type: "textarea", maxLength: 2000 },
	{ name: "kondisi_ac", label: "Kondisi AC", maxLength: 100 },
	{ name: "keluhan", label: "Keluhan", type: "textarea", maxLength: 2000 },
	{ name: "tindakan", label: "Tindakan", type: "textarea", maxLength: 2000 },
	{ name: "sparepart", label: "Sparepart", type: "textarea", maxLength: 2000 },
	{ name: "foto_url", label: "URL foto servis", type: "url", maxLength: 500 },
	{ name: "biaya", label: "Biaya (Rp)", type: "number", min: 0, required: true },
	{ name: "catatan", label: "Catatan", type: "textarea", maxLength: 2000 },
];
const PRODUCT_FIELDS = [
	{ name: "tipe", label: "Tipe", type: "select", required: true, options: [{ value: "produk", label: "Produk" }, { value: "layanan", label: "Layanan" }] },
	{ name: "nama", label: "Nama", required: true, maxLength: 150 },
	{ name: "kategori", label: "Kategori", required: true, maxLength: 80 },
	{ name: "deskripsi", label: "Deskripsi", type: "textarea", maxLength: 5000 },
	{ name: "harga", label: "Harga (Rp)", type: "number", min: 0, required: true },
	{ name: "maintenance", label: "Informasi maintenance", type: "textarea", maxLength: 5000 },
	{ name: "gambar_url", label: "URL gambar", type: "url", maxLength: 500 },
	{ name: "aktif", label: "Status", type: "select", options: [{ value: "true", label: "Aktif" }, { value: "false", label: "Nonaktif" }] },
];
const SCHEDULE_STATUSES = [
	"menunggu_konfirmasi", "dijadwalkan", "sedang_dikerjakan", "selesai", "dibatalkan",
];
const DEFAULT_SETTINGS = {
	site_name: "PT AFE Teknik",
	site_slogan: "Manajemen & Servis",
	site_logo: "",
	maintenance_mode: "0",
	maintenance_message: "Website sedang dalam pemeliharaan operasional.",
};

function responseRedirect(path, headers = {}) {
	return new Response(null, { status: 303, headers: { Location: path, ...headers } });
}

function getValue(form, name, fallback = "") {
	const value = form.get(name);
	return typeof value === "string" ? value.trim() : fallback;
}

function integer(value, label, min = 0, max = 100000000) {
	const number = Number(value);
	if (!Number.isInteger(number) || number < min || number > max) {
		throw new HttpError(400, `${label} tidak valid.`);
	}
	return number;
}

function money(value) {
	const number = Number(value);
	if (!Number.isFinite(number) || number < 0) throw new HttpError(400, "Nilai biaya tidak valid.");
	return number;
}

function required(form, name, label, max = 5000) {
	const value = getValue(form, name);
	if (!value || value.length > max) throw new HttpError(400, `${label} wajib diisi (maksimal ${max} karakter).`);
	return value;
}

function nullable(form, name, max = 5000) {
	const value = getValue(form, name);
	if (value.length > max) throw new HttpError(400, `Nilai ${name} terlalu panjang.`);
	return value || null;
}

function notice(url) {
	return url.searchParams.get("notice") ?? "";
}

function render(title, content, ctx, error = "", status = 200) {
	return page(title, content, { user: ctx.user, csrf: ctx.csrf, csrfCookie: ctx.csrfCookie, pushPublicKey: ctx.env.VAPID_PUBLIC_KEY ?? "", settings: ctx.settings, status, notice: notice(ctx.url), error });
}

function postCsrf(form, request, env) {
	return verifyCsrf(form, request, env.SESSION_SECRET);
}

async function siteSettings(db) {
	const result = await db.query("SELECT setting_key, setting_value FROM website_settings");
	return { ...DEFAULT_SETTINGS, ...Object.fromEntries(result.rows.map((row) => [row.setting_key, row.setting_value])) };
}

async function customerOptions(db) {
	const { rows } = await db.query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama");
	return rows.map((row) => ({ value: row.id, label: `${row.nama} (${row.no_hp})` }));
}

async function unitOptions(db, customerId = "") {
	const values = [];
	let sql = "SELECT id, nama_unit, merk, kapasitas, pelanggan_id FROM unit_ac";
	if (customerId) {
		values.push(customerId);
		sql += " WHERE pelanggan_id = $1";
	}
	sql += " ORDER BY nama_unit";
	const { rows } = await db.query(sql, values);
	return [{ value: "", label: "Tidak memilih unit tertentu" }, ...rows.map((row) => ({
		value: row.id,
		label: `${row.nama_unit} · ${row.merk} ${row.kapasitas}`,
	}))];
}

function showCustomer(user, customerId) {
	if (!userCanSeeCustomer(user, customerId)) throw new HttpError(403, "Data pelanggan ini tidak dapat diakses.");
}

async function loginPage(ctx, db, error = "") {
	if (ctx.user) return responseRedirect("/dashboard");
	const settings = ctx.settings;
	const content = `<section class="card" style="max-width:520px;margin:auto"><p class="muted">Masuk untuk mengelola operasional servis AC.</p>${renderForm("/auth/login", [
		{ name: "username", label: "Username", required: true, maxLength: 50 },
		{ name: "password", label: "Password", type: "password", required: true, maxLength: 200 },
	], ctx.csrf, { submit: "Masuk" })}<p class="muted">Belum punya akun? <a href="/auth/register">Daftar</a></p></section>`;
	return page(`Masuk · ${settings.site_name}`, content, { csrf: ctx.csrf, csrfCookie: ctx.csrfCookie, pushPublicKey: ctx.env.VAPID_PUBLIC_KEY ?? "", settings, error });
}

async function registerPage(ctx, db, error = "") {
	if (ctx.user) return responseRedirect("/dashboard");
	const settings = ctx.settings;
	const content = `<section class="card" style="max-width:520px;margin:auto">${renderForm("/auth/register", [
		{ name: "nama", label: "Nama lengkap", required: true, maxLength: 100 },
		{ name: "username", label: "Username", required: true, maxLength: 50 },
		{ name: "password", label: "Password (minimal 8 karakter)", type: "password", required: true, maxLength: 200 },
		{ name: "password_confirmation", label: "Ulangi password", type: "password", required: true, maxLength: 200 },
	], ctx.csrf, { submit: "Daftar" })}<p class="muted">Sudah punya akun? <a href="/auth/login">Masuk</a></p></section>`;
	return page(`Daftar · ${settings.site_name}`, content, { csrf: ctx.csrf, csrfCookie: ctx.csrfCookie, pushPublicKey: ctx.env.VAPID_PUBLIC_KEY ?? "", settings, error });
}

async function home(ctx, db) {
	const settings = ctx.settings;
	const { rows } = await db.query("SELECT nama, kategori, deskripsi, harga, maintenance, gambar_url FROM produk WHERE aktif = TRUE ORDER BY created_at DESC LIMIT 6");
	const products = rows.map((row) => `<article class="card">${row.gambar_url ? `<img src="${escapeHtml(row.gambar_url)}" alt="" loading="lazy" style="width:100%;max-height:180px;object-fit:cover">` : ""}<h3>${escapeHtml(row.nama)}</h3><p class="muted">${escapeHtml(row.kategori)}</p><p>${escapeHtml(row.deskripsi)}</p><strong>Rp${Number(row.harga).toLocaleString("id-ID")}</strong><p class="muted">${escapeHtml(row.maintenance)}</p></article>`).join("");
	const content = `<section class="card"><h2>${escapeHtml(settings.site_name)}</h2><p>${escapeHtml(settings.site_slogan)}</p><p>Kelola pelanggan, unit AC, riwayat servis, dan jadwal perawatan dalam satu sistem.</p>${ctx.user ? link("Buka dashboard", ctx.user.role === "user" ? "/user" : "/dashboard") : link("Masuk", "/auth/login")}</section><h2>Produk & layanan</h2><div class="grid">${products || `<p class="muted">Belum ada produk yang ditampilkan.</p>`}</div>`;
	return render("Beranda", content, ctx);
}

async function dashboard(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const [{ rows: countRows }, { rows: recent }, { rows: reminders }] = await Promise.all([
		db.query("SELECT (SELECT COUNT(*) FROM pelanggan) AS pelanggan, (SELECT COUNT(*) FROM unit_ac) AS unit, (SELECT COUNT(*) FROM servis WHERE date_trunc('month', tanggal_servis) = date_trunc('month', CURRENT_DATE)) AS servis_bulan_ini"),
		db.query("SELECT s.id, s.tanggal_servis, s.jenis_servis, s.teknisi, s.biaya, p.nama AS pelanggan FROM servis s JOIN pelanggan p ON p.id=s.pelanggan_id ORDER BY s.tanggal_servis DESC, s.created_at DESC LIMIT 5"),
		db.query("SELECT p.id, p.nama, p.no_hp, MAX(s.tanggal_servis) AS terakhir FROM pelanggan p LEFT JOIN servis s ON s.pelanggan_id=p.id GROUP BY p.id ORDER BY p.nama LIMIT 100"),
	]);
	const stats = countRows[0];
	const needsService = reminders.filter((row) => row.terakhir && reminderInfo(row.terakhir).status !== "aman").length;
	const content = `<div class="grid">${stat("Pelanggan", stats.pelanggan)}${stat("Unit AC", stats.unit)}${stat("Servis bulan ini", stats.servis_bulan_ini)}${stat("Perlu pengingat", needsService)}</div><section class="card"><h2>Servis terbaru</h2>${renderTable([{ label: "Tanggal", key: "tanggal_servis" }, { label: "Pelanggan", key: "pelanggan" }, { label: "Pekerjaan", key: "jenis_servis" }, { label: "Teknisi", key: "teknisi" }, { label: "Biaya", render: (row) => `Rp${Number(row.biaya).toLocaleString("id-ID")}` }], recent, { empty: "Belum ada servis." })}</section><div class="actions">${link("Tambah pelanggan", "/pelanggan/new")}${link("Catat servis", "/servis/new")}${link("Buka pengingat", "/pengingat", "secondary")}</div>`;
	return render("Ringkasan operasional", content, ctx);
}

function stat(label, value) {
	const display = typeof value === "number" || /^\d+$/.test(String(value))
		? Number(value).toLocaleString("id-ID")
		: String(value);
	return `<article class="card"><div class="muted">${escapeHtml(label)}</div><div class="stat">${escapeHtml(display)}</div></article>`;
}

export function reminderInfo(lastService, todayValue = new Date()) {
	const start = new Date(`${lastService}T00:00:00Z`);
	const next = new Date(start);
	next.setUTCMonth(next.getUTCMonth() + 3);
	const today = todayValue instanceof Date ? todayValue : new Date(`${todayValue}T00:00:00Z`);
	const day = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate());
	const left = Math.ceil((next.getTime() - day) / 86400000);
	return { date: next.toISOString().slice(0, 10), days: left, status: left < 0 ? "terlambat" : left === 0 ? "jatuh_tempo" : left <= 14 ? "segera" : "aman" };
}

async function customers(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi", "user"]);
	const search = ctx.url.searchParams.get("q")?.trim() ?? "";
	const args = [];
	let sql = "SELECT p.id, p.nama, p.no_hp, p.email, p.status, COUNT(DISTINCT u.id) AS unit FROM pelanggan p LEFT JOIN unit_ac u ON u.pelanggan_id=p.id";
	if (ctx.user.role === "user") {
		sql += " WHERE p.id=$1";
		args.push(ctx.user.pelanggan_id);
	} else if (search) {
		sql += " WHERE (p.nama ILIKE $1 OR p.no_hp ILIKE $1 OR p.alamat ILIKE $1)";
		args.push(`%${search}%`);
	}
	sql += " GROUP BY p.id ORDER BY p.nama LIMIT 200";
	const { rows } = await db.query(sql, args);
	const actions = (row) => `<div class="actions">${link("Detail", `/pelanggan/${row.id}`, "secondary")}${ctx.user.role !== "teknisi" && ctx.user.role !== "user" ? link("Edit", `/pelanggan/${row.id}/edit`, "secondary") : ""}</div>`;
	const table = renderTable([{ label: "Nama", key: "nama" }, { label: "No. HP", key: "no_hp" }, { label: "Email", key: "email" }, { label: "Unit", key: "unit" }, { label: "Status", key: "status" }], rows, { actions, empty: "Belum ada pelanggan." });
	const searchForm = `<form class="search" method="get"><input name="q" value="${escapeHtml(search)}" placeholder="Cari nama, nomor HP, atau alamat"><button>Cari</button></form>`;
	const content = `${ctx.user.role !== "user" ? link("Tambah pelanggan", "/pelanggan/new") : ""}${searchForm}${table}`;
	return render("Pelanggan", content, ctx);
}

async function customerDetail(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi", "user"]);
	showCustomer(ctx.user, id);
	const [{ rows: customerRows }, { rows: units }, { rows: services }] = await Promise.all([
		db.query("SELECT * FROM pelanggan WHERE id=$1", [id]),
		db.query("SELECT * FROM unit_ac WHERE pelanggan_id=$1 ORDER BY nama_unit", [id]),
		db.query("SELECT * FROM servis WHERE pelanggan_id=$1 ORDER BY tanggal_servis DESC, created_at DESC LIMIT 100", [id]),
	]);
	const customer = customerRows[0];
	if (!customer) throw new HttpError(404, "Pelanggan tidak ditemukan.");
	const unitTable = renderTable([{ label: "Unit", key: "nama_unit" }, { label: "Merek", key: "merk" }, { label: "Jenis", key: "jenis" }, { label: "Kapasitas", key: "kapasitas" }, { label: "Nomor seri", key: "nomor_seri" }], units, { actions: (row) => ctx.user.role === "user" ? "" : `<div class="actions">${link("Edit", `/unit/${row.id}/edit`, "secondary")}${deleteForm(`/unit/${row.id}/delete`, ctx.csrf)}</div>`, empty: "Belum ada unit AC." });
	const serviceTable = renderTable([{ label: "Tanggal", key: "tanggal_servis" }, { label: "Jenis", key: "jenis_servis" }, { label: "Teknisi", key: "teknisi" }, { label: "Jumlah unit", key: "jumlah_unit" }, { label: "Biaya", render: (row) => `Rp${Number(row.biaya).toLocaleString("id-ID")}` }], services, { actions: (row) => link("Invoice", `/servis/${row.id}/invoice`, "secondary"), empty: "Belum ada riwayat servis." });
	const content = `<section class="card"><div class="actions">${link("Kembali", "/pelanggan", "secondary")}${ctx.user.role !== "user" ? link("Edit pelanggan", `/pelanggan/${id}/edit`, "secondary") : ""}${ctx.user.role !== "user" ? link("Catat servis", `/servis/new?pelanggan_id=${id}`) : ""}${ctx.user.role === "admin" || ctx.user.role === "owner" ? deleteForm(`/pelanggan/${id}/delete`, ctx.csrf) : ""}</div><h2>${escapeHtml(customer.nama)}</h2><p>${escapeHtml(customer.no_hp)} · ${escapeHtml(customer.email)}</p><p>${escapeHtml(customer.alamat)}</p><p>${escapeHtml(customer.catatan)}</p></section><h2>Unit AC</h2>${unitTable}<h2>Riwayat servis</h2>${serviceTable}`;
	return render(`Detail pelanggan · ${customer.nama}`, content, ctx);
}

async function saveCustomer(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const nama = required(form, "nama", "Nama pelanggan", 100);
	const noHp = required(form, "no_hp", "Nomor HP", 25);
	const email = nullable(form, "email", 150);
	const alamat = required(form, "alamat", "Alamat", 2000);
	const catatan = nullable(form, "catatan", 2000);
	if (!/^\+?[0-9][0-9\s-]{7,20}$/.test(noHp)) throw new HttpError(400, "Nomor HP tidak valid.");
	if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new HttpError(400, "Alamat email tidak valid.");

	let customerId = id;
	if (id) {
		await db.query("UPDATE pelanggan SET nama=$1, no_hp=$2, email=$3, alamat=$4, catatan=$5, updated_at=CURRENT_TIMESTAMP WHERE id=$6", [nama, noHp, email, alamat, catatan, id]);
	} else {
		const result = await db.query("INSERT INTO pelanggan (nama,no_hp,email,alamat,catatan) VALUES ($1,$2,$3,$4,$5) RETURNING id", [nama, noHp, email, alamat, catatan]);
		customerId = result.rows[0].id;
	}
	await audit(db, ctx, id ? "customer_updated" : "customer_created", customerId, "Data pelanggan disimpan.");
	return responseRedirect(`/pelanggan/${customerId}?notice=${encodeURIComponent("Data pelanggan berhasil disimpan.")}`);
}

async function customerForm(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin"]);
	const values = {};
	if (id) {
		const { rows } = await db.query("SELECT nama,no_hp,email,alamat,catatan FROM pelanggan WHERE id=$1", [id]);
		if (!rows[0]) throw new HttpError(404, "Pelanggan tidak ditemukan.");
		Object.assign(values, rows[0]);
	}
	const form = renderForm(id ? `/pelanggan/${id}/edit` : "/pelanggan/new", CUSTOMER_FIELDS, ctx.csrf, { values, submit: id ? "Simpan perubahan" : "Tambah pelanggan" });
	return render(id ? "Edit pelanggan" : "Tambah pelanggan", `<section class="card">${form}</section>`, ctx);
}

async function deleteCustomer(ctx, db, id) {
	requireRoles(ctx.user, ["admin"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	await db.query("DELETE FROM pelanggan WHERE id=$1", [id]);
	await audit(db, ctx, "customer_deleted", id, "Data pelanggan dihapus.");
	return responseRedirect("/pelanggan?notice=Data%20pelanggan%20dihapus");
}

async function units(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const values = [];
	let sql = "SELECT u.*, p.nama AS pelanggan FROM unit_ac u JOIN pelanggan p ON p.id=u.pelanggan_id";
	if (ctx.user.role === "user") {
		sql += " WHERE p.id=$1";
		values.push(ctx.user.pelanggan_id);
	}
	sql += " ORDER BY p.nama,u.nama_unit LIMIT 300";
	const { rows } = await db.query(sql, values);
	return render("Unit AC", `${ctx.user.role !== "user" ? link("Tambah unit AC", "/unit/new") : ""}${renderTable([
		{ label: "Nama unit", key: "nama_unit" }, { label: "Pelanggan", key: "pelanggan" },
		{ label: "Merek", key: "merk" }, { label: "Jenis", key: "jenis" },
		{ label: "Kapasitas", key: "kapasitas" }, { label: "Nomor seri", key: "nomor_seri" },
	], rows, { actions: (row) => link("Edit", `/unit/${row.id}/edit`, "secondary"), empty: "Belum ada unit AC." })}`, ctx);
}

async function unitForm(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const customersList = await customerOptions(db);
	if (ctx.user.role === "user") throw new HttpError(403, "Akses ditolak.");
	const fields = UNIT_FIELDS.map((field) => field.name === "pelanggan_id" ? { ...field, options: customersList } : field);
	let values = {};
	if (id) {
		const { rows } = await db.query("SELECT * FROM unit_ac WHERE id=$1", [id]);
		if (!rows[0]) throw new HttpError(404, "Unit AC tidak ditemukan.");
		values = rows[0];
	}
	const remove = id ? deleteForm(`/unit/${id}/delete`, ctx.csrf) : "";
	return render(id ? "Edit unit AC" : "Tambah unit AC", `<section class="card">${renderForm(id ? `/unit/${id}/edit` : "/unit/new", fields, ctx.csrf, { values, submit: id ? "Simpan perubahan" : "Simpan unit" })}${remove}</section>`, ctx);
}

async function saveUnit(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const customerId = required(form, "pelanggan_id", "Pelanggan", 50);
	const name = required(form, "nama_unit", "Nama unit", 100);
	const brand = required(form, "merk", "Merek", 50);
	const kind = required(form, "jenis", "Jenis AC", 50);
	const capacity = required(form, "kapasitas", "Kapasitas", 20);
	const serial = nullable(form, "nomor_seri", 100);
	const installationDate = nullable(form, "tanggal_pemasangan", 10);
	const notes = nullable(form, "catatan", 2000);
	if (id) {
		await db.query("UPDATE unit_ac SET pelanggan_id=$1,nama_unit=$2,merk=$3,jenis=$4,kapasitas=$5,nomor_seri=$6,tanggal_pemasangan=$7,catatan=$8 WHERE id=$9", [customerId, name, brand, kind, capacity, serial, installationDate, notes, id]);
	} else {
		await db.query("INSERT INTO unit_ac (pelanggan_id,nama_unit,merk,jenis,kapasitas,nomor_seri,tanggal_pemasangan,catatan) VALUES ($1,$2,$3,$4,$5,$6,$7,$8)", [customerId, name, brand, kind, capacity, serial, installationDate, notes]);
	}
	return responseRedirect(`/pelanggan/${customerId}?notice=Data%20unit%20disimpan`);
}

async function deleteUnit(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const { rows } = await db.query("DELETE FROM unit_ac WHERE id=$1 RETURNING pelanggan_id", [id]);
	return responseRedirect(rows[0] ? `/pelanggan/${rows[0].pelanggan_id}?notice=Unit%20dihapus` : "/unit");
}

async function services(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const { rows } = await db.query("SELECT s.id,s.tanggal_servis,s.jenis_servis,s.teknisi,s.jumlah_unit,s.biaya,p.nama AS pelanggan FROM servis s JOIN pelanggan p ON p.id=s.pelanggan_id ORDER BY s.tanggal_servis DESC,s.created_at DESC LIMIT 300");
	const content = `${link("Catat servis / buat invoice", "/servis/new")}${renderTable([
		{ label: "Tanggal", key: "tanggal_servis" }, { label: "Pelanggan", key: "pelanggan" },
		{ label: "Pekerjaan", key: "jenis_servis" }, { label: "Teknisi", key: "teknisi" },
		{ label: "Unit", key: "jumlah_unit" }, { label: "Biaya", render: (row) => `Rp${Number(row.biaya).toLocaleString("id-ID")}` },
	], rows, { actions: (row) => `<div class="actions">${link("Invoice", `/servis/${row.id}/invoice`, "secondary")}${deleteForm(`/servis/${row.id}/delete`, ctx.csrf)}</div>`, empty: "Belum ada catatan servis." })}`;
	return render("Riwayat servis", content, ctx);
}

async function serviceForm(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const customersList = await customerOptions(db);
	const selectedCustomer = ctx.url.searchParams.get("pelanggan_id") ?? "";
	const unitsList = await unitOptions(db, selectedCustomer);
	const fields = SERVICE_FIELDS.map((field) => {
		if (field.name === "pelanggan_id") return { ...field, options: [{ value: "", label: "Pilih pelanggan" }, ...customersList] };
		if (field.name === "unit_ac_id") return { ...field, options: unitsList };
		if (field.name === "tanggal_servis") return { ...field, value: new Date().toISOString().slice(0, 10) };
		if (field.name === "teknisi") return { ...field, value: ctx.user.nama };
		if (field.name === "jumlah_unit") return { ...field, value: "1" };
		if (field.name === "biaya") return { ...field, value: "0" };
		return field;
	});
	return render("Catat servis / buat invoice", `<section class="card"><p class="muted">Invoice dapat mencatat beberapa unit sekaligus.</p>${renderForm("/servis/new", fields, ctx.csrf, { submit: "Simpan servis" })}</section>`, ctx);
}

async function saveService(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const customerId = required(form, "pelanggan_id", "Pelanggan", 50);
	const unitId = nullable(form, "unit_ac_id", 50);
	const date = required(form, "tanggal_servis", "Tanggal servis", 10);
	const type = required(form, "jenis_servis", "Jenis servis", 50);
	const technician = required(form, "teknisi", "Teknisi", 100);
	const count = integer(getValue(form, "jumlah_unit", "1"), "Jumlah unit", 1, 50);
	const detail = nullable(form, "detail_unit", 2000);
	const condition = nullable(form, "kondisi_ac", 100);
	const complaint = nullable(form, "keluhan", 2000);
	const action = nullable(form, "tindakan", 2000);
	const parts = nullable(form, "sparepart", 2000);
	const photo = nullable(form, "foto_url", 500);
	if (photo && !/^https?:\/\//i.test(photo)) throw new HttpError(400, "URL foto servis harus diawali http:// atau https://.");
	const cost = money(getValue(form, "biaya", "0"));
	const notes = nullable(form, "catatan", 2000);
	const result = await db.query("INSERT INTO servis (pelanggan_id,unit_ac_id,tanggal_servis,jenis_servis,teknisi,jumlah_unit,detail_unit,kondisi_ac,keluhan,tindakan,sparepart,foto_url,biaya,catatan) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14) RETURNING id", [customerId, unitId, date, type, technician, count, detail, condition, complaint, action, parts, photo, cost, notes]);
	await audit(db, ctx, "service_created", result.rows[0].id, "Catatan servis dibuat.");
	return responseRedirect(`/servis/${result.rows[0].id}/invoice?notice=Invoice%20servis%20dibuat`);
}

async function serviceInvoice(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const { rows } = await db.query("SELECT s.*,p.nama AS pelanggan,p.no_hp,p.email,p.alamat FROM servis s JOIN pelanggan p ON p.id=s.pelanggan_id WHERE s.id=$1", [id]);
	const item = rows[0];
	if (!item) throw new HttpError(404, "Invoice tidak ditemukan.");
	const value = (label, text) => `<div class="card"><div class="muted">${escapeHtml(label)}</div><strong>${escapeHtml(text)}</strong></div>`;
	const content = `<section class="card"><div class="actions">${link("Kembali", "/servis", "secondary")}<button onclick="window.print()">Cetak invoice</button></div><h2>Invoice servis AC</h2><p>No. invoice: ${escapeHtml(item.id)}</p></section><div class="grid">${value("Pelanggan", item.pelanggan)}${value("Kontak", item.no_hp)}${value("Alamat", item.alamat)}${value("Tanggal servis", item.tanggal_servis)}${value("Jenis pekerjaan", item.jenis_servis)}${value("Teknisi", item.teknisi)}${value("Jumlah unit", item.jumlah_unit)}${value("Detail unit", item.detail_unit)}${value("Biaya", `Rp${Number(item.biaya).toLocaleString("id-ID")}`)}</div><section class="card"><h3>Catatan pekerjaan</h3><p>Keluhan: ${escapeHtml(item.keluhan)}</p><p>Tindakan: ${escapeHtml(item.tindakan)}</p><p>Sparepart: ${escapeHtml(item.sparepart)}</p><p>Kondisi: ${escapeHtml(item.kondisi_ac)}</p><p>${escapeHtml(item.catatan)}</p></section>`;
	return render("Invoice servis", content, ctx);
}

async function deleteService(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	await db.query("DELETE FROM servis WHERE id=$1", [id]);
	return responseRedirect("/servis?notice=Catatan%20servis%20dihapus");
}

async function products(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const { rows } = await db.query("SELECT id,tipe,nama,kategori,harga,aktif,maintenance FROM produk ORDER BY created_at DESC LIMIT 300");
	const content = `${link("Tambah produk / layanan", "/produk/new")}${renderTable([
		{ label: "Tipe", key: "tipe" }, { label: "Nama", key: "nama" }, { label: "Kategori", key: "kategori" },
		{ label: "Harga", render: (row) => `Rp${Number(row.harga).toLocaleString("id-ID")}` },
		{ label: "Aktif", render: (row) => row.aktif ? "Ya" : "Tidak" },
	], rows, { actions: (row) => link("Edit", `/produk/${row.id}/edit`, "secondary"), empty: "Belum ada produk." })}`;
	return render("Produk & layanan", content, ctx);
}

async function productForm(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	let values = { tipe: "produk", aktif: "true", harga: "0" };
	if (id) {
		const { rows } = await db.query("SELECT * FROM produk WHERE id=$1", [id]);
		if (!rows[0]) throw new HttpError(404, "Produk tidak ditemukan.");
		values = { ...rows[0], aktif: String(rows[0].aktif) };
	}
	const form = renderForm(id ? `/produk/${id}/edit` : "/produk/new", PRODUCT_FIELDS, ctx.csrf, { values, submit: id ? "Simpan perubahan" : "Tambah produk" });
	const deleteAction = id ? deleteForm(`/produk/${id}/delete`, ctx.csrf) : "";
	return render(id ? "Edit produk" : "Tambah produk", `<section class="card">${form}${deleteAction}</section>`, ctx);
}

async function saveProduct(ctx, db, id = "") {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const type = getValue(form, "tipe", "produk");
	if (!["produk", "layanan"].includes(type)) throw new HttpError(400, "Tipe produk tidak valid.");
	const name = required(form, "nama", "Nama", 150);
	const category = required(form, "kategori", "Kategori", 80);
	const description = nullable(form, "deskripsi", 5000);
	const price = money(getValue(form, "harga", "0"));
	const maintenance = nullable(form, "maintenance", 5000);
	const image = nullable(form, "gambar_url", 500);
	if (image && !/^https?:\/\//i.test(image)) throw new HttpError(400, "URL gambar harus diawali http:// atau https://.");
	const active = getValue(form, "aktif", "true") === "true";
	if (id) {
		await db.query("UPDATE produk SET tipe=$1,nama=$2,kategori=$3,deskripsi=$4,harga=$5,maintenance=$6,gambar_url=$7,aktif=$8,updated_at=CURRENT_TIMESTAMP WHERE id=$9", [type, name, category, description, price, maintenance, image, active, id]);
	} else {
		await db.query("INSERT INTO produk (tipe,nama,kategori,deskripsi,harga,maintenance,gambar_url,aktif,created_by) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)", [type, name, category, description, price, maintenance, image, active, ctx.user.id]);
	}
	return responseRedirect("/produk?notice=Produk%20berhasil%20disimpan");
}

async function deleteProduct(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	await db.query("DELETE FROM produk WHERE id=$1", [id]);
	return responseRedirect("/produk?notice=Produk%20dihapus");
}

async function maintenance(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi", "user"]);
	const search = ctx.url.searchParams.get("q")?.trim() ?? "";
	const args = search ? [`%${search}%`] : [];
	const filter = search ? " AND (p.nama ILIKE $1 OR p.kategori ILIKE $1 OR p.maintenance ILIKE $1)" : "";
	const { rows } = await db.query(`SELECT p.id,p.nama,p.kategori,p.tipe,p.harga,p.maintenance,p.gambar_url,p.deskripsi FROM produk p WHERE p.aktif=TRUE${filter} ORDER BY p.nama LIMIT 200`, args);
	const cards = rows.map((row) => `<article class="card"><h2>${escapeHtml(row.nama)}</h2><p class="muted">${escapeHtml(row.kategori)} · ${escapeHtml(row.tipe)}</p><p>${escapeHtml(row.deskripsi)}</p><p>${escapeHtml(row.maintenance)}</p><strong>Rp${Number(row.harga).toLocaleString("id-ID")}</strong></article>`).join("");
	const content = `<form class="search"><input name="q" value="${escapeHtml(search)}" placeholder="Cari produk atau panduan perawatan"><button>Cari</button></form><div class="grid">${cards || `<p class="muted">Belum ada informasi produk atau perawatan.</p>`}</div>`;
	return render("Produk & panduan maintenance", content, ctx);
}

async function schedules(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const { rows } = await db.query("SELECT j.*,p.nama AS pelanggan,p.no_hp,u.nama AS teknisi FROM jadwal_servis j JOIN pelanggan p ON p.id=j.pelanggan_id LEFT JOIN users u ON u.id=j.teknisi_id ORDER BY j.tanggal ASC,j.jam ASC NULLS LAST,j.created_at DESC LIMIT 300");
	const statusOptions = SCHEDULE_STATUSES.map((value) => ({ value, label: value.replaceAll("_", " ") }));
	const list = renderTable([
		{ label: "Tanggal", key: "tanggal" }, { label: "Waktu", key: "jam" },
		{ label: "Pelanggan", key: "pelanggan" }, { label: "Telepon", key: "no_hp" },
		{ label: "Unit", render: (row) => `${row.jumlah_unit} · ${escapeHtml(row.detail_unit)}` },
		{ label: "Teknisi", render: (row) => escapeHtml(row.teknisi || "Belum ditugaskan") },
		{ label: "Status", render: (row) => `<form action="/jadwal/${row.id}/status" method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(ctx.csrf)}"><select name="status">${statusOptions.map((option) => `<option value="${option.value}" ${row.status === option.value ? "selected" : ""}>${escapeHtml(option.label)}</option>`).join("")}</select><button class="secondary">Simpan</button></form>` },
	], rows, { empty: "Belum ada jadwal servis." });
	const customerList = await customerOptions(db);
	const fields = [
		{ name: "pelanggan_id", label: "Pelanggan", type: "select", required: true, options: [{ value: "", label: "Pilih pelanggan" }, ...customerList] },
		{ name: "unit_ac_id", label: "Unit (opsional)", type: "select", options: await unitOptions(db) },
		{ name: "tanggal", label: "Tanggal", type: "date", required: true },
		{ name: "jam", label: "Jam", type: "time" },
		{ name: "jumlah_unit", label: "Jumlah unit", type: "number", min: 1, value: "1", required: true },
		{ name: "detail_unit", label: "Detail unit", type: "textarea", maxLength: 2000 },
		{ name: "catatan", label: "Catatan", type: "textarea", maxLength: 2000 },
	];
	const form = `<section class="card"><h2>Tambah jadwal</h2>${renderForm("/jadwal/new", fields, ctx.csrf)}</section>`;
	return render("Jadwal servis", `${form}${list}`, ctx);
}

async function createSchedule(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const customerId = required(form, "pelanggan_id", "Pelanggan", 50);
	const unitId = nullable(form, "unit_ac_id", 50);
	const date = required(form, "tanggal", "Tanggal", 10);
	const time = nullable(form, "jam", 5);
	const count = integer(getValue(form, "jumlah_unit", "1"), "Jumlah unit", 1, 50);
	const detail = nullable(form, "detail_unit", 2000);
	const notes = nullable(form, "catatan", 2000);
	await db.query("INSERT INTO jadwal_servis (pelanggan_id,unit_ac_id,jumlah_unit,detail_unit,teknisi_id,tanggal,jam,status,catatan) VALUES ($1,$2,$3,$4,$5,$6,$7,'dijadwalkan',$8)", [customerId, unitId, count, detail, ctx.user.id, date, time, notes]);
	return responseRedirect("/jadwal?notice=Jadwal%20servis%20dibuat");
}

async function updateScheduleStatus(ctx, db, id) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const status = getValue(form, "status");
	if (!SCHEDULE_STATUSES.includes(status)) throw new HttpError(400, "Status jadwal tidak valid.");
	await db.query("UPDATE jadwal_servis SET status=$1,updated_at=CURRENT_TIMESTAMP WHERE id=$2", [status, id]);
	return responseRedirect("/jadwal?notice=Status%20jadwal%20diperbarui");
}

async function reminders(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const search = ctx.url.searchParams.get("q")?.trim() ?? "";
	const status = ctx.url.searchParams.get("status") ?? "semua";
	const args = search ? [`%${search}%`] : [];
	const where = search ? "WHERE p.nama ILIKE $1 OR p.no_hp ILIKE $1 OR COALESCE(last.detail_unit,'') ILIKE $1" : "";
	const { rows } = await db.query(`SELECT p.id,p.nama,p.no_hp,p.alamat,last.tanggal_servis,last.jenis_servis,last.teknisi,last.jumlah_unit,last.detail_unit FROM pelanggan p LEFT JOIN LATERAL (SELECT tanggal_servis,jenis_servis,teknisi,jumlah_unit,detail_unit FROM servis WHERE pelanggan_id=p.id ORDER BY tanggal_servis DESC,created_at DESC LIMIT 1) last ON TRUE ${where} ORDER BY p.nama LIMIT 300`, args);
	const { rows: templates } = await db.query("SELECT isi FROM whatsapp_templates WHERE aktif=TRUE ORDER BY updated_at DESC LIMIT 1");
	const template = templates[0]?.isi ?? "";
	const items = rows.map((row) => ({ ...row, reminder: row.tanggal_servis ? reminderInfo(row.tanggal_servis) : { status: "belum_pernah", date: "", days: null } }))
		.filter((row) => status === "semua" || row.reminder.status === status);
	const table = renderTable([
		{ label: "Pelanggan", key: "nama" }, { label: "No. HP", key: "no_hp" },
		{ label: "Servis terakhir", render: (row) => escapeHtml(row.tanggal_servis ?? "Belum pernah") },
		{ label: "Jadwal berikutnya", render: (row) => escapeHtml(row.reminder.date || "-") },
		{ label: "Status", render: (row) => escapeHtml(row.reminder.status.replaceAll("_", " ")) },
		{ label: "Sisa hari", render: (row) => row.reminder.days ?? "-" },
	], items, { actions: (row) => `<div class="actions">${link("Detail", `/pelanggan/${row.id}`, "secondary")}${row.tanggal_servis ? `<a class="button" rel="noopener" target="_blank" href="${whatsappUrl(row.no_hp, row.nama, row.detail_unit || "unit AC", row.tanggal_servis, template, ctx.settings.site_name)}">WhatsApp</a>` : ""}${link("Jadwalkan", `/jadwal?notice=${encodeURIComponent(`Hubungi ${row.nama} untuk menentukan jadwal.`)}`, "secondary")}</div>`, empty: "Tidak ada data pengingat." });
	const filters = ["semua", "belum_pernah", "terlambat", "jatuh_tempo", "segera", "aman"].map((item) => `<option value="${item}" ${status === item ? "selected" : ""}>${item.replaceAll("_", " ")}</option>`).join("");
	const content = `<form class="search"><input name="q" value="${escapeHtml(search)}" placeholder="Cari pelanggan, telepon, unit"><select name="status">${filters}</select><button>Filter</button></form>${table}`;
	return render("Pengingat perawatan berkala", content, ctx);
}

export function whatsappUrl(phone, name, unit, date, template = "", businessName = "AC Care") {
	const destination = phone.replace(/^0/, "62").replace(/\D/g, "");
	const next = new Date(`${date}T00:00:00Z`);
	next.setUTCMonth(next.getUTCMonth() + 3);
	const defaultTemplate = "Halo Bapak/Ibu {nama_pelanggan}, kami ingin mengingatkan servis unit AC {nama_unit}. Servis terakhir {tanggal_servis}, jadwal berikutnya {jadwal_berikutnya}. Apakah ingin menjadwalkan servis?";
	const message = (template || defaultTemplate).replace(/\{(nama_pelanggan|nama|nama_unit|unit|tanggal_servis|tanggal|jadwal_berikutnya|nama_bisnis)\}/g, (placeholder, key) => ({
		nama_pelanggan: name,
		nama: name,
		nama_unit: unit,
		unit,
		tanggal_servis: date,
		tanggal: date,
		jadwal_berikutnya: next.toISOString().slice(0, 10),
		nama_bisnis: businessName,
	})[key]);
	return `https://wa.me/${encodeURIComponent(destination)}?text=${encodeURIComponent(message)}`;
}

async function reports(ctx, db) {
	requireRoles(ctx.user, ["admin"]);
	const [{ rows: totals }, { rows: byMonth }, { rows: byTech }] = await Promise.all([
		db.query("SELECT COUNT(*) AS total_servis,COALESCE(SUM(biaya),0) AS pendapatan,COUNT(DISTINCT pelanggan_id) AS pelanggan_terlayani FROM servis"),
		db.query("SELECT date_trunc('month',tanggal_servis)::date AS bulan,COUNT(*) AS jumlah,COALESCE(SUM(biaya),0) AS pendapatan FROM servis WHERE tanggal_servis >= CURRENT_DATE - INTERVAL '12 months' GROUP BY 1 ORDER BY 1 DESC"),
		db.query("SELECT teknisi,COUNT(*) AS jumlah,COALESCE(SUM(biaya),0) AS pendapatan FROM servis GROUP BY teknisi ORDER BY jumlah DESC"),
	]);
	const summary = totals[0];
	return render("Laporan operasional", `<div class="grid">${stat("Total servis", summary.total_servis)}${stat("Pelanggan terlayani", summary.pelanggan_terlayani)}${stat("Pendapatan", `Rp${Number(summary.pendapatan).toLocaleString("id-ID")}`)}</div><h2>Ringkasan bulanan</h2>${renderTable([{ label: "Bulan", key: "bulan" }, { label: "Jumlah servis", key: "jumlah" }, { label: "Pendapatan", render: (row) => `Rp${Number(row.pendapatan).toLocaleString("id-ID")}` }], byMonth)}<h2>Kinerja teknisi</h2>${renderTable([{ label: "Teknisi", key: "teknisi" }, { label: "Servis", key: "jumlah" }, { label: "Pendapatan", render: (row) => `Rp${Number(row.pendapatan).toLocaleString("id-ID")}` }], byTech)}`, ctx);
}

async function accounts(ctx, db) {
	requireRoles(ctx.user, ["owner"]);
	const [{ rows: users }, customersList] = await Promise.all([
		db.query("SELECT id,nama,username,no_hp,role,aktif,pelanggan_id FROM users ORDER BY nama"),
		customerOptions(db),
	]);
	const customerSelect = [{ value: "", label: "Tidak terhubung" }, ...customersList];
	const accountTable = renderTable([
		{ label: "Nama", key: "nama" }, { label: "Username", key: "username" },
		{ label: "Telepon", key: "no_hp" }, { label: "Role", key: "role" },
		{ label: "Aktif", render: (row) => row.aktif ? "Ya" : "Tidak" },
	], users, { actions: (row) => `<details><summary>Kelola</summary><form action="/akun/${row.id}/update" method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(ctx.csrf)}"><label>Role<select name="role">${["owner", "admin", "teknisi", "user", "developer"].map((role) => `<option ${row.role === role ? "selected" : ""}>${role}</option>`).join("")}</select></label><label>Hubungkan pelanggan<select name="pelanggan_id">${customerSelect.map((option) => `<option value="${escapeHtml(option.value)}" ${row.pelanggan_id === option.value ? "selected" : ""}>${escapeHtml(option.label)}</option>`).join("")}</select></label><label>Status<select name="aktif"><option value="true" ${row.aktif ? "selected" : ""}>Aktif</option><option value="false" ${!row.aktif ? "selected" : ""}>Nonaktif</option></select></label><button class="secondary">Simpan</button></form>${row.id !== ctx.user.id ? deleteForm(`/akun/${row.id}/delete`, ctx.csrf) : ""}</details>`, empty: "Belum ada akun." });
	const createForm = renderForm("/akun/new", [
		{ name: "nama", label: "Nama lengkap", required: true, maxLength: 100 },
		{ name: "username", label: "Username", required: true, maxLength: 50 },
		{ name: "no_hp", label: "Nomor HP", maxLength: 25 },
		{ name: "role", label: "Role", type: "select", required: true, options: ["admin", "teknisi", "user", "developer"] },
		{ name: "pelanggan_id", label: "Pelanggan terkait (untuk akun pelanggan)", type: "select", options: customerSelect },
		{ name: "password", label: "Password sementara", type: "password", required: true, maxLength: 200 },
	], ctx.csrf, { submit: "Buat akun" });
	return render("Kelola akun", `<section class="card"><h2>Tambah akun</h2>${createForm}</section>${accountTable}`, ctx);
}

async function createAccount(ctx, db) {
	requireRoles(ctx.user, ["owner"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const name = required(form, "nama", "Nama", 100);
	const username = required(form, "username", "Username", 50).toLowerCase();
	const phone = nullable(form, "no_hp", 25);
	const role = getValue(form, "role");
	if (!/^[a-z0-9._-]{4,50}$/.test(username)) throw new HttpError(400, "Username harus berisi 4–50 huruf kecil, angka, titik, garis bawah, atau tanda hubung.");
	if (!["admin", "teknisi", "user", "developer"].includes(role)) throw new HttpError(400, "Role akun tidak valid.");
	const password = required(form, "password", "Password", 200);
	if (password.length < 8) throw new HttpError(400, "Password minimal 8 karakter.");
	const customerId = nullable(form, "pelanggan_id", 50);
	await db.query("INSERT INTO users (nama,username,no_hp,role,aktif,pelanggan_id,password_hash) VALUES ($1,$2,$3,$4,TRUE,$5,$6)", [name, username, phone, role, customerId, await hashPassword(password)]);
	await audit(db, ctx, "account_created", username, "Akun dibuat.");
	return responseRedirect("/akun?notice=Akun%20berhasil%20dibuat");
}

async function updateAccount(ctx, db, id) {
	requireRoles(ctx.user, ["owner"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const role = getValue(form, "role");
	const active = getValue(form, "aktif");
	const customerId = nullable(form, "pelanggan_id", 50);
	if (!["owner", "admin", "teknisi", "user", "developer"].includes(role) || !["true", "false"].includes(active)) {
		throw new HttpError(400, "Pengaturan akun tidak valid.");
	}
	if (id === ctx.user.id && (active !== "true" || role !== "owner")) {
		throw new HttpError(400, "Akun owner yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan rolenya.");
	}
	const target = await db.query("SELECT role FROM users WHERE id=$1", [id]);
	if (!target.rowCount) throw new HttpError(404, "Akun tidak ditemukan.");
	if (target.rows[0].role === "owner" && id !== ctx.user.id) {
		throw new HttpError(403, "Akun owner lain tidak dapat diubah dari halaman ini.");
	}
	await db.query("UPDATE users SET role=$1,aktif=$2,pelanggan_id=$3,updated_at=CURRENT_TIMESTAMP WHERE id=$4", [role, active === "true", customerId, id]);
	return responseRedirect("/akun?notice=Akun%20diperbarui");
}

async function deleteAccount(ctx, db, id) {
	requireRoles(ctx.user, ["owner"]);
	if (id === ctx.user.id) throw new HttpError(400, "Akun yang sedang digunakan tidak dapat dihapus.");
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const result = await db.query("DELETE FROM users WHERE id=$1 AND role <> 'owner'", [id]);
	if (!result.rowCount) throw new HttpError(403, "Akun owner tidak dapat dihapus.");
	return responseRedirect("/akun?notice=Akun%20dihapus");
}

async function profile(ctx, db) {
	if (!ctx.user) throw new HttpError(401, "Silakan masuk terlebih dahulu.", "/auth/login");
	const phoneForm = renderForm("/profile/contact", [
		{ name: "nama", label: "Nama", required: true, maxLength: 100 },
		{ name: "username", label: "Username", required: true, maxLength: 50 },
		{ name: "no_hp", label: "Nomor HP", maxLength: 25 },
	], ctx.csrf, { values: ctx.user, submit: "Simpan profil" });
	const passwordForm = renderForm("/profile/password", [
		{ name: "current_password", label: "Password saat ini", type: "password", required: true },
		{ name: "new_password", label: "Password baru (minimal 8 karakter)", type: "password", required: true },
		{ name: "password_confirmation", label: "Ulangi password baru", type: "password", required: true },
	], ctx.csrf, { submit: "Ubah password" });
	const content = `<section class="card"><h2>Profil akun</h2>${phoneForm}</section><section class="card"><h2>Ubah password</h2>${passwordForm}</section>${ctx.user.role === "user" ? link("Pengaturan pelanggan", "/user/settings", "secondary") : ""}`;
	return render("Profil", content, ctx);
}

async function updateProfile(ctx, db) {
	if (!ctx.user) throw new HttpError(401, "Silakan masuk terlebih dahulu.", "/auth/login");
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const name = required(form, "nama", "Nama", 100);
	const username = required(form, "username", "Username", 50).toLowerCase();
	const phone = nullable(form, "no_hp", 25);
	if (!/^[a-z0-9._-]{4,50}$/.test(username)) throw new HttpError(400, "Format username tidak valid.");
	await db.query("UPDATE users SET nama=$1,username=$2,no_hp=$3,updated_at=CURRENT_TIMESTAMP WHERE id=$4", [name, username, phone, ctx.user.id]);
	return responseRedirect("/profile?notice=Profil%20diperbarui");
}

async function changePassword(ctx, db) {
	if (!ctx.user) throw new HttpError(401, "Silakan masuk terlebih dahulu.", "/auth/login");
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const current = required(form, "current_password", "Password saat ini", 200);
	const next = required(form, "new_password", "Password baru", 200);
	const confirmation = required(form, "password_confirmation", "Konfirmasi password", 200);
	if (next.length < 8 || !/[A-Za-z]/.test(next) || !/[0-9]/.test(next)) throw new HttpError(400, "Password baru minimal 8 karakter dan harus berisi huruf serta angka.");
	if (next !== confirmation) throw new HttpError(400, "Konfirmasi password baru tidak sama.");
	const { rows } = await db.query("SELECT password_hash FROM users WHERE id=$1", [ctx.user.id]);
	if (!rows[0] || !await verifyPassword(current, rows[0].password_hash)) throw new HttpError(400, "Password saat ini tidak sesuai.");
	await db.query("UPDATE users SET password_hash=$1,updated_at=CURRENT_TIMESTAMP WHERE id=$2", [await hashPassword(next), ctx.user.id]);
	return responseRedirect("/profile?notice=Password%20berhasil%20diubah");
}

async function websiteSettingsPage(ctx, db) {
	requireRoles(ctx.user, ["owner"]);
	const settings = await siteSettings(db);
	const fields = Object.entries(settings).map(([name, value]) => ({
		name, label: name.replaceAll("_", " "), type: name === "maintenance_message" ? "textarea" : "text",
		value, required: name === "site_name",
	}));
	return render("Pengaturan website", `<section class="card">${renderForm("/pengaturan/website", fields, ctx.csrf, { values: settings })}<p class="muted">Logo dapat diatur dengan URL gambar HTTPS. Unggahan file langsung belum tersedia di runtime Workers ini.</p></section>`, ctx);
}

async function saveWebsiteSettings(ctx, db) {
	requireRoles(ctx.user, ["owner"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	for (const key of Object.keys(DEFAULT_SETTINGS)) {
		const value = getValue(form, key, DEFAULT_SETTINGS[key]);
		if (key === "site_name" && !value) throw new HttpError(400, "Nama website wajib diisi.");
		if (value.length > 2000) throw new HttpError(400, `Nilai ${key} terlalu panjang.`);
		if (key === "site_logo" && value && !/^https:\/\//i.test(value)) throw new HttpError(400, "URL logo harus menggunakan HTTPS.");
		await db.query("INSERT INTO website_settings (setting_key,setting_value,updated_at) VALUES ($1,$2,CURRENT_TIMESTAMP) ON CONFLICT (setting_key) DO UPDATE SET setting_value=EXCLUDED.setting_value,updated_at=CURRENT_TIMESTAMP", [key, value]);
	}
	return responseRedirect("/pengaturan/website?notice=Pengaturan%20website%20disimpan");
}

async function whatsappSettings(ctx, db) {
	requireRoles(ctx.user, ["admin"]);
	const { rows } = await db.query("SELECT id,nama,isi,aktif FROM whatsapp_templates ORDER BY updated_at DESC LIMIT 20");
	const list = renderTable([{ label: "Nama template", key: "nama" }, { label: "Isi template", render: (row) => `<pre>${escapeHtml(row.isi)}</pre>` }, { label: "Aktif", render: (row) => row.aktif ? "Ya" : "Tidak" }], rows, { empty: "Belum ada template." });
	const values = rows[0] ?? { nama: "Reminder servis berkala", isi: "Halo {nama_pelanggan}, jadwal servis berikutnya {jadwal_berikutnya}.", aktif: true };
	const form = renderForm("/pengaturan/whatsapp", [
		{ name: "nama", label: "Nama template", required: true, maxLength: 100 },
		{ name: "isi", label: "Isi pesan", type: "textarea", required: true, maxLength: 5000 },
		{ name: "aktif", label: "Aktif", type: "select", options: [{ value: "true", label: "Ya" }, { value: "false", label: "Tidak" }] },
	], ctx.csrf, { values: { ...values, aktif: String(values.aktif) }, submit: "Simpan template" });
	return render("Template WhatsApp", `<section class="card">${form}<p class="muted">Placeholder: {nama_pelanggan}, {nama_unit}, {tanggal_servis}, {jadwal_berikutnya}, {nama_bisnis}.</p></section>${list}`, ctx);
}

async function saveWhatsappSettings(ctx, db) {
	requireRoles(ctx.user, ["admin"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const name = required(form, "nama", "Nama template", 100);
	const message = required(form, "isi", "Isi template", 5000);
	const active = getValue(form, "aktif", "true") === "true";
	await db.query("UPDATE whatsapp_templates SET aktif=FALSE");
	await db.query("INSERT INTO whatsapp_templates (nama,isi,aktif,updated_by,updated_at) VALUES ($1,$2,$3,$4,CURRENT_TIMESTAMP)", [name, message, active, ctx.user.id]);
	return responseRedirect("/pengaturan/whatsapp?notice=Template%20WhatsApp%20disimpan");
}

async function userDashboard(ctx, db) {
	requireRoles(ctx.user, ["user"]);
	if (!ctx.user.pelanggan_id) {
		return render("Akun pelanggan", `<section class="card"><p>Akun ini belum dihubungkan dengan data pelanggan. Hubungi admin untuk menghubungkan akun dengan profil pelanggan Anda.</p></section>`, ctx);
	}
	const [{ rows: customerRows }, { rows: transactions }, { rows: notices }] = await Promise.all([
		db.query("SELECT id,nama,no_hp,alamat FROM pelanggan WHERE id=$1", [ctx.user.pelanggan_id]),
		db.query("SELECT id,tanggal_servis,jenis_servis,teknisi,jumlah_unit,detail_unit,biaya,catatan FROM servis WHERE pelanggan_id=$1 ORDER BY tanggal_servis DESC,created_at DESC LIMIT 200", [ctx.user.pelanggan_id]),
		db.query("SELECT judul,pesan,tanggal,status FROM notifikasi WHERE pelanggan_id=$1 ORDER BY created_at DESC LIMIT 20", [ctx.user.pelanggan_id]),
	]);
	const customer = customerRows[0];
	if (!customer) throw new HttpError(404, "Profil pelanggan tidak ditemukan.");
	const spent = transactions.reduce((sum, item) => sum + Number(item.biaya), 0);
	const customerUnits = await unitOptions(db, customer.id);
	const bookingForm = renderForm("/user/schedule", [
		{ name: "unit_ac_id", label: "Unit AC", type: "select", required: true, options: customerUnits.filter((unit) => unit.value) },
		{ name: "tanggal", label: "Tanggal yang diinginkan", type: "date", required: true },
		{ name: "jam", label: "Jam yang diinginkan", type: "time" },
		{ name: "catatan", label: "Catatan", type: "textarea", maxLength: 2000 },
	], ctx.csrf, { submit: "Ajukan jadwal servis" });
	const content = `<section class="card"><p>Akun terhubung sebagai pelanggan <strong>${escapeHtml(customer.nama)}</strong>.</p><p>${escapeHtml(customer.alamat)} · ${escapeHtml(customer.no_hp)}</p></section><div class="grid">${stat("Total transaksi", transactions.length)}${stat("Total pembayaran", `Rp${spent.toLocaleString("id-ID")}`)}</div><section class="card"><h2>Ajukan jadwal servis</h2>${customerUnits.length > 1 ? bookingForm : `<p>Tambahkan unit AC melalui admin sebelum mengajukan jadwal.</p>`}</section><h2>Riwayat transaksi servis</h2>${renderTable([{ label: "Tanggal", key: "tanggal_servis" }, { label: "Pekerjaan", key: "jenis_servis" }, { label: "Detail unit", key: "detail_unit" }, { label: "Teknisi", key: "teknisi" }, { label: "Biaya", render: (row) => `Rp${Number(row.biaya).toLocaleString("id-ID")}` }], transactions, { empty: "Belum ada transaksi servis." })}<h2>Notifikasi perawatan</h2>${renderTable([{ label: "Tanggal", key: "tanggal" }, { label: "Judul", key: "judul" }, { label: "Pesan", key: "pesan" }, { label: "Status", key: "status" }], notices, { empty: "Belum ada notifikasi." })}`;
	return render("Portal pelanggan", content, ctx);
}

async function userSettings(ctx, db) {
	requireRoles(ctx.user, ["user"]);
	const { rows } = await db.query("SELECT no_hp FROM users WHERE id=$1", [ctx.user.id]);
	const form = renderForm("/user/settings", [{ name: "no_hp", label: "Nomor HP", maxLength: 25 }], ctx.csrf, { values: rows[0] ?? {}, submit: "Simpan pengaturan" });
	return render("Pengaturan pelanggan", `<section class="card">${form}</section>`, ctx);
}

async function saveUserSettings(ctx, db) {
	requireRoles(ctx.user, ["user"]);
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const phone = nullable(form, "no_hp", 25);
	await db.query("UPDATE users SET no_hp=$1,updated_at=CURRENT_TIMESTAMP WHERE id=$2", [phone, ctx.user.id]);
	return responseRedirect("/user/settings?notice=Pengaturan%20disimpan");
}

async function createCustomerSchedule(ctx, db) {
	requireRoles(ctx.user, ["user"]);
	if (!ctx.user.pelanggan_id) throw new HttpError(403, "Akun ini belum terhubung dengan data pelanggan.");
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const unitId = required(form, "unit_ac_id", "Unit AC", 50);
	const date = required(form, "tanggal", "Tanggal", 10);
	const time = nullable(form, "jam", 5);
	const notes = nullable(form, "catatan", 2000);
	const unit = await db.query("SELECT id,nama_unit FROM unit_ac WHERE id=$1 AND pelanggan_id=$2", [unitId, ctx.user.pelanggan_id]);
	if (!unit.rowCount) throw new HttpError(400, "Unit AC tidak ditemukan pada akun pelanggan Anda.");
	await db.query("INSERT INTO jadwal_servis (pelanggan_id,unit_ac_id,jumlah_unit,detail_unit,tanggal,jam,status,catatan) VALUES ($1,$2,1,$3,$4,$5,'menunggu_konfirmasi',$6)", [ctx.user.pelanggan_id, unitId, unit.rows[0].nama_unit, date, time, notes]);
	return responseRedirect("/user?notice=Permintaan%20jadwal%20dikirim");
}

async function auditPage(ctx, db) {
	requireRoles(ctx.user, ["developer", "owner"]);
	const { rows } = await db.query("SELECT action,target,description,role,created_at FROM audit_logs ORDER BY created_at DESC LIMIT 100");
	return render("Log aktivitas", renderTable([
		{ label: "Waktu", key: "created_at" }, { label: "Role", key: "role" },
		{ label: "Aksi", key: "action" }, { label: "Target", key: "target" }, { label: "Keterangan", key: "description" },
	], rows), ctx);
}

async function saveSubscription(ctx, db) {
	if (!ctx.user) throw new HttpError(401, "Silakan masuk terlebih dahulu.");
	const token = ctx.request.headers.get("X-CSRF-Token") ?? "";
	const form = new URLSearchParams({ csrf_token: token });
	await postCsrf(form, ctx.request, ctx.env);
	let payload;
	try {
		payload = await ctx.request.json();
	} catch {
		throw new HttpError(400, "Payload subscription tidak valid.");
	}
	if (!payload?.endpoint || !payload?.keys?.p256dh || !payload?.keys?.auth) throw new HttpError(400, "Subscription push tidak lengkap.");
	await db.query("INSERT INTO push_subscriptions (user_id,endpoint,subscription_json) VALUES ($1,$2,$3::jsonb) ON CONFLICT (endpoint) DO UPDATE SET user_id=EXCLUDED.user_id,subscription_json=EXCLUDED.subscription_json,updated_at=CURRENT_TIMESTAMP", [ctx.user.id, payload.endpoint, JSON.stringify(payload)]);
	return Response.json({ success: true });
}

async function sendReminderNotification(ctx, db) {
	requireRoles(ctx.user, ["admin", "teknisi"]);
	const token = ctx.request.headers.get("X-CSRF-Token") ?? "";
	await postCsrf(new URLSearchParams({ csrf_token: token }), ctx.request, ctx.env);
	if (!ctx.env.VAPID_PUBLIC_KEY || !ctx.env.VAPID_PRIVATE_KEY) {
		throw new HttpError(503, "Web Push belum dikonfigurasi. Tambahkan VAPID_PUBLIC_KEY dan VAPID_PRIVATE_KEY sebagai Worker secrets.");
	}
	let payload = {};
	if (ctx.request.headers.get("Content-Length") !== "0") {
		try {
			payload = await ctx.request.json();
		} catch {
			if (ctx.request.headers.get("Content-Type")?.includes("application/json")) {
				throw new HttpError(400, "Payload notifikasi tidak valid.");
			}
		}
	}
	const settings = await siteSettings(db);
	const message = {
		title: String(payload.judul ?? settings.site_name),
		body: String(payload.pesan ?? "Ada pelanggan yang memasuki jadwal servis AC."),
		url: "/pengingat",
	};
	if (payload.pelanggan_id) {
		await db.query("INSERT INTO notifikasi (pelanggan_id,judul,pesan,tanggal,status) VALUES ($1,$2,$3,CURRENT_DATE,'unread')", [String(payload.pelanggan_id), message.title.slice(0, 150), message.body.slice(0, 2000)]);
	}
	const { rows: subscriptions } = await db.query("SELECT id,endpoint,subscription_json FROM push_subscriptions");
	const config = {
		publicKey: ctx.env.VAPID_PUBLIC_KEY,
		privateKey: ctx.env.VAPID_PRIVATE_KEY,
		subject: ctx.env.VAPID_SUBJECT ?? "mailto:admin@afeteknik.local",
	};
	let sent = 0;
	let failed = 0;
	for (const row of subscriptions) {
		try {
			const response = await sendWebPush(row.subscription_json, message, config);
			if (response.status === 404 || response.status === 410) {
				await db.query("DELETE FROM push_subscriptions WHERE id=$1", [row.id]);
			} else if (response.ok) {
				sent++;
			} else {
				failed++;
				console.error(`Web Push delivery failed with HTTP ${response.status} for subscription ${row.id}.`);
			}
		} catch (error) {
			failed++;
			console.error(`Web Push delivery failed for subscription ${row.id}:`, error);
		}
	}
	return Response.json({ success: failed === 0, sent, failed });
}

async function audit(db, ctx, action, target = null, description = "") {
	await db.query("INSERT INTO audit_logs (user_id,role,action,target,description,ip_address,user_agent) VALUES ($1,$2,$3,$4,$5,$6,$7)", [
		ctx.user?.id ?? null,
		ctx.user?.role ?? null,
		action,
		target,
		description,
		ctx.request.headers.get("CF-Connecting-IP"),
		(ctx.request.headers.get("User-Agent") ?? "").slice(0, 500),
	]);
}

export function legacyPath(url) {
	const path = url.pathname;
	if (path === "/index.php" || path === "/home.php") return "/";
	if (path === "/profile.php") return "/profile";
	if (path === "/auth/login.php") return "/auth/login";
	if (path === "/auth/register.php") return "/auth/register";
	if (path === "/auth/logout.php") return "/auth/logout";
	if (path === "/dashboard/index.php") return "/dashboard";
	if (path === "/Developer/index.php") return "/Developer";
	if (path === "/user/index.php") return "/user";
	if (path === "/user/pengaturan.php") return "/user/settings";
	if (path === "/akun/index.php") return "/akun";
	if (path === "/jadwal/index.php") return "/jadwal";
	if (path === "/pengingat/index.php") return "/pengingat";
	if (path === "/maintenance/index.php") return "/maintenance";
	if (path === "/pengaturan/website.php") return "/pengaturan/website";
	if (path === "/pengaturan/whatsapp.php") return "/pengaturan/whatsapp";
	if (["/pelanggan/hapus.php", "/servis/hapus.php", "/produk/hapus.php"].includes(path)) return path.replace(".php", "");
	const directory = path.split("/").filter(Boolean)[0];
	if (["pelanggan", "unit", "servis", "produk"].includes(directory)) {
		if (path.endsWith("/index.php")) return `/${directory}`;
		if (path.endsWith("/tambah.php")) return `/${directory}/new`;
		const match = path.match(new RegExp(`^/${directory}/(detail|edit|hapus|invoice)\\.php$`));
		if (match) {
			const id = url.searchParams.get("id");
			if (match[1] === "detail" && id) return `/${directory}/${id}`;
			if (match[1] === "invoice" && id) return `/servis/${id}/invoice`;
			if (match[1] === "edit" && id) return `/${directory}/${id}/edit`;
			if (match[1] === "hapus" && id) return `/${directory}/${id}/delete`;
		}
	}
	if (path === "/api/subscribe.php") return "/api/subscribe";
	if (path === "/api/send-notification.php") return "/api/send-notification";
	return path;
}

async function login(ctx, db) {
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const username = getValue(form, "username").toLowerCase();
	const password = getValue(form, "password");
	if (!username || !password) return loginPage(ctx, db, "Username atau password tidak sesuai.");
	const ip = ctx.request.headers.get("CF-Connecting-IP") ?? "unknown";
	const key = createHash("sha256").update(`${username}|${ip}`).digest("hex");
	const blocked = await db.query("SELECT 1 FROM login_attempts WHERE attempt_key=$1 AND blocked_until>CURRENT_TIMESTAMP", [key]);
	if (blocked.rowCount) return loginPage(ctx, db, "Username atau password tidak sesuai.");

	const result = await db.query("SELECT id,nama,username,role,pelanggan_id,no_hp,aktif,password_hash FROM users WHERE username=$1 LIMIT 1", [username]);
	const user = result.rows[0];
	if (!user?.aktif || !await verifyPassword(password, user.password_hash)) {
		await db.query("INSERT INTO login_attempts (attempt_key,failed_attempts,first_failed_at,blocked_until,updated_at) VALUES ($1,1,CURRENT_TIMESTAMP,NULL,CURRENT_TIMESTAMP) ON CONFLICT (attempt_key) DO UPDATE SET failed_attempts=login_attempts.failed_attempts+1,blocked_until=CASE WHEN login_attempts.failed_attempts+1>=5 THEN CURRENT_TIMESTAMP+INTERVAL '15 minutes' ELSE login_attempts.blocked_until END,updated_at=CURRENT_TIMESTAMP", [key]);
		await db.query("INSERT INTO audit_logs (user_id,action,description,ip_address,user_agent) VALUES (NULL,'login_failed','Percobaan login gagal.',$1,$2)", [ip, (ctx.request.headers.get("User-Agent") ?? "").slice(0, 500)]);
		return loginPage(ctx, db, "Username atau password tidak sesuai.");
	}
	await db.query("DELETE FROM login_attempts WHERE attempt_key=$1", [key]);
	await db.query("INSERT INTO audit_logs (user_id,role,action,description,ip_address,user_agent) VALUES ($1,$2,'login_success','Login berhasil.',$3,$4)", [user.id, user.role, ip, (ctx.request.headers.get("User-Agent") ?? "").slice(0, 500)]);
	const cookie = await createSession(user, ctx.env.SESSION_SECRET);
	return responseRedirect(user.role === "user" ? "/user" : "/dashboard", { "Set-Cookie": cookie });
}

async function register(ctx, db) {
	const form = await ctx.request.formData();
	await postCsrf(form, ctx.request, ctx.env);
	const name = required(form, "nama", "Nama", 100);
	const username = required(form, "username", "Username", 50).toLowerCase();
	const password = required(form, "password", "Password", 200);
	const confirmation = required(form, "password_confirmation", "Konfirmasi password", 200);
	if (!/^[a-z0-9._-]{4,50}$/.test(username)) throw new HttpError(400, "Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung (4–50 karakter).");
	if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) throw new HttpError(400, "Password minimal 8 karakter dan harus berisi huruf serta angka.");
	if (password !== confirmation) throw new HttpError(400, "Konfirmasi password tidak sama.");
	await db.query("INSERT INTO users (nama,username,password_hash,role) VALUES ($1,$2,$3,'user')", [name, username, await hashPassword(password)]);
	return responseRedirect("/auth/login?notice=Akun%20berhasil%20dibuat");
}

async function route(ctx, db) {
	const path = legacyPath(ctx.url).replace(/\/+$/, "") || "/";
	const method = ctx.request.method;
	if (method === "GET" && path === "/") return home(ctx, db);
	if (path === "/auth/login" && method === "GET") return loginPage(ctx, db);
	if (path === "/auth/login" && method === "POST") return login(ctx, db);
	if (path === "/auth/register" && method === "GET") return registerPage(ctx, db);
	if (path === "/auth/register" && method === "POST") return register(ctx, db);
	if (path === "/auth/logout" && method === "POST") {
		const form = await ctx.request.formData();
		await postCsrf(form, ctx.request, ctx.env);
		return responseRedirect("/auth/login", { "Set-Cookie": await destroySession() });
	}
	if (path === "/auth/logout" && method === "GET") {
		return responseRedirect("/auth/login", { "Set-Cookie": await destroySession() });
	}
	if (method === "GET" && path === "/dashboard") return dashboard(ctx, db);
	if (method === "GET" && path === "/user") return userDashboard(ctx, db);
	if (method === "POST" && path === "/user/schedule") return createCustomerSchedule(ctx, db);
	if (method === "GET" && path === "/user/settings") return userSettings(ctx, db);
	if (method === "POST" && path === "/user/settings") return saveUserSettings(ctx, db);
	if (method === "GET" && path === "/profile") return profile(ctx, db);
	if (method === "POST" && path === "/profile/contact") return updateProfile(ctx, db);
	if (method === "POST" && path === "/profile/password") return changePassword(ctx, db);
	if (method === "GET" && path === "/pelanggan") return customers(ctx, db);
	if (method === "GET" && path === "/pelanggan/new") return customerForm(ctx, db);
	if (method === "POST" && path === "/pelanggan/new") return saveCustomer(ctx, db);
	let match = path.match(/^\/pelanggan\/([0-9a-f-]{36})$/i);
	if (method === "GET" && match) return customerDetail(ctx, db, match[1]);
	match = path.match(/^\/pelanggan\/([0-9a-f-]{36})\/edit$/i);
	if (method === "GET" && match) return customerForm(ctx, db, match[1]);
	if (method === "POST" && match) return saveCustomer(ctx, db, match[1]);
	match = path.match(/^\/pelanggan\/([0-9a-f-]{36})\/delete$/i);
	if (method === "POST" && match) return deleteCustomer(ctx, db, match[1]);
	if (method === "GET" && path === "/unit") return units(ctx, db);
	if (method === "GET" && path === "/unit/new") return unitForm(ctx, db);
	if (method === "POST" && path === "/unit/new") return saveUnit(ctx, db);
	match = path.match(/^\/unit\/([0-9a-f-]{36})\/edit$/i);
	if (method === "GET" && match) return unitForm(ctx, db, match[1]);
	if (method === "POST" && match) return saveUnit(ctx, db, match[1]);
	match = path.match(/^\/unit\/([0-9a-f-]{36})\/delete$/i);
	if (method === "POST" && match) return deleteUnit(ctx, db, match[1]);
	if (method === "GET" && path === "/servis") return services(ctx, db);
	if (method === "GET" && path === "/servis/new") return serviceForm(ctx, db);
	if (method === "POST" && path === "/servis/new") return saveService(ctx, db);
	match = path.match(/^\/servis\/([0-9a-f-]{36})\/invoice$/i);
	if (method === "GET" && match) return serviceInvoice(ctx, db, match[1]);
	match = path.match(/^\/servis\/([0-9a-f-]{36})\/delete$/i);
	if (method === "POST" && match) return deleteService(ctx, db, match[1]);
	if (method === "GET" && path === "/produk") return products(ctx, db);
	if (method === "GET" && path === "/produk/new") return productForm(ctx, db);
	if (method === "POST" && path === "/produk/new") return saveProduct(ctx, db);
	match = path.match(/^\/produk\/([0-9a-f-]{36})\/edit$/i);
	if (method === "GET" && match) return productForm(ctx, db, match[1]);
	if (method === "POST" && match) return saveProduct(ctx, db, match[1]);
	match = path.match(/^\/produk\/([0-9a-f-]{36})\/delete$/i);
	if (method === "POST" && match) return deleteProduct(ctx, db, match[1]);
	if (method === "POST" && ["/pelanggan/hapus", "/servis/hapus", "/produk/hapus"].includes(path)) {
		requireRoles(ctx.user, path === "/pelanggan/hapus" ? ["admin"] : ["admin", "teknisi"]);
		const form = await ctx.request.formData();
		await postCsrf(form, ctx.request, ctx.env);
		const id = required(form, "id", "ID", 50);
		if (path === "/pelanggan/hapus") {
			await db.query("DELETE FROM pelanggan WHERE id=$1", [id]);
			return responseRedirect("/pelanggan?notice=Data%20pelanggan%20dihapus");
		}
		if (path === "/servis/hapus") {
			await db.query("DELETE FROM servis WHERE id=$1", [id]);
			return responseRedirect("/servis?notice=Catatan%20servis%20dihapus");
		}
		await db.query("DELETE FROM produk WHERE id=$1", [id]);
		return responseRedirect("/produk?notice=Produk%20dihapus");
	}
	if (method === "GET" && path === "/maintenance") return maintenance(ctx, db);
	if (method === "GET" && path === "/jadwal") return schedules(ctx, db);
	if (method === "POST" && path === "/jadwal/new") return createSchedule(ctx, db);
	match = path.match(/^\/jadwal\/([0-9a-f-]{36})\/status$/i);
	if (method === "POST" && match) return updateScheduleStatus(ctx, db, match[1]);
	if (method === "GET" && path === "/pengingat") return reminders(ctx, db);
	if (method === "GET" && path === "/laporan") return reports(ctx, db);
	if (method === "GET" && path === "/akun") return accounts(ctx, db);
	if (method === "POST" && path === "/akun/new") return createAccount(ctx, db);
	match = path.match(/^\/akun\/([0-9a-f-]{36})\/update$/i);
	if (method === "POST" && match) return updateAccount(ctx, db, match[1]);
	match = path.match(/^\/akun\/([0-9a-f-]{36})\/delete$/i);
	if (method === "POST" && match) return deleteAccount(ctx, db, match[1]);
	if (method === "GET" && path === "/pengaturan/website") return websiteSettingsPage(ctx, db);
	if (method === "POST" && path === "/pengaturan/website") return saveWebsiteSettings(ctx, db);
	if (method === "GET" && path === "/pengaturan/whatsapp") return whatsappSettings(ctx, db);
	if (method === "POST" && path === "/pengaturan/whatsapp") return saveWhatsappSettings(ctx, db);
	if (method === "GET" && path === "/Developer") return auditPage(ctx, db);
	if (method === "POST" && path === "/api/subscribe") return saveSubscription(ctx, db);
	if (method === "POST" && path === "/api/send-notification") return sendReminderNotification(ctx, db);
	throw new HttpError(404, "Halaman tidak ditemukan.");
}

function errorResponse(error, ctx) {
	if (error instanceof HttpError) {
		if (error.redirectTo) return responseRedirect(error.redirectTo);
		return render("Permintaan tidak dapat diproses", `<section class="card">${escapeHtml(error.message)}<p><a href="/">Kembali ke beranda</a></p></section>`, ctx, "", error.status);
	}
	console.error("AC Care Worker request failed:", error);
	return new Response("Terjadi gangguan pada sistem. Silakan coba lagi nanti.", {
		status: 500,
		headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
	});
}

export default {
	async fetch(request, env) {
		const url = new URL(request.url);
		if ((url.pathname.startsWith("/assets/") || url.pathname === "/service-worker.js") && env.ASSETS) {
			return env.ASSETS.fetch(request);
		}
		if (!env.SESSION_SECRET || env.SESSION_SECRET.length < 32) {
			return new Response("Cloudflare Worker belum dikonfigurasi: tambahkan secret SESSION_SECRET (minimal 32 karakter).", {
				status: 503,
				headers: { "Content-Type": "text/plain; charset=utf-8" },
			});
		}
		try {
			return await withDatabase(env, async (db) => {
				const csrf = await csrfFor(request, env.SESSION_SECRET);
				const user = await currentUser(request, db, env.SESSION_SECRET);
				const ctx = {
					request,
					url,
					env,
					csrf: csrf.token,
					csrfCookie: csrf.cookie,
					user,
					settings: await siteSettings(db),
				};
				try {
					if (ctx.settings.maintenance_mode === "1" && !["owner", "admin", "developer"].includes(ctx.user?.role)) {
						return render("Sedang dalam pemeliharaan", `<section class="card"><p>${escapeHtml(ctx.settings.maintenance_message)}</p></section>`, ctx, "", 503);
					}
					return await route(ctx, db);
				} catch (error) {
					if (error instanceof HttpError && error.status === 401 && error.redirectTo) {
						return responseRedirect(error.redirectTo);
					}
					return errorResponse(error, ctx);
				}
			});
		} catch (error) {
			console.error("AC Care database request failed:", error);
			return new Response("Database belum tersedia. Pastikan Hyperdrive terhubung dan schema PostgreSQL AC Care telah dipasang.", {
				status: 503,
				headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
			});
		}
	},
};
