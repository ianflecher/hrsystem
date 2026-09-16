# HRIS

A standalone Human Resource Information System, extracted from the HR modules of
the [ianflecher/tgif](https://github.com/ianflecher/tgif) ERP project.

Laravel 12 · Livewire Volt · Flux UI · Fortify · MySQL

## What's inside

Three portals share one `web` guard and are separated by the user's `role`
and `username`.

| Portal | Entry | Screens |
| --- | --- | --- |
| HR back office | `/admin/login` | Dashboard, Employees, Attendance, Payroll, Openings, Applications, Leave |
| Employee self-service | `/employee/login` | Dashboard, Attendance (clock in/out), Payroll, Leave |
| Careers / applicant | `/applicant/login` | Register, application status, document uploads |

## Requirements

- PHP 8.2+
- Composer 2
- Node 20+
- **MySQL or MariaDB.** The HR screens use MySQL-specific SQL (double-quoted
  string literals inside raw `COUNT(CASE WHEN ...)` aggregates), so SQLite and
  Postgres will not work without rewriting those queries.

## Starting it on Windows

Double-click **`start.bat`**. On a fresh download it installs the dependencies,
creates `.env`, starts MySQL, creates and migrates the database, and seeds the two
starting accounts; on every run after that each of those steps is skipped, and it
never touches data that is already there. It then serves on port 8003 - beside
Imprint Production on 8000 rather than fighting it for the port - and opens the
sign-in page.

It prints the LAN address too, so other machines in the office can reach it.

Keep the two minimised windows ("Imprint MySQL", "Imprint HRIS") open; closing
them stops the system.

## Setup by hand

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

### Accounts

Seeding creates two sign-in accounts and nothing else - no sample departments,
employees or openings. Invented records are hard to tell apart from real ones
once they are in the database, and they turn up in headcounts and reports as if
they meant something. Everything else is entered through the app.

| Sign in with | Role |
| --- | --- |
| `admin` / `admin@imprintcustoms.ph` | Super admin |
| `hr` / `hr@imprintcustoms.ph` | HR supervisor |

Both start with the password `imprint123`, matching Imprint Production.
**Change them after the first sign-in.**

## My account

Every signed-in person has `/account`, reached from their name in the sidebar or
header. They can set a profile picture, change their name and email, and change
their password - and nothing else. Job title, department, salary and shift are
HR's to set, so they appear there as read-only context rather than as fields.

The page wears whichever chrome the person already knows: the HR sidebar for the
back office, the staff header for everyone else.

## Employee accounts

Staff accounts are created by HR at `/hr/employees`, not by the staff
themselves. Adding an employee creates their sign-in and their employee record
together, and shows a generated first password **once** - only a hash is
stored, so a missed password has to be reissued rather than looked up.

Because somebody other than the account holder knows that password, the account
is held at a change-password screen until it is replaced. The flag driving this
(`users.must_change_password`) defaults to false, so accounts that already
existed are unaffected.

## Biometric attendance

Run `php artisan migrate` to add the unique, optional scanner ID field. Set each
employee's Scanner ID under Employees to match their device enrolment number.
On Attendance, upload a CSV with `User ID,Date/Time` headers or a tab-separated
device `.dat` export (ID, timestamp, or ID, date, time).

For network pulls, enable PHP's sockets extension and set `ZKTECO_HOST` and
`ZKTECO_PORT` (default 4370) in `.env`. The server must reach the scanner's office
network. Clear cached configuration after changing these settings.

The same import is available from the command line:

```bash
php artisan attendance:sync
php artisan attendance:sync --file=attendance.csv
```

The earliest and latest scans on each calendar day become arrival and departure.
A single scan leaves departure empty. Incremental imports preserve earlier scans;
HR corrections are kept unless “Replace days entered by hand” or `--overwrite`
is selected. Unknown IDs are reported for linking and re-importing. Overnight
shifts are not grouped across midnight. Network pulls still need verification
against the actual scanner; no automatic schedule is enabled.

## Running payroll

Staff are paid twice a month: the 1st to the 15th, and the 16th to the end of
the month. Each payslip is half the monthly salary. SSS, PhilHealth and Pag-IBIG
are monthly obligations taken **whole on the second cutoff**, so the two
payslips are deliberately different sizes.

`/hr/payroll` generates a cutoff for everyone at once. It is three deliberate
steps, because this is the one part of the system that moves money:

1. **Generate** - creates a payslip for every active employee with a salary
   who does not already have one for the period. Status `calculated`.
2. **Approve** - moves the period's calculated payslips to `approved`.
3. **Mark paid** - records that the money has gone out.

Each button says how many rows it will touch, and the run is one transaction,
so a failure leaves no half-finished period. Running it twice does not pay
anyone twice.

### Lateness

Measured from each employee's own `shift_start`, set on the Employees screen.
Leave it blank and that person is never marked late.

| Late by | Deducted |
| --- | --- |
| Up to 5 minutes | nothing |
| 6 to 14 minutes | one hour of the daily rate |
| 15 minutes or more | half the daily rate |

The daily rate is the monthly salary over 22 working days, and the hourly rate
is that over 8. Time not worked is taken off before tax, because it was never
earned.

> **The deductions are simplified.** SSS brackets are coarse, PhilHealth has no
> floor or ceiling applied, and Pag-IBIG is the flat maximum - all carried over
> from the original code. Tax is charged on income *after* the statutory
> contributions, which is correct, but the brackets themselves need checking
> against the current SSS, PhilHealth and BIR tables before anyone is paid from
> them.

## Test data

```bash
php artisan demo:data --employees=50 --applicants=50
```

Fills every part of the system at a realistic size, so each screen can be tried
against something: departments and job openings, employees with shifts and rest
days and biometric IDs, 45 days of attendance with the usual lateness, undertime
and absences, two holidays a quarter of the shop worked, leave at every status,
overtime, loans part-way through repayment, documents including some expiring,
half-finished onboarding checklists, performance reviews at each stage, two
finished payroll cutoffs (one paid, one approved), payslip disputes waiting for
an answer, and applicants at every stage of hiring.

Deliberately a command rather than a seeder: invented records must never appear
because somebody ran the normal setup. Running it twice adds nothing.

Everything is marked. Accounts are `demo-NNNN` and `appl-NNNN` at
`@example.test`, a domain RFC 6761 reserves so it can never receive mail;
departments, openings and holidays carry a `(demo)` suffix. Demo employees and
applicants sign in with the password `demo-password`.

```bash
php artisan demo:data --purge
```

finds them by exactly those markers and removes them along with everything
attached. **Run it before going live.** Leave entitlements are the one thing it
leaves behind - those are company policy rather than test data, and it only
writes them when the table is empty.

## Job openings

The careers page and the application form both read the `job_positions` table,
managed in the HR back office at `/hr/positions`. Posting, editing or closing a
role there changes what applicants see straight away - no code edit, and the
two lists cannot drift apart.

## Statutory contributions

SSS, PhilHealth and Pag-IBIG live in `config/statutory.php`, not in the payroll
code, so they can be corrected without a developer:

```php
'philhealth' => ['premium_rate' => 0.05, 'employee_share' => 0.5, ...],
```

`timing` decides when the monthly contributions come off: `split` halves each one
across the two cutoffs so both payslips are the same size, `second_cutoff` takes
the whole month on the 16th-to-end payslip. That is a company decision about
timing, not about amount.

**Confirm the rates against the current circulars before anyone is paid.** They
change by circular, usually in January, and neither the config nor the code is
the authority on them. The figures that shipped before this file existed were
wrong: PhilHealth was fixed at 4% when the premium is 5%, SSS used round
brackets nobody had checked, and Pag-IBIG was a flat 100 with no rate behind it.

The withholding tax brackets are still in `App\Support\PayrollCalculator` and
need the same check.

## Work immersion

Somebody on work immersion is not a regular employee, so nothing is withheld from
their pay: no SSS, PhilHealth, Pag-IBIG or tax. Set **Work immersion until** on
them under Employees and payroll pays them in full until that date.

It is a date rather than a switch so it ends by itself: the first cutoff that
starts after it is a normal payslip, with no one having to remember to change
anything. The payslip says which it was.

Time not worked still comes off. Lateness and absence are pay that was never
earned rather than something withheld from pay, so immersion does not make them
free.

## Backups

Payroll history cannot be reconstructed from anything else, so there is a dump command:

```
php artisan db:backup
```

It writes a `.sql` file to `storage/app/backups` and keeps the last 14 (`--keep=`).
`mysqldump` is usually not on PATH on Windows, so the command looks in the usual
XAMPP and MySQL locations by itself. If yours is somewhere else, name it in `.env`:

```
DB_DUMP_BINARY="D:/some/other/path/mysqldump.exe"
```

A nightly run at 01:30 is already scheduled, but the scheduler has to be running for
that to mean anything: on Windows, a Task Scheduler entry running
`php artisan schedule:run` every minute; on Linux, the usual cron line.

**These dumps are not off-site.** A file beside the database survives a mistaken
`DROP` or a bad migration. It does not survive the disk dying or the office
flooding. Copy them somewhere else as well.

## Who changed what

Edits to attendance, payroll approvals and answers to correction requests are
written to `audit_logs` with who made them. The trail is on the Attendance screen
under "Change history". Attendance decides pay, so an edit there is a change to
somebody's money and should be answerable later.

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
