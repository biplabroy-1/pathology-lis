# PathoLab — Multi-Tenant Diagnostic Lab SaaS (v1) Design Spec

- **Date**: 2026-11-16
- **Status**: Approved-in-chat by Chayan Mondal (pending written-spec review by user)
- **Target**: New repo (per decision: `biplabroy-1/patholab`), separate from the Laravel demo (`pathology-lis`), which stays deployed at `lis.wowdev.xyz`.
- **Deploy**: Self-hosted VPS (default: same Coolify box as the Laravel app), Docker + Postgres container, Caddy/Coolify TLS. Domain TBD at deploy time.

## 1. Product

Self-serve SaaS for diagnostic labs (India, NABL-oriented). A lab signs up, verifies email, configures itself in a 5-minute wizard, subscribes at **₹2,999 per branch per month** (Razorpay), and runs registration → order → sample → result → verification → report/QR → billing/payments, all inside its tenant. A platform `super_admin` (Chayan) operates `/platform`.

### Goals
- One shared Postgres, many labs, strictly isolated.
- A lab is fully usable end-to-end with zero phone calls (except payment problems).
- Replace the Laravel demo's role: it is a **demo**; this is the real product.

### Non-goals (v1)
QC (Levey-Jennings/Westgard), reagent/inventory, CRM campaigns/reminders, HR/attendance, phlebotomy route planning, WhatsApp/SMS, HL7/ASTM machine integration, corporate self-service portal, FHIR/third-party API, per-branch stock, multi-currency, multi-language UI (English only), report designer.

## 2. Stack

| Concern | Choice | Notes |
|---|---|---|
| Framework | Next.js 15 App Router, React 19, TypeScript strict | Server Components by default; Server Actions for mutations |
| DB / ORM | PostgreSQL 16 + Prisma | `@prisma/client`, `prisma migrate deploy` in entrypoint |
| Auth | **better-auth** — `organization` + `admin` + `email-password` plugins | Org = tenant; member = staff; admin role = super_admin (platform-level, impersonation built in) |
| UI | Tailwind v4 + shadcn/ui (Radix) | Light background, teal accent (lab heritage), dense tables |
| Reports | Server PDF via headless Chromium (in container) + `qrcode` data-URI | Print-friendly HTML page as source of truth |
| Payments | Razorpay Subscriptions, one plan: "Branch seat ₹2,999/mo" (`amount=299900` paise, `period=monthly`) | quantity = branch count |
| Email | SMTP via env (`SMTP_URL`); dev falls back to log transport | Transactional only: verify, invite, reset |
| Files | Local volume `STORAGE_DIR` (default `/data/uploads`) | Patient docs; path tokens never exposed raw |
| Hosting | Docker on VPS; Postgres in a `postgres:16` container; named volumes for pgdata + uploads | Same pattern as Laravel/Coolify deploy |

## 3. Tenancy & Authorization

### 3.1 Tenant = organization
- better-auth `organization` plugin creates: `organization`, `member`, `invitation`.
- `member.role` ∈ `owner | pathologist | technician | frontdesk` (custom roles, string).
- **Every business table has `organizationId uuid NOT NULL REFERENCES organization(id) ON DELETE CASCADE`.**
- Composite FK pattern `(organizationId, id)` on child tables where ownership chains matter (e.g. `samples` → `orders`, `invoice_items` → `invoices`) so cross-tenant refs are impossible at DB level.
- A Prisma client extension (`tenantExtension`) applies `where: { organizationId }` + `data: { organizationId }` automatically from a request-scoped tenant context (`AsyncLocalStorage`). No route writes raw unscoped queries; escape hatch (`unscoped()`) requires explicit flag and logs loudly.
- `super_admin` is a **global user column** `user.platform_role TEXT NULL = 'super_admin'`. Set manually in DB by Chayan; never from any UI/API. Authorization checks live in middleware `requireSuperAdmin()`.

### 3.2 RBAC matrix (per tenant)

| Capability | owner | pathologist | technician | frontdesk |
|---|---|---|---|---|
| Dashboard KPIs | ✓ | ✓ | ✓ | ✓ |
| Patients: register/search/edit | ✓ | ✓ | ✓ | ✓ |
| Patient merge / delete | ✓ | ✗ | ✗ | ✗ |
| Orders: create/reschedule | ✓ | ✓ | ✓ | ✓ |
| Samples: collect/reject/track | ✓ | ✓ | ✓ | ✓ |
| Results: enter/edit | ✓ | ✓ | ✓ | ✗ |
| Results: verify + e-sign | ✓ | ✓ | ✗ | ✗ |
| Report: view/print/download | ✓ | ✓ | print-only | print-only |
| Billing: invoices/payments | ✓ | ✗ (view) | ✗ | ✓ |
| Catalog (departments/tests/packages) CRUD | ✓ | ✓ | ✗ | ✗ |
| Branches CRUD (triggers billing addon) | ✓ | ✗ | ✗ | ✗ |
| Members: invite/remove/reset pw | ✓ | ✗ | ✗ | ✗ |
| Subscription/settings | ✓ (owner only) | ✗ | ✗ | ✗ |
| Audit log | ✓ | ✗ | ✗ | ✗ |

Middleware: `requireMember(minRole)` per route group; server components fetch role once via session.

### 3.3 Super-admin (`/platform`)
- Auth: global `platform_role='super_admin'` on `user`; checked by `requireSuperAdmin()` in the `/platform/*` route group.
- Pages: dashboard (MRR = active branches × 2999; tenants total / trialing / active / past_due / cancelled; 30-day signups), tenant list (name, slug, branches, members, status, last activity), tenant detail (members, subscription, counts, last audit events), actions:
  - Suspend / reactivate tenant (`tenant.status: suspended`); app returns read-only banner for that tenant.
  - Force `subscriptionStatus` override (with reason; written to platform audit log).
  - Trigger password reset email for a tenant owner (no password shown/sent in plaintext).
  - Impersonate tenant admin → `better-auth` admin `impersonateUser`; red banner "Platform super-admin — you are viewing as <name>" persists for the session; every impersonated mutation carries `impersonatedBy: superAdminId` in `audit_log`.
- Never: edit PHI payloads, run arbitrary SQL. Console (psql) remains escape hatch on the VPS.
- Impersonation exit: banner button "End session" restores super_admin session.

## 4. Data model (Prisma, abridged)

`organization` (ba), `member` (ba), `invitation` (ba), `user` (ba) — extended with `platform_role`.

```prisma
branch(id, organizationId, name, code UNIQUE(org,code), address, city, phone, email, license, accreditation, is_central, created_at)
department(id, organizationId, code, name, tat_hours, is_active)               // seed: 11 defaults
test_category(id, organizationId, name, department_id, sort)                    // seed: 16 defaults
test(id, organizationId, category_id, department_id, code, name, sample_type, container, qty, tat, method,
     unit, normal_low, normal_high, critical_low, critical_high,
     ranges JSONB /* [{sex:'M'|'F'|'any', ageMin, ageMax, low, high}] */, is_active)
test_parameter(id, organizationId, test_id, name, unit, normal_low, normal_high, sort)
test_package(id, organizationId, code, name, price, is_active); package_test(package_id, test_id)
counter(organizationId, key /* uhid|report|invoice */, year, value)            // per-tenant seq
patient(id, organizationId, uhid UNIQUE(org,uhid), name, sex, dob, age_y, phone, email, address,
        emergency_name, emergency_phone, allergies, clinical_notes, merged_into_id NULL,
        merge_reason, created_at, updated_at)
patient_alias(id, organizationId, alias_uhid UNIQUE(org,alias_uhid), patient_id) // merged UHIDs keep resolving
patient_document(id, organizationId, patient_id, title, type, blob_path, uploaded_by, created_at)
lab_order(id, organizationId, branch_id, patient_id, order_no SEQ, status /* pending|in_progress|done|cancelled */,
          priority /* routine|urgent|stat */, ordered_at, created_by)
lab_order_item(id, organizationId, order_id, test_id, price)
sample(id, organizationId, order_id?, invoice_id?, patient_id, collector_id, code UNIQUE(org,code),
       accession, barcode UNIQUE(org,barcode), type, container, qty_ml, status, rejection_reason,
       collected_at, received_at, location)
test_result(id, organizationId, order_item_id, parameter_id, value, unit, flag /* normal|high|low|critical */,
            entered_by, entered_at)
verification(id, organizationId, order_id, verified_by, verified_at, esign_hash)
report(id, organizationId, order_id, report_no SEQ UNIQUE(org,report_no), qr_token UNIQUE, issued_at, pdf_path)
invoice(id, organizationId, branch_id, patient_id, invoice_no SEQ, status /* draft|unpaid|partial|paid|overdue|void */,
        gst_percent, subtotal, discount, coupon_code, gst_amount, total, paid, balance, created_by, created_at)
invoice_item(id, organizationId, invoice_id, test_id?, description, qty, unit_price, total)
payment(id, organizationId, invoice_id, receipt_no SEQ, amount, method /* cash|upi|card|netbanking */,
        ref, paid_at, created_by)
coupon(id, organizationId, code UNIQUE, type /* percent|fixed */, value, min_amount, valid_until, is_active)
corporate(id, organizationId, name, gstin, address, credit_days, price_list JSONB)
member_password_reset? — no, better-auth handles tokens; nothing to model
audit_log(id, organizationId?, actor_user_id, actor_label, action, entity, entity_id, detail, created_at)
platform_audit_log(id, super_admin_id, action, tenant_org_id NULL, detail, created_at)
subscription(organizationId PK/FK, razorpay_subscription_id UNIQUE, razorpay_plan_id, status, current_end, quantity)
```

UHID: per-tenant seq via `counter(key='uhid', year)` → `UHID-YYYY-000001`. Same pattern `RPT`, `INV`, `REC`.

## 5. Auth & onboarding flows

1. `/signup` (email, password, name, lab name) → unverified email; SMTP verification mail (or log transport in dev).
2. Verify → session → onboarding wizard (`/onboarding`, middleware-enforced):
   - Org created (better-auth `organization.create`) at signup with `slug` = lab name slug + random suffix; `member(owner)`.
   - Step 1: lab profile (name, GSTIN, license/accreditation, letterhead address, phone, logo upload).
   - Step 2: first branch (name/code/address/phone).
   - Step 3: departments (11 preselected checkboxes, editable/duplicable).
   - Step 4: test catalog preset (14 tests incl. parameters + 8 packages from the Laravel starter seed), checkbox-deselect allowed; per-tenant seeds are inserted at submit.
   - Step 5: done → subscribe (trial 14 days, `subscription.status='trialing'`, `trial_ends_at = now+14d`); dashboard.
3. Trial: full access; day 10/12 reminder emails; at expiry → read-only (mutations 403 with upgrade banner); owner-only checkout prompt.
4. Razorpay Checkout (subscription) → webhook (`/api/webhooks/razorpay`, HMAC-SHA256 signature via `RAZORPAY_WEBHOOK_SECRET`):
   - `subscription.activated` → status=active; `subscription.halted|cancelled` → status=past_due/cancelled; `subscription.charged` → current_end=next.
   - Idempotent by event id stored in `webhook_events` table; unknown events ignored w/ 200.
5. Adding branch → tenant members cannot create branch when `quantity < count(branches)`; owner action "Add & charge ₹2,999" calls Razorpay **subscription addon** (or plan quantity update where supported) with prorated charge; webhook reconciliation updates `subscription.quantity`; remove-branch decrements at next cycle (label: "takes effect next renewal"), effective immediately only if tenant pays for it past-cycle — explicit confirm dialog states this.

## 6. Core modules (v1)

### M1 Platform & auth (milestone 1)
Scaffold; Dockerfile; prisma; better-auth org+admin plugins; signup/verify/login/reset; super_admin role + `/platform` shell (tenant list, detail, suspend, force-status, impersonate w/ banner+audit); onboarding wizard; `/api/health`.

### M2 Catalog (m2)
Department/branch/test/category/package CRUD (owner/pathologist), reorder params, toggle active, CSV import/export of tests; branch add triggers Razorpay addon flow (mocked until `RAZORPAY_*` envs set).

### M3 Patients & orders & samples (m3)
- Register/search patients (phone, UHID, name), edit, document upload (PDF/JPEG ≤5 MB, magic-byte check, stored under `STORAGE_DIR/<org>/…` random token).
- **Duplicate detection & merge (in v1)**: on register, block-warn on exact phone match or (name+DOB) match; "Find duplicates" page: exact phone groups, name+DOB fuzzy (case/whitespace-insensitive Levenshtein ≤ 2). Merge flow: pick survivor → preview affected rows → require typed confirmation `MERGE` → transaction: re-point FK rows (orders/samples/results/reports/invoices/payments/documents/aliases) to survivor; loser `merged_into_id = survivor`, `is_active=false`; winner gets `patient_alias` entry from loser's UHID; audit_log rows (survivor, loser, counts, reason); UHID alias resolution on all lookups (`GET /patients/:uhid` resolves alias). Irreversible; UI states so.
- Orders: create from package(s)/test(s) per branch; stat/urgent; order items snapshot price; printable label (100×25 mm, barcode CODE128 of `barcode`, patient name/UHID tail, test code) via browser print.
- Samples: collect (timestamp, collector), reject w/ reason, print/reprint labels, received-at, location field.

### M4 Results & verification (m4)
- Entry grid per order item: one row per parameter; single `value` input per unit; auto-flag vs `test.ranges` selection (sex/age) else default pair; outliers beyond critical → red banner + requires override checkbox + audit.
- Verification: pathologist reviews order, signs (= `verification` row: hash of order_id+result payload+timestamp+user, stored); e-sign prints name + "Electronically verified by Dr. X".

### M5 Report & QR (m5)
- Server-rendered print HTML → headless Chromium → PDF buffer → `STORAGE_DIR/<org>/reports/<report_no>.pdf`; PDF served via authenticated route (session + tenant check), never as public static file.
- Layout: tenant letterhead from onboarding profile (logo, name, address, license, accreditation, footer disclaimer "For clinical correlation…"), patient block (name/age/sex/UHID/referred by/collected/verified), parameter table w/ flags (H/L bold), pathologist e-sign block, QR (120×120) → `https://<domain>/verify/<qr_token>` → public verify page: report number, patient name masked (`Ch███ M.`), verified by, issued date, validity status. **No PHI values on verify page.**

### M6 Billing (m6)
- Invoice from order (auto) or manual; GST % per line/total (rate configured in settings; default 0 for lab tests as exempt — confirm in prod); coupon validator (percent|fixed, min amount, expiry); partial payments; multi-mode (cash/UPI/card/netbanking); balance tracking; corporate invoices (credit_days, mark paid via bulk action).
- GST invoice PDF shares report pipeline (plain invoice layout); receipt (payment) print view.
- Sequences: invoice/receipt via `counter`.
- **Not in v1**: subscription GST e-invoices (Razorpay handles plan invoicing), GSTR reports (export CSV covered by M2-style export later).

### M7 Razorpay gate & staging (m7)
- Feature flag read-only mode on non-active sub; reminder emails day 10/12/14 (SMTP; skipped in dev); webhook events table + idempotency; Super-admin override path; VPS staging on Coolify (env from `.env.example`); `docker compose up -d db web` ; smoke: signup → verify → onboard → order → result → verify → PDF → billing → webhook replay → read-only flip; pg_dump cron (daily, keep 14) documented in README.

## 7. Security

- Passwords: better-auth argon2/bcrypt (default), strength ≥ 10 chars w/ mixed case or fallback passphrase; sessions httpOnly SameSite=Lax, 8h idle + 30d absolute; CSRF via SameSite + Next origin checks on actions; secure headers (`next.config.ts`): HSTS, X-Frame-Options DENY, nosniff.
- Rate limiting: better-auth built-in on `/api/auth/*` (20 req/10min per IP), login lockout after 5 fails (15 min).
- Upload: magic-byte PDF/JPEG/PNG only; 5 MB; random token names; tenant-scoped dir.
- PHI at rest: uploads + reports encrypted at rest via volume encryption (VPS disk) — documented ops step; DB dump via cron encrypted with gpg passphrase before leaving host.
- Audit: every mutation → `audit_log` (actor, action, entity, before/after diff size-capped); super-admin actions → `platform_audit_log`.
- Secrets: `.env.example` documents all; `APP_KEY`/`AUTH_SECRET` rotation procedure in README; razorpay secrets never shipped to client.
- Row-level tenant enforcement is single-point (`tenantExtension`) + DB FKs; quarterly fuzz test: attempt cross-tenant read via crafted ids → must 404/403. (Vitest + seeded 2 tenants.)

## 8. Testing

- `vitest` unit: `counter` sequencing, UHID format, range flag engine, dedupe matcher, tenant extension injection (2-tenant fixture), merge re-pointing transaction, webhook signature verify + idempotency.
- `playwright` e2e (chromium, tmp DB): signup→verify→onboard→catalog→patient→order→collect→enter→verify→PDF→billing→pay→read-only-after-cancel webhook.
- CI on push: install, migrate, unit + 5-min smoke only; full e2e nightly.
- ponytail note: no coverage gate; tests exist to catch the tenant/merge/payment regressions that actually hurt.

## 9. Env (authoritative `.env.example`)

```
DATABASE_URL=postgres://patholab:secret@localhost:5432/patholab
AUTH_SECRET=base64-32B            # better-auth
APP_URL=https://app.example.com   # verify/invite links, QR target
SMTP_URL=smtp://user:pass@host:587?tls=on   # or log transport in dev
STORAGE_DIR=/data/uploads
RAZORPAY_KEY_ID=rzp_…
RAZORPAY_KEY_SECRET=…
RAZORPAY_WEBHOOK_SECRET=…
RAZORPAY_PLAN_ID=plan_…            # one flat branch-seat plan ₹2,999
RAZORPAY_ADDON_ID=addon_…          # prorated seat addon (optional; falls back to plan quantity update)
PGSSL=disable                      # VPS-internal; enable for managed DB
```

## 10. Assumptions & decisions

- Working name **PathoLab**; repo `biplabroy-1/patholab`; license TBD (proprietary, all rights reserved).
- Same Coolify VPS as the Laravel demo (assumption — override now if wrong).
- Domain TBD; `lis.wowdev.xyz` keeps serving the Laravel demo unchanged.
- `TEST` seed = existing 14 tests / 8 packages / 16 categories (patholab starter catalog), inserted per-tenant at onboarding.
- ₹2,999 **per branch per month**, incl. GST handling by Razorpay invoice line.
- One Razorpay account (Chayan's) collects for all tenants; no per-tenant Razorpay keys in v1.
- Email verification required before onboarding completes; invited members are `frontdesk`-default role until owner changes.
- Postgres on same VPS (container), not managed; backups to `/data/backups` (gpg-encrypted, 14-day retention).

## 11. Milestones & definition of done

| # | Milestone | DoD |
|---|---|---|
| M1 | Scaffold, auth, org, super-admin, onboarding, health | Signup→wizard→trial dashboard; impersonation banner works; migration clean on fresh PG |
| M2 | Catalog CRUD | Owner can add/edit/deactivate branch+department+test+package; reorder; CSV roundtrip |
| M3 | Patients + orders + samples (+merge) | Dedupe warning blocks; merge re-points + aliases; label prints; 2-tenant cross-read test green |
| M4 | Results + verification | Range flags correct incl. age/sex; critical override logged; e-sign hash verifiable |
| M5 | Report PDF + QR | Chromium PDF byte-stream 200; QR resolves; verify page masks name; PDF auth-gated |
| M6 | Billing | Partial pay; coupon; GST invoice PDF; corporate invoices; ₹seq counters unique |
| M7 | Razorpay + staging | Webhook replay idempotent; add-branch addon charges; expiry → read-only; Coolify staging smoke green |

Effort estimate (AI-assisted, solo): M1 1d, M2 1d, M3 2–3d, M4 2d, M5 1–2d, M6 2d, M7 1–2d ⇒ **~2 weeks** first staging cut. QC/CRM/inventory = v2 backlog.
