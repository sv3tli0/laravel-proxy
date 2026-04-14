<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('request_id')->index();
            $table->string('tracking_id')->nullable()->index();
            $table->string('group')->index();
            $table->string('level');
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->string('upstream_service')->nullable();
            $table->string('upstream_url')->nullable();
            $table->unsignedInteger('upstream_duration_ms')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->json('token_payload')->nullable();
            $table->json('tags')->nullable();
            $table->json('pipeline_trace')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_logs');
    }
};
