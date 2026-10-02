<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Auth\Domain\UserRoleEnum;
use App\Auth\Domain\UserStatusEnum;
use App\Tests\Functional\AbstractApiTestCase;
use App\Tests\Functional\Fixtures\UserFixtureFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * A JWT is valid until it expires; what the account may do is read again on every
 * request, so a change made by the admin applies to a token already handed out.
 */
final class AccountAccessTest extends AbstractApiTestCase
{
    /** @return iterable<string, array{UserStatusEnum}> */
    public static function inactiveStatuses(): iterable
    {
        yield 'disabled' => [UserStatusEnum::Disabled];
        yield 'back to pending approval' => [UserStatusEnum::PendingAdminApproval];
        yield 'back to pending email verification' => [UserStatusEnum::PendingEmailVerification];
    }

    #[DataProvider('inactiveStatuses')]
    public function testAnAccountNoLongerActiveLosesAccessWithItsExistingToken(UserStatusEnum $status): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'reader@test.local');
        $readerToken = $this->tokenForUser('reader@test.local');

        $this->assertSame(200, $this->requestWithToken($readerToken, '/api/collection')->getStatusCode());

        $this->changeAccount('reader@test.local', 'status', $status->value);

        $this->assertSame(401, $this->requestWithToken($readerToken, '/api/collection')->getStatusCode());
    }

    public function testAnActiveAccountKeepsAccess(): void
    {
        UserFixtureFactory::createActiveUser(static::getContainer(), email: 'reader@test.local');
        $readerToken = $this->tokenForUser('reader@test.local');

        $this->assertSame(200, $this->requestWithToken($readerToken, '/api/collection')->getStatusCode());
        $this->assertSame(200, $this->requestWithToken($readerToken, '/api/stats')->getStatusCode());
    }

    public function testADemotedAdminLosesTheUnlockedAdminArea(): void
    {
        $gate = $this->assertJsonStatus(200, $this->jsonRequest('POST', '/api/auth/gate', ['password' => 'test-gate-password']));
        $unlockedToken = (string) $gate['token'];

        $this->assertSame(200, $this->requestWithToken($unlockedToken, '/api/admin/users')->getStatusCode());

        $this->changeAccount('admin@test.local', 'role', UserRoleEnum::User->value);

        $this->assertSame(403, $this->requestWithToken($unlockedToken, '/api/admin/users')->getStatusCode());
        // Still a valid account: only the admin area is gone.
        $this->assertSame(200, $this->requestWithToken($unlockedToken, '/api/collection')->getStatusCode());
    }

    public function testTheTokenLastsJwtTtl(): void
    {
        $payloadPart = explode('.', $this->token)[1] ?? '';
        /** @var array{iat: int, exp: int} $payload */
        $payload = json_decode((string) base64_decode(strtr($payloadPart, '-_', '+/'), true), true);

        $expectedTtl = (int) ($_SERVER['JWT_TTL'] ?? $_ENV['JWT_TTL'] ?? 0);
        $this->assertGreaterThan(0, $expectedTtl);
        $this->assertSame($expectedTtl, $payload['exp'] - $payload['iat']);
    }

    private function requestWithToken(string $token, string $url): Response
    {
        $this->client->request('GET', $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);

        return $this->client->getResponse();
    }

    /** What an admin edit does, straight in the table: the token is not reissued. */
    private function changeAccount(string $email, string $column, string $value): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            sprintf('UPDATE users SET %s = :value WHERE email = :email', $column === 'role' ? 'role' : 'status'),
            ['value' => $value, 'email' => $email],
        );
    }
}
