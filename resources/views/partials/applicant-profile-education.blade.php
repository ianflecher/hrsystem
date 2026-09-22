{{-- Secondary school comes in two shapes, and which one a person took decides
     which rows they are shown. The form used to list junior high, senior high
     AND plain high school together, so everybody was looking at one row that
     was not theirs and guessing whether to fill it. Asked once, up front, and
     the rows follow the answer. --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Educational background</h2>
        <p class="text-sm text-gray-600 mt-0.5">
            Fill in the ones that apply to you. Leave the rest blank &mdash; there is
            no need to write N/A.
        </p>
    </div>

    <div class="px-6 py-4 border-b border-gray-200">
        <span class="form-label">Which secondary schooling did you take? <span class="text-red-600">*</span></span>
        <div class="flex flex-wrap gap-6 mt-2">
            <label class="flex items-center gap-2 text-sm text-gray-800">
                <input type="radio" value="k12" wire:model.live="secondary">
                K-12 &mdash; junior and senior high school
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-800">
                <input type="radio" value="high_school" wire:model.live="secondary">
                High school &mdash; the old curriculum
            </label>
        </div>
        @error('secondary') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="px-6 py-4 space-y-3">
        @foreach ($this->shownLevels() as $key => $label)
            @php
                // A strand belongs to senior high, a course to vocational and
                // college. Elementary and junior high have neither, so they are
                // not asked for one.
                $second = match ($key) {
                    'senior_high'            => 'Strand or track',
                    'high_school'            => 'Strand or track (if any)',
                    'vocational', 'tertiary' => 'Course',
                    default                  => null,
                };
            @endphp
            <div wire:key="edu-{{ $key }}">
                <span class="form-label">
                    {{ $label }}
                    {{-- Plenty of people did neither, and without saying so the
                         blank row invites an N/A that means the same as leaving
                         it alone. --}}
                    @if (in_array($key, ['vocational', 'tertiary'], true))
                        <span class="font-normal text-gray-500">&mdash; only if you did</span>
                    @endif
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-1">
                    <div class="{{ $second ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                        <input type="text" wire:model="edu.{{ $key }}.school_name"
                               class="form-input" placeholder="Name of school">
                    </div>
                    @if ($second)
                        <div>
                            <input type="text" wire:model="edu.{{ $key }}.course"
                                   class="form-input" placeholder="{{ $second }}">
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" wire:model="edu.{{ $key }}.year_from" class="form-input" placeholder="From">
                        <input type="text" wire:model="edu.{{ $key }}.year_to" class="form-input" placeholder="To">
                    </div>
                </div>
            </div>
        @endforeach

        @if ($secondary === '')
            <p class="text-sm text-gray-600">
                Choose above and the rest of the levels will appear.
            </p>
        @endif
    </div>
</section>
