<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the inherited role list with the ones this company actually has.
 *
 * The old set was ['admin', 'manager', 'employee', 'customer', 'supplier'].
 * There is no manager here - the tiers are supervisor and leader - and
 * customer and supplier came across with the ERP code and have no meaning in
 * an HR system.
 *
 * 'hr' is added because the role-change screen already offered it while the
 * column would not accept it: choosing HR either threw or silently wrote an
 * empty role depending on the server's strict mode.
 */
return new class extends Migration
{
    private const NEW_ROLES = ['admin', 'hr', 'supervisor', 'leader', 'employee'];
    private const OLD_ROLES = ['admin', 'manager', 'employee', 'customer', 'supplier'];

    public function up(): void
    {
        // Anyone sitting on a role that is going away becomes an employee,
        // which grants the least. Nobody is silently left with more access
        // than the new list allows.
        DB::table('users')->whereNotIn('role', self::NEW_ROLES)->update(['role' => 'employee']);

        $this->setEnum(self::NEW_ROLES, 'employee');
    }

    public function down(): void
    {
        DB::table('users')->whereNotIn('role', self::OLD_ROLES)->update(['role' => 'employee']);

        $this->setEnum(self::OLD_ROLES, 'employee');
    }

    /**
     * Laravel cannot alter an enum's members portably, so this is raw SQL.
     * MySQL and MariaDB are the supported engines - see the README.
     */
    private function setEnum(array $roles, string $default): void
    {
        $list = implode(', ', array_map(fn ($role) => "'".$role."'", $roles));

        DB::statement(
            "ALTER TABLE `users` MODIFY `role` ENUM({$list}) NOT NULL DEFAULT '{$default}'"
        );
    }
};
