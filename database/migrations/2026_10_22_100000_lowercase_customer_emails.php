<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')->update(['email' => DB::raw('lower(email)')]);
    }

    public function down(): void
    {
        //
    }
};
