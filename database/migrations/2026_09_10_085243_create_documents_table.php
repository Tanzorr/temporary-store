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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('disk');
            $table->string('relative_path');
            $table->string('status', 20)->default('available');
            $table->timestamp('uploaded_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
