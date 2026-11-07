import bcrypt from "bcryptjs";

const COOKIE_NAME = "ac-care-session";
const CSRF_COOKIE_NAME = "ac-care-csrf";
const SESSION_TTL_SECONDS = 60 * 60 * 8;
const encoder = new TextEncoder();

function toBase64Url(bytes) {
	let binary = "";
	for (const byte of bytes) binary += String.fromCharCode(byte);
	return btoa(binary).replaceAll("+", "-").replaceAll("/", "_").replace(/=+$/, "");
}

function fromBase64Url(value) {
	const base64 = value.replaceAll("-", "+").replaceAll("_", "/");
	const binary = atob(base64 + "=".repeat((4 - base64.length % 4) % 4));
	return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

async function signingKey(secret) {
	if (typeof secret !== "string" || secret.length < 32) {
		throw new Error("SESSION_SECRET must contain at least 32 characters.");
	}
	return crypto.subtle.importKey("raw", encoder.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign", "verify"]);
}

async function sign(value, secret) {
	const key = await signingKey(secret);
	const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(value));
	return toBase64Url(new Uint8Array(signature));
}

export async function createSession(user, secret) {
	const payload = {
		sub: user.id,
		csrf: toBase64Url(crypto.getRandomValues(new Uint8Array(32))),
		exp: Math.floor(Date.now() / 1000) + SESSION_TTL_SECONDS,
	};
	const body = toBase64Url(encoder.encode(JSON.stringify(payload)));
	const value = `${body}.${await sign(body, secret)}`;
	return `${COOKIE_NAME}=${value}; Path=/; Max-Age=${SESSION_TTL_SECONDS}; HttpOnly; Secure; SameSite=Lax`;
}

export async function destroySession() {
	return `${COOKIE_NAME}=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax`;
}

export async function csrfFor(request, secret) {
	const cookie = request.headers.get("Cookie") ?? "";
	const match = cookie.split(";").map((part) => part.trim()).find((part) => part.startsWith(`${CSRF_COOKIE_NAME}=`));
	if (match) {
		const token = match.slice(CSRF_COOKIE_NAME.length + 1);
		const [nonce, signature, extra] = token.split(".");
		if (nonce && signature && !extra && await crypto.subtle.verify("HMAC", await signingKey(secret), fromBase64Url(signature), encoder.encode(nonce))) {
			return { token, cookie: "" };
		}
	}

	const nonce = toBase64Url(crypto.getRandomValues(new Uint8Array(32)));
	const token = `${nonce}.${await sign(nonce, secret)}`;
	return {
		token,
		cookie: `${CSRF_COOKIE_NAME}=${token}; Path=/; Max-Age=${SESSION_TTL_SECONDS}; HttpOnly; Secure; SameSite=Lax`,
	};
}

export async function verifyCsrf(form, request, secret) {
	const submitted = form.get("csrf_token");
	const csrf = await csrfFor(request, secret);
	const cookie = request.headers.get("Cookie") ?? "";
	const match = cookie.split(";").map((part) => part.trim()).find((part) => part.startsWith(`${CSRF_COOKIE_NAME}=`));
	if (typeof submitted !== "string" || !match || submitted !== match.slice(CSRF_COOKIE_NAME.length + 1)) {
		throw new HttpError(419, "Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.");
	}
	const [nonce, signature, extra] = submitted.split(".");
	if (!nonce || !signature || extra || !await crypto.subtle.verify("HMAC", await signingKey(secret), fromBase64Url(signature), encoder.encode(nonce))) {
		throw new HttpError(419, "Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.");
	}
	return csrf;
}

export async function currentUser(request, db, secret) {
	const cookie = request.headers.get("Cookie") ?? "";
	const match = cookie.split(";").map((part) => part.trim()).find((part) => part.startsWith(`${COOKIE_NAME}=`));
	if (!match) return null;

	const [body, signature, extra] = match.slice(COOKIE_NAME.length + 1).split(".");
	if (!body || !signature || extra) return null;
	const key = await signingKey(secret);
	const valid = await crypto.subtle.verify("HMAC", key, fromBase64Url(signature), encoder.encode(body));
	if (!valid) return null;

	let payload;
	try {
		payload = JSON.parse(new TextDecoder().decode(fromBase64Url(body)));
	} catch {
		return null;
	}
	if (!payload.sub || !payload.csrf || payload.exp <= Math.floor(Date.now() / 1000)) return null;

	const result = await db.query(
		"SELECT id, nama, username, role, pelanggan_id, no_hp, aktif FROM users WHERE id = $1 LIMIT 1",
		[payload.sub],
	);
	const user = result.rows[0];
	if (!user || !user.aktif) return null;
	return { ...user, csrf: payload.csrf };
}

export function userCanSeeCustomer(user, customerId) {
	return user.role !== "user" || user.pelanggan_id === customerId;
}

export function requireRoles(user, roles) {
	if (!user) throw new HttpError(401, "Silakan masuk terlebih dahulu.", "/auth/login");
	if (user.role !== "owner" && !roles.includes(user.role)) {
		throw new HttpError(403, "Akun ini tidak memiliki izin untuk halaman tersebut.");
	}
}

export async function hashPassword(value) {
	return bcrypt.hash(value, 12);
}

export async function verifyPassword(value, hash) {
	return bcrypt.compare(value, hash);
}

export class HttpError extends Error {
	constructor(status, message, redirectTo = "") {
		super(message);
		this.status = status;
		this.redirectTo = redirectTo;
	}
}
