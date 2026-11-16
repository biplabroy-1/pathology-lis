# PathoLab M1 — Foundation Implementation Plan (Scaffold → Auth → Onboarding → Super-admin)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A running multi-tenant SaaS skeleton where a stranger can sign up, verify email, onboard a diagnostic lab (org + branch + starter catalog), land on a trial dashboard, and a platform super-admin can see/suspend/impersonate tenants — deployable as a Docker app on the VPS.

**Architecture:** Next.js 15 App Router + TypeScript strict; PostgreSQL via Prisma; better-auth (organization + admin + email-password plugins, Next adapter); tenant = better-auth organization; a Prisma client extension injects `organizationId` into every business query; RBAC via per-tenant member roles; super-admin via global `platform_role` column; Razorpay wired in M7 (M1 ships `subscription` table + trial gate only).

**Tech Stack:** Next.js 15 (App Router, RSC), React 19, TypeScript strict, Tailwind v4, shadcn/ui, better-auth latest (^1.x, pinned at scaffold), Prisma, PostgreSQL 16 (docker-compose for dev), bun 1.4 as package manager/runner/test runner, Playwright for e2e smoke.

**Spec:** `docs/superpowers/specs/2026-11-16-patholab-saas-design.md` (read it first — this plan implements §3–§6/M1 only; M2+ get their own plans).

**New working directory:** `/Users/abcom/Desktop/patholab` (SCAFFOLDED IN TASK 1 — all file paths in this plan are relative to it unless absolute).

## Global Constraints

- Package manager, script runner, and test runner: **bun** (`bun install`, `bun run …`, `bun test`). Never npm/npx/yarn/pnpm; use `bunx …` for CLIs.
- Node floor: whatever bun 1.4 manages; Next.js 15 requires Node ≥ 18.18 — ignore, bun handles it.
- Create the app **only** via `bunx --bun create-next-app` (Task 1) — never hand-write framework scaffolding files.
- TypeScript `strict: true`; no `any` outside third-party shims; `eslint` default config from create-next-app must stay clean (`bun run lint`).
- better-auth: pin `^1.x` latest; heed its installed types over any API names shown in this plan (documented as v1.3-era).
- Auth: email+password only (no OAuth); email verification **required** in production; dev/test may set `AUTH_SKIP_VERIFICATION=true`.
- Roles (exact strings): `owner | pathologist | technician | frontdesk`; platform role exact string: `super_admin`.
- Envs validated by `lib/env.ts` (zod) at import time; `.env.example` is authoritative for names (spec §9) + `AUTH_SKIP_VERIFICATION`, `SUPER_ADMIN_EMAIL` (setup helper only).
- Database: docker-compose `postgres:16` on `127.0.0.1:5432`, db `patholab`, volume `pgdata`; tests use db `patholab_test`.
- Money: paise (₹2,999 = `299900`); never floats.
- Layout: light background, teal accent; no emojis in UI; lucide-react icons; shadcn/ui components.

## Review Focus (failure modes the spec implies — each has a test pinned to a task)

1. **Slug collision on concurrent signups** → Task 7 test: two orgs created in same second with same lab name → distinct slugs, distinct orgs.
2. **Abandoned onboarding / refresh re-seed** → Task 7 test: `completeOnboarding` called twice for one org → seed is idempotent (counts stay 11/16/14/8), branch not duplicated; wizard can resume, not restart.
3. **Cross-tenant id injection via forms** → Task 5 test: `getTenantContext()` ignores any `organizationId` from form/client; every action takes orgId from session only. Second test: crafted `patient` id from tenant A accessed by tenant B → not found.
4. **Super-admin trapped inside impersonated session** → Task 8 e2e/checklist: banner with "End super-admin session" button always visible; clicking ends impersonation (calls better-auth impersonate-done endpoint) and returns to `/platform`.
5. **Trial expiry lockouts owner out of signup data** → Task 6 test: `assertActiveAccess` returns explicit `{ok:false, reason:'past_due'}` (never throws raw), UI renders upgrade screen; trial gate must not block `/platform/*`, `/settings/subscription`, or logout.

---

### Task 1: Scaffold Next.js app on Desktop + copy spec/plan in

**Files:** Create: `/Users/abcom/Desktop/patholab/` (whole Next.js app); Modify: —; Copy: `pathology-lis/docs/superpowers/specs/2026-11-16-patholab-saas-design.md` → `patholab/docs/spec.md`; this plan → `patholab/docs/plans/m1.md`.

**Interfaces:** Produces the repo that every later task modifies. Working dir: `/Users/abcom/Desktop/patholab` afterwards.

- [ ] **Step 1: Scaffold**

```bash
cd /Users/abcom/Desktop
ls -d patholab 2>/dev/null && echo ABORT-EXISTS || bunx --bun create-next-app@latest patholab --yes --ts --tailwind --eslint --app --import-alias "@/*" --no-turbopack --use-bun
```

Expected: `patholab/` created, git repo initialized by the CLI.

- [ ] **Step 2: Bring in the spec + this plan (they travel with the code)**

```bash
cd /Users/abcom/Desktop/patholab
mkdir -p docs/plans docs/spec
cp /Users/abcom/Desktop/pathology-lis/docs/superpowers/specs/2026-11-16-patholab-saas-design.md docs/patholab-spec.md
cp /Users/abcom/Desktop/pathology-lis/docs/superpowers/plans/2026-11-16-patholab-m1-foundation.md docs/plans/m1.md
git mv docs/patholab-spec.md docs/spec/design.md && git mv docs/plans/m1.md docs/plans/m1.md 2>/dev/null; cd /Users/abcom/Desktop/patholab && git add docs && git commit -m "docs: import SaaS design spec and M1 plan"
```

- [ ] **Step 3: Verify dev server boots**

```bash
bun run dev >/tmp/patholab-dev.log 2>&1 &
sleep 6; curl -s -o /dev/null -w "%{http_code}\n" http://localhost:3000
kill %1
```

Expected: `200`.

- [ ] **Step 4: Commit** (create-next-app may auto-commit nothing; ensure clean tree)

```bash
git add -A && git commit -m "chore: scaffold Next.js 15 + Tailwind app (bun)" || echo "nothing to commit"
```

---

### Task 2: Dev database (docker-compose), env validation, /api/health

**Files:** Create: `docker-compose.yml`, `.env.example`, `.env.local` (gitignored — real secrets for dev only), `lib/env.ts`, `app/api/health/route.ts`, `lib/env.test.ts`. Modify: `.gitignore`, `README.md` (replace boilerplate).

**Interfaces:** Produces `lib/env.ts` exporting `const env: { DATABASE_URL:string; AUTH_SECRET:string; APP_URL:string; SMTP_URL?:string; STORAGE_DIR:string; RAZORPAY_*?:...;AUTH_SKIP_VERIFICATION?:boolean; SUPER_ADMIN_EMAIL?:string }`, used by every later task. Produces `GET /api/health` → `200 { ok:true, db:'up' }` or `503`.

- [ ] **Step 1: Write `docker-compose.yml`** (postgres:16-alpine, env `POSTGRES_USER=patholab POSTGRES_PASSWORD=dev POSTGRES_DB=patholab`, ports `127.0.0.1:5432:5432`, volume `pgdata`, healthcheck `pg_isready`).

- [ ] **Step 2: Write `.env.example`** — every name from spec §9 plus: `AUTH_SKIP_VERIFICATION=false`, `SUPER_ADMIN_EMAIL=` (one-line setup helper: if set at seed/setup, `bun run make-super-admin <email>` script from Task 8 grants it; not an env gate).

- [ ] **Step 3: Write the failing env test**

```ts
// lib/env.test.ts
import { describe, it, expect } from 'bun:test';
describe('env', () => {
  it('rejects a missing DATABASE_URL with a named error', async () => {
    const saved = process.env.DATABASE_URL; delete process.env.DATABASE_URL;
    await expect(import('./env')).rejects.toThrow(/DATABASE_URL/);
    process.env.DATABASE_URL = saved;
  });
});
```

Run: `bun test lib/env.test.ts` → FAIL (module not found).

- [ ] **Step 4: Implement `lib/env.ts`** — zod schema, `.parse`, export frozen `env`. Named errors via `path` in the issue message.

Run: `bun test lib/env.test.ts` → PASS.

- [ ] **Step 5: `/api/health` route** — `SELECT 1` via Prisma client singleton (`lib/db.ts`: creates/extends at import; do NOT enable tenant extension yet — Task 5). Return `Response.json({ok:true, db:'up'})` or 503 on error. No auth.

- [ ] **Step 6:** `cp .env.example .env.local` with dev values; `docker compose up -d db`; document `bun run dev` quickstart in README (install, db up, migrate/seed in Task 3, dev server).

- [ ] **Step 7: Verify** — `docker compose ps` healthy; `curl localhost:3000/api/health` → `{"ok":true,"db":"up"}`.

- [ ] **Step 8: Commit** `feat: dev Postgres, env validation, /api/health`

---

### Task 3: Prisma schema — tenancy spine + better-auth tables; client singleton; first migration

**Files:** Create: `prisma/schema.prisma`, `prisma.config.ts` (if CLI asks), `lib/db.ts`, `prisma/migrations/0001_init/migration.sql`. Modify: `package.json` (scripts `db:migrate`, `db:studio`).

- [ ] **Step 1:** `bun add -D prisma@latest && bun add @prisma/client@latest`; `bunx prisma init --datasource-provider postgresql`
- [ ] **Step 2:** Point `.env*` at `postgresql://patholab:dev@127.0.0.1:5432/patholab`. In `schema.prisma` add the **business spine only** for M1:

```prisma
model Branch { id String @id @default(uuid()) organizationId String code String name String address String? city String? phone String? email String? isCentral Boolean @default(false) createdAt DateTime @default(now()) @@unique([organizationId, code]) @@index([organizationId]) }
model Counter { id String @id @default(uuid()) organizationId String key String year Int value Int @default(0) @@unique([organizationId, key, year]) }
model Subscription { id String @id @default(uuid()) organizationId String @unique razorpaySubscriptionId String? @unique status String @default("trialing") quantity Int @default(1) trialEndsAt DateTime? currentEnd DateTime? updatedAt DateTime @updatedAt createdAt DateTime @default(now()) }
model AuditLog { id String @id @default(uuid()) organizationId String actorUserId String? actorLabel String action String entity String entityType String? detail Json? createdAt DateTime @default(now()) @@index([organizationId, createdAt]) }
model PlatformAuditLog { id String @id @default(uuid()) superAdminId String action String tenantOrgId String? detail Json? createdAt DateTime @default(now()) }
```

- [ ] **Step 3:** better-auth CLI generates its tables into the same schema: `bunx @better-auth/cli@latest generate --output prisma/schema.prisma` — expected models: `User` (**after generate, manually add `platformRole String?`** keeping field name `platformRole` mapped `platform_role`), `Session`, `Account`, `Verification`, `Organization` (keep CLI defaults; we use `name`, `slug`, `logo`, `metadata Json?`), `Member` (`role String`), `Invitation`.

- [ ] **Step 4:** `bunx prisma migrate dev --name m1_spine` — expected: migration applied, `prisma generate` OK.
- [ ] **Step 5:** `lib/db.ts` — singleton `PrismaClient`; export `prisma`.
- [ ] **Step 6:** Smoke — `bunx prisma db execute --stdin <<< '\\dt'` shows all tables including `organization`, `member`.
- [ ] **Step 7:** Commit `feat: prisma schema — tenancy spine + better-auth tables`

---

### Task 4: better-auth wiring + auth UI (signup / login / reset / verify / logout)

**Files:** Create: `lib/auth.ts`, `lib/auth-client.ts`, `app/api/auth/[...all]/route.ts`, `app/(auth)/signup/page.tsx`, `app/(auth)/login/page.tsx`, `app/(auth)/forgot/page.tsx`, `app/(auth)/reset/page.tsx`, `app/(auth)/verify/page.tsx`, `components/auth-form.tsx`, `components/ui/*` (shadcn), `lib/auth.test.ts`. Modify: `app/layout.tsx` (fonts, theme), `next.config.ts` (`output: 'standalone'` — leave Dockerfile to M7, but set the flag now; `poweredByHeader: false`), `app/page.tsx` → redirect `/login` when no session, `/` dashboard shell when session (dashboard ships Task 9; for now placeholder `redirect('/login')`).

**Interfaces:** Produces `auth` (server) from `lib/auth.ts`, `authClient` from `lib/auth-client.ts`, `getSession()` (server) helper in `lib/auth.ts` returning `{ user, session, activeOrganizationId? }` via `headers()`. All later tasks consume these.

- [ ] **Step 1:** `bun add better-auth zod nodemailer` (nodemailer for SMTP) ; `git submodule`-free.
- [ ] **Step 2:** Write failing test `lib/auth.test.ts`: importing `lib/auth` exposes `auth.handler` (function) and plugins list contains `organization`, `admin`. (Sanity, not behavior.)
- [ ] **Step 3:** Implement `lib/auth.ts`:

```ts
export const auth = betterAuth({
  appName: 'PathoLab',
  baseURL: env.APP_URL,
  secret: env.AUTH_SECRET,
  database: prismaAdapter(prisma, { provider: 'postgresql' }),
  emailAndPassword: { enabled: true, minPasswordLength: 10, requireEmailVerification: env.AUTH_SKIP_VERIFICATION !== true },
  emailVerification: { sendOnSignUp: true, autoSignInAfterVerification: true,
    sendVerificationEmail: async ({ user, url }) => sendMail({ to: user.email, subject: 'Verify your PathoLab account', text: `Confirm: ${url}` }) },
  plugins: [ organization({ allowUserToCreateOrganization: true, organizationLimit: 1 /* one lab per owner in v1 */ }),
             admin(), nextCookies() ],
  rateLimit: { enabled: true },
});
```

`lib/mail.ts`: `sendMail()` — uses `SMTP_URL` if present else `console.log('[mail]', to, subject, url)`.

- [ ] **Step 4:** `auth-client.ts`: `createAuthClient({ baseURL: env.APP_URL, plugins: [organizationClient(), adminClient()] })` + export `useSession`, `signIn`, `signUp`, `signOut`, org helpers (`createOrganization`, `inviteMember`, `acceptInvitation`, `setActive`).
- [ ] **Step 5:** Route handler `app/api/auth/[...all]/route.ts` → `export const { GET, POST } = auth.handler` (better-auth v1 pattern: `export const GET = auth.handler; export const POST = auth.handler;`).
- [ ] **Step 6:** `bunx shadcn@latest init -y -d` (New York, slate, CSS vars); add `bunx shadcn@latest add button input card table badge dialog dropdown-menu label form toast`. Email/password forms: signup (name, lab name) — org creation happens in Task 7 wizard, signup only creates the user; login; forgot (request reset); reset (set new password from token); verify ("check your inbox").
- [ ] **Step 7:** Rate limit login: better-auth default rule covers `/sign-in/*`; add stricter custom rule `{ path: '/sign-in/email', window: 600, max: 5 }`.

- [ ] **Step 8: Manual smoke** — `docker compose up -d db && bun run dev`; sign up with `chayan@example.com`; dev mail logs URL; open it; confirm redirected in. `curl -c jar -b jar localhost:3000/api/auth/get-session` shows user.

- [ ] **Step 9: Test** `bun test` green; **Step 10: Commit** `feat: better-auth org+admin+email-password, auth pages`

---

### Task 5: Tenant context + Prisma extension (the isolation wall) — TDD

**Files:** Create: `lib/tenant.ts`, `lib/db.ts` (extend from Task 3), `lib/tenant.test.ts`, `test/fixtures.ts` (`createTwoTenants()`: user A org A, user B org B, one `Branch` each).

**Interfaces:** Produces `tenant.enter()`/`getTenantContext()` (AsyncLocalStorage `{ organizationId, memberRole }`) and `withTenant(prisma, orgId)` → Prisma client whose `$extends(tenantExtension)` injects `organizationId` into reads/writes on the M1 business models (`branch`, `counter`, `subscription`, `auditLog`). Used by every page/action below.

- [ ] **Step 1: Write failing tests** (`bun test lib/tenant.test.ts`):

```ts
it('injects organizationId into branch findMany and create', async () => { const {a, b} = await createTwoTenants();
  const scopedA = withTenant(prisma, a.org.id);
  await scopedA.branch.create({ data: { code: 'X', name: 'A branch' } });
  expect(await scopedA.branch.findMany()).toHaveLength(1);
  expect(await withTenant(prisma, b.org.id).branch.findMany()).toHaveLength(0);
});
it('ignores any organizationId passed in data (session wins over client)', async () => { /* create with data.organizationId = attacker's B while scoped to A; row lands in A */ });
it('unscoped() without flag throws', () => expect(() => unscoped()).toThrow());
```

Expected: FAIL (module missing).

- [ ] **Step 2: Implement** — `lib/tenant.ts` AsyncLocalStorage; `lib/db.ts` `tenantExtension = Prisma.defineExtension(client => client.$extends({ query: { $allOperations({ args, query, model }) { if (!BUSINESS_MODELS.has(model)) return query(args); const { organizationId } = getTenantContext(); args.where = { ...args.where, organizationId }; if (args.data) args.data = stripAndSet(args.data, organizationId); return query(args); } } }))`. `stripAndSet`: drop incoming `organizationId`, set from context. Escape hatch `unscoped(prisma)` exported, sets `ALLOW_UNscoped=true` token in ctx else throws.
- [ ] **Step 3: Run tests → PASS** (uses `patholab_test`: `docker exec patholab-db psql -U patholab -c 'CREATE DATABASE patholab_test'` + migrate to test db: `DATABASE_URL=...test bunx prisma migrate deploy`).
- [ ] **Step 4: Commit** `feat: tenant context + prisma extension — the isolation wall`

---

### Task 6: Trial gate + RBAC helper + audit logger — TDD

**Files:** Create: `lib/access.ts` (`assertAccess(action?) → {ok:true, role} | {ok:false, reason:'trial_expired'|'suspended'|'past_due'}`), `lib/rbac.ts` (`roleRank`, `atLeast(role, min)`), `lib/audit.ts` (`audit(prismaOrg, {action, entity, entityId?, detail})` → writes `auditLog`; also `platformAudit` for super-admin), `lib/access.test.ts`.

- [ ] **Step 1: Write failing test** — create org + subscription (`trialing`, `trialEndsAt` tomorrow) → `assertAccess` ok; set `trialEndsAt` yesterday → `{ok:false, reason:'trial_expired'}`; status `suspended` → suspended; status `active` → ok; and **not** blocking `/onboarding`, `/platform`, sign-out (helper takes `pathname` arg, no enforcement inside).
- [ ] **Step 2:** Implement against `subscription` table only (no Razorpay yet).
- [ ] **Step 3: `lib/rbac.ts`** — `const rank = { frontdesk:1, technician:2, pathologist:3, owner:4 }`, `atLeast(...)`. Unit test the ladder (6 assertions).
- [ ] **Step 4: `lib/audit.ts`** — one transaction write; test persists row with actor label.
- [ ] **Step 5: Commit** `feat: trial gate + role ranks + audit logger`

---

### Task 7: Onboarding wizard (org creation, starter catalog seed, subscription row)

**Files:** Create: `app/onboarding/page.tsx` (client wizard, steps: Lab profile → Branch → Departments → Test catalog → Done), `app/actions/onboarding.ts` (`startOnboarding`, `completeOnboarding(payload)`), `lib/starter-catalog.ts` (data: 11 departments, 16 categories, **14 tests with params + age/sex ranges**, **8 packages** — transcribed from `pathology-lis/database/seeders/DatabaseSeeder.php`, do not invent), `lib/seed-tenant.ts` (`seedTenantCatalog(tenantPrisma, orgId)` idempotent transaction: first `if (organization already has test_categories > 0) return summary`), `test/onboarding.test.ts` (integration on test DB).

- [ ] **Step 1: Write failing integration test** — fresh org → `completeOnboarding(payload)` → expects `departments=11? or chosen count`, `testCategories=16`, `tests=14`, `packages=8`, member count 1 (owner), subscription `trialing` w/ `trialEndsAt = now+14d`, org metadata has gstin; **call completeOnboarding again → same counts (idempotent), branch code not duplicated**. Also: create two orgs with the same lab name in the same second → **distinct slugs** (suffix random) and both complete onboarding.
- [ ] **Step 2:** Implement. Flow at signup: signup page uses `authClient.signUp`; immediately after, call server action `createLabOrg({ labName })` → `auth.api.createOrganization? or direct prisma insert into organization + member` — **use the generated schema from Task 3 as ground truth**; wrap in transaction; slug = `slugify(labName)` + 4-char random suffix; store `metadata` (gstin, license, address, phone, email). Then wizard submits profile/branch/departments/tests → `completeOnboarding`.
- [ ] **Step 3: Run test → PASS.** Manual: fresh browser signup → verify (log URL) → wizard → dashboard placeholder shows "Trial: 14 days left".
- [ ] **Step 4:** Idempotency edge: wizard refresh mid-way → resume step from server state (wizard step stored in org metadata `onboarding.step`); abandoning signup is harmless (empty org visible to super-admin only).
- [ ] **Step 5: Commit** `feat: onboarding wizard + idempotent per-tenant starter catalog`

---

### Task 8: Super-admin `/platform` (list, detail, suspend, force-status, impersonate)

**Files:** Create: `app/platform/layout.tsx` (`requireSuperAdmin()` → 403 page otherwise), `app/platform/page.tsx` (tenant table: name, slug, plan status, branch count, member count, created), `app/platform/[orgId]/page.tsx` (detail + actions), `app/actions/platform.ts` (`suspendTenant`, `forceSubscriptionStatus(orgId, status, reason)`, `startImpersonation(userId)`, `endImpersonation()`), `components/impersonation-banner.tsx`, `scripts/make-super-admin.ts` (`bun run make-super-admin chayan@…` — sets `user.platformRole='super_admin'` — **the only path to the role**), `lib/super-admin.ts` (`requireSuperAdmin()`), `lib/platform.test.ts`.

- [ ] **Step 1: Write failing tests** — non-super_admin `platform.ts` action throws 403; `forceSubscriptionStatus(orgA, 'active', 'paid offline')` flips status and writes `platformAuditLog` with reason; `suspendTenant` sets organization `suspended=true` metadata + audit row; suspend is reversible.
- [ ] **Step 2:** Implement using `unscoped()` (Task 5 escape hatch) with `ALLOW_UNscoped`, every call wrapped in `try/finally` clearing the token. Impersonation: `impersonateUser` via `auth.api` admin plugin (verify the installed API — fallback: better-auth `adminClient.impersonateUser({ userId })` from a super-admin client component); banner shows in `app/(app)/layout.tsx` when session carries `impersonatedBy`; "End super-admin session" calls end-impersonation → returns to `/platform`.
- [ ] **Step 3:** `suspendTenant` effect = `assertAccess` (Task 6) returns `suspended` → app shell renders full-screen "This lab account is suspended — contact support" with sign-out available.
- [ ] **Step 4:** Verify as Chayan: signup with `SUPER_ADMIN` email → run `bun run make-super-admin` → `/platform` loads; another account gets 403; impersonate tenant owner → red banner; end → back at `/platform`.
- [ ] **Step 5: Commit** `feat: super-admin platform area with audited impersonation`

---

### Task 9: App shell + dashboard placeholder + members/invites (owner)

**Files:** Create: `app/(app)/layout.tsx` (nav: Dashboard | Patients↦/patients? — links render disabled badges "Soon" for M3+ modules; top bar: search stub, role badge, trial days-left pill, sign out; impersonation banner slot), `app/(app)/page.tsx` (dashboard stub: org name, trial countdown, quick actions disabled), `app/settings/members/page.tsx` (list members, invite by email + role select (owner-only), copy invitation link, remove member, "send password reset link" per member), `app/actions/members.ts` (owner-only guards via `atLeast(...,owner)`; wrap better-auth invitation API; on remove write `auditLog`), `app/settings/page.tsx` (lab profile edit — name/address/phone/gstin/license/logo **upload: pdf/png/jpeg ≤5 MB magic-byte checked into `STORAGE_DIR/<org>/letterhead/…`**), `test/members.test.ts`.

- [ ] **Step 1: Write failing test** — owner invites `frontdesk@example.com` → invitation row exists, slug org matches, audit row written; **technician tries invite → throws**; owner removes member → member's sessions deleted, audit written; non-owner hits members page → redirect.
- [ ] **Step 2:** Implement.
- [ ] **Step 3:** Dashboard: trial countdown from `subscription.trialEndsAt`; if past → `assertAccess` banner mode (mutations blocked); super-admin impersonation banner top.
- [ ] **Step 4: Commit** `feat: app shell, members & invitations, lab profile settings`

---

### Task 10: Build, Dockerfile, README deploy contract, milestone tag

**Files:** Create: `Dockerfile` (multi-stage: `oven/bun:1 AS deps` install w/ lockfile · `oven/bun:1 AS build` `bun run build` · `oven/bun:1-alpine AS run` non-root `bun server.js` standalone; healthcheck `GET /api/health`), `.dockerignore`, `.env.production.example`, `README.md` (replace: product, VPS deploy steps — same Coolify recipe as Laravel: Dockerfile build pack, port 3000, env, volume `/data`, Postgres container), `docs/plans/m1-done-checklist.md`. Modify: `package.json` (`build` with `bun run build`; `start` = `node .next/standalone/server.js` note).

- [ ] **Step 1:** `bun run build` — must pass with zero type errors; fix what surfaces.
- [ ] **Step 2:** `docker build -t patholab-web .` — must succeed; `docker run --rm -p 3100:3000 --env-file .env.local patholab-web` boots; `curl localhost:3100/api/health` → ok (Postgres from compose).
- [ ] **Step 3:** Write milestone checklist; tag `m1`. Commit `build: Dockerfile + deploy contract; tag m1`.

---

### Task 11: Playwright smoke e2e (signup → onboarding → dashboard)

**Files:** Create: `playwright.config.ts`, `e2e/smoke.spec.ts`. Modify: `package.json` (`test:e2e`), `.env.example` (add `AUTH_SKIP_VERIFICATION=true` guidance for e2e job).

- [ ] **Step 1:** `bunx playwright install --with-deps chromium` (first run only).
- [ ] **Step 2: Failing spec** — `signup → wizard 5 steps → dashboard shows "14 days" → sign out → /platform as super admin redirects 403`.
- [ ] **Step 3:** Passes headless (`bun run test:e2e`).
- [ ] **Step 4:** Commit `test: e2e smoke — signup → onboarding → dashboard gate`

---

**End of M1. M2+ plans are written at each milestone boundary.**
