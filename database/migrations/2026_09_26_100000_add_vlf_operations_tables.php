<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clients, intake details, court diary, deadlines, staff, firm settings,
     * notifications, invoicing links and uploaded files for the VLF prototype.
     */
    public function up(): void
    {
        Schema::create('vlf_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin')->nullable();
            $table->string('type')->default('Company');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('vlf_matters', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('ref')->constrained('vlf_clients')->nullOnDelete();
            $table->string('opposing_party')->nullable()->after('title');
            $table->string('practice_area')->nullable();
            $table->text('description')->nullable();
            $table->string('supervisor')->nullable();
            $table->string('fee_arrangement')->nullable();
            $table->date('instruction_date')->nullable();
            $table->string('status_label')->nullable();
            $table->string('status_level')->nullable();
        });

        Schema::table('vlf_invoices', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('matter_ref')->constrained('vlf_clients')->nullOnDelete();
        });

        Schema::table('vlf_time_entries', function (Blueprint $table) {
            $table->string('invoice_code')->nullable()->after('task_code');
        });

        Schema::table('vlf_documents', function (Blueprint $table) {
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_sha256', 64)->nullable();
        });

        Schema::create('vlf_court_events', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('matter_ref');
            $table->string('title');
            $table->string('court')->nullable();
            $table->string('judge')->nullable();
            $table->date('date');
            $table->string('time')->nullable();
            $table->string('advocate')->nullable();
            $table->string('level')->default('scheduled');
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_deadlines', function (Blueprint $table) {
            $table->id();
            $table->string('matter_ref');
            $table->string('title');
            $table->date('due_date');
            $table->string('due_time')->nullable();
            $table->string('owner')->nullable();
            $table->string('severity')->default('normal');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_staff', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('initials', 4);
            $table->string('role');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('rate')->default(0);
            $table->string('status')->default('Available');
            $table->boolean('active')->default(true);
            $table->decimal('month_hours', 6, 1)->default(0);
            $table->timestamps();
        });

        Schema::create('vlf_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient')->index();
            $table->string('type')->default('info');
            $table->string('icon', 16)->nullable();
            $table->string('type_label');
            $table->text('text');
            $table->json('link')->nullable();
            $table->string('ts_label')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'settings', 'staff', 'deadlines', 'court_events'] as $name) {
            Schema::dropIfExists('vlf_'.$name);
        }

        Schema::table('vlf_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_mime', 'file_size', 'file_sha256']);
        });
        Schema::table('vlf_time_entries', function (Blueprint $table) {
            $table->dropColumn('invoice_code');
        });
        Schema::table('vlf_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::table('vlf_matters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['opposing_party', 'practice_area', 'description', 'supervisor', 'fee_arrangement', 'instruction_date', 'status_label', 'status_level']);
        });

        Schema::dropIfExists('vlf_clients');
    }
};
