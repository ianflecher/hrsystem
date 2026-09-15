@if($extra['expiring'] > 0)
    <div class="alert warning">{{ $extra['expiring'] }} document(s) expired or expiring within 30 days.
        <a href="{{ $base }}?expiring=1"><u>Review expiry alerts</u></a> · <a href="{{ $base }}"><u>Show all</u></a></div>
@endif

{{-- The vault fills from the person it is about: what an applicant sent with
     their application is carried over when they are hired, and after that they
     upload their own. HR can still file something on somebody's behalf. --}}
<details class="card" @if($errors->any()) open @endif>
    <summary>{{ $hr ? 'Upload a document for an employee' : 'Upload a document' }}</summary>
    <form method="POST" action="{{ $base }}" enctype="multipart/form-data" class="grid divider">@csrf
        @include('people.employee-select')
        <x-people.field name="title" label="Document title" maxlength="150" />
        <label class="people-field"><span>Category</span>
            <select name="category">
                <option value="contract">Contract</option>
                <option value="id">Identification</option>
                <option value="certificate">Certificate</option>
                <option value="other">Other</option>
            </select>
        </label>
        <x-people.field name="expires_on" label="Expiry date (optional)" type="date" :required="false" />
        <label class="people-field wide"><span>File · PDF, Word, JPG or PNG · up to 10 MB</span>
            <input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></label>
        <div><button>Upload document</button></div>
    </form>
</details>

<p class="muted">Documents are private. Employees can download only their own files.</p>

@forelse($rows as $row)
    <article class="card">
        <div class="row between">
            <div>
                <h2>{{ $row->title }}</h2>
                <p>{{ $row->full_name }} · {{ $row->category === 'application' ? 'From their application' : ucfirst($row->category) }}</p>
                <p class="muted">{{ $row->original_name }} · Uploaded {{ substr($row->created_at, 0, 10) }}</p>
            </div>
            <a class="button" href="{{ route('people.documents.download', $row->id) }}">Download</a>
        </div>
        @if($row->expires_on)
            <p class="{{ $row->expires_on <= today()->addDays(30)->toDateString() ? 'alert warning' : 'muted' }}">Expires {{ $row->expires_on }}</p>
        @endif
        @if($hr)
            <form method="POST" action="{{ $base }}/{{ $row->id }}" onsubmit="return confirm('Delete this document permanently?')">@csrf
                <input type="hidden" name="action" value="delete"><button class="danger">Delete document</button>
            </form>
        @endif
    </article>
@empty
    <div class="card muted">No documents yet. What an applicant sends with their application arrives here when they are hired.</div>
@endforelse
