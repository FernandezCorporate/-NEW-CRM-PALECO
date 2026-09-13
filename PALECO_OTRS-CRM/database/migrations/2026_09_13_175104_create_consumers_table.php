<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Core API Identifiers
            $table->string('acct_no')->unique(); 
            $table->string('acct_code')->unique(); // Indexed by unique(); used for fast API lookups
            
            // Cached Consumer Data
            $table->string('name');
            $table->string('address');
            $table->string('status')->nullable();
            $table->string('meter_serial')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumers');
    }
};