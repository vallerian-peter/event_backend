<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\LoginRequest;

/**
 * Data Transfer Object for user login.
 */
class LoginDTO
{
    public function __construct(
        public readonly string $login,
        public readonly string $password,
        public readonly ?string $deviceName = 'authToken',
    ) {}

    /**
     * Build DTO from validated login request.
     */
    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            login: trim((string) $request->input('login')),
            password: (string) $request->input('password'),
            deviceName: $request->input('deviceName', 'authToken'),
        );
    }
}
