<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real accounts for the VLF app: every person who signs in has a role, and
     * client users belong to a client record. Staff profiles link to their user.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('associate')->after('email');
            $table->foreignId('client_id')->nullable()->after('role')->constrained('vlf_clients')->nullOnDelete();
            $table->string('phone')->nullable()->after('client_id');
            $table->boolean('active')->default(true)->after('phone');
        });

        Schema::table('vlf_staff', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Who wrote a message or comment, so "mine" is worked out per viewer.
        Schema::table('vlf_messages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('channel')->constrained('users')->nullOnDelete();
        });
        Schema::table('vlf_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('matter_ref')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['vlf_comments', 'vlf_messages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('vlf_staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['role', 'phone', 'active']);
        });
    }
};
