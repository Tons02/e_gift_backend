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
        Schema::create('one_chargings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('sync_id')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->bigInteger('company_id');
            $table->string('company_code');
            $table->string('company_name');
            $table->bigInteger('business_unit_id');
            $table->string('business_unit_code');
            $table->string('business_unit_name');
            $table->bigInteger('department_id');
            $table->string('department_code');
            $table->string('department_name');
            $table->bigInteger('unit_id');
            $table->string('unit_code');
            $table->string('unit_name');
            $table->bigInteger('sub_unit_id');
            $table->string('sub_unit_code');
            $table->string('sub_unit_name');
            $table->bigInteger('location_id');
            $table->string('location_code');
            $table->string('location_name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_chargings');
    }
};
