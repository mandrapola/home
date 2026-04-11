<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE pin SET digital_style = 'sensor' WHERE digital_style IS NULL OR digital_style <> 'power'");
        DB::statement("ALTER TABLE pin MODIFY digital_style VARCHAR(32) NOT NULL DEFAULT 'sensor'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pin MODIFY digital_style VARCHAR(32) NOT NULL DEFAULT 'power'");
    }
};

