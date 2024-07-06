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
        Schema::create('mix_bet_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('m2');
            $table->tinyInteger('m3');
            $table->tinyInteger('m4');
            $table->tinyInteger('m5');
            $table->tinyInteger('m6');
            $table->tinyInteger('m8');
            $table->tinyInteger('m9');
            $table->tinyInteger('m10');
            $table->tinyInteger('m11');
            $table->timestamps();
        });
        Schema::table('mix_bet_commissions',function (Blueprint $table){
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mix_bet_commissions');
    }
};
