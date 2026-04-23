<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pin')->where('unit', 'ADC')->update(['unit' => 'adc']);
        DB::table('pin')->where('unit', '%')->update(['unit' => 'percent']);
        DB::table('pin')->where('unit', '°C')->update(['unit' => 'celsius']);
        DB::table('pin')->whereRaw("HEX(unit) = 'C2B043'")->update(['unit' => 'celsius']);
        DB::table('pin')->where('unit', '°F')->update(['unit' => 'fahrenheit']);
        DB::table('pin')->whereRaw("HEX(unit) = 'C2B046'")->update(['unit' => 'fahrenheit']);
    }

    public function down(): void
    {
        DB::table('pin')->where('unit', 'adc')->update(['unit' => 'ADC']);
        DB::table('pin')->where('unit', 'percent')->update(['unit' => '%']);
        DB::table('pin')->where('unit', 'celsius')->update(['unit' => '°C']);
        DB::table('pin')->where('unit', 'fahrenheit')->update(['unit' => '°F']);
    }
};

