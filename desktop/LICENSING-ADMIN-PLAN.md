# Desktop licensing in the CloudKart admin portal

## Objective

Manage every AI Billing Offline activation from the existing super-admin login at
`https://billing.cloudkart24.com/admin`. The customer never sees or enters a licence
key. The PC sends an activation request, a super administrator approves it, and the
PC retrieves and stores a signed licence automatically.

## Admin navigation

Add **Desktop Licences** beside Businesses and Users in the super-admin sidebar.
The admin dashboard should also show Pending, Active, Expiring, Revoked and Total
PC counters. Pending requests should be visually prominent.

The list page needs filters for status, customer, company, device ID, edition,
expiry and activation date. Each row should show the customer, local company,
masked device ID, PC name, Windows version, status, activation date, expiry and
last cloud contact.

## Licence detail and actions

The detail page presents four panels:

1. Customer: cloud account, registered local user, email, mobile and local company.
2. Device: masked device fingerprint, PC name, Windows version, app version and
   first/last contact timestamps.
3. Licence: edition, status, issue date, expiry, maximum version and transfer count.
4. Audit history: request, approval, renewal, revocation and transfer entries with
   administrator, timestamp, IP address and reason.

Allowed admin actions:

- Approve a pending PC.
- Reject a request with a required reason.
- Extend or remove an expiry date.
- Change edition and maximum app version.
- Suspend or reactivate a licence.
- Revoke a licence with a required reason.
- Transfer to a replacement PC. This revokes the old licence and creates a linked
  pending request; history is retained.
- Reissue a damaged local licence only to the same device fingerprint.
- Download a support report containing masked, non-secret information.

Destructive or security-sensitive actions require a confirmation dialog and a
reason. A super administrator cannot delete licence or audit records.

## Cloud database

Use these tables:

- `desktop_activation_requests`: opaque request ID hash, encrypted request data,
  device HMAC, status, expiry, attempt counters and timestamps.
- `desktop_licenses`: UUID, customer/business links, device HMAC, status, edition,
  issue/expiry/version limits, licence-document hash, transfer links and timestamps.
- `desktop_license_events`: licence/request link, action, encrypted metadata,
  acting admin, IP address, user agent and immutable timestamp.

Names, emails, mobile numbers, company data and raw device details are encrypted
with AES-256-GCM. Searchable device identifiers use HMAC-SHA-256 with a separate
server key. Encryption, HMAC and licence-signing keys must be provided as production
environment secrets and must never be stored in the database or repository.

## Activation protocol

1. After local registration, the PC creates a local device fingerprint and sends an
   activation request to `billing.cloudkart24.com` over HTTPS.
2. The cloud stores encrypted details and returns a short-lived opaque request ID.
3. The local `/activation` page opens the cloud approval URL and polls the status
   endpoint with the request secret.
4. A super administrator reviews and approves the request.
5. The cloud creates a signed licence document. Only the cloud holds the private
   signing key.
6. The PC retrieves the licence once, verifies its signature, encrypts it with
   Windows DPAPI and writes it atomically with a recovery copy.
7. The compiled desktop host verifies the licence before starting PHP/MySQL and
   supplies a short-lived runtime token to the local API.

Request IDs expire after ten minutes. Polling is rate limited. Approval and licence
retrieval are one-time operations protected by independent random secrets. No user
password or signing key is sent to the PC.

## Enforcement and recovery

The current PowerShell/PHP launcher is not a sufficient security boundary because
a local administrator can edit scripts. Production enforcement belongs in a signed
compiled Windows host. PHP routes must also require the host-issued runtime token.

Missing, invalid, revoked or expired licences permit only activation, licence status,
backup, restore and sign-out. Business records remain readable for reports and data
export; protected mutations remain disabled. Corrupt local licence storage can be
reissued from the admin portal to the same device. A PC transfer always creates a
new licence and preserves the old audit history.

Cloud unavailability after successful activation must not stop offline billing.
Online revocation takes effect on the next permitted licence refresh, so the policy
must define a refresh/grace interval. A practical default is a 30-day signed lease
with a 7-day grace period; perpetual purchases receive automatically renewable
leases while entitled.

## Delivery sequence

1. Cloud tables, encryption service, signing service and immutable audit events.
2. Super-admin list, detail, filters, approval, revoke, renew and transfer actions.
3. Public request/status endpoints with rate limiting and one-time secrets.
4. Local activation page and automatic polling.
5. Compiled Windows host, DPAPI licence store and runtime-token enforcement.
6. Automated protocol, tamper, expiry, recovery, transfer and clean-PC tests.
7. Code-sign the host and installer, deploy cloud migrations, configure production
   secrets and run an end-to-end activation against `billing.cloudkart24.com`.

Do not enable production approval until the signing/encryption keys, HTTPS endpoint,
database backup policy and admin audit retention have been configured and tested.
