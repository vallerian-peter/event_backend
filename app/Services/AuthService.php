<?php

namespace App\Services;

use App\DTOs\Auth\AuthResponseDTO;
use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\ResetPasswordOtpDTO;
use App\DTOs\Auth\UserResponseDTO;
use App\DTOs\Auth\VerifyOtpDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EmailOtpVerify;
use App\Models\EventOwner;
use App\Models\GateKeeper;
use App\Models\PasswordResetOtp;
use App\Models\SystemUser;
use App\Models\User;
use App\Services\Messaging\BeemSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;


class AuthService
{
    public function __construct(
        protected readonly BeemSmsService $smsService
    ) {}

    /**
     * Register a new user and create their corresponding profile table record.
     *
     * Flow:
     * 1. Inspect userType from request.
     * 2. First create the individual profile record (eventOwners / gateKeepers / systemUsers).
     * 3. Get the profile record ID and create the main User record in the users table.
     * 4. Link the profile record back to the newly created user (userId).
     * 5. Issue a personal access token via Laravel Sanctum.
     *
     * @throws \Throwable
     */
    public function register(RegisterDTO $dto): AuthResponseDTO
    {
        return DB::transaction(function () use ($dto): AuthResponseDTO {
            $userRole = match ($dto->userType) {
                'gateKeeper', 'gate_keeper' => UserRole::GateKeeper,
                'admin' => UserRole::Admin,
                default => UserRole::EventOwner,
            };

            $profileId = null;

            // Step 1: Create record in the individual profile table first
            if ($userRole === UserRole::EventOwner) {
                $owner = EventOwner::create([
                    'firstName' => $dto->firstName ?: $dto->fullName,
                    'middleName' => $dto->middleName,
                    'lastName' => $dto->lastName ?: '',
                    'username' => $dto->username,
                    'email' => $dto->email,
                    'phone' => $dto->phone,
                    'eventIds' => [],
                ]);
                $profileId = $owner->id;
            } elseif ($userRole === UserRole::GateKeeper) {
                $gateKeeper = GateKeeper::create([
                    'fullName' => $dto->fullName,
                    'username' => $dto->username,
                    'email' => $dto->email,
                    'phone' => $dto->phone,
                    'eventIds' => [],
                ]);
                $profileId = $gateKeeper->id;
            } elseif ($userRole === UserRole::Admin) {
                $systemUser = SystemUser::create([
                    'firstName' => $dto->firstName ?: $dto->fullName,
                    'middleName' => $dto->middleName,
                    'lastName' => $dto->lastName ?: '',
                    'username' => $dto->username,
                    'email' => $dto->email,
                    'phone' => $dto->phone,
                ]);
                $profileId = $systemUser->id;
            }

            // Step 2: Create user in the central 'users' table with role and roleId
            $user = User::create([
                'name' => $dto->fullName,
                'username' => $dto->username,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'password' => Hash::make($dto->password),
                'role' => $userRole,
                'roleId' => $profileId,
                'status' => UserStatus::Active,
                'avatarUrl' => $dto->avatarUrl,
            ]);

            // Step 3: Link profile record back to the created user's ID
            if ($userRole === UserRole::EventOwner && isset($owner)) {
                $owner->update(['userId' => $user->id]);
            } elseif ($userRole === UserRole::GateKeeper && isset($gateKeeper)) {
                $gateKeeper->update(['userId' => $user->id]);
            } elseif ($userRole === UserRole::Admin && isset($systemUser)) {
                $systemUser->update(['userId' => $user->id]);
            }

            // Step 4: Generate Sanctum access token
            $tokenName = $dto->deviceName ?: 'authToken';
            $token = $user->createToken($tokenName)->plainTextToken;

            // Step 5: Send welcome SMS if phone number is provided
            if (! empty($user->phone)) {
                try {
                    $this->smsService->sendQuickSms(
                        $user->phone,
                        "Karibu IventPro, {$user->name}! Your account has been registered successfully. Login at " . config('app.url') . "/auth/login"
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not dispatch welcome SMS to {$user->phone}: {$e->getMessage()}");
                }
            }

            // Step 6: Format response using DTOs
            $userResponse = UserResponseDTO::fromModel($user);

            return new AuthResponseDTO(
                token: $token,
                user: $userResponse,
                message: 'User registered successfully. Welcome message sent!',
                statusCode: 201,
            );
        });
    }

    /**
     * Authenticate an existing user by email or username.
     *
     * @throws ValidationException
     */
    public function login(LoginDTO $dto): AuthResponseDTO
    {
        $loginInput = $dto->login;

        // Query by email OR username
        $user = User::where('email', strtolower($loginInput))
            ->orWhere('username', $loginInput)
            ->first();

        // Validate credentials
        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials do not match our records.'],
            ]);
        }

        // Validate account status
        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'login' => ['Your account is currently inactive or suspended. Please contact support.'],
            ]);
        }

        // Generate Sanctum access token
        $tokenName = $dto->deviceName ?: 'authToken';
        $token = $user->createToken($tokenName)->plainTextToken;

        $userResponse = UserResponseDTO::fromModel($user);

        return new AuthResponseDTO(
            token: $token,
            user: $userResponse,
            message: 'Signed in successfully',
            statusCode: 200,
        );
    }

    /**
     * Invalidate current user's active Sanctum access token.
     */
    public function logout(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        $token?->delete();
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(User $user): UserResponseDTO
    {
        return UserResponseDTO::fromModel($user);
    }

    /**
     * Send a 6-digit OTP code to the user's registered phone number.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function sendPasswordResetOtp(ForgotPasswordDTO $dto): array
    {
        $loginInput = $dto->login;

        // Find user by email or username
        $user = User::where('email', strtolower($loginInput))
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['No account found matching this email or username.'],
            ]);
        }

        if (empty($user->phone)) {
            throw ValidationException::withMessages([
                'login' => ['No registered phone number found for this account. Please contact support.'],
            ]);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);

        // Upsert OTP record for this user (updates existing record if already generated)
        PasswordResetOtp::updateOrCreate(
            ['userId' => $user->id],
            [
                'phone' => $user->phone,
                'otp' => $otp,
                'isVerified' => false,
                'verifiedAt' => null,
                'isUsed' => false,
                'expiresAt' => $expiresAt,
            ]
        );

        // Send SMS via Beem Africa
        $this->smsService->sendOtp($user->phone, $otp);

        $maskedPhone = BeemSmsService::maskPhone($user->phone);

        return [
            'status' => 'success',
            'message' => "A 6-digit verification code has been sent to your registered phone number ({$maskedPhone}).",
            'data' => [
                'expiresIn' => 300,
                'phoneMasked' => $maskedPhone,
                'otp' => app()->environment('local', 'testing') ? $otp : null,
            ],
        ];
    }

    /**
     * Verify a 6-digit OTP code within its 5-minute validity window.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function verifyOtp(VerifyOtpDTO $dto): array
    {
        $loginInput = $dto->login;

        $user = User::where('email', strtolower($loginInput))
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['No account found matching this email or username.'],
            ]);
        }

        $otpRecord = PasswordResetOtp::where('userId', $user->id)->first();

        if (! $otpRecord || $otpRecord->otp !== $dto->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        if ($otpRecord->isUsed) {
            throw ValidationException::withMessages([
                'otp' => ['This verification code has already been used.'],
            ]);
        }

        if ($otpRecord->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new one.'],
            ]);
        }

        // Mark OTP as verified
        $otpRecord->update([
            'isVerified' => true,
            'verifiedAt' => now(),
        ]);

        return [
            'status' => 'success',
            'message' => 'Verification code confirmed successfully.',
        ];
    }

    /**
     * Reset user password using verified OTP and revoke previous access tokens.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function resetPasswordWithOtp(ResetPasswordOtpDTO $dto): array
    {
        $loginInput = $dto->login;

        $user = User::where('email', strtolower($loginInput))
            ->orWhere('username', $loginInput)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['No account found matching this email or username.'],
            ]);
        }

        $otpRecord = PasswordResetOtp::where('userId', $user->id)->first();

        if (! $otpRecord) {
            throw ValidationException::withMessages([
                'login' => ['No verification record found for this account. Please request a code first.'],
            ]);
        }

        if ($otpRecord->isUsed) {
            throw ValidationException::withMessages([
                'login' => ['This verification code has already been used. Please request a new code.'],
            ]);
        }

        // If OTP provided in request, check it; otherwise check if it was verified in step 2
        if (! empty($dto->otp)) {
            if ($otpRecord->otp !== $dto->otp) {
                throw ValidationException::withMessages([
                    'otp' => ['Invalid verification code.'],
                ]);
            }
            if ($otpRecord->isExpired()) {
                throw ValidationException::withMessages([
                    'otp' => ['Verification code has expired. Please request a new one.'],
                ]);
            }
        } else {
            if (! $otpRecord->isVerified || empty($otpRecord->verifiedAt)) {
                throw ValidationException::withMessages([
                    'login' => ['Verification code has not been verified yet. Please verify your OTP first.'],
                ]);
            }

            // Must be within 15 minutes of verification
            if (now()->diffInMinutes($otpRecord->verifiedAt) > 15) {
                throw ValidationException::withMessages([
                    'login' => ['Verification session has timed out. Please request a new code.'],
                ]);
            }
        }

        // Update password with Hash
        $user->update([
            'password' => Hash::make($dto->newPassword),
        ]);

        // Mark OTP as used to prevent replay
        $otpRecord->update([
            'isUsed' => true,
        ]);

        // Invalidate active session tokens for security
        $user->tokens()->delete();

        return [
            'status' => 'success',
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
        ];
    }

    /**
     * Generate and dispatch a registration OTP to phone or email.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function generateRegistrationOtp(string $email, ?string $phone = null): array
    {
        // 1. Verify that email is not already taken
        if (User::where('email', strtolower($email))->exists()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists.'],
            ]);
        }

        // 2. Verify that phone is not already taken
        if ($phone && User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['An account with this phone number already exists.'],
            ]);
        }

        // 3. Generate 6-digit OTP code
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $startTime = now();
        $endTime = $startTime->copy()->addMinutes(5);

        // 4. Upsert OTP record
        EmailOtpVerify::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'phone' => $phone,
                'otp' => $otp,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'isVerified' => false,
            ]
        );

        // 5. Dispatch SMS if phone provided
        if (! empty($phone)) {
            try {
                $this->smsService->sendOtp($phone, $otp);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Could not dispatch registration OTP SMS: {$e->getMessage()}");
            }
        }

        $maskedPhone = $phone ? BeemSmsService::maskPhone($phone) : null;

        return [
            'status' => 'success',
            'message' => $phone
                ? "Verification code sent to {$maskedPhone}."
                : 'Verification code generated successfully.',
            'data' => [
                'endTime' => $endTime->toIso8601String(),
                'phoneMasked' => $maskedPhone,
                'otp' => app()->environment('local', 'testing') ? $otp : null,
            ],
        ];
    }

    /**
     * Verify a registration OTP code.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function verifyRegistrationOtp(string $email, ?string $phone, string $otp): array
    {
        $query = EmailOtpVerify::where('email', strtolower($email));
        if ($phone) {
            $query->orWhere('phone', $phone);
        }

        $record = $query->latest('createdAt')->first();

        if (! $record || $record->otp !== $otp) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new one.'],
            ]);
        }

        $record->update([
            'isVerified' => true,
        ]);

        return [
            'status' => 'success',
            'message' => 'Verification code confirmed successfully.',
        ];
    }
}
