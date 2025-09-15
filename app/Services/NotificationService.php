<?php

namespace App\Services;

use App\Models\UserNotification;
use App\Models\User;
use App\Models\Course;
use App\Models\Subscription; // Added User and Subscription imports

class NotificationService
{
    /**
     * إرسال إشعار لمستخدم واحد
     */
    public static function sendToUser($userId, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        return UserNotification::create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'user_id' => $userId,
            'course_id' => $courseId,
            'sender_id' => $senderId,
            'data' => $data
        ]);
    }

    /**
     * إرسال إشعار لعدة مستخدمين
     */
    public static function sendToUsers($userIds, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = self::sendToUser($userId, $title, $message, $type, $courseId, $senderId, $data);
        }

        return $notifications;
    }

    /**
     * إرسال إشعار لجميع الطلبة
     */
    public static function sendToAllStudents($title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $studentIds = User::where('role', 'student')->pluck('id')->toArray();
        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة حسب الجنس
     */
    public static function sendToStudentsByGender($gender, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $studentIds = User::where('role', 'student')
                         ->where('gender', $gender)
                         ->pluck('id')->toArray();
        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة كورس معين حسب الجنس
     */
    public static function sendToCourseStudentsByGender($courseId, $gender, $title, $message, $type = 'course', $senderId = null, $data = null)
    {
        $studentIds = User::whereHas('subscriptions', function ($query) use ($courseId) {
            $query->where('course_id', $courseId)
                  ->where('is_active', true)
                  ->where('status', 'approved');
        })->where('gender', $gender)->pluck('id')->toArray();

        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة كورس معين
     */
    public static function sendToCourseStudents($courseId, $title, $message, $type = 'course', $senderId = null, $data = null)
    {
        $studentIds = User::whereHas('subscriptions', function ($query) use ($courseId) {
            $query->where('course_id', $courseId)
                  ->where('is_active', true)
                  ->where('status', 'approved');
        })->pluck('id')->toArray();

        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إشعار عند الموافقة على الاشتراك
     */
    public static function subscriptionApproved($userId, $courseId)
    {
        $course = Course::find($courseId);

        return self::sendToUser(
            $userId,
            'تم قبول اشتراكك',
            "تم قبول اشتراكك في كورس: {$course->title}. يمكنك الآن الوصول إلى جميع دروس الكورس.",
            'subscription',
            $courseId,
            null,
            ['action' => 'subscription_approved', 'course_id' => $courseId]
        );
    }

    /**
     * إشعار عند رفض الاشتراك
     */
    public static function subscriptionRejected($userId, $courseId, $reason = null)
    {
        $course = Course::find($courseId);
        $message = "تم رفض اشتراكك في كورس: {$course->title}.";

        if ($reason) {
            $message .= " السبب: {$reason}";
        }

        return self::sendToUser(
            $userId,
            'تم رفض اشتراكك',
            $message,
            'subscription',
            $courseId,
            null,
            ['action' => 'subscription_rejected', 'course_id' => $courseId, 'reason' => $reason]
        );
    }

    /**
     * إشعار عند انتهاء صلاحية الاشتراك
     */
    public static function subscriptionExpired($user, $courseOrSubscription): void
{
    // لو $user هو object
    if ($user instanceof User && $courseOrSubscription instanceof Subscription) {
        self::sendToUser(
            $user->id,
            'انتهت صلاحية اشتراكك',
            'يرجى تجديد اشتراكك للاستمرار في الوصول إلى الدروس',
            'subscription',
            $courseOrSubscription->id
        );
        return;
    }

    // لو جاي ID
    if (is_numeric($user) && is_numeric($courseOrSubscription)) {
        $course = Course::find($courseOrSubscription);

        self::sendToUser(
            $user,
            'انتهت صلاحية اشتراكك',
            "انتهت صلاحية اشتراكك في كورس: {$course->title}. يرجى التجديد.",
            'subscription',
            $courseOrSubscription,
            null,
            ['action' => 'subscription_expired', 'course_id' => $courseOrSubscription]
        );
        return;
    }
}

    /**
     * إشعار عند إضافة درس جديد
     */
    public static function newLessonAdded($courseId, $lessonTitle)
    {
        $course = Course::find($courseId);

        return self::sendToCourseStudents(
            $courseId,
            'درس جديد متاح',
            "تم إضافة درس جديد '{$lessonTitle}' إلى كورس: {$course->title}",
            'course',
            null,
            ['action' => 'new_lesson', 'course_id' => $courseId, 'lesson_title' => $lessonTitle]
        );
    }

    /**
     * إشعار عند اقتراب انتهاء الاشتراك (تذكير)
     */
    public static function subscriptionExpiringReminder($userId, $courseId, $daysRemaining)
    {
        $course = Course::find($courseId);

        return self::sendToUser(
            $userId,
            'تذكير: اشتراكك على وشك الانتهاء',
            "سينتهي اشتراكك في كورس: {$course->title} خلال {$daysRemaining} أيام. يرجى تجديد الاشتراك.",
            'subscription',
            $courseId,
            null,
            ['action' => 'subscription_reminder', 'course_id' => $courseId, 'days_remaining' => $daysRemaining]
        );
    }

    /**
     * إشعار للمديرين
     */
    public function notifyAdmins($title, $message, $type = 'info')
    {
        try {
            // البحث عن جميع المديرين
            $admins = User::admins()->get();

            if ($admins->isEmpty()) {
                return false; // لا يوجد مدراء لإرسال الإشعار لهم
            }

            foreach ($admins as $admin) {
                self::sendToUser($admin->id, $title, $message, $type);
            }

            return true;
        } catch (\Exception $e) {
            // يمكنك تسجيل الخطأ هنا إذا لزم الأمر
            // Log::error($e->getMessage());
            return false;
        }
    }

    // Methods added based on the user's intent, likely from another controller or service
    // These methods assume the existence of a `createNotification` method and the $user and $subscription objects.

    /**
     * إشعار عند انتهاء صلاحية الاشتراك (تم تحديثه)
     */

    /**
     * إشعار عند تفعيل اشتراك جديد
     */
    public function newSubscription($user)
    {
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'subscription',
            'title' => 'اشتراك جديد',
            'message' => 'تم تفعيل اشتراكك بنجاح',
            'data' => json_encode(['user_id' => $user->id])
        ]);

        return $notification;
    }

    /**
     * إشعار عند تجديد الاشتراك
     */
    public function newSubscriptionRenewal($user)
    {
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'subscription_renewal',
            'title' => 'تجديد الاشتراك',
            'message' => 'تم تجديد اشتراكك بنجاح',
            'data' => json_encode(['user_id' => $user->id])
        ]);

        return $notification;
    }
}