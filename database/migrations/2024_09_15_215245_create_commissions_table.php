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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bet_id'); 
            $table->decimal('user', 11, 2)->default(0); 
            $table->decimal('agent', 11, 2)->default(0); ; 
            $table->decimal('master', 11, 2)->default(0); ; 
            $table->decimal('senior', 11, 2)->default(0); ; 
            $table->decimal('ssenior', 11, 2)->default(0); ; 
            $table->timestamps(); 
        });
        Schema::table('commissions',function (Blueprint $table){
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
        Schema::dropIfExists('commissions');
    }
};
