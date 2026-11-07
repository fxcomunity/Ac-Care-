# AC Care

AC Care is a PHP application backed by PostgreSQL. The Cloudflare deployment uses a Worker to route requests to a PHP/Apache Container.

The source project's MIT notice is preserved in `LICENSE-AC-CARE`; the repository's existing `LICENSE` is retained.

## Deploy to Cloudflare

Cloudflare Containers require a Workers Paid plan, Docker, and a Cloudflare account.

1. Install dependencies with `npm install`.
2. Authenticate with `npx wrangler login`.
3. Add the database URL as a Worker secret: `npx wrangler secret put DATABASE_URL`.
4. Optionally add `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` as Worker secrets for web push.
5. Ensure the PostgreSQL database has been initialized with `database/schema.sql`.
6. Deploy with `npm run deploy`.

Do not commit `.env` or paste secret values into source files. Cloudflare Container filesystems are not a durable store; use persistent object storage for branding images uploaded after deployment. The existing tracked branding images are included in each image build.

For a local PHP setup, the app continues to read settings from `.env`. On Cloudflare, `DATABASE_URL` and optional push settings are supplied as Worker secrets.
