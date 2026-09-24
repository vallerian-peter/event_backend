<?php

namespace App\DTOs\Auth;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;

/**
 * Global Authentication Response DTO.
 * Used for both registration and login responses across the application.
 */
class AuthResponseDTO implements Arrayable
{
    public function __construct(
        public readonly string $token,
        public readonly UserResponseDTO $user,
        public readonly string $message = 'Authentication successful',
        public readonly string $tokenType = 'Bearer',
        public readonly int $statusCode = 200,
    ) {}

    /**
     * Convert DTO to JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => 'success',
            'message' => $this->message,
            'data' => [
                'token' => $this->token,
                'tokenType' => $this->tokenType,
                'user' => $this->user->toArray(),
            ],
        ];
    }

    /**
     * Return direct Laravel JsonResponse.
     */
    public function toResponse(): JsonResponse
    {
        return response()->json($this->toArray(), $this->statusCode);
    }
}
