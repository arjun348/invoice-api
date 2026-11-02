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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('status')->default('pending')->index()->after('name');
            $table->text('status_reason')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('status_reason');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
        });

        // Ensure any existing companies remain active so existing data continues to work
        DB::table('companies')->update(['status' => 'active']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('viewer')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'viewer'])->default('viewer')->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_reason', 'approved_at', 'approved_by']);
        });
    }
};

