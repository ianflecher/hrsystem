{{-- Who they are: the name in parts, both addresses, how to reach them, and
     who to call if something happens. --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Personal details</h2>
    </div>

    <div class="px-6 py-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="surname">Surname <span class="text-red-600">*</span></label>
                <input id="surname" type="text" wire:model="p.surname" class="form-input">
                @error('p.surname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="first_name">First name <span class="text-red-600">*</span></label>
                <input id="first_name" type="text" wire:model="p.first_name" class="form-input">
                @error('p.first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="middle_name">Middle name</label>
                <input id="middle_name" type="text" wire:model="p.middle_name" class="form-input" placeholder="N/A if none">
            </div>
        </div>

        <div>
            <span class="form-label">Present address <span class="text-red-600">*</span></span>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-1">
                <input id="present_street" type="text" wire:model="p.present_street" class="form-input" placeholder="House/unit no., street, barangay">
                <input type="text" wire:model="p.present_city" class="form-input" placeholder="City/municipality">
                <input type="text" wire:model="p.present_province" class="form-input" placeholder="Province">
            </div>
            @error('p.present_street') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('p.present_city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('p.present_province') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between">
                <span class="form-label mb-0">Permanent address <span class="text-red-600">*</span></span>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model.live="p.permanent_same_as_present"
                           class="rounded border-gray-300 text-red-600">
                    Same as above
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-1">
                <input id="permanent_street" type="text" wire:model="p.permanent_street" class="form-input" placeholder="House/unit no., street, barangay"
                       @if ($p['permanent_same_as_present'] ?? false) readonly @endif>
                <input type="text" wire:model="p.permanent_city" class="form-input" placeholder="City/municipality"
                       @if ($p['permanent_same_as_present'] ?? false) readonly @endif>
                <input type="text" wire:model="p.permanent_province" class="form-input" placeholder="Province"
                       @if ($p['permanent_same_as_present'] ?? false) readonly @endif>
            </div>
            @error('p.permanent_street') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('p.permanent_city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('p.permanent_province') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="form-label" for="cellphone">Cellphone no. <span class="text-red-600">*</span></label>
                <input id="cellphone" type="text" wire:model="p.cellphone" class="form-input" placeholder="09XX XXX XXXX">
                @error('p.cellphone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="email_address">Email address <span class="text-red-600">*</span></label>
                <input id="email_address" type="email" wire:model="p.email_address" class="form-input">
                @error('p.email_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="bank_account_number">Account number (bank)</label>
                <input id="bank_account_number" type="text" wire:model="p.bank_account_number"
                       class="form-input" placeholder="e.g. BDO 1234567890 &mdash; N/A if none">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="date_of_birth">Date of birth <span class="text-red-600">*</span></label>
                <input id="date_of_birth" type="date" wire:model="p.date_of_birth" class="form-input">
                @error('p.date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="birthplace">Birthplace <span class="text-red-600">*</span></label>
                <input id="birthplace" type="text" wire:model="p.birthplace" class="form-input">
                @error('p.birthplace') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label" for="civil_status">Civil status <span class="text-red-600">*</span></label>
                <select id="civil_status" wire:model.live="p.civil_status" class="form-input">
                    <option value="">Not specified</option>
                    @foreach ($civilStatuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('p.civil_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Only worth asking when there is a spouse to name. --}}
        @if (in_array($p['civil_status'] ?? '', ['married', 'live_in', 'widowed', 'separated'], true))
            <div>
                <span class="form-label">Spouse</span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-1">
                    <input type="text" wire:model="p.spouse_surname" class="form-input" placeholder="Surname">
                    <input type="text" wire:model="p.spouse_first_name" class="form-input" placeholder="First name">
                    <input type="text" wire:model="p.spouse_middle_name" class="form-input" placeholder="Middle name (N/A if none)">
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="fathers_name">Father's name</label>
                <input id="fathers_name" type="text" wire:model="p.fathers_name" class="form-input" placeholder="N/A if none">
            </div>
            <div>
                <label class="form-label" for="mothers_maiden_name">Mother's maiden name <span class="text-red-600">*</span></label>
                <input id="mothers_maiden_name" type="text" wire:model="p.mothers_maiden_name" class="form-input">
                @error('p.mothers_maiden_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- How many first, then that many boxes. Fixed slots either left empty
             ones on screen or ran out, and neither matched the family. --}}
        <div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="form-label" for="sibling_count">How many siblings?</label>
                    <input id="sibling_count" type="number" min="0" max="20"
                           wire:model.live="p.sibling_count" class="form-input" placeholder="0">
                    @error('p.sibling_count') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if (count($siblings) > 0)
                <div class="mt-3">
                    <span class="form-label">Their names</span>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-1">
                        @foreach ($siblings as $i => $sibling)
                            <div class="flex gap-2" wire:key="sibling-{{ $i }}">
                                <input type="text" wire:model="siblings.{{ $i }}.name"
                                       class="form-input flex-1" placeholder="Sibling {{ $i + 1 }}">
                                <button type="button" wire:click="removeSibling({{ $i }})"
                                        class="text-sm text-red-600 hover:text-red-700 px-1"
                                        aria-label="Remove sibling {{ $i + 1 }}">&times;</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" wire:click="addSibling" class="btn-secondary text-sm mt-3">
                        + Add another
                    </button>
                </div>
            @endif
        </div>
    </div>
</section>
