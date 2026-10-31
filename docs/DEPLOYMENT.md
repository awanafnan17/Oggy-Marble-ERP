# Production deployment checklist

This guide is a review checklist, not a substitute for environment-specific
operations and security engineering.

## 1. Platform

- PHP 8.2 or 8.3 with PDO for the selected database, Mbstring, Fileinfo, GD with
  WebP, and EXIF.
- Composer 2 and Node.js 20.19+ for the build stage.
- Nginx/Apache or a managed PHP runtime with the document root set to `public/`.
- MySQL or PostgreSQL for a durable multi-user deployment.
- HTTPS from the public edge to the application or a verified private upstream.

Do not expose the repository root as the web root. Do not trust arbitrary
forwarded headers. Configure only the reverse-proxy addresses used by the chosen
platform.

## 2. Secrets and environment

Create the production environment outside version control. At minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.example.com
APP_KEY=<generated-secret>

DB_CONNECTION=mysql
DB_HOST=<private-host>
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<least-privilege-user>
DB_PASSWORD=<secret>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

OGGY_SUPERADMIN_EMAIL=<initial-admin>
OGGY_SUPERADMIN_PASSWORD=<initial-secret>
```

Store values in the platform's secret manager. Never bake them into an image,
workflow, repository secret file, support bundle, or client-side JavaScript.
Generate the application key with `php artisan key:generate --show` in a secure
environment.

## 3. Build and release

```bash
composer install --no-dev --classmap-authoritative --no-interaction
npm ci
npm run typecheck
npm run build
composer audit --locked --no-dev
npm audit --omit=dev --audit-level=high
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run the full test suite in CI before producing the release artifact. Build assets
once, then deploy the same immutable artifact to each application instance.

## 4. Database initialization

Back up the target before migrations.

```bash
php artisan migrate --force
php artisan oggy:setup-master-data
```

The setup command creates the first administrator only when that email does not
exist. Rerunning it refreshes master reference data but does not silently rotate
the existing administrator's password.

After the first successful login:

1. create named accounts for each operator;
2. assign only required roles;
3. verify the first administrator's password and recovery process;
4. remove the initial password from any temporary deployment input;
5. review the seeded chart of accounts, warehouse, company identity, and tax
   settings with the responsible accountant.

## 5. Writable paths and private files

The runtime user needs write access to `storage/` and `bootstrap/cache/`. Proof
images belong on a private disk and must not be served directly from a public
bucket. A public `storage:link` is not required for transaction proofs.

Apply retention, access logging, encryption, and deletion policies to proofs,
exports, backups, and application logs. Validate restore procedures rather than
only checking that backup files exist.

## 6. Runtime services

- Use durable sessions and cache for more than one application instance.
- Run `php artisan queue:work` under a process supervisor when a non-sync queue
  is selected.
- Schedule recurring jobs with one scheduler leader if scheduled commands are
  added.
- Send logs to an access-controlled central destination and redact personal and
  financial fields.
- Monitor `/up`, queue depth, failed jobs, database capacity, storage capacity,
  authentication failures, and application exceptions.

## 7. Security review

Before launch, verify:

- HTTPS and secure-cookie settings;
- permitted proxy addresses and hostnames;
- least-privilege database and object-storage credentials;
- role/permission assignments for each workflow;
- upload validation and malware-handling requirements;
- login throttling and edge rate limits;
- backup encryption and restore testing;
- dependency audit results and patch cadence;
- log and proof-image retention;
- disaster recovery and administrator lockout procedures;
- an independent review of the deployment configuration and business rules.

## 8. Rollback

Keep the previous application artifact and a migration-aware rollback plan.
Database rollback is not equivalent to restoring business state: invoices,
payments, production, and inventory may be posted during a release. Coordinate
application rollback with accounting and operations owners, and prefer
corrective migrations/reversal entries over deleting committed records.
