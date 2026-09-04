<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDiscordWebhookUrlException;
use App\Shared\Domain\ValueObject\DiscordWebhookUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscordWebhookUrlTest extends TestCase
{
    #[DataProvider('acceptedUrls')]
    public function testAcceptsDiscordWebhookUrls(string $url): void
    {
        self::assertTrue(DiscordWebhookUrl::isValid($url));
        self::assertSame($url, DiscordWebhookUrl::fromString($url)->value);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedUrls(): iterable
    {
        yield 'discord.com'        => ['https://discord.com/api/webhooks/123/abcDEF-_'];
        yield 'canary subdomain'   => ['https://canary.discord.com/api/webhooks/123/token'];
        yield 'ptb subdomain'      => ['https://ptb.discord.com/api/webhooks/123/token'];
        yield 'legacy discordapp'  => ['https://discordapp.com/api/webhooks/123/token'];
        yield 'uppercase host'     => ['https://Discord.com/api/webhooks/123/token'];
    }

    #[DataProvider('rejectedUrls')]
    public function testRejectsEverythingElse(string $url): void
    {
        self::assertFalse(DiscordWebhookUrl::isValid($url));
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedUrls(): iterable
    {
        yield 'empty'               => [''];
        yield 'not a url'           => ['not-a-url'];
        yield 'plain http'          => ['http://discord.com/api/webhooks/123/token'];
        yield 'unrelated host'      => ['https://attacker.example/api/webhooks/123/token'];
        yield 'suffix lookalike'    => ['https://discord.com.attacker.example/api/webhooks/1/t'];
        yield 'prefix lookalike'    => ['https://notdiscord.com/api/webhooks/1/t'];
        yield 'userinfo smuggling'  => ['https://discord.com@attacker.example/api/webhooks/1/t'];
        yield 'explicit port'       => ['https://discord.com:8443/api/webhooks/1/t'];
        yield 'wrong path'          => ['https://discord.com/api/users/@me'];
        yield 'path prefix only'    => ['https://discord.com/api/webhooks'];
        yield 'loopback'            => ['https://127.0.0.1/api/webhooks/1/t'];
        yield 'cloud metadata'      => ['http://169.254.169.254/latest/meta-data/'];
        yield 'file scheme'         => ['file:///etc/passwd'];
        yield 'gopher scheme'       => ['gopher://discord.com/api/webhooks/1/t'];
        yield 'unparseable'         => ['http://:80'];
    }

    public function testFromStringThrowsOnInvalidUrl(): void
    {
        $this->expectException(InvalidDiscordWebhookUrlException::class);

        DiscordWebhookUrl::fromString('https://attacker.example/api/webhooks/1/t');
    }

    public function testExceptionMapsToUnprocessableEntity(): void
    {
        self::assertSame(422, (new InvalidDiscordWebhookUrlException())->getHttpStatusCode());
    }
}
