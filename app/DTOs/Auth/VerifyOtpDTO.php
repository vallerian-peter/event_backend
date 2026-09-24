<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\VerifyOtpRequest;

/**
 * DTO for verifying OTP code.
 */
class VerifyOtpDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $otp,
    ) {}

    public static function fromRequest(VerifyOtpRequest $request): self
    {
        return new self(
            login: trim((string) $request->input('login', $request->input('email', $request->input('username')))),
            otp: trim((string) $request->input('otp')),
        );
    }
}
