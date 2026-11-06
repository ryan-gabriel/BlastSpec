<?php

namespace BlastSpec\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $timestamp = $request->header('X-BlastSpec-Timestamp');
        $signature = $request->header('X-BlastSpec-Signature');

        if (empty($timestamp) || empty($signature)) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1501',
                'title' => 'Missing webhook signature headers.',
                'status' => 401,
                'code' => 'ERR-1501',
            ], 401);
        }

        $tolerance = (int) config('webhook_tolerance_seconds', 300);
        $now = time();

        // Parse timestamp (Unix seconds or ISO-8601)
        if (is_numeric($timestamp)) {
            $ts = (int) $timestamp;
        } else {
            $ts = strtotime($timestamp);
            if ($ts === false) {
                return response()->json([
                    'type' => 'https://blastspec.dev/errors/ERR-1501',
                    'title' => 'Invalid timestamp format.',
                    'status' => 401,
                    'code' => 'ERR-1501',
                ], 401);
            }
        }

        if (abs($now - $ts) > $tolerance) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1502',
                'title' => 'Webhook timestamp outside the 5-minute window.',
                'status' => 401,
                'code' => 'ERR-1502',
            ], 401);
        }

        $secret = (string) config('webhook_secret', '');
        $previousSecret = (string) config('webhook_previous_secret', '');

        $rawBody = $request->getContent();
        $canonical = $timestamp . '.' . $rawBody;

        $secrets = array_filter([$secret, $previousSecret]);
        $valid = false;

        foreach ($secrets as $s) {
            $expected = 'v1=' . hash_hmac('sha256', $canonical, $s);
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            return response()->json([
                'type' => 'https://blastspec.dev/errors/ERR-1501',
                'title' => 'Webhook signature invalid.',
                'status' => 401,
                'code' => 'ERR-1501',
            ], 401);
        }

        return $next($request);
    }
}
