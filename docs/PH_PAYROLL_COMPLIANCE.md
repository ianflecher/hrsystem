# Philippine Payroll Compliance Notes

This HRIS contains a configurable Philippine payroll layer. It is designed so government rules are versioned and a payroll record stores the exact rule snapshot used for its calculation.

## Included

- SSS employee/employer SS shares and employer ECP
- PhilHealth employee/employer premium split
- Pag-IBIG employee/employer contributions
- BIR compensation withholding table support
- Minimum Wage Earner flag
- 13th-month computation based on basic salary earned
- Night Shift Differential (10 PM–6 AM)
- Philippine holiday classifications
- Philippine overtime suggestion engine
- Rule-version snapshots
- Employee government identifiers with masking support
- Final-pay calculation foundation
- COE service

## Government references

The configured baseline was checked against official agency publications available during development, including:

- SSS contribution schedule: https://www.sss.gov.ph/pay-contribution/
- PhilHealth CY2025 premium advisory: https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf
- Pag-IBIG HDMF Circular No. 274: https://www.pagibigfund.gov.ph/document/pdf/circulars/provident/HDMF%20Circular%20No.%20274%20-%20Revised%20Guidelines%20on%20Pag-IBIG%20Fund%20Membership.pdf
- BIR withholding tax calculator: https://web-services.bir.gov.ph/tax_calculator/wt_calculator.html
- BIR RR No. 11-2018 / Annex E reference: https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf

Government issuances can change. Before live payroll, HR/accounting should verify the active rule version and company-specific treatment of allowances, MWEs, de minimis benefits, taxable supplementary compensation, leave conversions, and separation/final-pay entitlements.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan test
```

## Production checklist

1. Configure the database and mail settings.
2. Confirm the Philippine rule version against current SSS, PhilHealth, Pag-IBIG, BIR and DOLE issuances.
3. Configure employee government numbers securely.
4. Configure employee minimum-wage status and applicable work region/wage information.
5. Review holiday and rest-day calendars for the year.
6. Run payroll in a test environment and reconcile against the company accountant's sample payslips.
7. Only then activate live payroll.
