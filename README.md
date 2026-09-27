# MindBridge — Virtual Law Firm (VLF)

A Laravel 13 application with two parts:

- **MindBridge website** — the public site at `/`.
- **VLF app** — a law-firm workspace at `/app` (sign-in required): matters and intake, clients, court diary, deadlines, tasks, time recording, invoicing, document review and uploads, messages and notifications.

## Setup

Requirements: PHP 8.3+, Composer, MySQL.

```bash
composer install
cp .env.example .env        # set DB_*, APP_URL and MAIL_* (see below)
php artisan key:generate
php artisan migrate
php artisan db:seed --class=VlfEmptySeeder     # empty firm, settings only
php artisan vlf:user you@yourfirm.com "Your Name" partner
php artisan serve
```

`vlf:user` emails a set-your-password link. Add `--password="…"` to set one directly (at least 10 characters).
Everyone else is added from inside the app: staff under **Firm Admin → People & Workload**, client contacts with **Invite to portal** on the **Clients** screen. Each receives a set-your-password email.

### Mail

Invites, password resets and notifications are sent by email. With `MAIL_MAILER=log` they are written to `storage/logs/laravel.log` instead of being sent. For real delivery set the SMTP settings in `.env`, for example:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=noreply@yourfirm.com
```

### Demo data

`php artisan db:seed --class=VlfSeeder` loads a sample firm (Katende, Ssempebwa & Co.) with demo accounts, all with the password `password`:
`margaret@ksc-advocates.example` (partner), `peter@ksc-advocates.example` (associate), `tendo@ksc-advocates.example` (junior), `grace@ksc-advocates.example` (administrator), `james.opolot@equitybank.example` (client).

**Both seeders delete all VLF data and all sign-in accounts.** Don't run them on a live system.

## Roles

| Role | Opens | Can |
|---|---|---|
| Partner | Advocate + Firm Admin | Everything, including Class A acts: approving invoices, filing documents |
| Associate | Advocate | Matters, intake, tasks, time, drafting and reviewing documents |
| Junior / Clerk | Advocate | Same screens; work is reviewed before it is approved |
| Administrator | Firm Admin | Staff, settings, billing overview |
| Client | Client portal | Only their own organisation's matters, issued invoices, client-approved documents and their conversation with the firm |

## Authority classes

- **Class A** — partner authority: filing a document, approving an invoice for issue.
- **Class B** — associate authority: drafting, reviewing, internal coordination, client communication.
- **Class C** — junior work: research and first drafts, always reviewed.

These rules are enforced on the server. Who is acting always comes from the signed-in account, never from the request, and the browser cannot approve or file anything by editing a document.
Document review: Draft → Under review (with the matter's advocate, or a partner) → Approved → Filed (partner). Nobody can review their own document.
Invoices: Draft (from unbilled time) → Approved (partner) → Issued → Paid. Issued invoices can't be edited.

## Tests

The tests use a separate MySQL database:

```bash
mysql -u root -e "CREATE DATABASE mindbridge_test"
php artisan test
```

`tests/Feature/VlfAccessTest.php` covers sign-in, per-person data, the Class A rules and notifications.

## Layout

- `resources/vlf/app.html` — the app page (served by `VlfAppController`, never directly from `public/`).
- `public/js/vlf-api.js`, `public/js/vlf-ops.js` — load and save through the API.
- `routes/web.php` — sign-in routes and the `/api/vlf/*` API (session + CSRF protected).
- `app/Http/Controllers/Vlf*`, `app/Policies`, `app/Support/Vlf*` — the rules.
