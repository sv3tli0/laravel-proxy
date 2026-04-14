<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tracking_id')->unique();
            $table->string('service')->index();
            $table->string('method', 10);
            $table->string('url');
            $table->string('status')->index();
            $table->json('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->json('callback_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_requests');
    }
};
