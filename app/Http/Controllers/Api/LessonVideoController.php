
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use getID3;

class LessonVideoController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Upload video for lesson
     */
    public function upload(Request $request, $lessonId)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك برفع الفيديو', 403);
            }

            $lesson = Lesson::findOrFail($lessonId);

            $request->validate([
                'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:1048576', // 1GB max
            ]);

            $video = $request->file('video');
            
            // حذف الفيديو القديم إذا وجد
            if ($lesson->video_path) {
                $lesson->deleteVideoFile();
            }

            // إنشاء مجلد خاص للفيديوهات
            $directory = 'lessons/videos/' . $lesson->course_id;
            $fileName = 'lesson_' . $lesson->id . '_' . time() . '.' . $video->getClientOriginalExtension();
            $filePath = $directory . '/' . $fileName;

            // رفع الفيديو إلى التخزين المحمي
            $uploaded = Storage::disk('private')->putFileAs($directory, $video, $fileName);

            if (!$uploaded) {
                return $this->errorResponse('فشل في رفع الفيديو', 500);
            }

            // معالجة معلومات الفيديو
            $this->processVideoMetadata($lesson, $filePath);

            return $this->successResponse([
                'lesson_id' => $lesson->id,
                'video_path' => $filePath,
                'status' => $lesson->video_status,
                'message' => 'تم رفع الفيديو بنجاح'
            ], 'تم رفع الفيديو بنجاح');

        } catch (\Exception $e) {
            Log::error('Video upload error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Stream protected video
     */
    public function stream(Request $request, $lessonId)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                abort(401, 'Unauthorized');
            }

            $lesson = Lesson::findOrFail($lessonId);

            // التحقق من صحة التوكن
            $token = $request->get('token');
            $timestamp = $request->get('t');

            if (!$token || !$timestamp || !$lesson->validateVideoToken($token, $timestamp)) {
                abort(403, 'Invalid or expired token');
            }

            // التحقق من الوصول للدرس
            if (!$lesson->is_free && !$user->isAdmin()) {
                $isSubscribed = $user->isSubscribedTo($lesson->course_id);
                if (!$isSubscribed) {
                    abort(403, 'Subscription required');
                }
            }

            // التحقق من توافق الجنس
            if ($lesson->target_gender !== 'both' && $lesson->target_gender !== $user->gender) {
                abort(403, 'Content not available for your gender');
            }

            if (!$lesson->hasVideo()) {
                abort(404, 'Video not found');
            }

            $filePath = Storage::disk('private')->path($lesson->video_path);
            
            if (!file_exists($filePath)) {
                abort(404, 'Video file not found');
            }

            return $this->streamVideoFile($filePath, $request);

        } catch (\Exception $e) {
            Log::error('Video streaming error: ' . $e->getMessage());
            abort(500, 'Video streaming error');
        }
    }

    /**
     * Delete video
     */
    public function delete($lessonId)
    {
        try {
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بحذف الفيديو', 403);
            }

            $lesson = Lesson::findOrFail($lessonId);
            
            if ($lesson->deleteVideoFile()) {
                $lesson->update([
                    'video_path' => null,
                    'video_duration' => null,
                    'video_size' => null,
                    'video_metadata' => null,
                    'video_status' => 'pending'
                ]);

                return $this->successResponse([], 'تم حذف الفيديو بنجاح');
            }

            return $this->errorResponse('فشل في حذف الفيديو', 500);

        } catch (\Exception $e) {
            Log::error('Video deletion error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Process video metadata
     */
    private function processVideoMetadata(Lesson $lesson, string $filePath)
    {
        try {
            $fullPath = Storage::disk('private')->path($filePath);
            
            // استخدام getID3 لاستخراج معلومات الفيديو
            if (class_exists('getID3')) {
                $getID3 = new getID3;
                $fileInfo = $getID3->analyze($fullPath);
                
                $duration = isset($fileInfo['playtime_seconds']) ? (int) $fileInfo['playtime_seconds'] : null;
                $size = filesize($fullPath);
                
                $metadata = [
                    'width' => $fileInfo['video']['resolution_x'] ?? null,
                    'height' => $fileInfo['video']['resolution_y'] ?? null,
                    'bitrate' => $fileInfo['bitrate'] ?? null,
                    'format' => $fileInfo['fileformat'] ?? null,
                    'codec' => $fileInfo['video']['codec'] ?? null,
                ];
            } else {
                // بديل بسيط إذا لم يكن getID3 متاحاً
                $duration = null;
                $size = filesize($fullPath);
                $metadata = ['format' => 'unknown'];
            }

            $lesson->update([
                'video_path' => $filePath,
                'video_duration' => $duration,
                'video_size' => $size,
                'video_metadata' => $metadata,
                'video_status' => 'ready'
            ]);

        } catch (\Exception $e) {
            Log::error('Video metadata processing error: ' . $e->getMessage());
            
            $lesson->update([
                'video_path' => $filePath,
                'video_size' => file_exists($fullPath) ? filesize($fullPath) : null,
                'video_status' => 'ready'
            ]);
        }
    }

    /**
     * Stream video file with range support
     */
    private function streamVideoFile(string $filePath, Request $request)
    {
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'video/mp4';
        
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];

        // دعم Range requests للفيديو
        $range = $request->header('Range');
        
        if ($range) {
            list($start, $end) = $this->parseRange($range, $fileSize);
            
            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            $headers['Content-Length'] = $end - $start + 1;
            
            $stream = fopen($filePath, 'rb');
            fseek($stream, $start);
            
            return response()->stream(function() use ($stream, $start, $end) {
                $bufferSize = 8192;
                $currentPos = $start;
                
                while (!feof($stream) && $currentPos <= $end) {
                    $readLength = min($bufferSize, $end - $currentPos + 1);
                    echo fread($stream, $readLength);
                    $currentPos += $readLength;
                    
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
                
                fclose($stream);
            }, 206, $headers);
        }

        return response()->file($filePath, $headers);
    }

    /**
     * Parse HTTP Range header
     */
    private function parseRange(string $range, int $fileSize): array
    {
        if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            return [0, $fileSize - 1];
        }
        
        $start = (int) $matches[1];
        $end = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;
        
        $start = max(0, min($start, $fileSize - 1));
        $end = max($start, min($end, $fileSize - 1));
        
        return [$start, $end];
    }
}
