<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accumulators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bet_id');
            $table->unsignedBigInteger('match_id');
            $table->enum('selected_outcome',['W1','W2','Over','Under']);
            $table->enum('status',['Accepted','Win','Lose','Refund',])->default('Accepted');
            $table->timestamps();
        });
        Schema::table('accumulators', function (Blueprint $table) {
            $table->foreign('bet_id')->references('id')->on('bets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accumulators');
    }
};
