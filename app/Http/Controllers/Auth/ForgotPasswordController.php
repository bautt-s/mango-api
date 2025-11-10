<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Personal\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('User not found.', [], 404);
        }

        $code = rand(100000, 999999);

        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $request->email],
            [
                'code' => $code,
                'created_at' => Carbon::now(),
                'expires_at' => now()->addMinutes(10)
            ]
        );

        Mail::raw("Your password reset code is: {$code}", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Your Password Reset Code');
        });

        return $this->successResponse('Reset code sent.');
    }

    public function validateCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$record) {
            return $this->errorResponse('Invalid reset code.', [], 400);
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            return $this->errorResponse('Reset code expired.', [], 410);
        }

        return $this->successResponse('Code validated successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Validate the code again for security
        $record = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$record) {
            return $this->errorResponse('Invalid reset code.', [], 400);
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            return $this->errorResponse('Reset code expired.', [], 410);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->errorResponse('User not found.', [], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        // Clean up the reset code after successful password change
        DB::table('password_reset_codes')->where('email', $request->email)->delete();

        return $this->successResponse('Password changed successfully.');
    }

    // Keep the original method for backward compatibility if needed
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_codes')
            ->where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$record) {
            return $this->errorResponse('Invalid reset code.', [], 400);
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            return $this->errorResponse('Reset code expired.', [], 410);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->errorResponse('User not found.', [], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        DB::table('password_reset_codes')->where('email', $request->email)->delete();

        return $this->successResponse('Password reset successfully.');
    }
}
