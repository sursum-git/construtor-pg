<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ClienteEmailConfirmationJobHandler implements RuntimeJobHandlerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'cliente.email_confirmation';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $to = trim((string) ($payload['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('E-mail do cliente invalido para confirmacao.');
        }

        $nome = trim((string) ($payload['nome'] ?? 'Cliente'));
        $subject = 'Confirme seu endereco de e-mail';
        $body = sprintf(
            "Ola, %s.\n\nSeu cadastro foi criado. Esta etapa registra o envio de confirmacao do endereco %s.\n",
            $nome !== '' ? $nome : 'Cliente',
            $to,
        );

        $email = (new Email())
            ->from('noreply@construtor-pg.test')
            ->to($to)
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);

        return [
            'delivery' => 'mailer',
            'mode' => 'prepared',
            'to' => $to,
            'subject' => $subject,
        ];
    }
}
