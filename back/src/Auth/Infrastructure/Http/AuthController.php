<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Login\LoginCommand;
use App\Auth\Application\Register\RegisterUserCommand;
use App\Auth\Application\RequestPasswordReset\RequestPasswordResetCommand;
use App\Auth\Application\ResetPassword\ResetPasswordCommand;
use App\Auth\Application\VerifyEmail\VerifyEmailCommand;
use App\Auth\Infrastructure\Http\Request\LoginRequest;
use App\Auth\Infrastructure\Http\Request\RegisterRequest;
use App\Auth\Infrastructure\Http\Request\RequestResetRequest;
use App\Auth\Infrastructure\Http\Request\ResetPasswordRequest;
use App\Auth\Infrastructure\Http\Request\VerifyEmailRequest;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\RateLimit\CacheRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final readonly class AuthController
{
    /**
     * These endpoints are public, so they are the app's credential-guessing and
     * mail-flooding surface. Each one is limited per client IP (the address the
     * frontend nginx got from Railway's edge, trusted from the private network
     * only — see framework.trusted_proxies), so one client cannot sweep many
     * accounts, and per email so one address cannot be flooded.
     *
     * Login failures are counted per email AND client IP, and a successful login
     * clears them: someone typing a victim's email ten times locks out their own
     * address, never the victim's.
     *
     * verify-email and reset-password are deliberately not limited here: the
     * only identity in those payloads is the token itself, so a per-token key
     * would not slow an attacker down (each guess carries a different token),
     * and the tokens are 256 bits of random — see SecureTokenGenerator.
     */
    private const int LOGIN_LIMIT             = 10;
    private const int LOGIN_WINDOW            = 300;
    private const int LOGIN_IP_LIMIT          = 30;
    private const int LOGIN_IP_WINDOW         = 300;
    private const int REGISTER_LIMIT          = 5;
    private const int REGISTER_WINDOW         = 3600;
    private const int REGISTER_IP_LIMIT       = 10;
    private const int REGISTER_IP_WINDOW      = 3600;
    private const int REQUEST_RESET_LIMIT     = 5;
    private const int REQUEST_RESET_WINDOW    = 3600;
    private const int REQUEST_RESET_IP_LIMIT  = 10;
    private const int REQUEST_RESET_IP_WINDOW = 3600;

    public function __construct(
        private CommandBusInterface $commandBus,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $request, Request $httpRequest): JsonResponse
    {
        $this->rateLimiter->consume(
            'auth_register_ip:' . $this->clientIp($httpRequest),
            self::REGISTER_IP_LIMIT,
            self::REGISTER_IP_WINDOW,
        );
        $this->rateLimiter->consume(
            'auth_register:' . mb_strtolower($request->email),
            self::REGISTER_LIMIT,
            self::REGISTER_WINDOW,
        );

        $this->commandBus->dispatch(new RegisterUserCommand(
            email: $request->email,
            password: $request->password,
            displayName: $request->displayName,
        ));

        // Same answer whether or not the address already has an account: the owner
        // of an existing one is told by email instead (no account enumeration).
        return new JsonResponse(
            ['message' => 'Registration successful. Please check your email to verify your account.'],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/verify-email', methods: ['POST'])]
    public function verifyEmail(#[MapRequestPayload] VerifyEmailRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new VerifyEmailCommand($request->token));

        return new JsonResponse(['message' => 'Email verified. Awaiting admin approval.']);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequest $request, Request $httpRequest): JsonResponse
    {
        $clientIp = $this->clientIp($httpRequest);
        $this->rateLimiter->consume('auth_login_ip:' . $clientIp, self::LOGIN_IP_LIMIT, self::LOGIN_IP_WINDOW);

        $attemptsKey = 'auth_login:' . mb_strtolower($request->email) . '|' . $clientIp;
        $this->rateLimiter->consume($attemptsKey, self::LOGIN_LIMIT, self::LOGIN_WINDOW);

        $token = $this->commandBus->dispatch(new LoginCommand(
            email: $request->email,
            password: $request->password,
        ));

        // Only failures count against the account: the owner logging in starts over.
        $this->rateLimiter->reset($attemptsKey);

        return new JsonResponse(['token' => $token]);
    }

    #[Route('/request-reset', methods: ['POST'])]
    public function requestReset(#[MapRequestPayload] RequestResetRequest $request, Request $httpRequest): JsonResponse
    {
        $this->rateLimiter->consume(
            'auth_request_reset_ip:' . $this->clientIp($httpRequest),
            self::REQUEST_RESET_IP_LIMIT,
            self::REQUEST_RESET_IP_WINDOW,
        );
        // Also stops the endpoint being used to mail-bomb an address.
        $this->rateLimiter->consume(
            'auth_request_reset:' . mb_strtolower($request->email),
            self::REQUEST_RESET_LIMIT,
            self::REQUEST_RESET_WINDOW,
        );

        $this->commandBus->dispatch(new RequestPasswordResetCommand($request->email));

        return new JsonResponse(['message' => 'If an account exists for this email, a reset link has been sent.']);
    }

    #[Route('/reset-password', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload] ResetPasswordRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new ResetPasswordCommand(
            token: $request->token,
            newPassword: $request->newPassword,
        ));

        return new JsonResponse(['message' => 'Password updated successfully.']);
    }

    private function clientIp(Request $httpRequest): string
    {
        return $httpRequest->getClientIp() ?? 'unknown';
    }
}
