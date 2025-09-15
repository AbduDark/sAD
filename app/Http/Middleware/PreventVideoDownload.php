
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventVideoDownload
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // التحقق من أن الاستجابة تحتوي على فيديو
        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || !str_starts_with($contentType, 'video/')) {
            return $response;
        }

        // Headers أمنية أساسية
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Download-Options', 'noopen');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy متقدم
        $csp = "default-src 'self'; " .
               "media-src 'self'; " .
               "object-src 'none'; " .
               "script-src 'none'; " .
               "style-src 'none'; " .
               "img-src 'none'; " .
               "font-src 'none'; " .
               "connect-src 'none'; " .
               "frame-src 'none'; " .
               "worker-src 'none'; " .
               "manifest-src 'none'";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // منع الـ caching بشكل صارم
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        // Headers إضافية لمنع التحميل
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, nosnippet, noarchive, notranslate, noimageindex');
        
        // منع التحميل عبر Content-Disposition
        $response->headers->set('Content-Disposition', 'inline; filename=""');
        
        // Headers مخصصة للحماية
        $response->headers->set('X-Video-Protection', 'enabled');
        $response->headers->set('X-Anti-Download', 'true');
        $response->headers->set('X-Stream-Only', 'true');
        
        // إضافة معرف جلسة فريد لتتبع المشاهدة
        if (!$response->headers->has('X-Session-ID')) {
            $response->headers->set('X-Session-ID', uniqid('video_', true));
        }
        
        // Headers للتحكم في التخزين المؤقت للمتصفح
        $response->headers->set('Surrogate-Control', 'no-store');
        $response->headers->set('Vary', 'Accept-Encoding, User-Agent');
        
        // منع حفظ الصفحة
        $response->headers->set('X-Save-Page', 'disabled');

        return $response;
    }
}
