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
        Schema::create('transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('description');
            $table->enum('Type',['IN','OUT']);
            $table->decimal('amount',11,2);
            $table->decimal('IN',11,2);
            $table->decimal('OUT',11,2);
            $table->decimal('Bet',11,2);
            $table->decimal('Win',11,2);
            $table->decimal('commission',11,2);
            $table->decimal('balance',11,2);
            $table->timestamps();
        });
        Schema::table('transitions', function (Blueprint $table) {
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
        Schema::dropIfExists('transitions');
    }
};
