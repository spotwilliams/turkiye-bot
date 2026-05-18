<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');

        if ($expected === '') {
            throw new HttpException(503, 'Telegram webhook secret is not configured.');
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}
