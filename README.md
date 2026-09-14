# HRIS

A standalone Human Resource Information System, extracted from the HR modules of
the [ianflecher/tgif](https://github.com/ianflecher/tgif) ERP project.

Laravel 12 · Livewire Volt · Flux UI · Fortify · MySQL

## What's inside

Three portals share one `web` guard and are separated by the user's `role`
and `username`.

| Portal | Entry | Screens |
| --- | --- | --- |
| HR back office | `/admin/login` | Dashboard, Applications, Attendance, Leave, Payroll |
| Employee self-service | `/employee/login` | Dashboard, Attendance (clock in/out), Payroll, Leave |
| Careers / applicant | `/applicant/login` | Register, application status, document uploads |

## Requirements

- PHP 8.2+
- Composer 2
- Node 20+
- **MySQL or MariaDB.** The HR screens use MySQL-specific SQL (double-quoted
  string literals inside raw `COUNT(CASE WHEN ...)` aggregates), so SQLite and
  Postgres will not work without rewriting those queries.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the database, then:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

`storage:link` is required — the careers portal writes application documents to
the `public` disk.

### Seeded accounts

All use the password `password`.

| Username | Role |
| --- | --- |
| `admin` | Super admin |
| `hr` | HR manager |
| `msantos`, `jdelacruz`, `areyes`, `pgarcia` | Employees |

## Tests

```bash
php artisan test
```

The suite renders every screen for both guests and signed-in users, and covers
the careers loop end to end (register → apply → upload → HR review → interview →
hire). Tests run against the **development database** named in `phpunit.xml`,
not an in-memory SQLite database, because of the MySQL-only SQL noted above.
They clean up the rows they create but do not wrap in transactions, so do not
point `phpunit.xml` at production data.

## Differences from upstream tgif

Things that changed while carving these modules out:

- **Excluded** the employee Tasks, Support and Warehouse screens — they depend
  on the projects, helpdesk and inventory modules, which are not part of HR.
  The dashboard's task widgets were replaced with leave-request equivalents.
- **Added migrations for `clock_logs` and `payroll_corrections`.** Both tables
  are queried by the employee screens but had no migration anywhere in tgif, so
  those features could only ever have failed. Columns were derived from the
  queries and inserts that use them.
- **Added the auth columns `remember_token`, `email_verified_at` and the
  Fortify two-factor columns** to `users`. Without `remember_token`, ticking
  "Remember me" threw a SQL error on every login form.
- **Trimmed `User::$fillable`** to the columns that actually exist, and fixed
  `initials()`, which read a non-existent `name` attribute.
- **Added `auth` middleware** to the HR, employee and applicant routes. They
  were unprotected upstream and threw a 500 for signed-out visitors instead of
  redirecting; guests are now sent to the matching portal's login screen.
- **Added `vite.config.js`**, which was missing from the upstream repository.
- The landing page is now a portal chooser rather than the e-commerce storefront.
