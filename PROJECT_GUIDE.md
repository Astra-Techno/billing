# Project guide

Repository review: 2026-09-14. This describes the checked-in implementation, not a verified production environment. No application code or database data was changed during the review.

This document records the initial review. The subsequent Windows offline implementation and its current backup/restore behavior are documented in [desktop/README.md](desktop/README.md). That implementation also addresses several initial findings, including guest-task allowlisting, authenticated tenant placeholder precedence, invoice discount allocation, payment transactions, password task routing and exception status codes. The findings below describe the original baseline.

## Product and scope

The product is branded **AI Billing** (`src/config/brand.js`), for Indian businesses. It supports multiple businesses per user, GST invoices, walk-in customers, quotes, collections, expenses, products, customer statements, purchasing and delivery documents, credit notes, payroll, timesheets, and platform administration. Public business cards are available at `/shop/:slug`.

Despite the product name, the reviewed application code contains no identified AI model, OCR, or LLM integration. GST exports and e-way bill support are application features; their presence alone does not establish regulatory correctness or successful integration with an external service.

## Stack and directories

| Location | Responsibility |
| --- | --- |
| `src/main.js`, `src/App.vue` | Vue 3 startup, Pinia, router, shared styles, stale chunk recovery, Android WebView input handling |
| `src/router/index.js` | Lazy-loaded routes and frontend authentication/role guards |
| `src/components/layout/` | Responsive desktop/mobile shell, navigation, search, help and toast integration |
| `src/views/` | Feature screens, forms, lists, details and print layouts |
| `src/api/index.js` | Axios transport and generic query/task helpers |
| `src/stores/` | Authentication/session, active business settings and UI state |
| `src/composables/` | Permissions, list refresh, form shortcuts, help, tours, notifications and toasts |
| `src/utils/` | Invoice calculations, currency/date formatting, scrollbars and deployment chunk recovery |
| `api/index.php`, `api/bootstrap/app.php` | PHP entry point, dotenv, Slim 4, database/cache setup and middleware |
| `api/routes/api.php` | Public and authenticated API routes |
| `api/app/Task/` | Commands and business workflows |
| `api/app/Sql/` | Read queries and report aggregation |
| `api/app/Tables/` | Table metadata and persistence models |
| `api/app/Base/`, `api/app/Core/` | Custom DataForge-style dispatch, query building, persistence, validation, auth, PDO and cache |
| `api/database/` | Baseline schema, seed and 18 numbered SQL migrations |
| `e2e/` | Playwright authentication setup, page objects and browser tests |
| `public/` | Static assets and Apache SPA rewrite rules copied by Vite |
| `public_html/` | Committed production frontend build used by deployment |
| `deploy.php` | Key-protected GitHub deployment and SQL migration runner |
| `design-mockups/`, root mockup HTML files | Design references rather than primary application source |

Frontend dependencies include Vue Router, Pinia, Axios, VueUse, QRCode and Tailwind CSS. Vite builds the frontend. Backend requires PHP 8.1+ and uses Slim/PSR-7, PDO MySQL, dotenv, Carbon and Dompdf. Composer dependencies are committed for shared hosting without Composer access.

## How a request works

1. A view calls `list`, `all`, `item`, `count`, or `task` from `src/api/index.js`.
2. Axios adds the bearer token and `X-Business-ID`. GET requests also receive a timestamp parameter to avoid stale responses.
3. Slim parses the body; `RequestMiddleware` puts request data into `RequestHolder`.
4. For protected routes, `AuthMiddleware` validates the SHA-256 token hash, checks an active user/membership, and establishes the user and business context.
5. Reads go through `SqlController` into `App\Sql\{Name}`. URL method selectors such as `Invoice:items` become `Invoice.items`.
6. Writes go through `TaskController` and `Task::run('Invoice.create', input)`. The factory resolves the class/method, applies task rules, injects the user and optionally wraps execution in a database transaction.
7. Task code validates inputs, checks context/roles where implemented, persists records and returns a JSON envelope such as `{success, message, data}`.

The API is primarily a generic read/command API rather than a separate REST controller per feature. List responses contain arrays; counts use a separate endpoint. `list` defaults to 200 records and caps explicit limits at 1000. `all` does not apply the same cap.

## Session and tenant model

Global users connect to businesses through `business_users`, with roles `owner`, `admin`, `accountant`, or `staff`, plus optional custom permissions. Most business records carry `business_id`.

Registration creates the user and first business, owner membership, trial subscription, default tax rates and expense categories. Login returns user details, businesses and a plain bearer token. Tokens are stored hashed in `personal_access_tokens`; the frontend stores the plain token and session metadata in localStorage. Token creation does not set an expiry, although validation supports expiry values.

For one business, login scopes the token automatically. For multiple businesses, the frontend selects the first and requests a business switch. Switching validates membership, revokes the old token and creates another scoped token. Scoped token context takes precedence over the header. Super administrators have dedicated frontend screens and explicit checks in administrative task code.

Tenant isolation and custom permissions are not enforced automatically by the base table or dispatch layers. Each query/task must enforce the relevant boundaries; see the findings below.

## Main billing workflow

- Clients and products supply reusable customer and item information. Invoice forms can also create them inline; client selection is optional for walk-in invoices.
- Invoice creation validates dates/items, resolves intra/inter-state supply, reserves a financial-year sequence number, computes totals, stores the invoice and its items, and tracks usage inside a transaction.
- Numbering is business/document/year specific, with formatting such as `INV/2026-27/0001`. The financial year runs April through March. Sequence generation uses a transaction and `SELECT ... FOR UPDATE`.
- New invoices are drafts. Editing is limited to drafts. Sending, partial/full payment, cancellation, duplication, and bulk sending/payment have separate commands. Deletion is restricted to owner/admin draft invoices without payments or credit notes and uses the invoice model's soft-delete setting.
- `Payment.record` stores a payment and updates `amount_paid`, `amount_due`, and status. Payment deletion recalculates balances from remaining payments and writes an audit entry.
- Quote conversion reads quote items, invokes `Invoice.create`, and records the resulting invoice ID on the converted quote.
- Delivery challan data can populate an invoice form. Purchase orders have their own supplier, item and status workflows.
- Credit notes can adjust an invoice's outstanding balance. Payment recalculation and credit adjustment need to remain consistent across future changes.
- Invoice output has two implementations: Vue print templates and PHP/Dompdf PDF generation. Invoice detail includes payment and e-way bill actions.

## Calculation and reporting behavior

`src/utils/invoice.js` previews totals; `api/app/Task/Invoice.php` computes persisted totals independently. Both calculate quantity times price, apply an invoice-level percent/fixed discount, allocate discounted taxable value proportionally across lines, split intra-state tax into CGST/SGST or use IGST for inter-state supply, and round the final total to whole rupees.

The backend rounds tax per line before summing; the frontend accumulates tax before rounding. Small rounding differences are therefore possible. Backend totals include a UTGST field, but the reviewed invoice calculation leaves it at zero.

Dashboard queries aggregate billing and payment metrics. `Report.php` provides B2B/B2C GST summaries, HSN aggregation, revenue/collection data, ageing, and payment collection. Some reports aggregate invoice headers, others aggregate saved items; those two sources currently differ for discounted invoices.

Payroll uses separate `staff_members` and `payroll_runs` records, distinct from login/team membership. Monthly payroll starts with full working days and calculates prorated salary plus bonus minus deductions. Paying payroll creates expense entries. Timesheets have user ownership and owner/admin approval/rejection workflows; the reviewed payroll generation does not derive worked days from timesheets.

E-way bills use configured platform GSP credentials and per-business credentials when all required values exist. Otherwise generation returns a simulated 12-digit number starting with `99`, while saving the record with `active` status. External GSP behavior was not exercised.

## Frontend behavior worth preserving

Lists use nested routes for forms/details, supporting desktop split panes and mobile navigation. `AppLayout` keys the page by the parent feature route, so lifecycle/refetch behavior matters when moving within a feature. `useListRefresh` refreshes on mount, activation and return to a named list route.

Business feature switches are stored as JSON settings. Core modules default on, with several advanced modules default off. The business store loads logo/state/features, while dark mode and the selected invoice template use localStorage. Feature switches are primarily UI configuration rather than a backend authorization layer.

The application includes guided tours, contextual help, keyboard shortcuts, global search, invoice QR/payment information, mobile safe-area spacing and WebView-specific input direction handling. Hashed build assets are immutable; the HTML shell is configured to revalidate, with stale chunk reload recovery.

## Deployment and local operation

Development uses Vite with a `/billing/api` proxy to local Apache. The Axios base URL comes from `VITE_API_URL`, with a fallback of `http://localhost/billing/api`. PHP derives its Slim base path from `APP_URL`; frontend history routing and Apache rewrites assume hosting paths are configured consistently.

`npm.cmd run build` writes to `public_html` by default. Deployment downloads the configured GitHub branch, replaces backend/frontend files while preserving selected runtime files, and executes unapplied SQL migration files tracked by `_migrations`. This is a direct shared-hosting deployer, not an atomic release mechanism. Do not invoke it for routine validation.

The README is still the Vue starter template and does not explain installation, database bootstrap or runtime configuration. Do not infer the live database's migration state from the repository files.

## Findings from source review

These are source-confirmed paths or inconsistencies; no exploit requests or production mutations were performed.

| Priority | Finding | Evidence and implication |
| --- | --- | --- |
| High | Guest task dispatch has no allowlist | `TaskController::guest` delegates directly to `action`; `Task::run` has no universal authentication gate. Public access depends entirely on individual method checks. For example, `Invoice.updateOverdue` updates invoices across businesses without requiring authentication or tenant context. |
| High | Query input can override authenticated tenant placeholders | `Query::replaceConstant` reads an input key before its `Auth::businessId()` fallback. A supplied `business_id` can therefore override queries that rely on `{business_id}` for isolation. |
| High | Generic reads expose unscoped queries | `Sql/User.php` lists users globally and `byEmail` selects `u.*`; `Sql/Admin.php` has global queries without its own super-admin check. Generic SQL routes require authentication but have no centralized role/query allowlist. |
| High | Public database migration route | `GET /run-migrate` performs ALTER/CREATE statements without authentication. The authenticated `/migrate` route also lacks a privileged-role check. |
| High | Discounted headers disagree with saved items | `Invoice::calculateTotals` allocates the invoice discount, while `saveItems` calculates undiscounted amounts and saves zero line discount. HSN reports sum those saved amounts. |
| High | Payment writes are not atomic | `Payment` does not enable the base transaction wrapper; payment insertion/deletion and invoice balance updates are separate operations. Read-modify-write balances also lack row locks, including transactional invoice payment shortcuts. Failures or concurrent requests can desynchronize balances. |
| Medium | Three permission systems disagree | Router role defaults, `useRole` custom permissions/defaults, and backend task checks differ. Router guards ignore custom permissions; many backend creation/read paths do not enforce the page permissions shown in the UI. |
| Medium | Password change calls a missing task class | Settings calls `task('User', 'changePassword')`, but the implementation lives in `Task/Auth.php`; there is no `Task/User.php`. |
| Medium | Entity route targets a missing controller method | Routes reference `EntityController::fetch`, while the controller defines `get`. |
| Medium | Related-record tenant validation is incomplete | Invoice creation accepts client/product/quote IDs without consistently verifying they belong to the active business; supply resolution queries client state by ID alone. |
| Medium | Sending can overwrite paid status | Single `Invoice.markSent` rejects cancelled invoices but accepts paid invoices and sets `sent`; the bulk variant excludes paid invoices. |
| Medium | Business settings can survive session/context changes | The business store's loaded flag and values are not reset by auth logout/switch; `switchBusiness` also leaves the stored membership/permission list untouched. Same-app session changes can display stale settings. |
| Medium | Simulated e-way bills appear active | Missing integration credentials trigger simulation instead of an explicit unavailable/test state. UI and operational assumptions need to reflect this behavior. |
| Medium | Error status handling loses task codes | `raiseError` throws ordinary `Exception` objects with a code, but the JSON handler checks `getStatusCode()` and otherwise returns 400. Intended 401/403/404/422 task failures can be reported as 400. PHP error display is also explicitly enabled at entry. |
| Low | Table query hydration calls a protected method | The separate `TableQuery` class invokes `Table::hydrate`, which is protected. Those finder paths need runtime verification before use. |

## Validation performed

- Production build passed using `node node_modules/vite/bin/vite.js build --outDir dist/project-review`, keeping the committed deployment output intact.
- Frontend unit tests: **61 passed, 2 failed**, across 63 tests. The failures in `src/utils/invoice.test.js` expect line-level discounts, which the current helpers do not apply.
- Backend unit tests: **62 passed, 73 assertions**, under PHP 8.3.16. These cover query/validator behavior; they do not establish database workflow, authorization or integration correctness.
- Playwright tests were inspected, not executed. They share authentication and create/update application records, including customer, invoice and settings data. Several invoice expectations describe an older wizard/customer-selection flow, so compatibility with the present UI needs checking against an isolated test database.
- No migrations, deployment, external service calls or application-data mutations were run.

## Where to start future changes

For billing behavior, trace `InvoiceForm.vue` -> `src/utils/invoice.js` -> `Task/Invoice.php` -> invoice/item table models -> `Sql/Invoice.php`, report queries and both print implementations. For payment changes, include `Task/Payment.php`, invoice payment shortcuts and credit-note adjustment. For access changes, examine auth middleware, placeholder binding, both frontend permission maps and every dispatched read/write method. For new schema fields, update migrations, model fillable metadata, write payloads, reads and the relevant forms together.

The first correctness work should close public dispatch/tenant override paths, align persisted discount calculations and reports, and make payment balance changes transactional with concurrency protection. Existing unit test success alone does not cover these boundaries.
