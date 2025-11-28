<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUspsLabelsTable extends Migration
{
    public function up()
    {
        Schema::create('usps_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('tracking_number', 34)->nullable();
            $table->string('label_image_path')->nullable();
            $table->json('label_metadata')->nullable();
            $table->decimal('postage_amount', 10, 2)->default(0);
            $table->string('mail_class', 50);
            $table->string('rate_indicator', 10)->nullable();
            $table->string('processing_category', 20);
            $table->enum('status', ['active', 'canceled', 'refund_pending', 'refunded', 'failed'])->default('active');
            $table->enum('service_type', ['outbound', 'return'])->default('outbound');
            $table->unsignedInteger('reprint_count')->default(0);
            $table->json('response_data')->nullable();
            $table->json('request_data')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('usps_transaction_id')->nullable();
            $table->string('dispute_id')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['order_id', 'status']);
            $table->index(['tracking_number']);
            $table->index(['status', 'created_at']);
            $table->index(['mail_class', 'created_at']);
            $table->index(['usps_transaction_id']);
            $table->index(['created_at']); // For time-based queries
            $table->index(['service_type', 'status']);

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create audit log table
        Schema::create('usps_label_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usps_label_id');
            $table->string('action'); // created, canceled, reprinted, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['usps_label_id', 'action']);
            $table->index(['action', 'created_at']);
            $table->foreign('usps_label_id')->references('id')->on('usps_labels')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create API log table for monitoring
        Schema::create('usps_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('status_code')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->float('response_time')->default(0);
            $table->string('correlation_id')->nullable();
            $table->unsignedBigInteger('usps_label_id')->nullable();
            $table->timestamps();

            $table->index(['endpoint', 'status_code']);
            $table->index(['correlation_id']);
            $table->index(['created_at']);
            $table->index(['usps_label_id']);
            $table->foreign('usps_label_id')->references('id')->on('usps_labels')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('usps_api_logs');
        Schema::dropIfExists('usps_label_audits');
        Schema::dropIfExists('usps_labels');
    }
}