<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\RegisterRequest;

/**
 * Data Transfer Object for user registration.
 */
class RegisterDTO
{
    public function __construct(
        public readonly string $userType,
        public readonly string $email,
        public readonly string $password,
        public readonly string $fullName,
        public readonly ?string $firstName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $username = null,
        public readonly ?string $phone = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?string $deviceName = 'authToken',
    ) {}

    /**
     * Build DTO from validated registration request.
     */
    public static function fromRequest(RegisterRequest $request): self
    {
        $userType = $request->input('userType', $request->input('role', 'event_owner'));

        $firstName = $request->input('firstName');
        $middleName = $request->input('middleName');
        $lastName = $request->input('lastName');

        // Resolve unified full name
        if ($request->filled('name')) {
            $fullName = (string) $request->input('name');
            if (empty($firstName)) {
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';
            }
        } elseif ($request->filled('fullName')) {
            $fullName = (string) $request->input('fullName');
            if (empty($firstName)) {
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';
            }
        } else {
            $fullName = trim("{$firstName} {$lastName}");
        }

        return new self(
            userType: (string) $userType,
            email: strtolower(trim((string) $request->input('email'))),
            password: (string) $request->input('password'),
            fullName: $fullName,
            firstName: $firstName,
            middleName: $middleName,
            lastName: $lastName,
            username: $request->filled('username') ? trim((string) $request->input('username')) : null,
            phone: $request->filled('phone') ? trim((string) $request->input('phone')) : null,
            avatarUrl: $request->input('avatarUrl'),
            deviceName: $request->input('deviceName', 'authToken'),
        );
    }
}
