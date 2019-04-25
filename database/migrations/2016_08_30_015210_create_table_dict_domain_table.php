<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableDictDomainTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dict_domain', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain')->default('');
            $table->string('url')->default('');
            $table->string('title')->default('');
            $table->string('keywords')->default('');
            $table->text('description');
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
