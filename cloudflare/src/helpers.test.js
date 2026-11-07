import test from "node:test";
import assert from "node:assert/strict";
import { legacyPath, reminderInfo, whatsappUrl } from "./index.js";
import { escapeHtml } from "./views.js";

test("legacy PHP URLs resolve to Worker routes", () => {
	assert.equal(legacyPath(new URL("https://ac-care.test/auth/login.php")), "/auth/login");
	assert.equal(legacyPath(new URL("https://ac-care.test/pelanggan/detail.php?id=00000000-0000-0000-0000-000000000001")), "/pelanggan/00000000-0000-0000-0000-000000000001");
	assert.equal(legacyPath(new URL("https://ac-care.test/servis/invoice.php?id=00000000-0000-0000-0000-000000000001")), "/servis/00000000-0000-0000-0000-000000000001/invoice");
});

test("reminders calculate due, upcoming, safe, and late statuses", () => {
	assert.deepEqual(reminderInfo("2026-01-15", "2026-04-15"), { date: "2026-04-15", days: 0, status: "jatuh_tempo" });
	assert.equal(reminderInfo("2026-01-15", "2026-04-10").status, "segera");
	assert.equal(reminderInfo("2026-01-15", "2026-03-31").status, "aman");
	assert.equal(reminderInfo("2026-01-15", "2026-04-16").status, "terlambat");
});

test("reminder dates preserve PHP month-overflow semantics", () => {
	assert.equal(reminderInfo("2026-01-31", "2026-05-01").date, "2026-05-01");
});

test("WhatsApp links encode customer content and normalize Indonesian numbers", () => {
	const url = new URL(whatsappUrl("081234567890", "A & B", "AC 1", "2026-01-15", "Halo {nama_pelanggan}: {nama_unit}"));
	assert.equal(url.hostname, "wa.me");
	assert.equal(url.pathname, "/6281234567890");
	assert.equal(url.searchParams.get("text"), "Halo A & B: AC 1");
});

test("HTML renderer escapes untrusted content", () => {
	assert.equal(escapeHtml(`<script>alert("x")</script>`), "&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;");
});
