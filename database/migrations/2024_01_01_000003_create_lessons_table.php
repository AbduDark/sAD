<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content');
            // رابط عام احتياطي (legacy) — لو مش محتاجه تقدر تحذفه لاحقاً
            $table->string('video_url')->nullable();
            // مسار الفيديو داخل storag(مثال: private_videos/lessons/12/source.mp4)
            $table->string('video_path')->nullable();
            // استهداف حسب الجنس
            $table->enum('target_gender', ['male', 'female', 'both'])->default('both');

            $table->integer('order')->default(0);
            $table->integer('duration_minutes')->default(0);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
