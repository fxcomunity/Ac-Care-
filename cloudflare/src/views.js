export function escapeHtml(value) {
	return String(value ?? "").replace(/[&<>"']/g, (character) => ({
		"&": "&amp;",
		"<": "&lt;",
		">": "&gt;",
		'"': "&quot;",
		"'": "&#39;",
	})[character]);
}

export function page(title, content, { user = null, csrf = "", csrfCookie = "", pushPublicKey = "", settings = {}, status = 200, notice = "", error = "" } = {}) {
	const siteName = settings.site_name || "AC Care";
	const logoPath = settings.site_logo
		? (/^https:\/\//i.test(settings.site_logo) ? settings.site_logo : `/${String(settings.site_logo).replace(/^\/+/, "")}`)
		: "";
	const links = user ? [
		["Dashboard", user.role === "user" ? "/user" : "/dashboard"],
		...(user.role === "user" ? [["Unit & servis", "/user"]] : [
			["Pelanggan", "/pelanggan"],
			["Unit AC", "/unit"],
			["Servis", "/servis"],
			["Jadwal", "/jadwal"],
			["Pengingat", "/pengingat"],
			["Produk", "/produk"],
			...(user.role === "owner" || user.role === "admin" ? [["Laporan", "/laporan"], ["WhatsApp", "/pengaturan/whatsapp"]] : []),
		]),
		["Maintenance", "/maintenance"],
		...(user.role === "owner" ? [["Akun", "/akun"], ["Website", "/pengaturan/website"], ["Log", "/Developer"]] : []),
		["Profil", "/profile"],
	] : [["Beranda", "/"], ["Masuk", "/auth/login"], ["Daftar", "/auth/register"]];

	const nav = links.map(([label, href]) =>
		`<a class="nav-link" href="${href}">${escapeHtml(label)}</a>`,
	).join("");
	const message = error
		? `<div class="alert error" role="alert">${escapeHtml(error)}</div>`
		: notice ? `<div class="alert success">${escapeHtml(notice)}</div>` : "";
	const account = user
		? `<span class="muted">${escapeHtml(user.nama)} · ${escapeHtml(user.role)}</span>${pushPublicKey ? '<button class="secondary" data-enable-notifications hidden>Aktifkan notifikasi</button>' : ""}<form action="/auth/logout" method="post"><input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}"><button class="link-button">Keluar</button></form>`
		: "";

	return new Response(`<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f172a"><title>${escapeHtml(title)} · ${escapeHtml(siteName)}</title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>
:root{font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#172033;background:#f8fafc}*{box-sizing:border-box}body{margin:0}.topbar{background:#0f172a;color:#fff;padding:1rem clamp(1rem,4vw,3rem);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}.brand{color:#fff;text-decoration:none;font-size:1.15rem;font-weight:800}.topbar .muted{color:#cbd5e1}.account{display:flex;gap:1rem;align-items:center}.nav{display:flex;gap:.45rem;flex-wrap:wrap;background:#fff;padding:.8rem clamp(1rem,4vw,3rem);border-bottom:1px solid #e2e8f0}.nav-link{padding:.55rem .7rem;border-radius:.5rem;color:#334155;text-decoration:none;font-size:.9rem;font-weight:600}.nav-link:hover{background:#eff6ff;color:#1d4ed8}main{max-width:1180px;margin:2rem auto;padding:0 1rem 3rem}h1{font-size:1.65rem;margin:0 0 1.25rem}.card{background:#fff;border:1px solid #e2e8f0;border-radius:.8rem;padding:1.2rem;margin-bottom:1rem;box-shadow:0 1px 2px #0f172a0a}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem}.stat{font-size:1.7rem;font-weight:800;margin-top:.3rem}.muted{color:#64748b;font-size:.88rem}.actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.button,button{border:0;border-radius:.5rem;padding:.65rem .9rem;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;text-decoration:none;font-size:.9rem}.button.secondary,button.secondary{background:#e2e8f0;color:#1e293b}.button.danger,button.danger{background:#dc2626}.link-button{background:none;color:#fff;padding:0}.fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem}.field{display:flex;flex-direction:column;gap:.35rem}.field label{font-size:.9rem;font-weight:700}.field input,.field select,.field textarea,.search input{padding:.7rem;border:1px solid #cbd5e1;border-radius:.5rem;font:inherit;width:100%}.field textarea{min-height:90px}.form-actions{margin-top:1rem}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:.9rem}th,td{text-align:left;padding:.75rem;border-bottom:1px solid #e2e8f0;vertical-align:top}th{background:#f8fafc;color:#475569}.alert{padding:.85rem 1rem;border-radius:.6rem;margin-bottom:1rem}.alert.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.alert.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}.search{display:flex;gap:.5rem;margin-bottom:1rem}.footer{color:#64748b;text-align:center;padding:1rem;font-size:.85rem}@media(max-width:600px){.topbar{align-items:flex-start}.account{width:100%;justify-content:space-between}.nav{gap:.15rem}.nav-link{font-size:.82rem;padding:.45rem}}
</style>
</head>
<body>
<header class="topbar"><a class="brand" href="${user ? (user.role === "user" ? "/user" : "/dashboard") : "/"}">${logoPath ? `<img src="${escapeHtml(logoPath)}" alt="" width="32" height="32" style="object-fit:contain;vertical-align:middle;margin-right:.5rem">` : ""}${escapeHtml(siteName)}</a><div class="account">${account}</div></header>
<nav class="nav">${nav}</nav><main><h1>${escapeHtml(title)}</h1>${message}${content}</main>
<footer class="footer">AC Care · Manajemen dan servis AC</footer>
<script>window.appBaseUrl="";window.csrfToken=${JSON.stringify(csrf)};window.pushPublicKey=${JSON.stringify(pushPublicKey)};</script>
<script src="/assets/js/app.js" defer></script><script src="/assets/js/notifications.js" defer></script>
</body></html>`, {
		status,
		headers: {
			"Content-Type": "text/html; charset=utf-8",
			"X-Content-Type-Options": "nosniff",
			"Referrer-Policy": "strict-origin-when-cross-origin",
			"X-Frame-Options": "SAMEORIGIN",
			"Cache-Control": "no-store",
			"Content-Security-Policy": "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data: https:; form-action 'self'; base-uri 'self'; frame-ancestors 'self'",
			...(csrfCookie ? { "Set-Cookie": csrfCookie } : {}),
		},
	});
}

export function renderForm(action, fields, csrf, { submit = "Simpan", values = {}, method = "post" } = {}) {
	const controls = fields.map((field) => {
		const value = values[field.name] ?? field.value ?? "";
		const label = `<label for="${escapeHtml(field.name)}">${escapeHtml(field.label)}${field.required ? " *" : ""}</label>`;
		let control;
		if (field.type === "select") {
			const options = (field.options ?? []).map((option) => {
				const key = typeof option === "string" ? option : option.value;
				const text = typeof option === "string" ? option : option.label;
				return `<option value="${escapeHtml(key)}" ${String(value) === String(key) ? "selected" : ""}>${escapeHtml(text)}</option>`;
			}).join("");
			control = `<select id="${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" ${field.required ? "required" : ""}>${options}</select>`;
		} else if (field.type === "textarea") {
			control = `<textarea id="${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" maxlength="${field.maxLength ?? 5000}" ${field.required ? "required" : ""}>${escapeHtml(value)}</textarea>`;
		} else {
			control = `<input id="${escapeHtml(field.name)}" name="${escapeHtml(field.name)}" type="${escapeHtml(field.type ?? "text")}" value="${escapeHtml(value)}" ${field.maxLength ? `maxlength="${field.maxLength}"` : ""} ${field.required ? "required" : ""} ${field.min !== undefined ? `min="${field.min}"` : ""}>`;
		}
		return `<div class="field">${label}${control}</div>`;
	}).join("");
	return `<form action="${escapeHtml(action)}" method="${method}"><input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}"><div class="fields">${controls}</div><div class="form-actions"><button>${escapeHtml(submit)}</button></div></form>`;
}

export function renderTable(columns, rows, { actions = () => "", empty = "Belum ada data." } = {}) {
	if (!rows.length) return `<div class="card muted">${escapeHtml(empty)}</div>`;
	const heads = columns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join("");
	const body = rows.map((row) => `<tr>${columns.map((column) => `<td>${column.render ? column.render(row) : escapeHtml(row[column.key])}</td>`).join("")}<td>${actions(row)}</td></tr>`).join("");
	return `<div class="card table-wrap"><table><thead><tr>${heads}<th>Tindakan</th></tr></thead><tbody>${body}</tbody></table></div>`;
}

export function link(label, href, kind = "") {
	return `<a class="button ${escapeHtml(kind)}" href="${escapeHtml(href)}">${escapeHtml(label)}</a>`;
}

export function deleteForm(action, csrf, label = "Hapus") {
	return `<form action="${escapeHtml(action)}" method="post" onsubmit="return confirm('Yakin ingin menghapus data ini?')"><input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}"><button class="danger">${escapeHtml(label)}</button></form>`;
}
