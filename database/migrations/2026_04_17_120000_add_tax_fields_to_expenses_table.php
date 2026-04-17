<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('value', 12, 2)->nullable()->after('description');
            $table->foreignId('tax_id')->nullable()->after('amount')->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_id');
        });

        DB::table('expenses')->update([
            'value' => DB::raw('amount'),
            'tax_amount' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn(['value', 'tax_amount']);
        });
    }
};
