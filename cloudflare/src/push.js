const encoder = new TextEncoder();

function bytesToBase64Url(bytes) {
	let binary = "";
	for (const byte of bytes) binary += String.fromCharCode(byte);
	return btoa(binary).replaceAll("+", "-").replaceAll("/", "_").replace(/=+$/, "");
}

function base64UrlToBytes(value) {
	const base64 = value.replaceAll("-", "+").replaceAll("_", "/");
	const binary = atob(base64 + "=".repeat((4 - base64.length % 4) % 4));
	return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

function joinBytes(...parts) {
	const output = new Uint8Array(parts.reduce((size, part) => size + part.length, 0));
	let offset = 0;
	for (const part of parts) {
		output.set(part, offset);
		offset += part.length;
	}
	return output;
}

function publicJwk(raw) {
	if (raw.length !== 65 || raw[0] !== 4) throw new Error("Invalid P-256 public key.");
	return {
		kty: "EC",
		crv: "P-256",
		x: bytesToBase64Url(raw.slice(1, 33)),
		y: bytesToBase64Url(raw.slice(33)),
		ext: true,
	};
}

async function hmac(keyBytes, message) {
	const key = await crypto.subtle.importKey("raw", keyBytes, { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
	return new Uint8Array(await crypto.subtle.sign("HMAC", key, message));
}

async function hkdfExpand(prk, info, length) {
	const blocks = [];
	let previous = new Uint8Array();
	for (let counter = 1; blocks.reduce((sum, block) => sum + block.length, 0) < length; counter++) {
		previous = await hmac(prk, joinBytes(previous, info, new Uint8Array([counter])));
		blocks.push(previous);
	}
	return joinBytes(...blocks).slice(0, length);
}

export async function encryptPushPayload(subscription, payload) {
	const clientPublic = base64UrlToBytes(subscription.keys.p256dh);
	const authSecret = base64UrlToBytes(subscription.keys.auth);
	if (authSecret.length !== 16) throw new Error("Invalid Web Push auth secret.");
	const clientKey = await crypto.subtle.importKey("raw", clientPublic, { name: "ECDH", namedCurve: "P-256" }, false, []);
	const serverKeys = await crypto.subtle.generateKey({ name: "ECDH", namedCurve: "P-256" }, true, ["deriveBits"]);
	const serverPublic = new Uint8Array(await crypto.subtle.exportKey("raw", serverKeys.publicKey));
	const sharedSecret = new Uint8Array(await crypto.subtle.deriveBits({ name: "ECDH", public: clientKey }, serverKeys.privateKey, 256));

	const keyInfo = joinBytes(encoder.encode("WebPush: info\0"), clientPublic, serverPublic);
	const keyPrk = await hmac(authSecret, sharedSecret);
	const inputKeyMaterial = await hkdfExpand(keyPrk, keyInfo, 32);
	const salt = crypto.getRandomValues(new Uint8Array(16));
	const contentPrk = await hmac(salt, inputKeyMaterial);
	const contentKeyBytes = await hkdfExpand(contentPrk, encoder.encode("Content-Encoding: aes128gcm\0"), 16);
	const nonce = await hkdfExpand(contentPrk, encoder.encode("Content-Encoding: nonce\0"), 12);
	const contentKey = await crypto.subtle.importKey("raw", contentKeyBytes, "AES-GCM", false, ["encrypt"]);
	const paddedPayload = joinBytes(payload, new Uint8Array([2]));
	const ciphertext = new Uint8Array(await crypto.subtle.encrypt({ name: "AES-GCM", iv: nonce, tagLength: 128 }, contentKey, paddedPayload));

	const recordSize = new Uint8Array([0, 0, 16, 0]);
	return joinBytes(salt, recordSize, new Uint8Array([serverPublic.length]), serverPublic, ciphertext);
}

async function vapidToken(endpoint, publicKey, privateKey, subject) {
	const publicBytes = base64UrlToBytes(publicKey);
	const jwk = {
		...publicJwk(publicBytes),
		d: bytesToBase64Url(base64UrlToBytes(privateKey)),
	};
	const key = await crypto.subtle.importKey("jwk", jwk, { name: "ECDSA", namedCurve: "P-256" }, false, ["sign"]);
	const header = bytesToBase64Url(encoder.encode(JSON.stringify({ typ: "JWT", alg: "ES256" })));
	const audience = new URL(endpoint).origin;
	const claims = bytesToBase64Url(encoder.encode(JSON.stringify({
		aud: audience,
		exp: Math.floor(Date.now() / 1000) + 12 * 60 * 60,
		sub: subject,
	})));
	const unsigned = `${header}.${claims}`;
	const signature = await crypto.subtle.sign({ name: "ECDSA", hash: "SHA-256" }, key, encoder.encode(unsigned));
	return `${unsigned}.${bytesToBase64Url(new Uint8Array(signature))}`;
}

function allowedPushEndpoint(endpoint) {
	const url = new URL(endpoint);
	if (url.protocol !== "https:") return false;
	return [
		"fcm.googleapis.com",
		"push.services.mozilla.com",
		"web.push.apple.com",
		"notify.windows.com",
	].some((domain) => url.hostname === domain || url.hostname.endsWith(`.${domain}`));
}

export async function sendWebPush(subscription, payload, config) {
	if (!allowedPushEndpoint(subscription.endpoint)) throw new Error("Unsupported push service endpoint.");
	const token = await vapidToken(subscription.endpoint, config.publicKey, config.privateKey, config.subject);
	const body = await encryptPushPayload(subscription, encoder.encode(JSON.stringify(payload)));
	return fetch(subscription.endpoint, {
		method: "POST",
		redirect: "manual",
		headers: {
			Authorization: `vapid t=${token}, k=${config.publicKey}`,
			"Content-Encoding": "aes128gcm",
			"Content-Type": "application/octet-stream",
			TTL: "86400",
			Urgency: "normal",
		},
		body,
	});
}
