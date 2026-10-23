<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\ForgotPasswordDTO;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\ResetPasswordOtpDTO;
use App\DTOs\Auth\VerifyOtpDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller managing version 1 (V1) user registration and authentication endpoints.
 */
class AuthController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService
    ) {}

    /**
     * Register a new user account.
     *
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // 1. Convert validated request into typed DTO
        $dto = RegisterDTO::fromRequest($request);

        // 2. Delegate creation to service layer
        $authResponse = $this->authService->register($dto);

        // 3. Return formatted JSON response
        return $authResponse->toResponse();
    }

    /**
     * Authenticate an existing user and obtain an access token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // 1. Convert validated request into typed DTO
        $dto = LoginDTO::fromRequest($request);

        // 2. Authenticate credentials
        $authResponse = $this->authService->login($dto);

        // 3. Return token and user profile
        return $authResponse->toResponse();
    }

    /**
     * Get the authenticated user's profile details.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $userResponse = $this->authService->me($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully.',
            'data' => $userResponse->toArray(),
        ]);
    }

    /**
     * Invalidate the authenticated user's current session token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Send 6-digit OTP to user's registered phone number for password reset.
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $dto = ForgotPasswordDTO::fromRequest($request);
        $result = $this->authService->sendPasswordResetOtp($dto);

        return response()->json($result, 200);
    }

    /**
     * Verify 6-digit OTP code before resetting password.
     *
     * POST /api/v1/auth/verify-otp
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $dto = VerifyOtpDTO::fromRequest($request);
        $result = $this->authService->verifyOtp($dto);

        return response()->json($result, 200);
    }

    /**
     * Reset password using validated OTP and new credentials.
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        $dto = ResetPasswordOtpDTO::fromRequest($request);
        $result = $this->authService->resetPasswordWithOtp($dto);

        return response()->json($result, 200);
    }

    /**
     * Generate registration OTP code sent to phone/email.
     *
     * POST /api/v1/auth/otp/generate
     */
    public function generateRegistrationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $result = $this->authService->generateRegistrationOtp(
            $validated['email'],
            $validated['phone']
        );

        return response()->json($result, 200);
    }

    /**
     * Verify registration OTP code before submitting registration form.
     *
     * POST /api/v1/auth/otp/verify
     */
    public function verifyRegistrationOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc'],
            'phone' => ['nullable', 'string'],
            'otp' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $result = $this->authService->verifyRegistrationOtp(
            $validated['email'],
            $validated['phone'] ?? null,
            $validated['otp']
        );

        return response()->json($result, 200);
    }
}
