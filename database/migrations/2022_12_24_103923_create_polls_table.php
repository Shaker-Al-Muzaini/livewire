<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('message_id')->unsigned();
            $table->foreign('message_id')->references('id')->on('messages');
//            $table->bigInteger('user_id')->unsigned();
//            $table->foreign('user_id')->references('id')->on('users');
            $table->text('poll_options')->nullable();
            $table->integer('rate')->default('0');
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
        Schema::dropIfExists('polls');
    }
}
