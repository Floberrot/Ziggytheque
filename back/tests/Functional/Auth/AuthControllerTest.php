<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Auth\Domain\AuthToken;
use App\Auth\Domain\AuthTokenTypeEnum;
use App\Auth\Domain\User;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Infrastructure\Token\SecureTokenGenerator;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AuthControllerTest extends AbstractApiTestCase
{
    // ── POST /api/auth/register ────────────────────────────────────────────

    public function testRegisterCreatesUserAndReturns201(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'new@test.local', 'password' => 'Password1!', 'displayName' => 'New User'],
            auth: false,
        );
        $this->assertJsonStatus(201, $response);
    }

    public function testRegisterWithDuplicateEmailReturns409(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'dup@test.local');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'dup@test.local', 'password' => 'Password1!', 'displayName' => 'Dup'],
            auth: false,
        );
        $this->assertJsonStatus(409, $response);
    }

    public function testRegisterWithMissingFieldReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/register', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    public function testRegisterWithInvalidEmailReturns422(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'not-an-email', 'password' => 'Password1!', 'displayName' => 'User'],
            auth: false,
        );
        $this->assertJsonStatus(422, $response);
    }

    // ── POST /api/auth/verify-email ────────────────────────────────────────

    public function testVerifyEmailWithValidTokenReturns200(): void
    {
        $token        = $this->createEmailVerificationToken();
        $response     = $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $token], auth: false);

        $this->assertJsonStatus(200, $response);
    }

    public function testVerifyEmailWithInvalidTokenReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => 'bad-token'], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    public function testVerifyEmailWithMissingTokenReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/verify-email', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    // ── POST /api/auth/login ───────────────────────────────────────────────

    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'login@test.local');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'login@test.local', 'password' => 'Test1234!'],
            auth: false,
        );
        $data = $this->assertJsonStatus(200, $response);

        $this->assertArrayHasKey('token', $data);
        $this->assertIsString($data['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'wrongpw@test.local');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'wrongpw@test.local', 'password' => 'WrongPassword!'],
            auth: false,
        );
        $this->assertJsonStatus(401, $response);
    }

    public function testLoginWithUnknownEmailReturns401(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'nobody@test.local', 'password' => 'Test1234!'],
            auth: false,
        );
        $this->assertJsonStatus(401, $response);
    }

    public function testLoginWithInactiveAccountReturns403(): void
    {
        UserFixtureFactory::createPendingUser(static::getContainer(), email: 'pending@test.local');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'pending@test.local', 'password' => 'Test1234!'],
            auth: false,
        );
        $this->assertJsonStatus(403, $response);
    }

    public function testLoginWithMissingBodyReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/login', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    // ── POST /api/auth/request-reset ──────────────────────────────────────

    public function testRequestResetAlwaysReturns200(): void
    {
        // Silent no-op for unknown email
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/request-reset',
            ['email' => 'nobody@test.local'],
            auth: false,
        );
        $this->assertJsonStatus(200, $response);
    }

    public function testRequestResetWithMissingEmailReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/request-reset', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    // ── POST /api/auth/reset-password ─────────────────────────────────────

    public function testResetPasswordWithValidTokenReturns200(): void
    {
        $plainToken = $this->createPasswordResetToken();

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/reset-password',
            ['token' => $plainToken, 'newPassword' => 'NewPass123!'],
            auth: false,
        );
        $this->assertJsonStatus(200, $response);
    }

    public function testResetPasswordWithInvalidTokenReturns400(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/reset-password',
            ['token' => 'invalid-token', 'newPassword' => 'NewPass123!'],
            auth: false,
        );
        $this->assertJsonStatus(400, $response);
    }

    public function testResetPasswordWithMissingFieldsReturns400(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/reset-password', [], auth: false);
        $this->assertJsonStatus(400, $response);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function createEmailVerificationToken(): string
    {
        return $this->persistAuthToken(
            UserStatusEnum::PendingEmailVerification,
            AuthTokenTypeEnum::EmailVerification,
        );
    }

    private function createPasswordResetToken(): string
    {
        return $this->persistAuthToken(
            UserStatusEnum::Active,
            AuthTokenTypeEnum::PasswordReset,
        );
    }

    private function persistAuthToken(UserStatusEnum $userStatus, AuthTokenTypeEnum $tokenType): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $generator = new SecureTokenGenerator();
        $plainToken = $generator->generate();
        $tokenHash  = $generator->hash($plainToken);

        $user = UserFixtureFactory::createActiveUser(
            static::getContainer(),
            email: 'token-user-' . uniqid() . '@test.local',
        );

        // Override status after creation
        $user->status = $userStatus;

        $authToken = new AuthToken(
            id: Uuid::v4()->toRfc4122(),
            user: $user,
            type: $tokenType,
            tokenHash: $tokenHash,
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $entityManager->persist($authToken);
        $entityManager->flush();

        return $plainToken;
    }
}
