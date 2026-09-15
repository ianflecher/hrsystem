<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Carries the files somebody already sent us into their vault.
 *
 * An applicant uploads their documents while applying, and if they are hired
 * those are the same documents HR would otherwise ask for again. This copies
 * them onto the employee record when the account is created, so the vault
 * starts with what the person already provided rather than empty.
 *
 * The files move disks on the way: an application upload sits on the public
 * disk, while an employee document is private and served only through the
 * download route, which checks who is asking.
 */
class DocumentVault
{
    /**
     * @return int how many documents were carried over
     */
    public function adoptApplicationDocuments(int $userId, int $employeeId): int
    {
        $documents = DB::table('application_documents')->where('user_id', $userId)->get();
        $carried = 0;

        foreach ($documents as $document) {
            // Already carried over once - re-running must not duplicate them.
            $already = DB::table('employee_documents')->where('employee_id', $employeeId)
                ->where('original_name', $document->filename)->where('category', 'application')->exists();

            if ($already || ! Storage::disk('public')->exists($document->filepath)) {
                continue;
            }

            $path = 'employee-documents/'.uniqid('app-', true).'-'.basename($document->filepath);

            if (! Storage::disk('local')->put($path, Storage::disk('public')->get($document->filepath))) {
                continue;
            }

            DB::table('employee_documents')->insert([
                'employee_id'   => $employeeId,
                'title'         => 'From their application: '.$document->filename,
                'category'      => 'application',
                'path'          => $path,
                'original_name' => $document->filename,
                'uploaded_by'   => auth()->id(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $carried++;
        }

        return $carried;
    }
}
