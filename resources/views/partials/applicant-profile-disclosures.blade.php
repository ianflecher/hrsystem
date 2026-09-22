{{-- The four disclosure sections, asked of applicants.

     These hold sensitive personal information under the Data Privacy Act -
     health, medication and criminal history. They are written here by the
     person they are about and read in the back office by HR. Nothing else on
     the site reads them, and nothing here is shown on any public page.

     Every answer starts unset rather than defaulting to "no": on a form
     somebody signs, "not answered" and "answered no" are different things. --}}

@php
    /** A yes/no pair. Unset until one is chosen. */
    $yesno = function (string $field, string $question, bool $live = false) {
        return ['field' => $field, 'question' => $question, 'live' => $live];
    };
@endphp

<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Applicant disclosures</h2>
        <p class="text-sm text-gray-600 mt-0.5">
            Answered once, kept confidential, and seen only by HR.
        </p>
    </div>

    {{-- ----------------------------------------------------------- 1. health --}}
    <div class="px-6 py-3 border-b border-gray-200">
        <h3 class="font-medium text-gray-900 mb-3">1. Health disclosure</h3>

        <div class="space-y-4">
            <div>
                <p class="text-sm text-gray-800">
                    Do you have a pre-existing medical condition the company should be aware of?
                </p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.has_medical_condition"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.has_medical_condition"> No</label>
                </div>
                @if ((string) ($d['has_medical_condition'] ?? '') === '1')
                    <textarea wire:model="d.medical_condition_details" rows="2" class="form-input mt-2"
                              placeholder="Please specify"></textarea>
                @endif
            </div>

            <div>
                <p class="text-sm text-gray-800">Are you currently taking any maintenance medication?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.takes_maintenance_medication"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.takes_maintenance_medication"> No</label>
                </div>
                @if ((string) ($d['takes_maintenance_medication'] ?? '') === '1')
                    <textarea wire:model="d.maintenance_medication_details" rows="2" class="form-input mt-2"
                              placeholder="Please specify"></textarea>
                @endif
            </div>
        </div>
    </div>

    {{-- -------------------------------------------------------- 2. relatives --}}
    <div class="px-6 py-3 border-b border-gray-200">
        <h3 class="font-medium text-gray-900 mb-3">2. Relatives employed at the company</h3>

        <p class="text-sm text-gray-800">
            Do you have any relatives currently employed at GKLASAM OPC (Imprint Customs)
            or Imprint Caf&eacute;? <span class="text-gray-600">(up to the 3rd degree of consanguinity or affinity)</span>
        </p>
        <div class="flex gap-6 mt-2">
            <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.has_relative_employed"> Yes</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.has_relative_employed"> No</label>
        </div>

        @if ((string) ($d['has_relative_employed'] ?? '') === '1')
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-700">Please indicate below.</span>
                    <button type="button" wire:click="addRelative" class="btn-secondary text-sm">+ Add relative</button>
                </div>

                @forelse ($relatives as $i => $rel)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3" wire:key="rel-{{ $i }}">
                        <input type="text" wire:model="relatives.{{ $i }}.name" class="form-input" placeholder="Name">
                        <input type="text" wire:model="relatives.{{ $i }}.relationship" class="form-input" placeholder="Relationship">
                        <div class="flex gap-2">
                            <input type="text" wire:model="relatives.{{ $i }}.department" class="form-input flex-1" placeholder="Department">
                            <button type="button" wire:click="removeRelative({{ $i }})"
                                    class="text-sm text-red-600 hover:text-red-700 px-1">Remove</button>
                        </div>
                    </div>
                @empty
                    <button type="button" wire:click="addRelative" class="text-sm text-red-600">Add the first one</button>
                @endforelse
            </div>
        @endif
    </div>

    {{-- ------------------------------------------- 3. prior employment / legal --}}
    <div class="px-6 py-3 border-b border-gray-200">
        <h3 class="font-medium text-gray-900 mb-3">3. Prior employment and legal disclosure</h3>

        <div class="space-y-4">
            <div>
                <p class="text-sm text-gray-800">Have you ever been terminated or asked to resign from previous employment?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.ever_terminated"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.ever_terminated"> No</label>
                </div>
                @if ((string) ($d['ever_terminated'] ?? '') === '1')
                    <textarea wire:model="d.ever_terminated_details" rows="2" class="form-input mt-2" placeholder="Please specify"></textarea>
                @endif
            </div>

            <div>
                <p class="text-sm text-gray-800">Have you ever been convicted of a crime, or do you currently have a pending case?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.ever_convicted"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.ever_convicted"> No</label>
                </div>
                @if ((string) ($d['ever_convicted'] ?? '') === '1')
                    <textarea wire:model="d.ever_convicted_details" rows="2" class="form-input mt-2" placeholder="Please specify"></textarea>
                @endif
            </div>

            {{-- Asked as two, because they are two facts: working somewhere now
                 is not the same as being bound by a contract that has not
                 ended. Either one changes when somebody can start. --}}
            <div>
                <p class="text-sm text-gray-800">Are you currently employed elsewhere?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model="d.employed_elsewhere"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model="d.employed_elsewhere"> No</label>
                </div>
            </div>

            <div>
                <p class="text-sm text-gray-800">Do you have an existing employment bond or contract still in effect?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.has_employment_bond"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.has_employment_bond"> No</label>
                </div>
                @if ((string) ($d['has_employment_bond'] ?? '') === '1')
                    <textarea wire:model="d.employment_bond_details" rows="2" class="form-input mt-2" placeholder="Please specify"></textarea>
                @endif
            </div>

            <div>
                <p class="text-sm text-gray-800">Have you ever been part of a union in a previous company?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.was_union_member"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.was_union_member"> No</label>
                </div>
                @if ((string) ($d['was_union_member'] ?? '') === '1')
                    <input type="text" wire:model="d.union_position" class="form-input mt-2" placeholder="What position did you hold?">
                @endif
            </div>

            <div>
                <p class="text-sm text-gray-800">Can you start immediately?</p>
                <div class="flex gap-6 mt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="1" wire:model.live="d.can_start_immediately"> Yes</label>
                    <label class="flex items-center gap-2 text-sm"><input type="radio" value="0" wire:model.live="d.can_start_immediately"> No</label>
                </div>
                @if ((string) ($d['can_start_immediately'] ?? '') === '0')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                        <div>
                            <label class="form-label">Days you have to render</label>
                            <input type="number" min="0" max="365" wire:model="d.days_to_render" class="form-input">
                            @error('d.days_to_render') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Or the date you could start</label>
                            <input type="date" wire:model="d.available_start_date" class="form-input">
                            @error('d.available_start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ----------------------------------------- 4. government IDs and benefits --}}
    <div class="px-6 py-3 border-b border-gray-200">
        <h3 class="font-medium text-gray-900 mb-1">4. Government mandated IDs and benefits</h3>
        <p class="text-sm text-gray-600 mb-3">
            Which of these do you already have on file? Answered from the numbers you
            gave earlier &mdash; change any that is wrong.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                'sss_on_file'        => 'SSS number',
                'pagibig_on_file'    => 'Pag-IBIG (HDMF) MID number',
                'philhealth_on_file' => 'PhilHealth number',
                'tin_on_file'        => 'TIN',
            ] as $field => $label)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2" wire:key="gov-{{ $field }}">
                    <span class="text-sm text-gray-800">{{ $label }}</span>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-1.5 text-sm"><input type="radio" value="1" wire:model="d.{{ $field }}"> Yes</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="radio" value="0" wire:model="d.{{ $field }}"> No</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ------------------------------------------------------------ declaration --}}
    <div class="px-6 py-5 bg-gray-50 rounded-b-xl">
        <p class="text-sm text-gray-800">
            I certify that the information I have disclosed above is true and correct to the best
            of my knowledge. I understand that any concealment or misrepresentation of the above
            information may be ground for disqualification from employment, or termination if
            discovered after hiring.
        </p>

        {{-- No box to type your own name into: you are signed in, and the
             record keeps the name and the time by itself. --}}
        <div class="mt-3">
            <button type="button" wire:click="declare" class="btn-primary">Agree and save</button>
        </div>

        @if (! empty($d['declared_at']))
            <p class="mt-3 text-sm text-green-700">
                Declared {{ \Illuminate\Support\Carbon::parse($d['declared_at'])->format('j M Y, g:ia') }}.
            </p>
        @endif
    </div>
</section>
