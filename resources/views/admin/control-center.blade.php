<x-layouts.humanresource :title="'Admin Control Center'">
    <div class="p-6 md:p-8 max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-red-600">Central administration</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Admin Control Center</h1>
                <p class="mt-2 max-w-2xl text-slate-600">One place to open the HRIS, Production and Marketing systems and see whether each one is reachable.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
                Checked {{ $summary['checked_at']->format('M j, Y g:i A') }}
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Systems online</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $summary['online'] }} <span class="text-base font-medium text-slate-400">/ {{ $summary['configured'] }}</span></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Admin scope</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">All systems</p>
            </div>
        </div>

        <section aria-labelledby="systems-heading">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h2 id="systems-heading" class="text-xl font-bold text-slate-900">Connected systems</h2>
                    <p class="mt-1 text-sm text-slate-500">Open a system in a new tab while keeping this control center available.</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach($services as $service)
                    @php
                        $accent = match($service['accent']) {
                            'blue' => ['text' => 'text-blue-700', 'bg' => 'bg-blue-50', 'border' => 'border-blue-200'],
                            'amber' => ['text' => 'text-amber-700', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200'],
                            default => ['text' => 'text-red-700', 'bg' => 'bg-red-50', 'border' => 'border-red-200'],
                        };
                    @endphp
                    <article class="flex min-h-[245px] flex-col rounded-xl border {{ $accent['border'] }} bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $accent['bg'] }} {{ $accent['text'] }}" aria-hidden="true">
                                <i class="fa-solid {{ $service['icon'] }} text-lg"></i>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $service['status'] === 'online' ? 'bg-emerald-50 text-emerald-700' : ($service['status'] === 'error' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                <span class="h-2 w-2 rounded-full {{ $service['status'] === 'online' ? 'bg-emerald-500' : ($service['status'] === 'error' ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                                {{ ucfirst($service['status']) }}
                            </span>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-slate-900">{{ $service['name'] }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $service['description'] }}</p>
                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <span class="text-xs text-slate-500">
                                @if($service['status_code'])
                                    HTTP {{ $service['status_code'] }} · {{ $service['latency_ms'] }} ms
                                @else
                                    No response
                                @endif
                            </span>
                            <a href="{{ $service['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                                Open system <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="quick-links-heading" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="quick-links-heading" class="text-xl font-bold text-slate-900">HRIS quick links</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('hr.home') }}" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:text-red-700"><i class="fa-solid fa-chart-line mr-2" aria-hidden="true"></i>HR dashboard</a>
                <a href="{{ route('hr.employees') }}" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:text-red-700"><i class="fa-solid fa-users mr-2" aria-hidden="true"></i>Employees</a>
                <a href="{{ route('hr.payroll') }}" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:text-red-700"><i class="fa-solid fa-money-check-dollar mr-2" aria-hidden="true"></i>Payroll</a>
                <a href="{{ route('hr.operations.analytics') }}" class="rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-red-300 hover:text-red-700"><i class="fa-solid fa-chart-pie mr-2" aria-hidden="true"></i>Analytics</a>
            </div>
        </section>
    </div>
</x-layouts.humanresource>
