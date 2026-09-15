<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files an applicant sent with their application.
 *
 * Both used to be buttons that flashed "download would start here" and did
 * nothing, which is worse than no button: HR could see a document existed and
 * had no way to open it.
 *
 * They are streamed through here rather than linked directly because an
 * application document sits on the public disk, where its URL would be
 * guessable by anybody. This checks who is asking first.
 */
class ApplicationFileController extends Controller
{
    /** The resume, held as base64 on the application row itself. */
    public function resume(int $applicationId): StreamedResponse
    {
        $application = DB::table('job_applications')->where('application_id', $applicationId)->first();

        abort_unless($application && $application->resume_data, 404);
        $this->allow((int) $application->user_id);

        $bytes = $this->bytesFrom($application->resume_data);

        abort_unless($bytes !== null, 404, 'That resume could not be read.');

        $name = 'resume-'.$applicationId.'.'.$this->extension($bytes);

        return response()->streamDownload(fn () => print($bytes), $name, [
            'Content-Type'            => $this->mime($bytes),
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }

    /**
     * resume_data is a JSON column, so what is inside it depends on whoever
     * wrote it - it may be an object carrying the file under a key, or a bare
     * base64 string. Both are handled, and anything else is a 404 rather than
     * a stream of nonsense with a .pdf name on it.
     */
    private function bytesFrom(?string $stored): ?string
    {
        if (! $stored) {
            return null;
        }

        $decoded = json_decode($stored, true);

        if (is_array($decoded)) {
            foreach (['data', 'contents', 'file', 'base64'] as $key) {
                if (! empty($decoded[$key]) && is_string($decoded[$key])) {
                    $stored = $decoded[$key];
                    break;
                }
            }
        } elseif (is_string($decoded)) {
            $stored = $decoded;
        }

        $bytes = base64_decode($stored, true);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }

    /** Anything else they uploaded, which lives on disk. */
    public function document(int $documentId)
    {
        $document = DB::table('application_documents')->where('id', $documentId)->first();

        abort_unless($document, 404);
        $this->allow((int) $document->user_id);
        abort_unless(Storage::disk('public')->exists($document->filepath), 404, 'That file is no longer on disk.');

        return Storage::disk('public')->download($document->filepath, $document->filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** HR, or the applicant themselves. Nobody else. */
    private function allow(int $ownerId): void
    {
        if (auth()->id() === $ownerId) {
            return;
        }

        PeopleAccess::hr();
    }

    /**
     * Applications do not record the file type, so it is read from the first
     * few bytes rather than trusted from a name that may not exist.
     */
    private function mime(string $bytes): string
    {
        return match ($this->extension($bytes)) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    private function extension(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, '%PDF')            => 'pdf',
            str_starts_with($bytes, "\x89PNG")         => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF")    => 'jpg',
            str_starts_with($bytes, "PK\x03\x04")      => 'docx',
            default                                     => 'bin',
        };
    }
}
