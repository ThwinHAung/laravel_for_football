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
            $table->decimal('m2',2,1);
            $table->decimal('m3',3,1);
            $table->decimal('m4',3,1);
            $table->decimal('m5',3,1);
            $table->decimal('m6',3,1);
            $table->decimal('m7',3,1);
            $table->decimal('m8',3,1);
            $table->decimal('m9',3,1);
            $table->decimal('m10',3,1);
            $table->decimal('m11',3,1);
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
