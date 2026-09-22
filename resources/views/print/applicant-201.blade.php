{{-- The 201 file as a printed document: three pages, A4.

     Deliberately plain. Nothing here is interactive, nothing is coloured in a
     way that costs toner to no purpose, and every section is sized so it does
     not straddle a page break. The screen version lives in the application
     dialog; this one exists to be filed. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>201 file &mdash; {{ \App\Support\Na::name($profile->surname, $profile->first_name) }}</title>

    {{-- Nothing external: a printed page should not wait on a CDN, and this
         is opened to be printed immediately. --}}
    <style>
        @page { size: A4; margin: 14mm 14mm 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #111;
            background: #f3f4f6;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            padding: 14mm;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        }

        /* The three pages. The last takes no break after it, or a printer
           adds a fourth, blank one. */
        .sheet + .sheet { page-break-before: always; }

        .doc-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 6pt;
            margin-bottom: 12pt;
        }

        .doc-head h1 { margin: 0; font-size: 15pt; letter-spacing: .01em; }
        .doc-head .who { margin-top: 2pt; font-size: 10pt; color: #444; }
        .doc-head .meta { text-align: right; font-size: 8.5pt; color: #555; }

        h2 {
            margin: 14pt 0 5pt;
            padding-bottom: 2pt;
            border-bottom: 1px solid #999;
            font-size: 10.5pt;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        h2:first-of-type { margin-top: 0; }

        /* Keep a heading with what follows it. */
        h2, tr, .entry { page-break-inside: avoid; }

        table { width: 100%; border-collapse: collapse; }

        .fields td {
            padding: 2.5pt 0;
            vertical-align: top;
        }

        .fields td.label {
            width: 34%;
            padding-right: 8pt;
            color: #555;
        }

        .fields td.value {
            border-bottom: 1px solid #ddd;
        }

        .grid { width: 100%; border-collapse: collapse; }
        .grid th, .grid td {
            padding: 3.5pt 5pt;
            border: 1px solid #bbb;
            text-align: left;
            vertical-align: top;
        }
        .grid th {
            background: #f0f0f0;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .entry { margin-bottom: 8pt; padding-bottom: 6pt; border-bottom: 1px solid #ddd; }
        .entry:last-child { border-bottom: 0; }
        .entry .top { display: flex; justify-content: space-between; gap: 10pt; }
        .entry strong { font-size: 11pt; }
        .muted { color: #555; font-size: 9.5pt; }

        .answers td { padding: 3pt 5pt; border-bottom: 1px solid #e3e3e3; }
        .answers td.a { width: 60pt; text-align: right; font-weight: 600; }
        .answers td.detail { padding-top: 0; color: #444; font-size: 9.5pt; }

        .signed {
            margin-top: 14pt;
            padding: 8pt 10pt;
            border: 1px solid #999;
        }

        .signed .line { display: flex; justify-content: space-between; }

        .note {
            margin-top: 4pt;
            font-size: 8.5pt;
            color: #555;
        }

        .foot {
            margin-top: 14pt;
            padding-top: 4pt;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        /* The toolbar is for the screen only; it must never reach paper. */
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: #111827;
        }

        .toolbar button, .toolbar a {
            padding: 7px 14px;
            border: 0;
            border-radius: 7px;
            font: inherit;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar button { background: #E31B23; color: #fff; }
        .toolbar a { background: rgba(255, 255, 255, .14); color: #fff; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

@php
    use App\Support\Na;

    // Blank and N/A mean the same thing on this form; on paper an empty ruled
    // line is clearer than either word repeated down the page.
    $v = fn ($value, $blank = '') => Na::show($value, $blank);

    $yn = function ($value) {
        if ($value === null || $value === '') {
            return 'Not answered';
        }

        return ((int) $value === 1) ? 'YES' : 'No';
    };

    $fullName = Na::name($profile->surname, $profile->first_name, $profile->middle_name, 'Unnamed');
    $printedOn = now()->format('j M Y, g:ia');
@endphp

<div class="toolbar">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('hr.applications') }}">Back to applications</a>
</div>

{{-- ------------------------------------------------------------- page one --}}
<div class="sheet">
    <div class="doc-head">
        <div>
            <h1>Applicant 201 File</h1>
            <div class="who">{{ $fullName }}</div>
        </div>
        <div class="meta">
            Application #{{ $application->application_id }}<br>
            {{ $application->position_applied }}<br>
            Applied {{ \Illuminate\Support\Carbon::parse($application->application_date)->format('j M Y') }}
        </div>
    </div>

    <h2>Personal details</h2>
    <table class="fields">
        <tr><td class="label">Surname, first name, middle name</td><td class="value">{{ $fullName }}</td></tr>
        <tr><td class="label">Date of birth</td><td class="value">{{ $profile->date_of_birth ? \Illuminate\Support\Carbon::parse($profile->date_of_birth)->format('j F Y') : '' }}</td></tr>
        <tr><td class="label">Birthplace</td><td class="value">{{ $v($profile->birthplace) }}</td></tr>
        <tr><td class="label">Civil status</td><td class="value">{{ $profile->civil_status ? ucfirst(str_replace('_', '-', $profile->civil_status)) : '' }}</td></tr>
        <tr><td class="label">Cellphone</td><td class="value">{{ $v($profile->cellphone) }}</td></tr>
        <tr><td class="label">Email</td><td class="value">{{ $v($profile->email_address) }}</td></tr>
        <tr><td class="label">Present address</td><td class="value">{{ Na::join([$profile->present_street, $profile->present_city, $profile->present_province], ', ') }}</td></tr>
        <tr><td class="label">Permanent address</td><td class="value">{{ Na::join([$profile->permanent_street, $profile->permanent_city, $profile->permanent_province], ', ') }}</td></tr>
        <tr><td class="label">Bank account</td><td class="value">{{ $v($profile->bank_account_number) }}</td></tr>
    </table>

    {{-- Only when there is a spouse named. The columns hold "N/A" for
         everybody else, which is an answer, not a person. --}}
    @php $spouse = Na::name($profile->spouse_surname, $profile->spouse_first_name, $profile->spouse_middle_name); @endphp
    @if ($spouse !== '')
        <h2>Spouse</h2>
        <table class="fields">
            <tr><td class="label">Name</td><td class="value">{{ $spouse }}</td></tr>
        </table>
    @endif

    <h2>Family</h2>
    <table class="fields">
        <tr><td class="label">Father / guardian</td><td class="value">{{ $v($profile->fathers_name) }}</td></tr>
        <tr><td class="label">Mother's maiden name</td><td class="value">{{ $v($profile->mothers_maiden_name) }}</td></tr>
        <tr>
            <td class="label">Siblings</td>
            <td class="value">
                @php $sibNames = Na::join($siblings->pluck('name')->all(), ', '); @endphp
                @if ($sibNames !== '')
                    {{ $sibNames }}
                @elseif ($profile->sibling_count !== null && (int) $profile->sibling_count === 0)
                    None
                @endif
            </td>
        </tr>
    </table>

    <h2>Government numbers</h2>
    <table class="fields">
        <tr><td class="label">SSS</td><td class="value">{{ $v($profile->sss_number) }}</td></tr>
        <tr><td class="label">Pag-IBIG (HDMF) MID</td><td class="value">{{ $v($profile->pagibig_number) }}</td></tr>
        <tr><td class="label">PhilHealth</td><td class="value">{{ $v($profile->philhealth_number) }}</td></tr>
        <tr><td class="label">TIN</td><td class="value">{{ $v($profile->tin) }}</td></tr>
    </table>

    <h2>In case of emergency, please contact</h2>
    <table class="fields">
        <tr><td class="label">Name</td><td class="value">{{ $v($profile->emergency_name) }}</td></tr>
        <tr><td class="label">Contact number</td><td class="value">{{ $v($profile->emergency_contact_no) }}</td></tr>
        <tr><td class="label">Relationship</td><td class="value">{{ $v($profile->emergency_relationship) }}</td></tr>
        <tr><td class="label">Address</td><td class="value">{{ $v($profile->emergency_address) }}</td></tr>
    </table>

    <div class="foot">
        <span>{{ $fullName }} &mdash; 201 file</span>
        <span>Page 1 of 3 &middot; printed {{ $printedOn }}</span>
    </div>
</div>

{{-- ------------------------------------------------------------- page two --}}
<div class="sheet">
    <div class="doc-head">
        <div>
            <h1>Education &amp; employment</h1>
            <div class="who">{{ $fullName }}</div>
        </div>
        <div class="meta">Application #{{ $application->application_id }}</div>
    </div>

    <h2>Educational background</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 20%">Level</th>
                <th style="width: 34%">School</th>
                <th style="width: 26%">Course / strand</th>
                <th style="width: 20%">Years attended</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($levels as $key => $label)
                @php $row = $education->get($key); @endphp
                @if ($row && trim((string) $row->school_name) !== '')
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $row->school_name }}</td>
                        <td>{{ $v($row->course) }}</td>
                        <td>{{ Na::join([$row->year_from, $row->year_to], ' - ') }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <h2>Employment record</h2>
    @forelse ($employment as $job)
        <div class="entry">
            <div class="top">
                <strong>{{ $v($job->company_name) }}</strong>
                <span class="muted">{{ Na::join([$job->date_from, $job->date_to], ' - ') }}</span>
            </div>
            <div>{{ $v($job->position) }}</div>
            @if (trim((string) $job->company_address) !== '')
                <div class="muted">{{ $job->company_address }}</div>
            @endif
            @if ($job->daily_salary)
                <div class="muted">Daily rate: {{ number_format((float) $job->daily_salary, 2) }}</div>
            @endif
            @if (trim((string) $job->reason_for_leaving) !== '')
                <div class="muted">Reason for leaving: {{ $job->reason_for_leaving }}</div>
            @endif
        </div>
    @empty
        <p class="muted">No previous employers listed.</p>
    @endforelse

    <h2>Character references</h2>
    @if ($references->count() > 0)
        <table class="grid">
            <thead>
                <tr><th style="width: 38%">Name</th><th style="width: 26%">Contact</th><th>Position / company</th></tr>
            </thead>
            <tbody>
                @foreach ($references as $ref)
                    <tr>
                        <td>{{ $v($ref->name) }}</td>
                        <td>{{ $v($ref->contact_no) }}</td>
                        <td>{{ $v($ref->position_company) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">None given.</p>
    @endif

    <div class="signed">
        <div>I hereby certify that the above information is true and correct to the best of my
            knowledge and belief.</div>
        <div class="line" style="margin-top: 10pt">
            <span><strong>{{ $v($profile->certified_name, $fullName) }}</strong><br>
                <span class="muted">Applicant</span></span>
            <span class="muted" style="text-align: right">
                @if ($profile->certified_at)
                    Certified {{ \Illuminate\Support\Carbon::parse($profile->certified_at)->format('j F Y, g:ia') }}
                @else
                    Not yet certified
                @endif
            </span>
        </div>
    </div>

    <div class="foot">
        <span>{{ $fullName }} &mdash; 201 file</span>
        <span>Page 2 of 3 &middot; printed {{ $printedOn }}</span>
    </div>
</div>

{{-- ----------------------------------------------------------- page three --}}
<div class="sheet">
    <div class="doc-head">
        <div>
            <h1>Applicant disclosures</h1>
            <div class="who">{{ $fullName }}</div>
        </div>
        <div class="meta">Application #{{ $application->application_id }}</div>
    </div>

    @if (! $disclosures)
        <p class="muted">This applicant has not answered the disclosures.</p>
    @else
        <p class="note">
            Confidential. Health, medication and legal answers below are sensitive personal
            information under the Data Privacy Act and are held for HR use only.
        </p>

        <h2>Health</h2>
        <table class="answers">
            <tr>
                <td>Pre-existing medical condition the company should know of</td>
                <td class="a">{{ $yn($disclosures->has_medical_condition) }}</td>
            </tr>
            @if ((int) $disclosures->has_medical_condition === 1 && trim((string) $disclosures->medical_condition_details) !== '')
                <tr><td class="detail" colspan="2">{{ $disclosures->medical_condition_details }}</td></tr>
            @endif
            <tr>
                <td>Currently taking maintenance medication</td>
                <td class="a">{{ $yn($disclosures->takes_maintenance_medication) }}</td>
            </tr>
            @if ((int) $disclosures->takes_maintenance_medication === 1 && trim((string) $disclosures->maintenance_medication_details) !== '')
                <tr><td class="detail" colspan="2">{{ $disclosures->maintenance_medication_details }}</td></tr>
            @endif
        </table>

        <h2>Relatives employed here</h2>
        <table class="answers">
            <tr>
                <td>Has a relative working at GKLASAM OPC or Imprint Caf&eacute;
                    <span class="muted">(to the 3rd degree)</span></td>
                <td class="a">{{ $yn($disclosures->has_relative_employed) }}</td>
            </tr>
            @if ($relatives->count() > 0)
                <tr>
                    <td class="detail" colspan="2">
                        {{ $relatives->map(fn ($r) => trim($r->name.' ('.$r->relationship.($r->department ? ', '.$r->department : '').')'))->implode('; ') }}
                    </td>
                </tr>
            @endif
        </table>

        <h2>Prior employment and legal</h2>
        <table class="answers">
            <tr>
                <td>Ever terminated or asked to resign</td>
                <td class="a">{{ $yn($disclosures->ever_terminated) }}</td>
            </tr>
            @if ((int) $disclosures->ever_terminated === 1 && trim((string) $disclosures->ever_terminated_details) !== '')
                <tr><td class="detail" colspan="2">{{ $disclosures->ever_terminated_details }}</td></tr>
            @endif
            <tr>
                <td>Ever convicted of a crime, or a case pending</td>
                <td class="a">{{ $yn($disclosures->ever_convicted) }}</td>
            </tr>
            @if ((int) $disclosures->ever_convicted === 1 && trim((string) $disclosures->ever_convicted_details) !== '')
                <tr><td class="detail" colspan="2">{{ $disclosures->ever_convicted_details }}</td></tr>
            @endif
            <tr>
                <td>Currently employed elsewhere</td>
                <td class="a">{{ $yn($disclosures->employed_elsewhere) }}</td>
            </tr>
            <tr>
                <td>Employment bond or contract still in effect</td>
                <td class="a">{{ $yn($disclosures->has_employment_bond) }}</td>
            </tr>
            @if ((int) $disclosures->has_employment_bond === 1 && trim((string) $disclosures->employment_bond_details) !== '')
                <tr><td class="detail" colspan="2">{{ $disclosures->employment_bond_details }}</td></tr>
            @endif
            <tr>
                <td>Was a union member in a previous company</td>
                <td class="a">{{ $yn($disclosures->was_union_member) }}</td>
            </tr>
            @if ((int) $disclosures->was_union_member === 1 && trim((string) $disclosures->union_position) !== '')
                <tr><td class="detail" colspan="2">Position held: {{ $disclosures->union_position }}</td></tr>
            @endif
        </table>

        <h2>Availability</h2>
        <table class="answers">
            <tr>
                <td>Can start immediately</td>
                <td class="a">{{ $yn($disclosures->can_start_immediately) }}</td>
            </tr>
            @if ((int) $disclosures->can_start_immediately === 0)
                <tr>
                    <td class="detail" colspan="2">
                        @if ($disclosures->days_to_render) {{ $disclosures->days_to_render }} days to render. @endif
                        @if ($disclosures->available_start_date)
                            Available from {{ \Illuminate\Support\Carbon::parse($disclosures->available_start_date)->format('j F Y') }}.
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        <h2>Government mandated IDs on file</h2>
        <table class="answers">
            <tr><td>SSS number</td><td class="a">{{ $yn($disclosures->sss_on_file) }}</td></tr>
            <tr><td>Pag-IBIG (HDMF) MID number</td><td class="a">{{ $yn($disclosures->pagibig_on_file) }}</td></tr>
            <tr><td>PhilHealth number</td><td class="a">{{ $yn($disclosures->philhealth_on_file) }}</td></tr>
            <tr><td>TIN</td><td class="a">{{ $yn($disclosures->tin_on_file) }}</td></tr>
        </table>

        <div class="signed">
            <div>I certify that the information disclosed above is true and correct to the best of
                my knowledge. I understand that any concealment or misrepresentation may be ground
                for disqualification from employment, or termination if discovered after hiring.</div>
            <div class="line" style="margin-top: 10pt">
                <span><strong>{{ $v($disclosures->declared_name, $fullName) }}</strong><br>
                    <span class="muted">Applicant</span></span>
                <span class="muted" style="text-align: right">
                    @if ($disclosures->declared_at)
                        Declared {{ \Illuminate\Support\Carbon::parse($disclosures->declared_at)->format('j F Y, g:ia') }}
                    @else
                        Not yet declared
                    @endif
                </span>
            </div>
        </div>
    @endif

    <div class="foot">
        <span>{{ $fullName }} &mdash; 201 file</span>
        <span>Page 3 of 3 &middot; printed {{ $printedOn }}</span>
    </div>
</div>

</body>
</html>
