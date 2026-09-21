<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    use WithFileUploads;

    public $positions = [];
    public $departments = [];

    /** A new photograph waiting to be saved, and the one already on the role. */
    public $image = null;
    public ?string $existingImage = null;

    /** Departments had no screen at all, so every dropdown of them was empty. */
    public string $newDepartment = '';
    public ?int $renamingDepartment = null;
    public string $renameTo = '';
    /** Creating a department from the form that wanted one. */
    public bool $addingDepartment = false;
    public string $inlineDepartment = '';


    public bool $showModal = false;
    public ?int $editingId = null;

    public string $title = '';
    public $department_id = '';
    public string $employment_type = 'full_time';
    public string $description = '';
    public bool $is_open = true;

    public string $filter = 'all';

    public array $employmentTypes = [
        'full_time'  => 'Full-time',
        'part_time'  => 'Part-time',
        'contract'   => 'Contract',
        'internship' => 'Internship',
    ];

    public function mount(): void
    {
        $this->loadDepartments();
        $this->loadPositions();
    }

    public function loadDepartments(): void
    {
        $this->departments = DB::table('departments')
            ->select('department_id', 'department_name')
            ->orderBy('department_name')
            ->get();
    }

    public function loadPositions(): void
    {
        $query = DB::table('job_positions as p')
            ->leftJoin('departments as d', 'p.department_id', '=', 'd.department_id')
            ->select('p.*', 'd.department_name');

        if ($this->filter === 'open') {
            $query->where('p.is_open', true);
        } elseif ($this->filter === 'closed') {
            $query->where('p.is_open', false);
        }

        $this->positions = $query->orderByDesc('p.is_open')
            ->orderByDesc('p.created_at')
            ->get();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->loadPositions();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $position = DB::table('job_positions')->where('position_id', $id)->first();

        if (! $position) {
            session()->flash('error', 'That opening no longer exists.');
            $this->loadPositions();

            return;
        }

        $this->editingId       = $position->position_id;
        $this->title           = $position->title;
        $this->department_id   = $position->department_id ?? '';
        $this->employment_type = $position->employment_type;
        $this->description     = $position->description ?? '';
        $this->is_open         = (bool) $position->is_open;
        $this->existingImage   = $position->image_path;
        $this->image           = null;
        $this->showModal       = true;
    }

    public function toggleAddDepartment(): void
    {
        $this->addingDepartment = ! $this->addingDepartment;
        $this->inlineDepartment = '';
        $this->resetValidation('inlineDepartment');
    }

    /**
     * Creates it and selects it, without disturbing anything else on the form.
     *
     * Abandoning a half-filled employee form to go and make a department was
     * the alternative, and it lost everything typed so far.
     */
    public function createDepartment(): void
    {
        $data = $this->validate(
            ['inlineDepartment' => ['required', 'string', 'max:100', 'unique:departments,department_name']],
            ['inlineDepartment.required' => 'Give the department a name.',
             'inlineDepartment.unique'   => 'There is already a department by that name.']
        );

        $id = DB::table('departments')->insertGetId([
            'department_name' => $data['inlineDepartment'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->loadDepartments();
        $this->department_id = $id;
        $this->addingDepartment = false;
        $this->inlineDepartment = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'title'           => ['required', 'string', 'max:150'],
            'department_id'   => ['nullable'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,internship'],
            'description'     => ['nullable', 'string', 'max:2000'],
            // 4 MB, and only formats a browser will draw. The careers page
            // renders this about 600px wide, so anything larger is weight an
            // applicant pays for and never sees.
            'image'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        // Stored only once the rest of the form has passed, so a rejected
        // save does not leave an orphan file behind.
        $imagePath = $this->existingImage;

        if ($this->image) {
            $imagePath = $this->image->store('job-photos', 'public');
            $this->forget($this->existingImage);
        }

        $row = [
            'title'           => $data['title'],
            'department_id'   => $data['department_id'] !== '' ? (int) $data['department_id'] : null,
            'employment_type' => $data['employment_type'],
            'description'     => $data['description'] !== '' ? $data['description'] : null,
            'image_path'      => $imagePath,
            'is_open'         => $this->is_open,
            'updated_at'      => now(),
        ];

        if ($this->editingId) {
            DB::table('job_positions')->where('position_id', $this->editingId)->update($row);
            session()->flash('success', 'Opening updated.');
        } else {
            $row['created_by'] = Auth::user()->user_id ?? null;
            $row['created_at'] = now();
            DB::table('job_positions')->insert($row);
            session()->flash('success', 'Opening posted.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->loadPositions();
    }

    /** Takes the photograph off a role without saving the rest of the form. */
    public function removeImage(): void
    {
        $this->forget($this->existingImage);
        $this->existingImage = null;
        $this->image = null;

        if ($this->editingId) {
            DB::table('job_positions')->where('position_id', $this->editingId)
                ->update(['image_path' => null, 'updated_at' => now()]);
            $this->loadPositions();
        }
    }

    /** Deletes a stored file, if there is one and it is still on disk. */
    private function forget(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function addDepartment(): void
    {
        $data = $this->validate(
            ['newDepartment' => ['required', 'string', 'max:100', 'unique:departments,department_name']],
            ['newDepartment.unique' => 'There is already a department by that name.']
        );

        DB::table('departments')->insert([
            'department_name' => $data['newDepartment'],
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->newDepartment = '';
        $this->loadDepartments();
        session()->flash('success', 'Department added.');
    }

    public function startRename(int $id, string $name): void
    {
        $this->renamingDepartment = $id;
        $this->renameTo = $name;
    }

    public function cancelRename(): void
    {
        $this->renamingDepartment = null;
        $this->renameTo = '';
    }

    public function saveRename(): void
    {
        $this->validate(['renameTo' => ['required', 'string', 'max:100']]);

        DB::table('departments')->where('department_id', $this->renamingDepartment)
            ->update(['department_name' => $this->renameTo, 'updated_at' => now()]);

        $this->cancelRename();
        $this->loadDepartments();
        $this->loadPositions();
        session()->flash('success', 'Department renamed.');
    }

    /**
     * Refused while anybody or any opening still points at it.
     *
     * Deleting anyway would quietly empty the department on every employee who
     * was in it, and nothing on the screen would say it had happened.
     */
    public function deleteDepartment(int $id): void
    {
        $employees = DB::table('employees')->where('department_id', $id)->count();
        $openings  = DB::table('job_positions')->where('department_id', $id)->count();

        if ($employees || $openings) {
            $parts = [];
            if ($employees) { $parts[] = $employees.' '.Str::plural('employee', $employees); }
            if ($openings)  { $parts[] = $openings.' '.Str::plural('opening', $openings); }

            session()->flash('error', 'That department still has '.implode(' and ', $parts).'. Move them first.');

            return;
        }

        DB::table('departments')->where('department_id', $id)->delete();
        $this->loadDepartments();
        session()->flash('success', 'Department removed.');
    }

    public function toggleOpen(int $id): void
    {
        $position = DB::table('job_positions')->where('position_id', $id)->first();

        if (! $position) {
            return;
        }

        DB::table('job_positions')
            ->where('position_id', $id)
            ->update(['is_open' => ! $position->is_open, 'updated_at' => now()]);

        session()->flash('success', $position->is_open ? 'Opening closed.' : 'Opening reopened.');
        $this->loadPositions();
    }

    /**
     * Applications record the position as text, so deleting a role never
     * orphans anyone's application - but the role disappearing from the
     * careers page is usually what is actually wanted, and closing it does
     * that while keeping the record.
     */
    public function delete(int $id): void
    {
        $this->forget(DB::table('job_positions')->where('position_id', $id)->value('image_path'));
        DB::table('job_positions')->where('position_id', $id)->delete();
        session()->flash('success', 'Opening deleted.');
        $this->loadPositions();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId       = null;
        $this->title           = '';
        $this->department_id   = '';
        $this->employment_type = 'full_time';
        $this->description     = '';
        $this->is_open         = true;
        $this->image           = null;
        $this->existingImage   = null;
        $this->resetErrorBag();
    }
}; ?>

<div class="p-6 md:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Job Openings</h1>
            <p class="text-sm text-gray-600 mt-1">
                What the careers page shows, and what applicants can apply for.
            </p>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            <i class="fas fa-plus"></i> Post an opening
        </button>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex gap-2 mb-5">
        @foreach (['all' => 'All', 'open' => 'Open', 'closed' => 'Closed'] as $key => $label)
            <button wire:click="setFilter('{{ $key }}')"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors
                        {{ $filter === $key
                            ? 'bg-red-600 border-red-600 text-white'
                            : 'bg-white border-gray-200 text-gray-700 hover:border-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @if (count($positions) === 0)
            <div class="px-6 py-14 text-center">
                <p class="text-gray-900 font-medium">No openings yet</p>
                <p class="text-sm text-gray-600 mt-1">
                    Post one and it appears on the careers page straight away.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($positions as $position)
                            <tr wire:key="pos-{{ $position->position_id }}">
                                <td>
                                    <div class="flex items-start gap-3">
                                        @if ($position->image_path)
                                            <img src="{{ Storage::disk('public')->url($position->image_path) }}"
                                                 alt="" class="h-12 w-16 flex-none rounded object-cover border border-gray-200">
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $position->title }}</div>
                                            @if ($position->description)
                                                <div class="text-sm text-gray-600 mt-0.5">
                                                    {{ \Illuminate\Support\Str::limit($position->description, 80) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-gray-600">{{ $position->department_name ?? '—' }}</td>
                                <td class="text-gray-600">
                                    {{ $employmentTypes[$position->employment_type] ?? $position->employment_type }}
                                </td>
                                <td>
                                    <span class="status-badge {{ $position->is_open ? 'status-active' : 'status-inactive' }}">
                                        {{ $position->is_open ? 'Open' : 'Closed' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="edit({{ $position->position_id }})"
                                                class="px-2.5 py-1.5 text-sm text-gray-700 hover:text-gray-900" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="toggleOpen({{ $position->position_id }})"
                                                class="px-2.5 py-1.5 text-sm text-gray-700 hover:text-gray-900"
                                                title="{{ $position->is_open ? 'Close' : 'Reopen' }}">
                                            <i class="fas {{ $position->is_open ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                        </button>
                                        <button wire:click="delete({{ $position->position_id }})"
                                                wire:confirm="Delete this opening? Applications already received are kept."
                                                class="px-2.5 py-1.5 text-sm text-blue-600 hover:text-blue-700" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="closeModal"></div>

                <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $editingId ? 'Edit opening' : 'Post an opening' }}
                        </h2>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="form-label" for="title">Position title</label>
                            <input id="title" type="text" wire:model="title" class="form-input"
                                   placeholder="e.g. Graphic Artist">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="form-label mb-0" for="department_id">Department</label>
                                    <button type="button" wire:click="toggleAddDepartment"
                                            class="text-sm text-red-600 hover:text-red-700 font-medium">
                                        {{ $addingDepartment ? 'Cancel' : '+ New department' }}
                                    </button>
                                </div>

                                @if ($addingDepartment)
                                    <div class="mt-1 flex items-start gap-2">
                                        <div class="flex-1">
                                            <input type="text" wire:model="inlineDepartment" wire:keydown.enter="createDepartment"
                                                   class="form-input" placeholder="Name of the new department" autofocus>
                                            @error('inlineDepartment')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button type="button" wire:click="createDepartment" class="btn-secondary">Create</button>
                                    </div>
                                @else
                                <select id="department_id" wire:model="department_id" class="form-input">
                                    <option value="">Not specified</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->department_id }}">
                                            {{ $department->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @endif
                            </div>

                            <div>
                                <label class="form-label" for="employment_type">Employment type</label>
                                <select id="employment_type" wire:model="employment_type" class="form-input">
                                    @foreach ($employmentTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('employment_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label" for="description">Short description</label>
                            <textarea id="description" wire:model="description" rows="3" class="form-input"
                                      placeholder="Optional — one or two lines shown under the title."></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="image">Photograph</label>

                            @php
                                $preview = $image
                                    ? $image->temporaryUrl()
                                    : ($existingImage ? Storage::disk('public')->url($existingImage) : null);
                            @endphp

                            @if ($preview)
                                <div class="mb-2 flex items-center gap-3">
                                    <img src="{{ $preview }}" alt=""
                                         class="h-20 w-32 rounded-lg object-cover border border-gray-200">
                                    <button type="button" wire:click="removeImage"
                                            class="text-sm text-red-600 hover:underline">Remove</button>
                                </div>
                            @endif

                            <input id="image" type="file" wire:model="image" accept="image/*" class="form-input">
                            <p class="mt-1 text-xs text-gray-500">
                                Shown beside this role on the careers page. JPG, PNG or WebP, up to 4&nbsp;MB.
                                A photograph of the room the work is done in beats a stock picture.
                            </p>
                            <p wire:loading wire:target="image" class="mt-1 text-xs text-gray-500">Uploading&hellip;</p>
                            @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_open" class="rounded border-gray-300 text-red-600">
                            Show on the careers page
                        </label>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
                        <button wire:click="closeModal" class="btn-secondary">Cancel</button>
                        <button wire:click="save" class="btn-primary">
                            {{ $editingId ? 'Save changes' : 'Post opening' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Departments had no screen anywhere in the back office. The only way one
         was ever created was as a side effect of hiring an applicant, so every
         department dropdown was empty and every employee read "No Department".
         It lives here because this is where roles are defined. --}}
    <div class="mt-8 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="font-semibold text-gray-900">Departments</h2>
            <p class="text-sm text-gray-600 mt-0.5">
                Used to group openings and employees, and to build the pathway cards on the careers page.
            </p>
        </div>

        <div class="px-6 py-4">
            @if (count($departments))
                <ul class="divide-y divide-gray-100 mb-4">
                    @foreach ($departments as $department)
                        <li class="py-2.5 flex items-center gap-3" wire:key="dept-{{ $department->department_id }}">
                            @if ($renamingDepartment === $department->department_id)
                                <input type="text" wire:model="renameTo" wire:keydown.enter="saveRename"
                                       class="form-input flex-1" autofocus>
                                <button wire:click="saveRename" class="btn-primary text-sm">Save</button>
                                <button wire:click="cancelRename" class="btn-secondary text-sm">Cancel</button>
                            @else
                                <span class="flex-1 text-gray-900">{{ $department->department_name }}</span>
                                <button wire:click="startRename({{ $department->department_id }}, '{{ addslashes($department->department_name) }}')"
                                        class="text-sm text-gray-600 hover:text-gray-900">Rename</button>
                                <button wire:click="deleteDepartment({{ $department->department_id }})"
                                        class="text-sm text-red-600 hover:text-red-700">Remove</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-600 mb-4">
                    None yet. Until there is at least one, openings and employees cannot be grouped
                    and the careers page has no pathways to offer.
                </p>
            @endif

            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <input type="text" wire:model="newDepartment" wire:keydown.enter="addDepartment"
                           class="form-input" placeholder="e.g. Production, Store, Administration">
                    @error('newDepartment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button wire:click="addDepartment" class="btn-secondary">Add</button>
            </div>
        </div>
    </div>
</div>
