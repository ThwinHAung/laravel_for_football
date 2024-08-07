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
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('match_id')->nullable();
            $table->enum('bet_type',['single','accumulator']);
            $table->enum('selected_outcome',['W1','W2','Over','Under'])->nullable();
            $table->decimal('amount',9,2);
            $table->enum('status',['Accepted','Win','Lose','Refund',])->default('Accepted');
            $table->decimal('wining_amount')->default(0.0);
            $table->timestamps();
        });
        Schema::table('bets', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('match_id')->references('id')->on('matches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bets');
    }
};
