<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'content',
        'order',
        'duration_minutes',
        'is_free',
        'target_gender',
        'video_path',
        'video_duration', // Added for video duration
        'video_size',     // Added for video size
        'video_status',   // Added for video processing status
    ];


    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Check if lesson has video
     */
    public function hasVideo(): bool
    {
        return !empty($this->video_path) && $this->videoFileExists();
    }

    /**
     * Check if video file exists
     */
    public function videoFileExists(): bool
    {
        if (!$this->video_path) {
            return false;
        }

        return Storage::disk('private')->exists($this->video_path);
    }

    /**
     * Get video stream URL with security token
     */
    public function getVideoStreamUrl(): ?string
    {
        if (!$this->hasVideo()) {
            return null;
        }

        // Generate secure token for video streaming
        $token = hash_hmac('sha256', $this->id . '|' . auth()->id() . '|' . time(), config('app.key'));

        return route('lesson.video.stream', [
            'lesson' => $this->id,
            'token' => $token,
            't' => time()
        ]);
    }

    /**
     * Validate video access token
     */
    public function validateVideoToken(string $token, int $timestamp): bool
    {
        // Token expires after 1 hour
        if (time() - $timestamp > 3600) {
            return false;
        }

        $expectedToken = hash_hmac('sha256', $this->id . '|' . auth()->id() . '|' . $timestamp, config('app.key'));

        return hash_equals($expectedToken, $token);
    }

    /**
     * Get formatted video duration
     */
    public function getFormattedDuration(): ?string
    {
        if (!$this->video_duration) {
            return null;
        }

        $hours = floor($this->video_duration / 3600);
        $minutes = floor(($this->video_duration % 3600) / 60);
        $seconds = $this->video_duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get formatted video size
     */
    public function getFormattedSize(): ?string
    {
        if (!$this->video_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->video_size;

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get video status message
     */
    public function getVideoStatusMessage(): array
    {
        $messages = [
            'pending' => [
                'ar' => 'في انتظار المعالجة',
                'en' => 'Pending processing'
            ],
            'processing' => [
                'ar' => 'جاري المعالجة',
                'en' => 'Processing'
            ],
            'ready' => [
                'ar' => 'جاهز للعرض',
                'en' => 'Ready to play'
            ],
            'failed' => [
                'ar' => 'فشل في المعالجة',
                'en' => 'Processing failed'
            ]
        ];

        return $messages[$this->video_status] ?? $messages['pending'];
    }

    /**
     * Delete video file
     */
    public function deleteVideoFile(): bool
    {
        if ($this->video_path && Storage::disk('private')->exists($this->video_path)) {
            return Storage::disk('private')->delete($this->video_path);
        }

        return true;
    }
}