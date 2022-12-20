<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            //

            $table->string('name')->nullable();
            $table->text('image')->nullable();
            $table->string('type')->nullable();
            $table->boolean('pin')->default(0);
            $table->boolean('mute')->default(0);
            $table->timestamp('last_time_message')->nullable();
            $table->bigInteger('admin_id')->unsigned()->nullable();
            $table->foreign('admin_id')->references('id')->on('users');
            $table->bigInteger('sender_id')->unsigned()->nullable();
            $table->foreign('sender_id')->references('id')->on('users');
            $table->bigInteger('receiver_id')->unsigned()->nullable();
            $table->foreign('receiver_id')->references('id')->on('users');
            $table->string('company_NO')->nullable();

            //

//            $table->unsignedBigInteger('sender_id');
//            $table->unsignedBigInteger('receiver_id');
//            $table->foreign('sender_id')->references('id')->on('users');
//            $table->foreign('receiver_id')->references('id')->on('users');
//            $table->timestamp('last_time_message');
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
        Schema::dropIfExists('conversations');
    }
}
