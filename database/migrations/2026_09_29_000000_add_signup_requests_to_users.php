<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People can ask for an account from the sign-in page. The request waits
     * (inactive, no access) until a partner or the administrator approves it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signup_status')->nullable()->after('active');
            $table->string('signup_as')->nullable()->after('signup_status');
            $table->string('signup_organisation')->nullable()->after('signup_as');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signup_status', 'signup_as', 'signup_organisation']);
        });
    }
};
