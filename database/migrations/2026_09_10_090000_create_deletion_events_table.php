<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deletion_events', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            // Unique, not just indexed: enforces I-3 (exactly one DeletionEvent
            // per Document) at the data layer, not only in DeleteDocument.
            $table->foreignId('document_id')->unique()->constrained();
            $table->string('trigger', 20);
            $table->string('initiator', 20);
            $table->timestamp('occurred_at');
            // Correlation id for one RetentionSweep run, not a foreign key —
            // there is no retention_sweeps table (architecture.md). Null for
            // manual deletions.
            $table->string('sweep_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_events');
    }
};
