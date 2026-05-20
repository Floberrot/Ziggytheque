<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Listener;

use App\Auth\Shared\Event\PasswordResetRequestedEvent;
use App\Auth\Shared\Event\UserApprovedEvent;
use App\Auth\Shared\Event\UserRegisteredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

/**
 * Sends the transactional auth emails (verification, password reset, approval).
 *
 * Dispatched synchronously by SymfonyEventBus. A delivery failure is logged but
 * never bubbles up — it must not roll back the user-facing action that triggered it.
 */
#[AsEventListener(event: UserRegisteredEvent::class, method: 'onUserRegistered')]
#[AsEventListener(event: PasswordResetRequestedEvent::class, method: 'onPasswordResetRequested')]
#[AsEventListener(event: UserApprovedEvent::class, method: 'onUserApproved')]
final readonly class AuthEmailListener
{
    private const FROM = 'Ziggytheque <noreply@ziggytheque.local>';

    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $frontUrl,
    ) {
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $verificationUrl = rtrim($this->frontUrl, '/')
            . '/verify-email?token=' . $event->verificationTokenPlain;

        $this->send(
            $event->email,
            'Vérifiez votre adresse email — Ziggytheque',
            'emails/auth/email_verification.html.twig',
            ['displayName' => $event->displayName, 'verificationUrl' => $verificationUrl],
        );
    }

    public function onPasswordResetRequested(PasswordResetRequestedEvent $event): void
    {
        $this->send(
            $event->email,
            'Réinitialisation de votre mot de passe — Ziggytheque',
            'emails/auth/password_reset.html.twig',
            ['resetUrl' => $event->resetUrl],
        );
    }

    public function onUserApproved(UserApprovedEvent $event): void
    {
        $this->send(
            $event->email,
            'Votre compte Ziggytheque est activé',
            'emails/auth/account_approved.html.twig',
            ['displayName' => $event->displayName, 'loginUrl' => rtrim($this->frontUrl, '/') . '/login'],
        );
    }

    /** @param array<string, string> $context */
    private function send(string $recipient, string $subject, string $template, array $context): void
    {
        try {
            $email = (new Email())
                ->from(self::FROM)
                ->to($recipient)
                ->subject($subject)
                ->html($this->twig->render($template, $context));

            $this->mailer->send($email);
        } catch (Throwable $exception) {
            $this->logger->error('Auth email delivery failed', [
                'template'  => $template,
                'recipient' => $recipient,
                'error'     => $exception->getMessage(),
            ]);
        }
    }
}
