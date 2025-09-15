
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('content');
            $table->integer('video_duration')->nullable()->comment('Duration in seconds');
            $table->bigInteger('video_size')->nullable()->comment('File size in bytes');
            $table->json('video_metadata')->nullable();
            $table->enum('video_status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->boolean('is_video_protected')->default(true);
        });
    }

    public function down()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'video_path',
                'video_duration', 
                'video_size',
                'video_metadata',
                'video_status',
                'is_video_protected'
            ]);
        });
    }
};
