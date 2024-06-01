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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('league_id');
            $table->string('home_match');
            $table->string('away_match');
            $table->dateTime('match_time');
            $table->enum('special_odd_team',['H','A']);
            // Special odds columns
            $table->enum('special_odd_first_digit', ['0', '1','2','3','4','5','6','7','8','9','10'])->nullable();
            $table->enum('special_odd_sign', ['-', '+'])->nullable(); 
            $table->integer('special_odd_last_digit')->nullable(); 
            // Over/under odds columns
            $table->enum('over_under_first_digit', ['1','2','3','4','5','6','7','8','9','10'])->nullable(); 
            $table->enum('over_under_sign', ['-', '+'])->nullable(); 
            $table->integer('over_under_last_digit')->nullable();
            // Match result columns
            $table->integer('home_goals')->nullable(); 
            $table->integer('away_goals')->nullable(); 
            // Additional columns
            $table->enum('status', ['pending', 'completed','postpone'])->default('pending');
            $table->timestamps();
        });
        
        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('league_id')->references('id')->on('leagues')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('matches');
    }
};
