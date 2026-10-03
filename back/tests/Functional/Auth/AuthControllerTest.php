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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
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

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'new@test.local');
        self::assertEmailHtmlBodyContains($email, 'verify-email?token=');
    }

    public function testRegisterWithAnExistingEmailAnswersLikeANewRegistration(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'dup@test.local', displayName: 'Dup Owner');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'dup@test.local', 'password' => 'Password1!', 'displayName' => 'Someone Else'],
            auth: false,
        );
        $data = $this->assertJsonStatus(201, $response);

        // The very answer of a new registration: nothing tells the address is taken.
        $this->assertSame('Registration successful. Please check your email to verify your account.', $data['message']);
        // The owner is told by email, with a way in — never a verification link.
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'dup@test.local');
        self::assertEmailHtmlBodyContains($email, 'Dup Owner');
        self::assertEmailHtmlBodyContains($email, '/forgot-password');
        self::assertEmailHtmlBodyNotContains($email, 'verify-email?token=');
        // And the existing account is untouched.
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $accounts = $entityManager->getRepository(User::class)->findBy(['email' => 'dup@test.local']);
        $this->assertCount(1, $accounts);
        $this->assertSame('Dup Owner', $accounts[0]->displayName);
        $this->assertSame(UserStatusEnum::Active, $accounts[0]->status);
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
        // Silent no-op for unknown email — no reset email leaked
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/request-reset',
            ['email' => 'nobody@test.local'],
            auth: false,
        );
        $this->assertJsonStatus(200, $response);
        self::assertEmailCount(0);
    }

    public function testRequestResetWithKnownEmailSendsResetEmail(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'resetmail@test.local');

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/request-reset',
            ['email' => 'resetmail@test.local'],
            auth: false,
        );
        $this->assertJsonStatus(200, $response);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'resetmail@test.local');
        self::assertEmailHtmlBodyContains($email, 'reset-password?token=');
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

    // ── Rate limiting ──────────────────────────────────────────────────────

    public function testRepeatedLoginFailuresAreRateLimited(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'bruteforce@test.local');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->jsonRequest(
                'POST',
                '/api/auth/login',
                ['email' => 'bruteforce@test.local', 'password' => 'wrong-password'],
                auth: false,
            );

            self::assertSame(
                401,
                $response->getStatusCode(),
                sprintf('Attempt %d should still be allowed through to the credential check.', $attempt),
            );
        }

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'bruteforce@test.local', 'password' => 'wrong-password'],
            auth: false,
        );
        $this->assertJsonStatus(429, $response);
    }

    public function testLoginRateLimitIsScopedToTheTargetedEmail(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'victim@test.local');
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'bystander@test.local');

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $this->jsonRequest(
                'POST',
                '/api/auth/login',
                ['email' => 'victim@test.local', 'password' => 'wrong-password'],
                auth: false,
            );
        }

        // Another account must not be locked out by the attack on the first one.
        $response = $this->jsonRequest(
            'POST',
            '/api/auth/login',
            ['email' => 'bystander@test.local', 'password' => 'Test1234!'],
            auth: false,
        );
        $this->assertJsonStatus(200, $response);
    }

    public function testFailuresFromAnotherAddressNeverLockTheOwnerOut(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'target@test.local');

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->assertSame(401, $this->failedLoginFrom('203.0.113.10', 'target@test.local')->getStatusCode());
        }
        $this->assertJsonStatus(429, $this->failedLoginFrom('203.0.113.10', 'target@test.local'));

        // The owner, from their own address, is not blocked by someone else's guesses.
        $this->assertJsonStatus(200, $this->loginFrom('198.51.100.20', 'target@test.local', 'Test1234!'));
    }

    public function testASuccessfulLoginClearsTheFailedAttempts(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'forgetful@test.local');

        for ($attempt = 1; $attempt <= 9; $attempt++) {
            $this->failedLoginFrom('198.51.100.30', 'forgetful@test.local');
        }
        $this->assertJsonStatus(200, $this->loginFrom('198.51.100.30', 'forgetful@test.local', 'Test1234!'));

        // The count starts over: ten more failures are still checked, the next is refused.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->assertSame(401, $this->failedLoginFrom('198.51.100.30', 'forgetful@test.local')->getStatusCode());
        }
        $this->assertJsonStatus(429, $this->failedLoginFrom('198.51.100.30', 'forgetful@test.local'));
    }

    public function testOneAddressCannotSweepManyAccounts(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $email = sprintf('sweep-%d@test.local', $attempt);
            $this->assertSame(401, $this->failedLoginFrom('203.0.113.40', $email)->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->failedLoginFrom('203.0.113.40', 'sweep-31@test.local'));
        // Other clients are not affected.
        $this->assertSame(401, $this->failedLoginFrom('203.0.113.41', 'sweep-31@test.local')->getStatusCode());
    }

    public function testAForwardedForSentFromTheInternetIsIgnored(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'spoofed@test.local');

        // A client reaching the backend directly (public address) forging a new
        // X-Forwarded-For on each try is still counted on its real address.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $forgedAddress = sprintf('10.0.0.%d', $attempt);
            $response = $this->failedLoginFrom($forgedAddress, 'spoofed@test.local', remoteAddress: '203.0.113.50');
            $this->assertSame(401, $response->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->failedLoginFrom('10.0.0.99', 'spoofed@test.local', remoteAddress: '203.0.113.50'));
    }

    public function testRegistrationsFromOneAddressAreLimited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->postFrom('203.0.113.60', '/api/auth/register', [
                'email' => sprintf('signup-%d@test.local', $attempt), 'password' => 'Password1!', 'displayName' => 'Signup',
            ]);
            $this->assertSame(201, $response->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->postFrom('203.0.113.60', '/api/auth/register', [
            'email' => 'signup-11@test.local', 'password' => 'Password1!', 'displayName' => 'Signup',
        ]));
    }

    public function testResetRequestsFromOneAddressAreLimited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->postFrom('203.0.113.70', '/api/auth/request-reset', [
                'email' => sprintf('reset-%d@test.local', $attempt),
            ]);
            $this->assertSame(200, $response->getStatusCode());
        }

        $this->assertJsonStatus(429, $this->postFrom('203.0.113.70', '/api/auth/request-reset', ['email' => 'reset-11@test.local']));
    }

    public function testRepeatedResetRequestsAreRateLimited(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'mailbomb@test.local');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->jsonRequest(
                'POST',
                '/api/auth/request-reset',
                ['email' => 'mailbomb@test.local'],
                auth: false,
            );
            $this->assertJsonStatus(200, $response);
        }

        $response = $this->jsonRequest(
            'POST',
            '/api/auth/request-reset',
            ['email' => 'mailbomb@test.local'],
            auth: false,
        );
        $this->assertJsonStatus(429, $response);
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

    /**
     * The request as the frontend nginx forwards it: from a private-network hop
     * (127.0.0.1 in tests, trusted) carrying the client address in X-Forwarded-For.
     * $remoteAddress replaces that hop, e.g. with a public address that is not trusted.
     *
     * @param array<string, string> $body
     */
    private function postFrom(string $clientIp, string $url, array $body, string $remoteAddress = '127.0.0.1'): Response
    {
        $this->client->request('POST', $url, [], [], [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_ACCEPT'          => 'application/json',
            'HTTP_X_FORWARDED_FOR' => $clientIp,
            'REMOTE_ADDR'          => $remoteAddress,
        ], (string) json_encode($body));

        return $this->client->getResponse();
    }

    private function loginFrom(string $clientIp, string $email, string $password, string $remoteAddress = '127.0.0.1'): Response
    {
        return $this->postFrom($clientIp, '/api/auth/login', ['email' => $email, 'password' => $password], $remoteAddress);
    }

    /** A login with a wrong password. */
    private function failedLoginFrom(string $clientIp, string $email, string $remoteAddress = '127.0.0.1'): Response
    {
        return $this->postFrom($clientIp, '/api/auth/login', ['email' => $email, 'password' => 'wrong-password'], $remoteAddress);
    }
}
