import { Client } from "pg";

export async function withDatabase(env, callback) {
	if (!env.HYPERDRIVE?.connectionString) {
		throw new Error("Hyperdrive is not configured for this Worker.");
	}

	const client = new Client({ connectionString: env.HYPERDRIVE.connectionString });
	await client.connect();
	try {
		return await callback(client);
	} finally {
		await client.end();
	}
}
