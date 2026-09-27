<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Names, in the two places people are created.
 *
 * A single "full name" box could not be sorted by surname, could not fill a
 * government form that asks for a middle name, and produced a different
 * username at each end - the register slugged the whole name into
 * juan.dela.cruz while the import made juan.delacruz, so a candidate who was
 * later hired gained a second login.
 */
class PersonNameTest extends TestCase
{
    private array $made = [];

    protected function tearDown(): void
    {
        DB::table('users')->whereIn('user_id', $this->made)->delete();

        parent::tearDown();
    }

    public function test_the_parts_make_the_whole(): void
    {
        $this->assertSame('Juan Santos Dela Cruz', PersonName::full('Juan', 'Santos', 'Dela Cruz'));
        $this->assertSame('Juan Dela Cruz', PersonName::full('Juan', '', 'Dela Cruz'));
        $this->assertSame('Juan Dela Cruz', PersonName::full(' Juan ', '   ', ' Dela Cruz '),
            'stray spacing was not tidied');
    }

    public static function casing(): array
    {
        return [
            'shouting'   => ['GIAN KARLO', 'Gian Karlo'],
            'suffix'     => ['MOISES REBATO III', 'Moises Rebato III'],
            'suffix dot' => ['PEDRO SANTOS JR.', 'Pedro Santos JR.'],
            'enye'       => ['PAULINE ACUÑA', 'Pauline Acuña'],
            'already ok' => ['Maria Santos', 'Maria Santos'],
        ];
    }

    #[DataProvider('casing')]
    public function test_a_shouted_name_is_tidied_without_mangling_suffixes(string $given, string $expected): void
    {
        $this->assertSame($expected, PersonName::tidy($given));
    }

    public function test_the_username_is_first_dot_last(): void
    {
        $this->assertSame('juan.delacruz', PersonName::username('Juan', 'Dela Cruz'));

        // An accent cannot be typed on every keyboard at the gate.
        $this->assertSame('pauline.acuna', PersonName::username('Pauline', 'Acuña'));

        // The middle name is deliberately left out.
        $this->assertStringNotContainsString('santos', PersonName::username('Juan', 'Dela Cruz'));
    }

    public function test_a_taken_username_gets_a_number_rather_than_colliding(): void
    {
        $u = User::create([
            'full_name' => 'Juan Dela Cruz', 'username' => 'juan.delacruz',
            'email' => 'jdc'.random_int(100000, 999999).'@example.test',
            'password' => 'x', 'role' => 'employee',
        ]);
        $this->made[] = $u->user_id;

        $this->assertSame('juan.delacruz2', PersonName::username('Juan', 'Dela Cruz'));

        // Unless it is that same person being looked at again.
        $this->assertSame('juan.delacruz', PersonName::username('Juan', 'Dela Cruz', $u->user_id));
    }

    public static function splits(): array
    {
        return [
            'two words'    => ['Raise Person', 'Raise', '', 'Person'],
            'three words'  => ['Juan Santos Cruz', 'Juan', 'Santos', 'Cruz'],
            'suffix'       => ['Moises John Montes Rebato III', 'Moises', 'John Montes', 'Rebato III'],
            'single word'  => ['Madonna', 'Madonna', '', ''],
            'empty'        => ['', '', '', ''],
        ];
    }

    /**
     * Records made before the name had parts are split on the last word so
     * they can be opened and corrected. It is a guess, and the alternative was
     * refusing to save anybody who had never been edited.
     */
    #[DataProvider('splits')]
    public function test_a_whole_name_is_split_so_old_records_stay_editable(
        string $full, string $first, string $middle, string $last): void
    {
        $parts = PersonName::split($full);

        $this->assertSame($first, $parts['first']);
        $this->assertSame($middle, $parts['middle']);
        $this->assertSame($last, $parts['last'], 'the surname was guessed wrong');
    }

    /** The imported staff keep the login they were given. */
    public function test_the_import_and_the_register_form_agree(): void
    {
        // Any account the masterlist created - they all carry their name in
        // parts. Not a named person: the one this used to check was merged
        // into the admin account and stopped existing.
        $imported = DB::table('users as u')
            ->join('employees as e', 'e.user_id', '=', 'u.user_id')
            ->where('e.employee_no', 'like', 'IC-%')
            ->where('u.username', 'like', '%.%')
            ->whereNotNull('u.first_name')->whereNotNull('u.last_name')
            ->select('u.*')
            ->first();

        if (! $imported) {
            $this->markTestSkipped('the masterlist has not been imported');
        }

        $this->assertSame(
            $imported->username,
            PersonName::username($imported->first_name, $imported->last_name, $imported->user_id),
            'registering would have produced a different login from the import',
        );
    }
}
