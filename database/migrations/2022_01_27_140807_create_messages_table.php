<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            //
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users');
            $table->bigInteger('conversations_id')->unsigned();
            $table->foreign('conversations_id')->references('id')->on('conversations');
            $table->text('message')->nullable();;
            $table->string('emoji')->nullable();
            $table->boolean('star')->default(0);
            $table->boolean('read')->default(0);
            $table->boolean('pin')->default(0);
            $table->boolean('is_image')->default(0);
            $table->boolean('is_file')->default(0);
            $table->boolean('is_voice')->default(0);
            $table->boolean('is_poll')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
                $table->foreign('parent_id')
                ->references('id')
                ->on('messages')
                ->onDelete('cascade');
            //

//            $table->unsignedBigInteger('sender_id');
//            $table->unsignedBigInteger('receiver_id');
//            $table->foreign('sender_id')->references('id')->on('users');
//            $table->foreign('receiver_id')->references('id')->on('users');
//            $table->text('body')->nullable();
//            $table->boolean('read')->default(0);
//            $table->string('type')->nullable();
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
        Schema::dropIfExists('messages');
    }
}
