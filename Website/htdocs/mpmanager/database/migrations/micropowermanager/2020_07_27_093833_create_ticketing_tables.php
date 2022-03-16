<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;


/**
 * Created by PhpStorm.
 * User: kemal
 * Date: 23.08.18
 * Time: 10:39
 */
return new class extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableNames = config('tickets.table_names');

        Schema::connection('micropowermanager')->create($tableNames['board'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('board_id');
            $table->string('board_name');
            $table->string('web_hook_id');
            $table->boolean('active');
            $table->timestamps();
        });

        Schema::connection('micropowermanager')->create($tableNames['card'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('card_id');
            $table->integer('status');
            $table->timestamps();
        });

        Schema::connection('micropowermanager')->create($tableNames['ticket'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('ticket_id');
            $table->morphs('creator');
            $table->integer('assigned_id')->nullable();
            $table->morphs('owner');
            $table->integer('status');
            $table->integer('category_id');
            $table->timestamps();
        });

        Schema::connection('micropowermanager')->create($tableNames['user'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_name');
            $table->string('user_tag');
            $table->integer('out_source');
            $table->string('extern_id');
            $table->timestamps();
        });

        Schema::connection('micropowermanager')->create($tableNames['ticket_categories'], function (Blueprint $table) {
            $table->increments('id');
            $table->string('label_name');
            $table->string('label_color');
            $table->boolean('out_source');
            $table->timestamps();
        });
        Schema::connection('micropowermanager')->create($tableNames['board_categories'], function (Blueprint $table) {
            $table->increments('id');
            $table->integer('category_id');
            $table->integer('board_id');
            $table->string('extern_category_id');
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
        $tableNames = config('ticket.table_names');
        Schema::connection('micropowermanager')->drop($tableNames['board']);
        Schema::connection('micropowermanager')->drop($tableNames['card']);
        Schema::connection('micropowermanager')->drop($tableNames['ticket']);
        Schema::connection('micropowermanager')->drop($tableNames['user']);
        Schema::connection('micropowermanager')->drop($tableNames['ticket_categories']);
        Schema::connection('micropowermanager')->drop($tableNames['board_categories']);
    }

};
