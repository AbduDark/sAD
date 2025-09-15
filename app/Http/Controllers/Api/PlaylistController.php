
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Course;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlaylistController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get course playlist
     */
    public function getCoursePlaylist($courseId, Request $request)
    {
        try {
            $user = auth()->user();
            $course = Course::findOrFail($courseId);

            // التحقق من الوصول للكورس
            if (!$course->is_active) {
                return $this->errorResponse('هذه الدورة غير متاحة حالياً', 403);
            }

            // التحقق من توافق الجنس
            if ($course->target_gender !== 'both' && $course->target_gender !== $user->gender) {
                return $this->errorResponse('هذه الدورة غير متاحة لجنسك', 403);
            }

            $isSubscribed = $user->isSubscribedTo($courseId);
            $isAdmin = $user->isAdmin();

            // جلب الدروس حسب الصلاحيات
            $lessonsQuery = $course->lessons()
                ->where(function($query) use ($user) {
                    $query->where('target_gender', 'both')
                          ->orWhere('target_gender', $user->gender);
                })
                ->orderBy('order', 'asc');

            if (!$isAdmin && !$isSubscribed) {
                $lessonsQuery->where('is_free', true);
            }

            $lessons = $lessonsQuery->get();

            // تحضير البلايليست
            $playlist = [
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'instructor_name' => $course->instructor_name,
                    'total_lessons' => $lessons->count(),
                    'total_duration' => $this->calculateTotalDuration($lessons),
                ],
                'lessons' => $lessons->map(function($lesson) use ($isSubscribed, $isAdmin) {
                    return $this->formatLessonForPlaylist($lesson, $isSubscribed || $isAdmin);
                }),
                'user_access' => [
                    'is_subscribed' => $isSubscribed,
                    'is_admin' => $isAdmin,
                    'can_access_all' => $isSubscribed || $isAdmin,
                ]
            ];

            return $this->successResponse($playlist, 'تم جلب البلايليست بنجاح');

        } catch (\Exception $e) {
            Log::error('Get playlist error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get next lesson in playlist
     */
    public function getNextLesson($courseId, $currentLessonId)
    {
        try {
            $user = auth()->user();
            $course = Course::findOrFail($courseId);
            $currentLesson = Lesson::where('course_id', $courseId)
                                 ->where('id', $currentLessonId)
                                 ->firstOrFail();

            $isSubscribed = $user->isSubscribedTo($courseId);
            $isAdmin = $user->isAdmin();

            // جلب الدرس التالي
            $nextLessonQuery = $course->lessons()
                ->where('order', '>', $currentLesson->order)
                ->where(function($query) use ($user) {
                    $query->where('target_gender', 'both')
                          ->orWhere('target_gender', $user->gender);
                })
                ->orderBy('order', 'asc');

            if (!$isAdmin && !$isSubscribed) {
                $nextLessonQuery->where('is_free', true);
            }

            $nextLesson = $nextLessonQuery->first();

            if (!$nextLesson) {
                return $this->successResponse(null, 'لا يوجد درس تالي');
            }

            $lessonData = $this->formatLessonForPlaylist($nextLesson, $isSubscribed || $isAdmin);

            return $this->successResponse($lessonData, 'تم جلب الدرس التالي بنجاح');

        } catch (\Exception $e) {
            Log::error('Get next lesson error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get previous lesson in playlist
     */
    public function getPreviousLesson($courseId, $currentLessonId)
    {
        try {
            $user = auth()->user();
            $course = Course::findOrFail($courseId);
            $currentLesson = Lesson::where('course_id', $courseId)
                                 ->where('id', $currentLessonId)
                                 ->firstOrFail();

            $isSubscribed = $user->isSubscribedTo($courseId);
            $isAdmin = $user->isAdmin();

            // جلب الدرس السابق
            $prevLessonQuery = $course->lessons()
                ->where('order', '<', $currentLesson->order)
                ->where(function($query) use ($user) {
                    $query->where('target_gender', 'both')
                          ->orWhere('target_gender', $user->gender);
                })
                ->orderBy('order', 'desc');

            if (!$isAdmin && !$isSubscribed) {
                $prevLessonQuery->where('is_free', true);
            }

            $prevLesson = $prevLessonQuery->first();

            if (!$prevLesson) {
                return $this->successResponse(null, 'لا يوجد درس سابق');
            }

            $lessonData = $this->formatLessonForPlaylist($prevLesson, $isSubscribed || $isAdmin);

            return $this->successResponse($lessonData, 'تم جلب الدرس السابق بنجاح');

        } catch (\Exception $e) {
            Log::error('Get previous lesson error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get lesson position in playlist
     */
    public function getLessonPosition($courseId, $lessonId)
    {
        try {
            $user = auth()->user();
            $course = Course::findOrFail($courseId);
            $lesson = Lesson::where('course_id', $courseId)
                          ->where('id', $lessonId)
                          ->firstOrFail();

            $isSubscribed = $user->isSubscribedTo($courseId);
            $isAdmin = $user->isAdmin();

            // عد الدروس السابقة
            $previousLessonsQuery = $course->lessons()
                ->where('order', '<', $lesson->order)
                ->where(function($query) use ($user) {
                    $query->where('target_gender', 'both')
                          ->orWhere('target_gender', $user->gender);
                });

            if (!$isAdmin && !$isSubscribed) {
                $previousLessonsQuery->where('is_free', true);
            }

            $previousCount = $previousLessonsQuery->count();

            // عد إجمالي الدروس المتاحة
            $totalLessonsQuery = $course->lessons()
                ->where(function($query) use ($user) {
                    $query->where('target_gender', 'both')
                          ->orWhere('target_gender', $user->gender);
                });

            if (!$isAdmin && !$isSubscribed) {
                $totalLessonsQuery->where('is_free', true);
            }

            $totalCount = $totalLessonsQuery->count();

            $position = [
                'current_position' => $previousCount + 1,
                'total_lessons' => $totalCount,
                'lesson' => $this->formatLessonForPlaylist($lesson, $isSubscribed || $isAdmin),
                'progress_percentage' => $totalCount > 0 ? round((($previousCount + 1) / $totalCount) * 100, 2) : 0,
            ];

            return $this->successResponse($position, 'تم جلب موقع الدرس بنجاح');

        } catch (\Exception $e) {
            Log::error('Get lesson position error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Format lesson for playlist
     */
    private function formatLessonForPlaylist(Lesson $lesson, bool $canAccess): array
    {
        $lessonData = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'order' => $lesson->order,
            'duration_minutes' => $lesson->duration_minutes,
            'is_free' => $lesson->is_free,
            'can_access' => $canAccess,
            'has_video' => $lesson->hasVideo(),
        ];

        if ($canAccess && $lesson->hasVideo()) {
            $lessonData['video'] = [
                'stream_url' => $lesson->getVideoStreamUrl(),
                'duration' => $lesson->video_duration,
                'duration_formatted' => $lesson->getFormattedDuration(),
                'size_formatted' => $lesson->getFormattedSize(),
                'status' => $lesson->video_status,
                'is_protected' => $lesson->is_video_protected,
            ];
        }

        return $lessonData;
    }

    /**
     * Calculate total duration for all lessons
     */
    private function calculateTotalDuration($lessons): array
    {
        $totalMinutes = $lessons->sum('duration_minutes') ?? 0;
        $totalVideoSeconds = $lessons->whereNotNull('video_duration')->sum('video_duration') ?? 0;

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        $videoHours = floor($totalVideoSeconds / 3600);
        $videoMinutes = floor(($totalVideoSeconds % 3600) / 60);

        return [
            'estimated_minutes' => $totalMinutes,
            'estimated_formatted' => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
            'video_duration_seconds' => $totalVideoSeconds,
            'video_duration_formatted' => $videoHours > 0 ? 
                sprintf('%02d:%02d:%02d', $videoHours, $videoMinutes, $totalVideoSeconds % 60) :
                sprintf('%02d:%02d', $videoMinutes, $totalVideoSeconds % 60),
        ];
    }
}
