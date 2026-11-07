import { Client } from "@neondatabase/serverless";

export async function withDatabase(env, callback) {
	if (!env.DATABASE_URL) {
		throw new Error("DATABASE_URL is not configured for this Worker.");
	}

	const client = new Client(env.DATABASE_URL);
	await client.connect();
	try {
		return await callback(client);
	} finally {
		await client.end();
	}
}
