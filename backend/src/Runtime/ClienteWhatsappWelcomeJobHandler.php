<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;

class ClienteWhatsappWelcomeJobHandler implements RuntimeJobHandlerInterface
{
    public function supports(string $jobType): bool
    {
        return $jobType === 'cliente.whatsapp_welcome';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $telefone = $this->normalizePhone($payload['telefone'] ?? null);
        if ($telefone === '') {
            throw new \RuntimeException('Telefone do cliente nao informado para WhatsApp.');
        }

        return [
            'delivery' => 'whatsapp',
            'mode' => 'prepared',
            'to' => $telefone,
            'message' => sprintf('Mensagem de WhatsApp preparada para %s.', (string) ($payload['nome'] ?? 'cliente')),
        ];
    }

    private function normalizePhone(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
