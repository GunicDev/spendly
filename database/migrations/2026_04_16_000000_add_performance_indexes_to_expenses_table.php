<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'expenses_user_id_date_index');
            $table->index(['user_id', 'type', 'date'], 'expenses_user_id_type_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_user_id_date_index');
            $table->dropIndex('expenses_user_id_type_date_index');
        });
    }
};
