<x-dynamic-component :component="$hr ? 'layouts.humanresource' : 'layouts.employeeland'">
    @php
        $modules = \App\Http\Controllers\PeopleController::MODULES;
        $base = url(($hr ? 'hr' : 'employee').'/people/'.$module);
        $descriptions = [
            'documents' => 'Keep important files together and stay ahead of expiry dates.',
            'overtime' => $hr ? 'Review overtime requests and approved pay in one place.' : 'Request extra hours and follow their approval and payment.',
            'shifts' => 'A clear view of working hours, rest days, and holidays.',
            'announcements' => 'Company updates and the information your team needs.',
            'checklists' => 'Track the next steps for a smooth arrival or handover.',
            'reviews' => 'Attendance, feedback, and progress through each review period.',
            'loans' => $hr ? 'Review requests, confirm disbursements, and track repayments.' : 'Follow your requests, repayment schedule, and remaining balance.',
            'reports' => 'Export the information you need for your next HR report.',
        ];
    @endphp
    @include('people.styles')
    <section class="people" aria-labelledby="people-title">
        <header class="page-header">
            <p class="page-eyebrow">{{ $hr ? 'People operations' : 'Employee services' }}</p>
            <h1 id="people-title">{{ $modules[$module] }}</h1>
            <p class="page-description">{{ $descriptions[$module] }}</p>
        </header>
        @if(session('success'))
            <div class="portal-feedback portal-feedback--success" role="status">
                <span>{{ session('success') }}</span>
                <button type="button" data-feedback-dismiss aria-label="Dismiss success message">&times;</button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert error" role="alert" tabindex="-1" id="people-errors">
                <strong>Please check your entries.</strong>
                <ul>@foreach($errors->messages() as $field => $messages)<li><a href="#people-errors" data-error-link="{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
            </div>
        @endif
        @include('people.'.$module)
        @if($rows && $module !== 'shifts')<div class="people-pagination">{{ $rows->links() }}</div>@endif
    </section>
    @include('people.scripts')
</x-dynamic-component>
