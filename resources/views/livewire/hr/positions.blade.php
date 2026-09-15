<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.humanresource')] class extends Component
{
    public $positions = [];
    public $departments = [];

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
        $this->showModal       = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'title'           => ['required', 'string', 'max:150'],
            'department_id'   => ['nullable'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,internship'],
            'description'     => ['nullable', 'string', 'max:2000'],
        ]);

        $row = [
            'title'           => $data['title'],
            'department_id'   => $data['department_id'] !== '' ? (int) $data['department_id'] : null,
            'employment_type' => $data['employment_type'],
            'description'     => $data['description'] !== '' ? $data['description'] : null,
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
                                    <div class="font-medium text-gray-900">{{ $position->title }}</div>
                                    @if ($position->description)
                                        <div class="text-sm text-gray-600 mt-0.5">
                                            {{ \Illuminate\Support\Str::limit($position->description, 80) }}
                                        </div>
                                    @endif
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
                                <label class="form-label" for="department_id">Department</label>
                                <select id="department_id" wire:model="department_id" class="form-input">
                                    <option value="">Not specified</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->department_id }}">
                                            {{ $department->department_name }}
                                        </option>
                                    @endforeach
                                </select>
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
</div>
