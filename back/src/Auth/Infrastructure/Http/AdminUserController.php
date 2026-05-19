<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Admin\ApproveUser\ApproveUserCommand;
use App\Auth\Application\Admin\DeleteUser\DeleteUserCommand;
use App\Auth\Application\Admin\GenerateResetLink\GenerateResetLinkCommand;
use App\Auth\Application\Admin\GetUser\GetUserQuery;
use App\Auth\Application\Admin\ListUsers\ListUsersQuery;
use App\Auth\Application\Admin\UpdateUser\UpdateUserCommand;
use App\Auth\Domain\User;
use App\Auth\Domain\UserStatusEnum;
use App\Auth\Infrastructure\Http\Request\UpdateUserRequest;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN_UNLOCKED')]
final readonly class AdminUserController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] string $search = '',
        #[MapQueryParameter] ?string $status = null,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 20,
    ): JsonResponse {
        $statusEnum = $status !== null ? UserStatusEnum::from($status) : null;

        $result = $this->queryBus->dispatch(new ListUsersQuery(
            search: $search,
            status: $statusEnum,
            page: $page,
            limit: $limit,
        ));

        return new JsonResponse($result->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->queryBus->dispatch(new GetUserQuery($id));

        return new JsonResponse($user->toArray());
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateUserRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->commandBus->dispatch(new UpdateUserCommand(
            userId: $id,
            displayName: $request->displayName,
            status: $request->status,
            notificationChannel: $request->notificationChannel,
            notificationEmail: $request->notificationEmail,
            discordWebhookUrl: $request->discordWebhookUrl,
        ));

        return new JsonResponse($user->toArray());
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new ApproveUserCommand($id));

        return new JsonResponse(['message' => 'User approved.']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteUserCommand($id));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/reset-link', methods: ['POST'])]
    public function generateResetLink(string $id): JsonResponse
    {
        $resetLink = $this->commandBus->dispatch(new GenerateResetLinkCommand($id));

        return new JsonResponse(['resetLink' => $resetLink]);
    }
}
