<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Resolve any existing duplicates before adding unique index
        $duplicateNames = DB::table('companies')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $companies = DB::table('companies')->where('name', $name)->orderBy('id')->get();

            // Keep the first company, resolve duplicates for the rest
            foreach ($companies->skip(1) as $index => $dup) {
                $hasInvoices = DB::table('invoices')->where('company_id', $dup->id)->exists();
                $hasClients  = DB::table('clients')->where('company_id', $dup->id)->exists();

                if (! $hasInvoices && ! $hasClients) {
                    DB::table('users')->where('company_id', $dup->id)->delete();
                    DB::table('companies')->where('id', $dup->id)->delete();
                } else {
                    DB::table('companies')->where('id', $dup->id)->update([
                        'name' => $dup->name . ' (' . ($index + 2) . ')',
                    ]);
                }
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};

