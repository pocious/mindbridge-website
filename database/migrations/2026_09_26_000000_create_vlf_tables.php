<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables backing the VLF (Virtual Law Firm) prototype at /vlf-fixed.html.
     */
    public function up(): void
    {
        Schema::create('vlf_matters', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->unique();
            $table->string('title');
            $table->string('court')->nullable();
            $table->string('judge')->nullable();
            $table->string('advocate')->nullable();
            $table->string('stage')->nullable();
            $table->string('risk_level')->nullable();
            $table->text('risk_note')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('matter_ref');
            $table->string('matter_title')->nullable();
            $table->char('class', 1)->default('B');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->nullable();
            $table->string('assigned_to');
            $table->string('assigned_by')->nullable();
            $table->string('deadline')->nullable();
            $table->string('status')->default('PENDING');
            $table->string('blocked_by')->nullable();
            $table->string('estimated_time')->nullable();
            $table->boolean('billable')->default(true);
            $table->string('related_doc')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_time_entries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('matter_ref');
            $table->string('task_code')->nullable();
            $table->text('description');
            $table->string('advocate');
            $table->string('date_label')->nullable();
            $table->string('duration');
            $table->unsignedInteger('duration_mins');
            $table->boolean('billable')->default(true);
            $table->unsignedBigInteger('rate')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();
        });

        Schema::create('vlf_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('matter_ref');
            $table->string('client');
            $table->string('status');
            $table->string('issue_date')->nullable();
            $table->string('due_date')->nullable();
            $table->string('paid_date')->nullable();
            $table->json('lines');
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid')->default(0);
            $table->timestamps();
        });

        Schema::create('vlf_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->index();
            $table->string('author_av', 8);
            $table->string('author_name')->nullable();
            $table->text('text');
            $table->boolean('mine')->default(false);
            $table->string('ts_label')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_comments', function (Blueprint $table) {
            $table->id();
            $table->string('matter_ref')->index();
            $table->string('author');
            $table->string('author_av', 8);
            $table->string('context')->nullable();
            $table->text('text');
            $table->json('replies')->nullable();
            $table->string('ts_label')->nullable();
            $table->timestamps();
        });

        Schema::create('vlf_documents', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'comments', 'messages', 'invoices', 'time_entries', 'tasks', 'matters'] as $name) {
            Schema::dropIfExists('vlf_'.$name);
        }
    }
};
