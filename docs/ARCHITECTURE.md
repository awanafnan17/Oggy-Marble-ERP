# Architecture guide

## System shape

Oggy Marble ERP is a Laravel monolith with an Inertia/React presentation layer.
The browser receives React pages and sends ordinary authenticated web requests;
Laravel remains the authority for validation, permissions, financial-period
rules, persistence, and audit behavior.

This shape keeps operational transactions inside one consistency boundary. A
production completion, stock movement, invoice, payment, and journal entry can
be committed or rolled back as a single database transaction.

## Request path

1. `routes/web.php` declares authenticated, permission-gated routes.
2. `CheckPermission` verifies server-side capabilities.
3. `EnsurePeriodOpen` blocks dated transactional writes into closed periods.
4. Controllers validate input and coordinate the use case.
5. Domain services apply cross-model rules inside database transactions.
6. Eloquent models persist records and expose relationships to Inertia pages.
7. `HandleInertiaRequests` shares authenticated user and flash state with React.

## Backend organization

| Location | Responsibility |
| --- | --- |
| `app/Modules/*/Controllers` | High-volume accounting, inventory, production, sales, and purchasing workflows |
| `app/Http/Controllers` | Dashboard, cash/bank, reports, security, quality, schedules, dispatch, and supporting workflows |
| `app/Services` | Inventory, accounts, subledgers, periods, reconciliation, production variance, audits, and dashboard aggregation |
| `app/Models` | ERP entities and relationships |
| `app/Http/Middleware` | Permission checks, period locking, and Inertia shared data |
| `database/migrations` | Schema evolution and financial/operational constraints |
| `database/seeders` | Synthetic master data and first-administrator provisioning |

## Frontend organization

| Location | Responsibility |
| --- | --- |
| `resources/js/Pages` | Route-level Inertia screens |
| `resources/js/Layouts` | Authenticated application shell and permission-aware navigation |
| `resources/js/components` | Tables, cards, report headers, status badges, modal patterns, and error boundary |
| `resources/js/brand.ts` | Public-edition display defaults |
| `resources/css/app.css` | Tailwind entry point and print rules |

The frontend may hide actions a user cannot perform, but authorization is always
rechecked on the server.

## Domain modules

### Inventory

Products carry inventory, COGS, and revenue-account mappings. Batches and stock
ledger entries preserve the source and cost of inventory. Adjustments,
reconciliation, and warehouse transfers are transactional and use guards to
prevent invalid negative stock. Transfer documents can produce gate passes.

### Marble production

The manufacturing model covers BOMs, production orders, schedules, and machines,
plus two domain-specific conversion paths:

- raw stone to slab;
- slab to finished tile.

Runs capture input, output, labor, overhead, yield, waste, and variance. The
variance service translates operational differences into the configured
financial accounts.

### Sales, purchasing, and subledgers

Sales orders produce customer invoices; purchasing produces supplier bills.
Payments can be allocated to receivables or payables. Customer/supplier ledgers
derive from posted documents and payments so aging and account statements remain
traceable.

### Accounting and period governance

Journal entries use balanced line items. Accounting periods can be closed and
reopened by authorized roles. Dated financial writes pass through the period
middleware. Posted transactions are voided or reversed instead of silently
deleted, preserving history for audit review.

### Cash, bank, and reconciliation

Bank accounts and transactions support deposits, withdrawals, and transfers.
Optional proof images are validated, resized, converted to WebP, and stored on a
private disk. They are served through an authorized controller rather than a
public storage symlink.

The bulk reconciliation action marks all pending transactions for the account
through the statement date after explicit confirmation. It is a workflow
shortcut, not imported-statement matching. Users review individual transactions
and the calculated difference before closing a reconciliation.

### Quality, dispatch, and equipment

Quality inspections, delivery challans, dispatch status, production machines,
and rental jobs link the factory floor to the same role and audit model used by
financial operations.

## Integrity and security boundaries

- Authentication sessions are regenerated on login and invalidated on logout.
- Login requests are rate-limited.
- Inactive users are rejected before authentication completes.
- Passwords require at least 12 characters in the application workflows.
- The first administrator password must be supplied through the environment;
  there is no committed default.
- Admin/owner checks protect user administration and prevent disabling the last
  active privileged account.
- Permission middleware protects view/manage capabilities.
- Period middleware protects dated financial writes.
- DB transactions and row locks protect multi-record posting.
- Proof uploads enforce size and MIME constraints and remain private.
- Client data, credentials, databases, uploads, logs, and backups are excluded
  from the public repository.

## Extension points

- Add imported bank-statement lines and deterministic matching without changing
  the current explicit bulk-reconcile semantics.
- Move queues, cache, and sessions to Redis for horizontally scaled deployments.
- Add object storage for encrypted private proofs.
- Introduce API resources only when an external integration requires them;
  current workflows benefit from one monolithic consistency boundary.
- Add tenant boundaries before serving multiple companies from one deployment.
