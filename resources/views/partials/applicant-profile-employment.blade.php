{{-- Previous employers, as many as they have had. Dates are month and year
     only: nobody remembers the day they started a job six years ago, and a
     date picker demanding one just invites a made-up answer. --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-900">Employment record</h2>
            <p class="text-sm text-gray-600 mt-0.5">Most recent first. Leave blank if this is your first job.</p>
        </div>
        <button type="button" wire:click="addJob" class="btn-secondary text-sm">+ Add employer</button>
    </div>

    <div class="px-6 py-5 space-y-6">
        @forelse ($jobs as $i => $job)
            <div class="rounded-lg border border-gray-200 p-4" wire:key="job-{{ $i }}">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">Employer {{ $i + 1 }}</span>
                    @if (count($jobs) > 1)
                        <button type="button" wire:click="removeJob({{ $i }})"
                                class="text-sm text-red-600 hover:text-red-700">Remove</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="md:col-span-3 lg:col-span-2">
                        <label class="form-label">Company name</label>
                        <input type="text" wire:model="jobs.{{ $i }}.company_name" class="form-input">
                        @error('jobs.'.$i.'.company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Position</label>
                        <input type="text" wire:model="jobs.{{ $i }}.position" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Start (month &amp; year)</label>
                        <input type="text" wire:model="jobs.{{ $i }}.date_from" class="form-input" placeholder="e.g. March 2021">
                    </div>
                    <div>
                        <label class="form-label">End (month &amp; year)</label>
                        <input type="text" wire:model="jobs.{{ $i }}.date_to" class="form-input" placeholder="e.g. August 2023">
                    </div>
                    <div>
                        <label class="form-label">Previous salary (per day)</label>
                        <input type="number" step="0.01" min="0" wire:model="jobs.{{ $i }}.daily_salary" class="form-input" placeholder="0.00">
                        @error('jobs.'.$i.'.daily_salary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-3 lg:col-span-6">
                        <label class="form-label">Company address</label>
                        <textarea wire:model="jobs.{{ $i }}.company_address" rows="2" class="form-input"></textarea>
                    </div>
                    <div class="md:col-span-3 lg:col-span-6">
                        <label class="form-label">Reason for leaving</label>
                        <textarea wire:model="jobs.{{ $i }}.reason_for_leaving" rows="2" class="form-input"></textarea>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">No previous employers listed.</p>
        @endforelse
    </div>

    {{-- ------------------------------------------------- character references --}}
    <div class="px-6 py-3 border-t border-gray-200">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-medium text-gray-900">Character references</h3>
                <p class="text-sm text-gray-600 mt-0.5">Optional. Not a relative.</p>
            </div>
            <button type="button" wire:click="addRef" class="btn-secondary text-sm">+ Add reference</button>
        </div>

        @forelse ($refs as $i => $ref)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3 items-start" wire:key="ref-{{ $i }}">
                <input type="text" wire:model="refs.{{ $i }}.name" class="form-input" placeholder="Name">
                <input type="text" wire:model="refs.{{ $i }}.contact_no" class="form-input" placeholder="Contact no.">
                <div class="flex gap-2">
                    <input type="text" wire:model="refs.{{ $i }}.position_company" class="form-input flex-1"
                           placeholder="Position / company">
                    <button type="button" wire:click="removeRef({{ $i }})"
                            class="text-sm text-red-600 hover:text-red-700 px-1">Remove</button>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-600">None given.</p>
        @endforelse
    </div>

    {{-- --------------------------------------------------------- certification --}}
    <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 rounded-b-xl">
        <p class="text-sm text-gray-800">
            I hereby certify that the above information is true and correct to the best of my
            knowledge and belief.
        </p>

        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[16rem]">
                <label class="form-label" for="certified_name">Signature over printed name</label>
                <input id="certified_name" type="text" wire:model="p.certified_name" class="form-input"
                       placeholder="Type your full name">
                @error('p.certified_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="certify" class="btn-primary">Certify and save</button>
        </div>

        @if (! empty($p['certified_at']))
            <p class="mt-3 text-sm text-green-700">
                Certified {{ \Illuminate\Support\Carbon::parse($p['certified_at'])->format('j M Y, g:ia') }}.
            </p>
        @endif
    </div>
</section>
