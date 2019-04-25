<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTableCookis extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cookie', function (Blueprint $table) {
            $table->increments('id');
            $table->string('md5')->default('用户 设备唯一标识 md5(HTTP_USER_AGENT.REMOTE_ADDR )');
            $table->string('host_id')->default('');
            $table->string('http_user_agent')->default('');
            $table->string('http_accept_language')->default('');
            $table->string('http_cookie')->default('');
            $table->string('http_referer')->default('');
            $table->string('query_info')->default('搜索内容');
            $table->string('http_accept_encoding')->default('');
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
        //
    }
}
