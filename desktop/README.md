# AI Billing Offline for Windows

The desktop edition uses cloud-managed, single-PC activation. After local account and
business registration, it submits the registered user, company and a hashed PC
fingerprint to `billing.cloudkart24.com`. A super administrator approves the request
from **Admin > Desktop Licences**. The signed licence then installs automatically; no
activation key is displayed or typed. An internet connection is needed for the initial
request and approval. Billing continues offline after activation.

The licence is signed by the cloud service and stored with Windows DPAPI protection for
the current Windows user. Protected API routes also verify the licence against the PC
fingerprint. When internet is available, the application checks cloud status once per
day so suspension, revocation and reactivation can reach the PC. No local protection can
be completely tamper-proof against a determined Windows administrator, so production
installers should also be Authenticode-signed and the server signing key must remain only
on the cloud host. The architecture is documented in
[`LICENSING-ADMIN-PLAN.md`](LICENSING-ADMIN-PLAN.md).

Migration files containing `_cloud_` in their filename are recorded but not executed by
the desktop migration runner, so activation requests, licences and audit events remain
cloud-only.

The Windows edition runs the existing Vue/PHP/MySQL application on a single PC. It opens maximized from a desktop shortcut in a dedicated Microsoft Edge app-mode window, without browser tabs or an address bar. All locally supported frontend modules are enabled for the registered PC owner. Microsoft Edge must be installed (included with standard Windows 10/11 installations). A separate app profile keeps it independent of normal browser windows. Node, Laragon and internet access are not required at runtime. Supported target: Windows 10/11 x64 with Windows PowerShell 5.1 and .NET Framework 4.x. Microsoft's signed Visual C++ x64 prerequisite installer is bundled and installed when needed, which may display Windows administrator approval (UAC).

## Build and install

From the repository, run `powershell.exe -NoProfile -ExecutionPolicy Bypass -File desktop/Build.ps1`. Optional `-PhpRoot` and `-MysqlRoot` select trusted Windows x64 runtime directories. The first build downloads Microsoft's prerequisite from its official endpoint and verifies its signature; later builds reuse the verified local file. Outputs appear under a timestamped `dist/offline-*` directory: a self-extracting Setup EXE, a portable ZIP and an installer SHA-256 checksum. The EXE is unsigned; signing and clean-machine acceptance are required before public distribution. Runtime license/readme files are included; redistribution obligations must be met for the exact binaries shipped.

Run Setup and open the AI Billing Offline desktop shortcut. Setup installs a new version directory under `%LOCALAPPDATA%\Programs\AI Billing Offline`; it does not overwrite running versions or customer data. The launcher starts an isolated MySQL instance and a loopback-only PHP server, opens the billing desktop window. Closing the billing window stops services, including a graceful database shutdown. Opening the shortcut again while running returns to the same app profile. Reopen the shortcut to recover after a PC restart.

Uninstall from **Windows Settings > Apps > Installed apps > AI Billing Offline**, or
open **Uninstall AI Billing Offline** from the Start Menu. The uninstaller first offers
to keep local billing data for a later reinstall. Its permanent-delete choice removes
the local database, uploads, licence and backups, so export a backup before choosing it.

First launch initializes an empty local database and shows business/account setup. After
setup it opens the activation page inside the desktop window. Keep the app open while a
super administrator approves the PC; it polls securely and installs the licence without
revealing it. No demo records or default administrator account are installed. Subsequent
launches show local login. The Sign in link also works before setup; online account
credentials are separate from the account created on this PC. One business is supported.
Remember the local account password; restore uses the credentials contained in the
selected backup.

## SC588/PSF588 printer setup for customers

The Windows desktop installer contains the Bluetooth receipt sender. Pair each printer once in **Windows Settings > Bluetooth & devices**, then open **Settings > Printer & Paper > Test SC588 printer**. A successful test prints a short line. The app finds the paired Bluetooth serial port automatically, even when its COM number differs between PCs. Windows may show **Driver is unavailable** for this device; that does not prevent direct Bluetooth serial receipts. On an invoice choose **Bluetooth SC588**. Keep the printer powered on while printing. A first-time customer still needs cloud activation approval before using billing.

For the hosted web app on a Windows PC, use current Microsoft Edge or Chrome over HTTPS. Pair the printer in Windows, open **Settings > Printer & Paper > Test SC588 printer**, and select its serial port in the browser prompt. On an invoice choose **Bluetooth SC588** and select the port again when prompted. Browser permission requires a user click, so fully silent printing is not available from the web page. The browser route needs no desktop installer, but it does need a compatible browser and OS Bluetooth pairing. If a printer uses a Windows print driver instead, **Print 58mm** uses the normal browser print dialog.

The supplied `bprint://` instructions target an iPhone Bluetooth Print app. Mobile Safari cannot directly use the Windows Bluetooth serial route, so iPhone printing needs that separate helper app. Test each customer's actual printer before delivery; printer model labels and Bluetooth profiles can vary.

## Data and backups

Data is stored under `%LOCALAPPDATA%\AI Billing\Data`, separately from application files. `state.json` contains the local database credential and active database/storage pointers; keep this directory private to the Windows user. Database services bind to 127.0.0.1 and use ports 18765 (app) and 18766 (database). The launcher refuses occupied ports rather than connecting to an unrelated service.

Daily backups are taken on startup and checked while the app is open. The Backup & Restore page creates manual backups and downloads copies for USB storage. Backups include a transactional SQL dump, uploaded files and SHA-256 checksums. Cache files and runtime credentials are excluded. Archives are not encrypted and contain customer information and password hashes: keep USB copies secure. Backups remain on disk until the user removes them; monitor disk space. Backup failure must be addressed rather than assuming local data is protected.

Restore accepts `.aibackup` files up to 512 MB uploaded size and 2 GB expanded size. It validates paths, missing/duplicate files, links and checksums, creates a safety backup, imports into a fresh database, validates essential tables and a single business, stages uploads, revokes imported sessions, and switches active pointers only after success. The previous database and uploads are retained. Existing data remains active on validation/import failure. Restoring replaces active business records; confirm by typing RESTORE.

SQL imports run with a temporary database account restricted to the staging database. The MySQL client runs in binary mode to disable client command execution. Backups must match the installed schema version; use the matching installer to restore an older/newer archive, then update the restored installation. Failed staging databases/files are retained for support diagnosis and may consume disk space; they never become active automatically.

From an initialized installation, support can run the bundled PHP with `desktop/cli.php backup` or `restore <file>` after setting the same environment variables as the launcher. This is for recovery when the owner cannot sign into the application. Do not run maintenance CLI concurrently with the UI server; stop the app server first while keeping its database available.

Updates create a pre-update backup before pending migrations. SQL migration failures stop startup. MySQL DDL is not transactional: a partially applied migration may need support repair, and the pre-update archive remains available. The installer retains old program versions; remove them only after successful upgrade verification.

## Offline boundaries

Local billing, customers, products, payments, reports, invoices and PDFs work without internet. Live e-way bill generation is disabled; guest task, public-card and HTTP migration paths are blocked locally. Cloud sync, cloud backup, email delivery and multi-PC access are outside this release. The server is intended only for the loopback single-user desktop workload, not public hosting.

## Verification

Run `npm.cmd run test:offline` for an isolated integration test. The test uses its own data directory and database instance, creates records, exercises backup/restore and invalid archives, verifies billing/PDF data, and stops its services. It never reads or modifies the online database. Test on a clean Windows PC, with internet disabled, before delivering the installer to customers; verify first-run setup, restart, USB download/restore and the customer's actual printer.

To verify a built package, set `BILLING_OFFLINE_PACKAGE` to its extracted/payload directory before running the test. This launches the packaged PHP, MySQL and application files instead of Laragon runtimes. The test includes browser-based backup download/restore, path/checksum/SQL rejection, staging-account isolation and a full service restart. Logs and isolated test data are retained under ignored `desktop/.test-data-*` directories.

Runtime references: [PHP loopback router](https://www.php.net/commandline.webserver), [MySQL data-directory initialization](https://dev.mysql.com/doc/refman/8.4/en/data-directory-initialization.html), [Microsoft Visual C++ prerequisite](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist).
