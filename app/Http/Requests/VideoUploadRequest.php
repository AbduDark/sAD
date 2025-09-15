
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoUploadRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    public function rules()
    {
        return [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv',
                'max:1048576', // 1GB
            ],
            'is_protected' => 'boolean',
        ];
    }

    public function messages()
    {
        return [
            'video.required' => 'يرجى اختيار ملف فيديو',
            'video.file' => 'يجب أن يكون الملف من نوع فيديو',
            'video.mimes' => 'نوع الفيديو غير مدعوم. الأنواع المدعومة: mp4, avi, mov, wmv, flv, webm, mkv',
            'video.max' => 'حجم الفيديو يجب أن يكون أقل من 1 جيجابايت',
        ];
    }
}
