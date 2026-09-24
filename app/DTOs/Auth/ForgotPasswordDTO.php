<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;

/**
 * DTO for initiating forgot password request.
 */
class ForgotPasswordDTO
{
    public function __construct(
        public readonly string $login
    ) {}

    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        return new self(
            login: trim((string) $request->input('login', $request->input('email', $request->input('username'))))
        );
    }
}
