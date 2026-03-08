<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class PublicFetchController extends Controller
{
    public function fetch(Request $request)
    {
        $lock = Cache::lock('dash:manual_fetch_lock', 30);

        if (!$lock->get()) {
            $retryAfter = 30;
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => 'Too many requests'], 429)->header('Retry-After', $retryAfter);
            }

            return back()->withErrors(['fetch' => 'Too many requests — please wait a moment.']);
        }

        try {
            Artisan::call('events:fetch');

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => 'Fetch requested'], 200);
            }

            return back()->with('status', 'API fetch requested.');
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) { /* ignore */
            }
        }
    }
}
