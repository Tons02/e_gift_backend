<?php

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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->onDelete('cascade');
            $table->string('reference_number')->unique();
            $table->integer('amount');
            $table->morphs('customer');
            $table->enum('status', ['Available', "Claimed"])->default('Available');
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('claimed_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
