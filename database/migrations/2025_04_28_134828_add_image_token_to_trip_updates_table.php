<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageTokenToTripUpdatesTable extends Migration
{
    public function up()
    {
        Schema::table('trip_updates', function (Blueprint $table) {
            $table->string('image_token')->nullable()->unique()->after('image_url');
        });
    }

    public function down()
    {
        Schema::table('trip_updates', function (Blueprint $table) {
            $table->dropColumn('image_token');
        });
    }
}
