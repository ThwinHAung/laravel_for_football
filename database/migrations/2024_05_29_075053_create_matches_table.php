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
            $table->dateTime('MatchTime')->nullable();
            $table->string('League');
            $table->string('HomeTeam');
            $table->string('AwayTeam');
            $table->string('Hdp')->nullable();
            $table->integer('HdpGoal');
            $table->integer('HdpUnit');
            $table->string('Gp')->nullable();
            $table->integer('GpGoal');
            $table->integer('GpUnit');
            $table->boolean('HomeUp');
            $table->integer('HomeGoal')->default(0);
            $table->integer('AwayGoal')->default(0);
            $table->boolean('IsEnd')->default(False);
            $table->boolean('IsPost')->default(False);
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
        Schema::dropIfExists('matches');
    }
};
