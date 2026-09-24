<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EventOwner;
use App\Models\GateKeeper;
use App\Models\PasswordResetOtp;
use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Registration API - Success Test Cases
|--------------------------------------------------------------------------
*/

test('PROPER: it registers an event owner successfully and returns proper message and Bearer token', function () {
    $payload = [
        'userType' => 'event_owner',
        'firstName' => 'Sarah',
        'middleName' => 'Grace',
        'lastName' => 'Kimaro',
        'username' => 'sarahkimaro',
        'email' => 'sarah.kimaro@iventpro.com',
        'phone' => '+255712345678',
        'password' => 'Pass123',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'tokenType' => 'Bearer',
                'user' => [
                    'name' => 'Sarah Kimaro',
                    'username' => 'sarahkimaro',
                    'email' => 'sarah.kimaro@iventpro.com',
                    'phone' => '+255712345678',
                    'role' => 'event_owner',
                    'status' => 'active',
                ],
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();

    // Verify record in eventOwners table
    $owner = EventOwner::where('email', 'sarah.kimaro@iventpro.com')->first();
    expect($owner)->not->toBeNull();
    expect($owner->firstName)->toBe('Sarah');

    // Verify record in users table linked with roleId
    $user = User::where('email', 'sarah.kimaro@iventpro.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe(UserRole::EventOwner);
    expect($user->roleId)->toBe($owner->id);
    expect($owner->userId)->toBe($user->id);
});

test('PROPER: it registers a gatekeeper successfully and returns proper message', function () {
    $payload = [
        'userType' => 'gate_keeper',
        'fullName' => 'Juma Athumani',
        'username' => 'juma_gate',
        'email' => 'juma.gate@iventpro.com',
        'phone' => '+255755123456',
        'password' => 'Gate2026',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'name' => 'Juma Athumani',
                    'role' => 'gate_keeper',
                ],
            ],
        ]);

    $gk = GateKeeper::where('email', 'juma.gate@iventpro.com')->first();
    expect($gk)->not->toBeNull();
    expect($gk->fullName)->toBe('Juma Athumani');

    $user = User::where('email', 'juma.gate@iventpro.com')->first();
    expect($user->roleId)->toBe($gk->id);
    expect($gk->userId)->toBe($user->id);
});

test('PROPER: it registers an admin successfully and returns proper message', function () {
    $payload = [
        'userType' => 'admin',
        'name' => 'Super Administrator',
        'username' => 'super_admin',
        'email' => 'admin@iventpro.com',
        'password' => 'Admin2026',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'name' => 'Super Administrator',
                    'role' => 'admin',
                ],
            ],
        ]);

    $admin = SystemUser::where('email', 'admin@iventpro.com')->first();
    expect($admin)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Registration API - Failure / Improper Test Cases
|--------------------------------------------------------------------------
*/

test('IMPROPER: register fails when password has fewer than 6 characters', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'shortpass@example.com',
        'name' => 'Short Pass',
        'password' => 'Abc1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJsonPath('errors.password.0', 'Password must be at least 6 characters long.');
});

test('IMPROPER: register fails when password lacks an uppercase capital letter', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'nocap@example.com',
        'name' => 'No Capital',
        'password' => 'abcdef12',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJsonPath('errors.password.0', 'Password must contain at least one uppercase (capital) letter.');
});

test('IMPROPER: register fails when email is missing', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Missing Email',
        'password' => 'ValidPass1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Email address is required.');
});

test('IMPROPER: register fails when email format is invalid', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'invalid-email-string',
        'name' => 'Bad Email',
        'password' => 'ValidPass1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Please provide a valid email address.');
});

test('IMPROPER: register fails when email is already taken', function () {
    User::create([
        'name' => 'Existing User',
        'email' => 'duplicate@iventpro.com',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'duplicate@iventpro.com',
        'name' => 'New User',
        'password' => 'ValidPass1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'An account with this email already exists.');
});

test('IMPROPER: register fails when username is already taken', function () {
    User::create([
        'name' => 'Existing Username',
        'email' => 'original@iventpro.com',
        'username' => 'taken_username',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'unique@iventpro.com',
        'username' => 'taken_username',
        'name' => 'Another User',
        'password' => 'ValidPass1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username'])
        ->assertJsonPath('errors.username.0', 'This username is already taken.');
});

test('IMPROPER: register fails when userType is not recognized', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'testtype@example.com',
        'name' => 'Unknown Role',
        'password' => 'ValidPass1',
        'userType' => 'invalid_role_type',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['userType'])
        ->assertJsonPath('errors.userType.0', 'User type must be one of: event_owner, gate_keeper, or admin.');
});

/*
|--------------------------------------------------------------------------
| Login API - Success Test Cases
|--------------------------------------------------------------------------
*/

test('PROPER: login succeeds using valid email and password', function () {
    User::create([
        'name' => 'Email Sign In',
        'email' => 'signin.email@iventpro.com',
        'username' => 'signin_email',
        'password' => Hash::make('CorrectPass1'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'signin.email@iventpro.com',
        'password' => 'CorrectPass1',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Signed in successfully',
            'data' => [
                'tokenType' => 'Bearer',
                'user' => [
                    'email' => 'signin.email@iventpro.com',
                ],
            ],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

test('PROPER: login succeeds using valid username and password', function () {
    User::create([
        'name' => 'Username Sign In',
        'email' => 'signin.user@iventpro.com',
        'username' => 'my_unique_username',
        'password' => Hash::make('CorrectPass2'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'my_unique_username',
        'password' => 'CorrectPass2',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Signed in successfully',
            'data' => [
                'user' => [
                    'username' => 'my_unique_username',
                ],
            ],
        ]);
});

/*
|--------------------------------------------------------------------------
| Login API - Failure / Improper Test Cases
|--------------------------------------------------------------------------
*/

test('IMPROPER: login fails when login field (email/username) is missing', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'password' => 'SomePassword1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'Email or username is required to sign in.');
});

test('IMPROPER: login fails when password field is missing', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'someone@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJsonPath('errors.password.0', 'Password is required.');
});

test('IMPROPER: login fails with improper/wrong password', function () {
    User::create([
        'name' => 'Wrong Password User',
        'email' => 'wrongpass.user@iventpro.com',
        'password' => Hash::make('ActualPass1'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'wrongpass.user@iventpro.com',
        'password' => 'IncorrectPassword1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'The provided credentials do not match our records.');
});

test('IMPROPER: login fails when account is inactive or suspended', function () {
    User::create([
        'name' => 'Suspended User',
        'email' => 'suspended.user@iventpro.com',
        'password' => Hash::make('Password123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Suspended,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'suspended.user@iventpro.com',
        'password' => 'Password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'Your account is currently inactive or suspended. Please contact support.');
});

/*
|--------------------------------------------------------------------------
| Profile & Logout - Success and Failure Test Cases
|--------------------------------------------------------------------------
*/

test('PROPER: retrieves authenticated profile via GET /api/v1/auth/me with proper message', function () {
    $user = User::create([
        'name' => 'Profile User',
        'email' => 'profile.user@iventpro.com',
        'password' => Hash::make('Pass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => 'Profile User',
                'email' => 'profile.user@iventpro.com',
            ],
        ]);
});

test('IMPROPER: profile GET /api/v1/auth/me fails when unauthenticated', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('PROPER: logout revokes token and returns proper success message', function () {
    $user = User::create([
        'name' => 'Logout User',
        'email' => 'logout.v1@iventpro.com',
        'password' => Hash::make('Pass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $token = $user->createToken('logout_test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);

    expect($user->tokens()->count())->toBe(0);

    // Forget authentication guard in testing container
    app('auth')->forgetGuards();

    // Verify previous token is now rejected
    $retryResponse = $this->withToken($token)->getJson('/api/v1/auth/me');
    $retryResponse->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

test('IMPROPER: logout fails with 401 when no token is provided', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});

/*
|--------------------------------------------------------------------------
| Forgot Password & OTP Flow - Success and Failure Test Cases
|--------------------------------------------------------------------------
*/

test('PROPER: forgot-password generates 6-digit OTP, records in DB with 5 min expiry, and sends to phone', function () {
    $user = User::create([
        'name' => 'OTP User',
        'email' => 'otp.user@iventpro.com',
        'username' => 'otpuser',
        'phone' => '+255712345678',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => 'otp.user@iventpro.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'expiresIn' => 300,
                'phoneMasked' => '+25571 *** 678',
            ],
        ]);

    expect($response->json('message'))->toContain('A 6-digit verification code has been sent');

    // Verify OTP record in database
    $otpRecord = PasswordResetOtp::where('userId', $user->id)->first();
    expect($otpRecord)->not->toBeNull();
    expect($otpRecord->otp)->toMatch('/^\d{6}$/');
    expect($otpRecord->isUsed)->toBeFalse();
    expect($otpRecord->isExpired())->toBeFalse();
});

test('PROPER: requesting OTP again updates existing record instead of creating duplicate and resets 5 min timer', function () {
    $user = User::create([
        'name' => 'Repeat OTP User',
        'email' => 'repeat.otp@iventpro.com',
        'username' => 'repeatuser',
        'phone' => '+255788990011',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    // First request
    $this->postJson('/api/v1/auth/forgot-password', ['login' => 'repeatuser'])->assertStatus(200);
    $firstRecord = PasswordResetOtp::where('userId', $user->id)->first();

    // Fast-forward 2 minutes
    $this->travel(2)->minutes();

    // Second request with email
    $this->postJson('/api/v1/auth/forgot-password', ['login' => 'repeat.otp@iventpro.com'])->assertStatus(200);

    // Only 1 record exists in database for this user
    expect(PasswordResetOtp::where('userId', $user->id)->count())->toBe(1);

    $updatedRecord = PasswordResetOtp::where('userId', $user->id)->first();
    expect($updatedRecord->expiresAt->greaterThan($firstRecord->expiresAt))->toBeTrue();
});

test('IMPROPER: forgot-password fails when user does not exist', function () {
    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'No account found matching this email or username.');
});

test('IMPROPER: forgot-password fails when user has no registered phone number', function () {
    User::create([
        'name' => 'No Phone User',
        'email' => 'nophone@iventpro.com',
        'phone' => null,
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => 'nophone@iventpro.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'No registered phone number found for this account. Please contact support.');
});

test('PROPER: verify-otp succeeds with correct 6-digit code within 5 minutes', function () {
    $user = User::create([
        'name' => 'Verify User',
        'email' => 'verify@iventpro.com',
        'phone' => '+255711223344',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255711223344',
        'otp' => '543210',
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'verify@iventpro.com',
        'otp' => '543210',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Verification code confirmed successfully.',
        ]);
});

test('IMPROPER: verify-otp fails with incorrect code', function () {
    $user = User::create([
        'name' => 'Wrong Code User',
        'email' => 'wrongcode@iventpro.com',
        'phone' => '+255711223344',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255711223344',
        'otp' => '999888',
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'wrongcode@iventpro.com',
        'otp' => '111222',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp'])
        ->assertJsonPath('errors.otp.0', 'Invalid verification code.');
});

test('IMPROPER: verify-otp fails when code is expired (> 5 minutes)', function () {
    $user = User::create([
        'name' => 'Expired OTP User',
        'email' => 'expired@iventpro.com',
        'phone' => '+255711223344',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255711223344',
        'otp' => '654321',
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    // Fast-forward time past 5 minutes (e.g. 6 minutes)
    $this->travel(6)->minutes();

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'expired@iventpro.com',
        'otp' => '654321',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp'])
        ->assertJsonPath('errors.otp.0', 'Verification code has expired. Please request a new one.');
});

test('IMPROPER: verify-otp fails when code has already been used', function () {
    $user = User::create([
        'name' => 'Used OTP User',
        'email' => 'used@iventpro.com',
        'phone' => '+255711223344',
        'password' => Hash::make('Secret123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255711223344',
        'otp' => '777888',
        'isUsed' => true,
        'expiresAt' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'used@iventpro.com',
        'otp' => '777888',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp'])
        ->assertJsonPath('errors.otp.0', 'This verification code has already been used.');
});

test('PROPER: reset-password updates password, marks OTP as used, revokes old tokens, and allows login with new password', function () {
    $user = User::create([
        'name' => 'Reset Password User',
        'email' => 'reset.success@iventpro.com',
        'phone' => '+255712000111',
        'password' => Hash::make('OldPass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    $oldToken = $user->createToken('old_session')->plainTextToken;

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255712000111',
        'otp' => '123987',
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    // Step 2: verify OTP
    $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'reset.success@iventpro.com',
        'otp' => '123987',
    ])->assertStatus(200);

    // Step 3: reset password with ONLY login and newPassword (no confirmation needed)
    $response = $this->postJson('/api/v1/auth/reset-password', [
        'login' => 'reset.success@iventpro.com',
        'newPassword' => 'NewStrongPass1',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Password has been reset successfully. You can now log in with your new password.',
        ]);

    // Verify password updated in DB
    $user->refresh();
    expect(Hash::check('NewStrongPass1', $user->password))->toBeTrue();

    // Verify OTP marked as used
    $otpRecord = PasswordResetOtp::where('userId', $user->id)->first();
    expect($otpRecord->isUsed)->toBeTrue();

    // Verify old tokens were revoked
    expect($user->tokens()->count())->toBe(0);

    // Verify user can now log in with new password
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'reset.success@iventpro.com',
        'password' => 'NewStrongPass1',
    ]);
    $loginResponse->assertStatus(200)->assertJson(['status' => 'success']);
});

test('PROPER: complete 3-step flow using username instead of email', function () {
    $user = User::create([
        'name' => 'Username Flow User',
        'email' => 'userflow@iventpro.com',
        'username' => 'userflow99',
        'phone' => '+255712000333',
        'password' => Hash::make('OldPass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    // 1. Forgot password request using username
    $step1 = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => 'userflow99',
    ]);
    $step1->assertStatus(200);

    $otpRecord = PasswordResetOtp::where('userId', $user->id)->first();
    $otp = $otpRecord->otp;

    // 2. Verify OTP using username
    $step2 = $this->postJson('/api/v1/auth/verify-otp', [
        'login' => 'userflow99',
        'otp' => $otp,
    ]);
    $step2->assertStatus(200);

    // 3. Reset password using username and newPassword
    $step3 = $this->postJson('/api/v1/auth/reset-password', [
        'login' => 'userflow99',
        'newPassword' => 'UpdatedPass99',
    ]);
    $step3->assertStatus(200);

    // Login with new credentials succeeds
    $loginRes = $this->postJson('/api/v1/auth/login', [
        'login' => 'userflow99',
        'password' => 'UpdatedPass99',
    ]);
    $loginRes->assertStatus(200);
});

test('IMPROPER: reset-password fails if verify-otp was not performed first', function () {
    $user = User::create([
        'name' => 'Unverified User',
        'email' => 'unverified@iventpro.com',
        'phone' => '+255712000444',
        'password' => Hash::make('OldPass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    // Forgot password called, but verify-otp skipped
    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255712000444',
        'otp' => '445566',
        'isVerified' => false,
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'login' => 'unverified@iventpro.com',
        'newPassword' => 'ValidPass1',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login'])
        ->assertJsonPath('errors.login.0', 'Verification code has not been verified yet. Please verify your OTP first.');
});

test('IMPROPER: reset-password rejects weak password (< 6 chars or no capital letter)', function () {
    $user = User::create([
        'name' => 'Weak Reset User',
        'email' => 'weakreset@iventpro.com',
        'phone' => '+255712000222',
        'password' => Hash::make('OldPass123'),
        'role' => UserRole::EventOwner,
        'status' => UserStatus::Active,
    ]);

    PasswordResetOtp::create([
        'userId' => $user->id,
        'phone' => '+255712000222',
        'otp' => '112233',
        'isVerified' => true,
        'verifiedAt' => now(),
        'isUsed' => false,
        'expiresAt' => now()->addMinutes(5),
    ]);

    // 1. Password < 6 chars
    $resShort = $this->postJson('/api/v1/auth/reset-password', [
        'login' => 'weakreset@iventpro.com',
        'newPassword' => 'Ab1',
    ]);
    $resShort->assertStatus(422)
        ->assertJsonValidationErrors(['newPassword'])
        ->assertJsonPath('errors.newPassword.0', 'Password must be at least 6 characters long.');

    // 2. No capital letter
    $resNoCap = $this->postJson('/api/v1/auth/reset-password', [
        'login' => 'weakreset@iventpro.com',
        'newPassword' => 'abcdef',
    ]);
    $resNoCap->assertStatus(422)
        ->assertJsonValidationErrors(['newPassword'])
        ->assertJsonPath('errors.newPassword.0', 'Password must contain at least one uppercase (capital) letter.');
});
