{{-- The 201 file, as HR reads it.

     Everything here was written by the applicant about themselves on their
     own details form. The disclosures at the end hold sensitive personal
     information under the Data Privacy Act - health, medication and criminal
     history - so this is the only screen that shows them, and only to HR.

     Read-only throughout: HR reviews it, the applicant owns it. --}}

@php
    // Blank and "N/A" both mean the same thing here, and neither should print
    // as an empty gap that reads like a rendering fault.
    $val = function ($value, string $fallback = 'Not given') {
        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
    };

    $yesNo = function ($value) {
        if ($value === null || $value === '') {
            return ['Not answered', 'text-gray-400'];
        }

        return ((int) $value === 1) ? ['Yes', 'text-gray-900 font-medium'] : ['No', 'text-gray-600'];
    };
@endphp

<div class="md:col-span-2 border-t pt-4 mt-2">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-medium text-gray-700">Application details (201 file)</h4>

        @if ($profile && $profile->certified_at)
            <span class="text-xs text-green-700">
                <i class="fas fa-check-circle mr-1"></i>Certified
                {{ \Illuminate\Support\Carbon::parse($profile->certified_at)->format('j M Y') }}
            </span>
        @endif
    </div>

    @if (! $profile)
        {{-- Not a fault: they have registered but not filled it in yet, and
             saying which of the two it is saves HR chasing the wrong thing. --}}
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center">
            <i class="fas fa-file-circle-question text-2xl text-gray-300"></i>
            <p class="mt-2 text-sm text-gray-600">This applicant has not filled in their details yet.</p>
            <p class="text-xs text-gray-500">They are asked for them under &ldquo;My details&rdquo; in their portal.</p>
        </div>
    @else
        <div class="space-y-4 text-sm">

            {{-- ------------------------------------------------------- person --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <h5 class="font-medium text-gray-900 mb-3">Personal</h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3">
                    <div>
                        <span class="text-xs text-gray-500">Name</span>
                        <p>{{ $val(trim($profile->surname.', '.$profile->first_name.' '.$profile->middle_name)) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Date of birth</span>
                        <p>{{ $profile->date_of_birth ? \Illuminate\Support\Carbon::parse($profile->date_of_birth)->format('j M Y') : 'Not given' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Birthplace</span>
                        <p>{{ $val($profile->birthplace) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Civil status</span>
                        <p>{{ $profile->civil_status ? ucfirst(str_replace('_', '-', $profile->civil_status)) : 'Not given' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Cellphone</span>
                        <p>{{ $val($profile->cellphone) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Email</span>
                        <p class="break-all">{{ $val($profile->email_address) }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <span class="text-xs text-gray-500">Present address</span>
                        <p>{{ $val(collect([$profile->present_street, $profile->present_city, $profile->present_province])->filter()->implode(', ')) }}</p>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <span class="text-xs text-gray-500">Permanent address</span>
                        <p>{{ $val(collect([$profile->permanent_street, $profile->permanent_city, $profile->permanent_province])->filter()->implode(', ')) }}</p>
                    </div>

                    @if ($profile->spouse_surname || $profile->spouse_first_name)
                        <div class="sm:col-span-2">
                            <span class="text-xs text-gray-500">Spouse</span>
                            <p>{{ $val(trim($profile->spouse_first_name.' '.$profile->spouse_middle_name.' '.$profile->spouse_surname)) }}</p>
                        </div>
                    @endif

                    <div>
                        <span class="text-xs text-gray-500">Father / guardian</span>
                        <p>{{ $val($profile->fathers_name) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Mother's maiden name</span>
                        <p>{{ $val($profile->mothers_maiden_name) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Siblings</span>
                        <p>
                            @if (count($siblings) === 0)
                                {{ $profile->sibling_count === 0 ? 'None' : 'Not given' }}
                            @else
                                {{ collect($siblings)->pluck('name')->filter()->implode(', ') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Bank account</span>
                        <p>{{ $val($profile->bank_account_number) }}</p>
                    </div>
                </div>
            </div>

            {{-- ----------------------------------------------- government IDs --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <h5 class="font-medium text-gray-900 mb-3">Government numbers</h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3">
                    @foreach (['SSS' => 'sss_number', 'Pag-IBIG' => 'pagibig_number',
                               'PhilHealth' => 'philhealth_number', 'TIN' => 'tin'] as $label => $column)
                        <div>
                            <span class="text-xs text-gray-500">{{ $label }}</span>
                            <p>{{ $val($profile->{$column}, 'None') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- --------------------------------------------------- emergency --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <h5 class="font-medium text-gray-900 mb-3">In case of emergency</h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3">
                    <div>
                        <span class="text-xs text-gray-500">Name</span>
                        <p>{{ $val($profile->emergency_name) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Contact</span>
                        <p>{{ $val($profile->emergency_contact_no) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Relationship</span>
                        <p>{{ $val($profile->emergency_relationship) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500">Address</span>
                        <p>{{ $val($profile->emergency_address) }}</p>
                    </div>
                </div>
            </div>

            {{-- --------------------------------------------------- education --}}
            @if (count($this->educationInOrder()) > 0)
                <div class="rounded-lg border border-gray-200 p-4">
                    <h5 class="font-medium text-gray-900 mb-3">Education</h5>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500">
                                    <th class="pb-2 pr-4 font-normal">Level</th>
                                    <th class="pb-2 pr-4 font-normal">School</th>
                                    <th class="pb-2 pr-4 font-normal">Course / strand</th>
                                    <th class="pb-2 font-normal">Years</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($this->educationInOrder() as $row)
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-600">{{ $educationLabels[$row->level] ?? $row->level }}</td>
                                        <td class="py-2 pr-4">{{ $val($row->school_name) }}</td>
                                        <td class="py-2 pr-4 text-gray-600">{{ $val($row->course, '&mdash;') }}</td>
                                        <td class="py-2 text-gray-600">
                                            {{ $val(collect([$row->year_from, $row->year_to])->filter()->implode(' - '), '&mdash;') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- -------------------------------------------------- employment --}}
            <div class="rounded-lg border border-gray-200 p-4">
                <h5 class="font-medium text-gray-900 mb-3">Employment record</h5>

                @forelse ($employment as $job)
                    <div class="@if (! $loop->first) border-t border-gray-100 pt-3 mt-3 @endif">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-medium text-gray-900">{{ $val($job->company_name) }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $val(collect([$job->date_from, $job->date_to])->filter()->implode(' - '), 'Dates not given') }}
                            </p>
                        </div>
                        <p class="text-gray-600">{{ $val($job->position, 'Position not given') }}</p>
                        @if ($job->daily_salary)
                            <p class="text-xs text-gray-500">Daily rate then: {{ number_format((float) $job->daily_salary, 2) }}</p>
                        @endif
                        @if (trim((string) $job->reason_for_leaving) !== '')
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="text-gray-400">Reason for leaving:</span> {{ $job->reason_for_leaving }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">No previous employers listed &mdash; this may be their first job.</p>
                @endforelse
            </div>

            {{-- -------------------------------------------------- references --}}
            @if (count($references) > 0)
                <div class="rounded-lg border border-gray-200 p-4">
                    <h5 class="font-medium text-gray-900 mb-3">Character references</h5>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($references as $ref)
                            <div>
                                <p class="font-medium">{{ $val($ref->name) }}</p>
                                <p class="text-gray-600">{{ $val($ref->position_company, '') }}</p>
                                <p class="text-gray-500 text-xs">{{ $val($ref->contact_no, '') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ------------------------------------------------- disclosures --}}
            @if ($disclosures)
                <div class="rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                    <div class="flex items-center justify-between mb-1">
                        <h5 class="font-medium text-gray-900">Disclosures</h5>
                        @if ($disclosures->declared_at)
                            <span class="text-xs text-green-700">
                                <i class="fas fa-check-circle mr-1"></i>Declared
                                {{ \Illuminate\Support\Carbon::parse($disclosures->declared_at)->format('j M Y') }}
                            </span>
                        @else
                            <span class="text-xs text-amber-700">Not yet declared</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        Confidential. Health and legal answers are sensitive personal information
                        under the Data Privacy Act.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                        @foreach ([
                            'has_medical_condition'        => ['Medical condition', 'medical_condition_details'],
                            'takes_maintenance_medication' => ['Maintenance medication', 'maintenance_medication_details'],
                            'ever_terminated'              => ['Terminated or asked to resign', 'ever_terminated_details'],
                            'ever_convicted'               => ['Convicted or pending case', 'ever_convicted_details'],
                            'employed_elsewhere'           => ['Employed elsewhere now', null],
                            'has_employment_bond'          => ['Bond or contract in effect', 'employment_bond_details'],
                            'was_union_member'             => ['Was a union member', 'union_position'],
                            'has_relative_employed'        => ['Relative working here', null],
                        ] as $field => [$label, $detail])
                            @php [$answer, $tone] = $yesNo($disclosures->{$field}); @endphp
                            <div class="flex items-baseline justify-between gap-3 border-b border-amber-100 pb-1">
                                <span class="text-gray-600">{{ $label }}</span>
                                <span class="{{ $tone }} whitespace-nowrap">{{ $answer }}</span>
                            </div>
                            @if ($detail && (int) $disclosures->{$field} === 1 && trim((string) $disclosures->{$detail}) !== '')
                                <div class="sm:col-span-2 -mt-1 pl-1 text-xs text-gray-600">
                                    <span class="text-gray-400">{{ $label }}:</span> {{ $disclosures->{$detail} }}
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if (count($relatives) > 0)
                        <div class="mt-3 text-xs text-gray-700">
                            <span class="text-gray-500">Relatives here:</span>
                            {{ collect($relatives)->map(fn ($r) => trim($r->name.' ('.$r->relationship.($r->department ? ', '.$r->department : '').')'))->implode('; ') }}
                        </div>
                    @endif

                    <div class="mt-3 pt-3 border-t border-amber-100 text-xs">
                        @php [$canStart, $tone] = $yesNo($disclosures->can_start_immediately); @endphp
                        <span class="text-gray-500">Can start immediately:</span>
                        <span class="{{ $tone }}">{{ $canStart }}</span>
                        @if ((int) $disclosures->can_start_immediately === 0)
                            @if ($disclosures->days_to_render)
                                <span class="text-gray-600">&mdash; {{ $disclosures->days_to_render }} days to render</span>
                            @endif
                            @if ($disclosures->available_start_date)
                                <span class="text-gray-600">
                                    &mdash; available {{ \Illuminate\Support\Carbon::parse($disclosures->available_start_date)->format('j M Y') }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
