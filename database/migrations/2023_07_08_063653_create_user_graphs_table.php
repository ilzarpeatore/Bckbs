<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserGraphsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_graphs', function (Blueprint $table) {
            $table->id();
            $table->string('value')->nullable();
            $table->string('type')->nullable();
            $table->string('unit')->nullable();
            $table->date('date')->nullable();
            $table->foreignId('user_id')->cascade('delete');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_graphs');
    }
}
