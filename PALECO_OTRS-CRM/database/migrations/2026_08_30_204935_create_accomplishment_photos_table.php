<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accomplishment_photos', function (Blueprint $table) {
            $table->id();
            
            // Links to your existing ticket_accomplishments table
            $table->foreignId('accomplishment_id')
                  ->constrained('ticket_accomplishments')
                  ->cascadeOnDelete(); 
                  
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accomplishment_photos');
    }
};