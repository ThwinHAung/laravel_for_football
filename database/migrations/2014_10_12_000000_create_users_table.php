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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('realname');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('phone_number');
            $table->decimal('balance',8,2)->default('0.0');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->decimal('maxSingleBet',8,2)->default(0);
            $table->decimal('maxMixBet',8,2)->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->string('status')->default('active');
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
