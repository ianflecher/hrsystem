{{-- Six levels, of which nobody fills in all six. Junior and senior high are
     listed for anyone schooled under K-12, and plain high school on its own
     for everybody before it - a form offering only the split would have older
     applicants inventing an answer. Blank levels are simply not stored. --}}
<section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Educational background</h2>
        <p class="text-sm text-gray-600 mt-0.5">
            Fill in the ones that apply to you and leave the rest blank.
        </p>
    </div>

    <div class="px-6 py-4 space-y-3">
        @foreach ($levels as $key => $label)
            <div wire:key="edu-{{ $key }}">
                <span class="form-label">{{ $label }}</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-1">
                    <div class="lg:col-span-2">
                        <input type="text" wire:model="edu.{{ $key }}.school_name"
                               class="form-input" placeholder="Name of school">
                    </div>
                    <div>
                        <input type="text" wire:model="edu.{{ $key }}.course"
                               class="form-input"
                               placeholder="{{ in_array($key, ['vocational', 'tertiary'], true) ? 'Course' : 'Strand or track' }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" wire:model="edu.{{ $key }}.year_from" class="form-input" placeholder="From">
                        <input type="text" wire:model="edu.{{ $key }}.year_to" class="form-input" placeholder="To">
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
