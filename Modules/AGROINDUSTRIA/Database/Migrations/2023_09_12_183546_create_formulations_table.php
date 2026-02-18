<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulations', function (Blueprint $table) {

            $table->bigIncrements('id');


            $table->unsignedBigInteger('element_id');
            $table->unsignedBigInteger('person_id');
            $table->unsignedBigInteger('productive_unit_id');

            $table->text('proccess'); 
            $table->integer('amount');
            $table->date('date');

            $table->date('produced_expiration_date')->nullable();

            $table->string('produced_lot_number', 255)
                  ->nullable(); 

            $table->string('produced_inventory_code', 255)
                  ->nullable();

            $table->string('produced_mark', 255)
                  ->nullable();

            $table->string('produced_destination', 50)
                  ->nullable(); 


            $table->timestamps();
            $table->softDeletes();

            $table->index('element_id');
            $table->index('person_id');
            $table->index('productive_unit_id');

            
            $table->foreign('element_id')
                  ->references('id')
                  ->on('elements')
                  ->onDelete('restrict');

            $table->foreign('person_id')
                  ->references('id')
                  ->on('people')
                  ->onDelete('restrict');

            $table->foreign('productive_unit_id')
                  ->references('id')
                  ->on('productive_units')
                  ->onDelete('restrict');
    
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulations');
    }
};
