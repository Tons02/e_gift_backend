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
        Schema::create('claim_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference_no');

            $table->foreignId('business_type_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('claimed_by')->constrained('users');

            $table->date('claim_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_vouchers');
    }
};
