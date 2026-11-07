import { DurableObject } from "cloudflare:workers";

const PORT = 80;
const INACTIVITY_TIMEOUT_MS = 10 * 60 * 1000;

export class AcCareContainer extends DurableObject {
	starting;

	constructor(ctx, env) {
		super(ctx, env);
		if (ctx.container.running) {
			void ctx.blockConcurrencyWhile(() =>
				ctx.container.setInactivityTimeout(INACTIVITY_TIMEOUT_MS),
			);
		}
	}

	async fetch(request) {
		this.starting ??= this.startAndWaitForPort().finally(() => {
			this.starting = undefined;
		});
		await this.starting;

		const url = new URL(request.url);
		url.protocol = "http:";
		url.host = "container";
		const forwarded = new Request(url, request);
		forwarded.headers.delete("host");
		return this.ctx.container.getTcpPort(PORT).fetch(forwarded);
	}

	async startAndWaitForPort() {
		const container = this.ctx.container;
		const databaseUrl = this.env.DATABASE_URL;
		if (typeof databaseUrl !== "string" || databaseUrl === "") {
			throw new Error("Set the DATABASE_URL Worker secret before starting AC Care.");
		}

		if (!container.running) {
			container.start({
				image: container.images.base,
				instance: "lite",
				enableInternet: true,
				env: {
					APP_ENV: "production",
					DATABASE_URL: databaseUrl,
					VAPID_PUBLIC_KEY: this.env.VAPID_PUBLIC_KEY ?? "",
					VAPID_PRIVATE_KEY: this.env.VAPID_PRIVATE_KEY ?? "",
					VAPID_SUBJECT: this.env.VAPID_SUBJECT ?? "mailto:admin@afeteknik.local",
				},
			});
		}
		await container.setInactivityTimeout(INACTIVITY_TIMEOUT_MS);

		const port = container.getTcpPort(PORT);
		let lastError;
		for (let attempt = 0; attempt < 100; attempt++) {
			try {
				const response = await port.fetch("http://container/assets/css/app.css", {
					signal: AbortSignal.timeout(1000),
				});
				await response.body?.cancel();
				if (!response.ok) {
					throw new Error(`Container readiness check returned ${response.status}.`);
				}
				return;
			} catch (error) {
				lastError = error;
				await scheduler.wait(200);
			}
		}

		throw new Error("AC Care container did not become ready on port 80.", {
			cause: lastError,
		});
	}
}

export default {
	fetch(request, env) {
		return env.AC_CARE_CONTAINER.getByName("ac-care").fetch(request);
	},
};
