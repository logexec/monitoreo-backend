<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageTypeToTripUpdatesTable extends Migration
{
    public function up()
    {
        Schema::table('trip_updates', function (Blueprint $table) {
            $table->string('image_type')->nullable()->after('image_token');
        });
    }

    public function down()
    {
        Schema::table('trip_updates', function (Blueprint $table) {
            $table->dropColumn('image_type');
        });
    }
}
