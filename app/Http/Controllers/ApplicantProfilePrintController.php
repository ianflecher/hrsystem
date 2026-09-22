<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * The 201 file as a document, for printing and for the physical folder.
 *
 * A screen and a printed page want different things - the screen can scroll
 * and collapse, the page cannot - so this is its own view rather than a print
 * stylesheet bolted onto the dialog. It comes out as three pages, in the order
 * the form asked for them:
 *
 *   1. who they are, their numbers, and who to call
 *   2. schooling and previous employers
 *   3. the disclosures, and what was signed
 *
 * HR only. It carries health, medication and criminal history, which are
 * sensitive personal information under the Data Privacy Act.
 */
class ApplicantProfilePrintController extends Controller
{
    /** The levels in the order a 201 file is read, not the order stored. */
    private const LEVEL_ORDER = [
        'elementary'  => 'Elementary',
        'junior_high' => 'Junior high',
        'senior_high' => 'Senior high',
        'high_school' => 'High school',
        'vocational'  => 'Vocational',
        'tertiary'    => 'College / tertiary',
    ];

    public function __invoke(int $applicationId): View
    {
        PeopleAccess::hr();

        $application = DB::table('job_applications as ja')
            ->select('ja.*', 'u.full_name', 'u.email')
            ->join('users as u', 'ja.user_id', '=', 'u.user_id')
            ->where('ja.application_id', $applicationId)
            ->first();

        abort_unless($application, 404);

        $userId = (int) $application->user_id;
        $profile = DB::table('applicant_profiles')->where('user_id', $userId)->first();

        abort_unless($profile, 404, 'This applicant has not filled in their details yet.');

        $education = DB::table('applicant_education')->where('user_id', $userId)->get()->keyBy('level');

        return view('print.applicant-201', [
            'application' => $application,
            'profile'     => $profile,
            'levels'      => self::LEVEL_ORDER,
            'education'   => $education,
            'employment'  => DB::table('applicant_employment')->where('user_id', $userId)->orderBy('sort_order')->get(),
            'references'  => DB::table('applicant_references')->where('user_id', $userId)->orderBy('sort_order')->get(),
            'relatives'   => DB::table('applicant_relatives')->where('user_id', $userId)->get(),
            'siblings'    => DB::table('applicant_siblings')->where('user_id', $userId)->orderBy('sort_order')->get(),
            'disclosures' => DB::table('applicant_disclosures')->where('user_id', $userId)->first(),
        ]);
    }
}
