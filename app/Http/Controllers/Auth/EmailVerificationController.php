<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Personal\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function sendCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', [], 401);
        }

        $email = $user->email;

        // Check if already verified
        if ($user->email_verified_at) {
            return $this->errorResponse('Email already verified.', [], 400);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(15);

        DB::table('email_verification_codes')->updateOrInsert(
            ['email' => $email],
            ['code' => $code, 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()]
        );

        Mail::raw("Your verification code is: {$code}", function ($message) use ($email) {
            $message->to($email)->subject('Verify Your Email');
        });

        return $this->successResponse('Verification code sent.');
    }

    public function verifyCode(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', [], 401);
        }

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $email = $user->email;

        $record = DB::table('email_verification_codes')
            ->where('email', $email)
            ->where('code', $request->code)
            ->first();

        if (!$record || Carbon::parse($record->expires_at)->isPast()) {
            return $this->errorResponse('Invalid or expired verification code.', [], 400);
        }

        $user->email_verified_at = now();
        $user->save();

        DB::table('email_verification_codes')->where('email', $email)->delete();

        return $this->successResponse('Email verified successfully.');
    }
}