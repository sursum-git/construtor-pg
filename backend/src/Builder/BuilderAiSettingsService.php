<?php

namespace App\Builder;

use App\Entity\SystemParameter;
use App\Entity\SystemParameterValue;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use App\Runtime\RuntimeHttpException;
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;

class BuilderAiSettingsService
{
    public const SECRET_SENTINEL = '__SECRET_CONFIGURED__';

    private const PARAMS = [
        'enabled' => 'ai.builder.enabled',
        'provider' => 'ai.builder.provider',
        'agentName' => 'ai.builder.agent_name',
        'baseUrl' => 'ai.builder.base_url',
        'model' => 'ai.builder.model',
        'apiToken' => 'ai.builder.api_token',
        'transcriptionEnabled' => 'ai.builder.transcription_enabled',
        'transcriptionModel' => 'ai.builder.transcription_model',
    ];

    public function __construct(
        private readonly SystemParameterResolver $resolver,
        private readonly SystemParameterRepository $parameters,
        private readonly SystemParameterValueRepository $values,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getUiSettings(): array
    {
        $settings = $this->resolveSettings();

        return [
            'enabled' => $settings['enabled'],
            'provider' => $settings['provider'],
            'agentName' => $settings['agentName'],
            'baseUrl' => $settings['baseUrl'],
            'model' => $settings['model'],
            'apiTokenConfigured' => $settings['apiToken'] !== '',
            'apiTokenMaskedValue' => $settings['apiToken'] !== '' ? self::SECRET_SENTINEL : '',
            'transcriptionEnabled' => $settings['transcriptionEnabled'],
            'transcriptionModel' => $settings['transcriptionModel'],
        ];
    }

    public function resolveOperationalSettings(): array
    {
        $settings = $this->resolveSettings();
        if (!$settings['enabled']) {
            throw new RuntimeHttpException('BUILDER_AI_DISABLED', 'O assistente de IA do construtor esta desabilitado.', 422);
        }
        if (!in_array($settings['provider'], ['mock', 'openai_compatible'], true)) {
            throw new RuntimeHttpException('BUILDER_AI_PROVIDER_INVALID', 'Provedor do assistente de IA invalido.', 422, [
                'provider' => $settings['provider'],
            ]);
        }
        if ($settings['provider'] === 'openai_compatible') {
            if ($settings['baseUrl'] === '' || $settings['model'] === '' || $settings['apiToken'] === '') {
                throw new RuntimeHttpException('BUILDER_AI_PROVIDER_NOT_CONFIGURED', 'Configure base URL, modelo e token para usar o provedor real.', 422);
            }
            if ($settings['transcriptionEnabled'] && $settings['transcriptionModel'] === '') {
                throw new RuntimeHttpException('BUILDER_AI_TRANSCRIPTION_MODEL_REQUIRED', 'Informe o modelo de transcricao para usar audio no provedor real.', 422);
            }
        }

        return $settings;
    }

    public function saveUiSettings(array $payload): array
    {
        $enabled = ($payload['enabled'] ?? false) === true;
        $provider = strtolower(trim((string) ($payload['provider'] ?? 'mock')));
        $agentName = trim((string) ($payload['agentName'] ?? 'Assistente do construtor'));
        $baseUrl = trim((string) ($payload['baseUrl'] ?? ''));
        $model = trim((string) ($payload['model'] ?? ''));
        $transcriptionEnabled = ($payload['transcriptionEnabled'] ?? false) === true;
        $transcriptionModel = trim((string) ($payload['transcriptionModel'] ?? ''));
        $clearApiToken = ($payload['clearApiToken'] ?? false) === true;
        $apiTokenInput = array_key_exists('apiToken', $payload) ? trim((string) $payload['apiToken']) : null;

        if (!in_array($provider, ['mock', 'openai_compatible'], true)) {
            throw new RuntimeHttpException('BUILDER_AI_PROVIDER_INVALID', 'Escolha um provedor de IA suportado.', 422, [
                'provider' => $provider,
            ]);
        }

        $this->upsertValue(self::PARAMS['enabled'], $enabled);
        $this->upsertValue(self::PARAMS['provider'], $provider);
        $this->upsertValue(self::PARAMS['agentName'], $agentName !== '' ? $agentName : 'Assistente do construtor');
        $this->upsertValue(self::PARAMS['baseUrl'], $baseUrl !== '' ? $baseUrl : null);
        $this->upsertValue(self::PARAMS['model'], $model !== '' ? $model : null);
        $this->upsertValue(self::PARAMS['transcriptionEnabled'], $transcriptionEnabled);
        $this->upsertValue(self::PARAMS['transcriptionModel'], $transcriptionModel !== '' ? $transcriptionModel : null);

        if ($clearApiToken) {
            $this->upsertValue(self::PARAMS['apiToken'], null);
        } elseif ($apiTokenInput !== null && $apiTokenInput !== '' && $apiTokenInput !== self::SECRET_SENTINEL) {
            $this->upsertValue(self::PARAMS['apiToken'], $apiTokenInput);
        }

        $this->entityManager->flush();

        return $this->getUiSettings();
    }

    public function isSecretParameterCode(string $code): bool
    {
        return in_array($code, [self::PARAMS['apiToken'], 'ai.builder.public_context_key'], true);
    }

    public function shouldPreserveSecretValue(string $code, mixed $value): bool
    {
        if (!$this->isSecretParameterCode($code)) {
            return false;
        }

        return trim((string) $value) === self::SECRET_SENTINEL;
    }

    public function maskSecretValue(string $code, mixed $value): mixed
    {
        if (!$this->isSecretParameterCode($code)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return self::SECRET_SENTINEL;
    }

    public function findParameterCodeById(int|string|null $parameterId): ?string
    {
        $id = (int) $parameterId;
        if ($id <= 0) {
            return null;
        }
        $parameter = $this->parameters->find($id);
        return $parameter?->getCode();
    }

    private function resolveSettings(): array
    {
        return [
            'enabled' => $this->safeBoolean(self::PARAMS['enabled'], false),
            'provider' => $this->safeString(self::PARAMS['provider'], 'mock'),
            'agentName' => $this->safeString(self::PARAMS['agentName'], 'Assistente do construtor'),
            'baseUrl' => $this->safeString(self::PARAMS['baseUrl'], ''),
            'model' => $this->safeString(self::PARAMS['model'], ''),
            'apiToken' => $this->safeString(self::PARAMS['apiToken'], ''),
            'transcriptionEnabled' => $this->safeBoolean(self::PARAMS['transcriptionEnabled'], false),
            'transcriptionModel' => $this->safeString(self::PARAMS['transcriptionModel'], ''),
        ];
    }

    private function safeString(string $code, string $default): string
    {
        try {
            $value = $this->resolver->get($code);
        } catch (\Throwable) {
            return $default;
        }

        if (!is_scalar($value) || $value === null) {
            return $default;
        }

        return trim((string) $value);
    }

    private function safeBoolean(string $code, bool $default): bool
    {
        try {
            return $this->resolver->getBoolean($code);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function upsertValue(string $parameterCode, mixed $value): void
    {
        $parameter = $this->parameters->findEnabledByCode($parameterCode);
        if (!$parameter) {
            throw new RuntimeHttpException('BUILDER_AI_PARAMETER_NOT_FOUND', 'Parametro do assistente de IA nao encontrado.', 500, [
                'parameterCode' => $parameterCode,
            ]);
        }

        $row = $this->values->findBestValue($parameter, null) ?? new SystemParameterValue();
        $row
            ->setParameter($parameter)
            ->setEstablishmentCode(null)
            ->setStartsAt($row->getId() ? $row->getStartsAt() : new \DateTimeImmutable('2000-01-01 00:00:00'))
            ->setEndsAt(null)
            ->setValue($value)
            ->setEnabled(true);

        $this->entityManager->persist($row);
    }
}
