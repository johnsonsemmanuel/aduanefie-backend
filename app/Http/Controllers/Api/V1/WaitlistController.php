<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'interest' => 'nullable|string|max:60',
            'source' => 'nullable|string|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email')));

        if (! Schema::hasTable('waitlist_entries')) {
            return response()->json([
                'message' => "You're on the waitlist! We'll notify you as soon as Aduanefie launches.",
                'already_registered' => false,
            ], 201);
        }

        $existing = DB::table('waitlist_entries')->where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'message' => "You're already on the Aduanefie waitlist! We'll reach out as soon as we open.",
                'already_registered' => true,
            ], 200);
        }

        DB::table('waitlist_entries')->insert([
            'name' => $request->filled('name') ? trim((string) $request->input('name')) : null,
            'email' => $email,
            'phone' => $request->filled('phone') ? trim((string) $request->input('phone')) : null,
            'interest' => $request->filled('interest') ? trim((string) $request->input('interest')) : 'buyer',
            'source' => $request->filled('source') ? trim((string) $request->input('source')) : 'homepage_popup',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => "Welcome to the waitlist! You'll be among the first to explore the new Aduanefie.",
            'already_registered' => false,
        ], 201);
    }

    public function verifyAccess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'unlocked' => false,
                'message' => 'Please enter your access code.',
            ], 422);
        }

        $expected = (string) env('PLATFORM_ACCESS_CODE', '0242');
        $provided = trim((string) $request->input('code'));

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'unlocked' => false,
                'message' => 'Invalid access code. Please check with the Aduanefie team.',
            ], 403);
        }

        return response()->json([
            'unlocked' => true,
            'message' => 'Platform access unlocked.',
        ], 200);
    }
}
