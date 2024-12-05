<?php

namespace MWGuerra\EmailSecurityManager\Middleware;

use App\Services\EmailVerificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmailSecurityMiddleware
{
    public function __construct(
        protected EmailVerificationService $verificationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $redirectRoute = config('email-verification.redirect_route', 'verification.notice');
        $skipRoutes = [
            'verification.notice',
            'verification.verify',
            'verification.send',
            'password.request',
            'password.reset',
            'password.update',
            'logout'
        ];

        // Skip verification checks for specific routes
        if (in_array($request->route()->getName(), $skipRoutes)) {
            return $next($request);
        }

        $messages = [];

        // Check email verification
        if ($this->verificationService->isEmailVerificationExpired($user)) {
            $messages[] = 'Your email verification has expired. Please verify your email address.';

            // Create audit record
            $user->verificationAudits()->create([
                'email' => $user->email,
                'triggered_by' => 'system',
                'reason' => 'Email verification expired'
            ]);

            // Send new verification email
            $user->sendEmailVerificationNotification();
        }

        // Check password expiry
        if ($this->verificationService->isPasswordExpired($user)) {
            $messages[] = 'Your password has expired. Please change your password.';

            // Create audit record
            $user->verificationAudits()->create([
                'email' => $user->email,
                'triggered_by' => 'system',
                'reason' => 'Password expired'
            ]);
        }

        // If any checks failed, redirect with messages
        if (!empty($messages)) {
            // For API requests, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verification required',
                    'details' => $messages
                ], 403);
            }

            // For web requests, redirect with flash messages
            return redirect()->route($redirectRoute)->with([
                'verification_messages' => $messages
            ]);
        }

        return $next($request);
    }
}
