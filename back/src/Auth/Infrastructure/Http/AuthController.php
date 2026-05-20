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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final readonly class AuthController
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $request): JsonResponse
    {
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
        $token = $this->commandBus->dispatch(new LoginCommand(
            email: $request->email,
            password: $request->password,
        ));

        return new JsonResponse(['token' => $token]);
    }

    #[Route('/request-reset', methods: ['POST'])]
    public function requestReset(#[MapRequestPayload] RequestResetRequest $request): JsonResponse
    {
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
