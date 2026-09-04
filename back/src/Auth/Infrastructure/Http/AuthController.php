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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final readonly class AuthController
{
    /**
     * These endpoints are public, so they are the app's credential-guessing and
     * mail-flooding surface. Limits are keyed on the email in the payload, not
     * on the client IP: the app runs behind a proxy with no trusted-proxy
     * configuration, so every request reports the same IP and an IP key would
     * be one bucket shared by every client.
     *
     * verify-email and reset-password are deliberately not limited here: the
     * only identity in those payloads is the token itself, so a per-token key
     * would not slow an attacker down (each guess carries a different token),
     * and the tokens are 256 bits of random — see SecureTokenGenerator.
     */
    private const int LOGIN_LIMIT          = 10;
    private const int LOGIN_WINDOW         = 300;
    private const int REGISTER_LIMIT       = 5;
    private const int REGISTER_WINDOW      = 3600;
    private const int REQUEST_RESET_LIMIT  = 5;
    private const int REQUEST_RESET_WINDOW = 3600;

    public function __construct(
        private CommandBusInterface $commandBus,
        private CacheRateLimiter $rateLimiter,
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $request): JsonResponse
    {
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
    public function login(#[MapRequestPayload] LoginRequest $request): JsonResponse
    {
        $this->rateLimiter->consume(
            'auth_login:' . mb_strtolower($request->email),
            self::LOGIN_LIMIT,
            self::LOGIN_WINDOW,
        );

        $token = $this->commandBus->dispatch(new LoginCommand(
            email: $request->email,
            password: $request->password,
        ));

        return new JsonResponse(['token' => $token]);
    }

    #[Route('/request-reset', methods: ['POST'])]
    public function requestReset(#[MapRequestPayload] RequestResetRequest $request): JsonResponse
    {
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
}
