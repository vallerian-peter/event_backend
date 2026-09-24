<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ResetPasswordOtpRequest;

/**
 * DTO for resetting password using username/email and newPassword.
 */
class ResetPasswordOtpDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $newPassword,
        public readonly ?string $otp = null,
    ) {}

    public static function fromRequest(ResetPasswordOtpRequest $request): self
    {
        $password = $request->input('newPassword', $request->input('password'));

        return new self(
            login: trim((string) $request->input('login', $request->input('email', $request->input('username')))),
            newPassword: (string) $password,
            otp: $request->filled('otp') ? trim((string) $request->input('otp')) : null,
        );
    }
}
