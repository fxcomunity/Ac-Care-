# AC Care

AC Care is deployed as a Cloudflare Worker. Its application backend is JavaScript and connects to the existing PostgreSQL database through Cloudflare Hyperdrive. The Worker does not run PHP or a container; the original PHP files remain in the repository as the legacy source and are not uploaded as runtime assets.

## Features ported to Workers

- Password login, public customer registration, signed secure sessions, CSRF protection, login throttling, and role-based access.
- Customer and AC-unit records, service history and printable invoices, scheduling, and three-month maintenance reminders.
- Product and maintenance catalog, operational reports, customer portal, account management, website and WhatsApp template settings, audit logs, and Web Push subscriptions/delivery.
- Existing PHP page URLs redirect internally to their corresponding Worker routes.

The existing PostgreSQL tables are reused without a database-engine migration. Existing PHP bcrypt password hashes are compatible with the Worker login.

## One-time setup

1. In the PostgreSQL database, run [`database/schema.sql`](./database/schema.sql). Back up the database first if this is a production database; the schema also contains sample AC customer and service records.
2. Create a Hyperdrive configuration for that PostgreSQL database in the Cloudflare dashboard. Copy its Hyperdrive ID. Hyperdrive supports Neon and is available on Cloudflare Workers Free and Paid plans.
3. Ensure there is an owner account. If the database has no owner, install dependencies on a trusted computer and create one using environment variables. This connects directly to PostgreSQL; the script never reads `.env`:

   ```powershell
   $env:DATABASE_URL = Read-Host "PostgreSQL connection URL"
   $env:OWNER_NAME = Read-Host "Owner name"
   $env:OWNER_USERNAME = Read-Host "Owner username"
   $securePassword = Read-Host "Owner password" -AsSecureString
   $env:OWNER_PASSWORD = [System.Net.NetworkCredential]::new("", $securePassword).Password
   npm ci
   node scripts/create-owner.mjs
   Remove-Item Env:DATABASE_URL, Env:OWNER_NAME, Env:OWNER_USERNAME, Env:OWNER_PASSWORD
   ```

   Use a new, strong owner password. The script refuses to run if an owner already exists.

## GitHub Actions deployment

The **Deploy to Cloudflare** workflow builds the static asset directory and deploys the Worker directly from a GitHub-hosted runner.

1. Create a restricted Cloudflare API token for the selected account with **Workers Scripts: Edit** and **Hyperdrive: Read** permissions. Do not paste the token into chat or commit it.
2. In GitHub, open **Settings → Secrets and variables → Actions** and add:
   - Secret `CLOUDFLARE_API_TOKEN`: the Cloudflare API token.
   - Secret `SESSION_SECRET`: a randomly generated secret with at least 32 characters.
   - Variable `CLOUDFLARE_ACCOUNT_ID`: the account ID shown in Cloudflare.
   - Variable `HYPERDRIVE_ID`: the Hyperdrive ID created above.
3. Optionally configure `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` as Worker secrets in the Cloudflare dashboard (or with `npx wrangler secret put <NAME>`) to enable Web Push. Keep the private key secret.
4. Open **Actions → Deploy to Cloudflare → Run workflow** and wait for a successful run.
5. In Cloudflare, open **Workers & Pages → ac-care → Settings → Domains & Routes** to use the `workers.dev` address or connect a custom domain.

The workflow only accepts a Hyperdrive ID, not a database URL. PostgreSQL credentials are stored in the Hyperdrive configuration in Cloudflare, not in GitHub or this repository.

## Local checks

- `npm ci` installs Worker dependencies.
- `npm run build` copies the existing CSS, JavaScript, and branding assets into the Worker static-assets directory.
- `npm test` runs Worker helper tests.
- `npx wrangler deploy --dry-run` validates the Worker bundle; a real deployment requires the Hyperdrive ID, Cloudflare account credentials, and Worker secrets above.

Website logos can be set using an HTTPS image URL. File uploads to the old PHP filesystem are not used by the Worker deployment.
