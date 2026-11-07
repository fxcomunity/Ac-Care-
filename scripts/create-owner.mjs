import bcrypt from "bcryptjs";
import pg from "pg";

const required = ["DATABASE_URL", "OWNER_NAME", "OWNER_USERNAME", "OWNER_PASSWORD"];
const missing = required.filter((name) => !process.env[name]);
if (missing.length) {
	console.error(`Set these environment variables before running this script: ${missing.join(", ")}`);
	process.exitCode = 1;
} else {
	const username = process.env.OWNER_USERNAME.toLowerCase();
	const password = process.env.OWNER_PASSWORD;
	if (!/^[a-z0-9._-]{4,50}$/.test(username)) {
		throw new Error("OWNER_USERNAME must be 4–50 lowercase letters, numbers, dots, underscores, or hyphens.");
	}
	if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
		throw new Error("OWNER_PASSWORD must contain at least 8 characters, including a letter and a number.");
	}

	const client = new pg.Client({ connectionString: process.env.DATABASE_URL });
	await client.connect();
	try {
		await client.query("BEGIN");
		await client.query("SELECT pg_advisory_xact_lock(hashtext('ac-care-owner-bootstrap'))");
		const owners = await client.query("SELECT 1 FROM users WHERE role='owner' LIMIT 1");
		if (owners.rowCount) throw new Error("An owner account already exists; bootstrap stopped without changing any account.");

		const hash = await bcrypt.hash(password, 12);
		const updated = await client.query(
			"UPDATE users SET nama=$1,role='owner',aktif=TRUE,password_hash=$2,updated_at=CURRENT_TIMESTAMP WHERE username=$3 RETURNING id",
			[process.env.OWNER_NAME, hash, username],
		);
		if (!updated.rowCount) {
			await client.query(
				"INSERT INTO users (nama,username,password_hash,role,aktif) VALUES ($1,$2,$3,'owner',TRUE)",
				[process.env.OWNER_NAME, username, hash],
			);
		}
		await client.query("COMMIT");
		console.log(`Owner account ${username} is ready. Remove the credential environment variables now.`);
	} catch (error) {
		await client.query("ROLLBACK");
		throw error;
	} finally {
		await client.end();
		process.env.OWNER_PASSWORD = "";
		process.env.DATABASE_URL = "";
	}
}
