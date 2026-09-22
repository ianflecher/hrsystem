<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Turning an accepted offer into an employee.
 *
 * It lives here rather than on either screen because two of them need it: the
 * applicant's page runs it when they accept, and HR's runs it if they mark an
 * application hired against an offer already accepted. One path, so the two
 * cannot drift into hiring people on different terms.
 *
 * Everything is read from the offer row. By the time this runs the candidate
 * has agreed to something specific - a title, a basic, an allowance, a start
 * date - and hiring them on anything else would make the record a lie. Not
 * from a form, not from the employee record, not from the application.
 */
class OfferAcceptance
{
    /**
     * Hire against the offer, if it is one that was accepted.
     *
     * Safe to call twice: the second time finds an employee already active on
     * these terms and writes the same thing again.
     */
    public static function hire(int $offerId): bool
    {
        $offer = DB::table('job_offers')->where('offer_id', $offerId)->first();

        if (! $offer || $offer->status !== 'accepted') {
            return false;
        }

        $application = DB::table('job_applications')
            ->where('application_id', $offer->application_id)->first();

        if (! $application) {
            return false;
        }

        DB::transaction(function () use ($application, $offer) {
            DB::table('users')->where('user_id', $application->user_id)
                ->update(['role' => 'employee', 'updated_at' => now()]);

            $fields = [
                'job_title'     => $offer->job_title,
                // The day they agreed to start, not the day they said yes.
                'hire_date'     => $offer->starts_on ?: now()->toDateString(),
                'salary'        => (float) $offer->basic_salary,
                'allowance'     => (float) $offer->allowance,
                'daily_rate'    => (float) $offer->daily_rate,
                'pay_basis'     => $offer->pay_basis,
                'department_id' => $offer->department_id,
                'status'        => 'active',
                'updated_at'    => now(),
            ];

            $exists = DB::table('employees')->where('user_id', $application->user_id)->exists();

            if ($exists) {
                DB::table('employees')->where('user_id', $application->user_id)->update($fields);
            } else {
                DB::table('employees')->insert($fields + [
                    'user_id'    => $application->user_id,
                    'created_at' => now(),
                ]);
            }

            // job_applications.notes is shown to the candidate on their own
            // page, so the bookkeeping that used to be appended to it -
            // "employee record updated" and the rest - is not written there.
            // The offer row already holds the terms and the date they were
            // accepted, which is what anybody asks for later.
            DB::table('job_applications')->where('application_id', $offer->application_id)
                ->update(['status' => 'hired', 'updated_at' => now()]);
        });

        return true;
    }
}
