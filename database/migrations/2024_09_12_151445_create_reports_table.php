<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    //
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('user_id'); 
            $table->unsignedBigInteger('bet_id'); 
            $table->unsignedBigInteger('commissions_id');  
            $table->decimal('turnover', 11, 2)->default(0.0); 
            $table->decimal('valid_amount', 11, 2)->default(0.0); 
            $table->decimal('win_loss', 11, 2)->default(0.0);
            $table->enum('type',['Win','Los','Refund'])->default('Win');
            $table->timestamps(); 
        });
        Schema::table('reports',function (Blueprint $table){
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bet_id')->references('id')->on('bets')->onDelete('cascade');
            $table->foreign('commissions_id')->references('id')->on('commissions')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
};
