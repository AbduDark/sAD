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
    ];


    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    
}
