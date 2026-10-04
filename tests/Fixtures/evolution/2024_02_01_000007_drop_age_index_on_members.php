<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('users_age_index');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->index('age', 'users_age_index');
        });
    }
};
