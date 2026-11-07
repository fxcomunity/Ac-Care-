# AC Care

AC Care is a PHP application backed by PostgreSQL. The Cloudflare deployment uses a Worker to route requests to a PHP/Apache Container.

The source project's MIT notice is preserved in `LICENSE-AC-CARE`; the repository's existing `LICENSE` is retained.

## Deploy to Cloudflare

Cloudflare Containers require a Workers Paid plan, a Cloudflare account, and a Docker image build. The included GitHub Actions workflow builds and deploys from a GitHub-hosted runner, so Docker does not need to run on your PC.

1. Enable the Workers Paid plan on your Cloudflare account.
2. In the GitHub repository settings, add `CLOUDFLARE_API_TOKEN` as an Actions secret and `CLOUDFLARE_ACCOUNT_ID` as an Actions variable.
3. Configure `DATABASE_URL` as a Worker secret in Cloudflare. Do not commit `.env` or paste secret values into source files.
4. Optionally configure `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` as Worker secrets for web push.
5. Ensure the PostgreSQL database has been initialized with `database/schema.sql`.
6. Open the **Deploy to Cloudflare** workflow in GitHub Actions and select **Run workflow**.

Cloudflare Container filesystems are not a durable store; use persistent object storage for branding images uploaded after deployment. The existing tracked branding images are included in each image build.

For a local PHP setup, the app continues to read settings from `.env`. On Cloudflare, database and optional push settings are supplied as Worker secrets.
