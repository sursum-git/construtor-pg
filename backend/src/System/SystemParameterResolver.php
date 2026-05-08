<?php

namespace App\System;

use App\Entity\SystemParameter;
use App\Repository\SystemOptionRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\SystemParameterValueRepository;
use App\Runtime\RuntimeHttpException;

class SystemParameterResolver
{
    public function __construct(
        private readonly SystemParameterRepository $parameters,
        private readonly SystemParameterValueRepository $values,
        private readonly SystemOptionRepository $options,
    ) {
    }

    public function get(string $code, ?string $establishmentCode = null): mixed
    {
        $parameter = $this->parameters->findEnabledByCode($code);
        if (!$parameter) {
            throw new RuntimeHttpException('PARAMETER_NOT_FOUND', 'Parametro nao encontrado.', 404, [
                'code' => $code,
            ]);
        }

        $value = $this->values->findBestValue($parameter, $establishmentCode)?->getValue() ?? $parameter->getDefaultValue();

        return $this->normalizeValue($parameter, $value);
    }

    public function getBoolean(string $code, ?string $establishmentCode = null): bool
    {
        $value = $this->get($code, $establishmentCode);
        if (!is_bool($value)) {
            throw new RuntimeHttpException('PARAMETER_TYPE_MISMATCH', 'Parametro nao e booleano.', 500, [
                'code' => $code,
            ]);
        }

        return $value;
    }

    public function getOption(string $code, ?string $establishmentCode = null): ?string
    {
        $value = $this->get($code, $establishmentCode);
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeHttpException('PARAMETER_TYPE_MISMATCH', 'Parametro nao e uma opcao.', 500, [
                'code' => $code,
            ]);
        }

        return $value;
    }

    /**
     * @return string[]
     */
    public function getOptions(string $code, ?string $establishmentCode = null): array
    {
        $value = $this->get($code, $establishmentCode);
        if (!is_array($value)) {
            throw new RuntimeHttpException('PARAMETER_TYPE_MISMATCH', 'Parametro nao e lista de opcoes.', 500, [
                'code' => $code,
            ]);
        }

        return array_values(array_map('strval', $value));
    }

    private function normalizeValue(SystemParameter $parameter, mixed $value): mixed
    {
        if ($this->isBlank($value)) {
            if ($parameter->isRequired()) {
                throw new RuntimeHttpException('PARAMETER_VALUE_REQUIRED', 'Valor do parametro e obrigatorio.', 422, [
                    'code' => $parameter->getCode(),
                ]);
            }

            return null;
        }

        return match ($parameter->getDataType()) {
            'string', 'text' => $this->normalizeString($parameter, $value),
            'integer' => $this->normalizeInteger($parameter, $value),
            'decimal' => $this->normalizeDecimal($parameter, $value),
            'boolean' => $this->normalizeBoolean($parameter, $value),
            'date' => $this->normalizeDate($parameter, $value),
            'datetime' => $this->normalizeDateTime($parameter, $value),
            'json' => $value,
            'option' => $this->normalizeOption($parameter, $value),
            'multi_option' => $this->normalizeMultiOption($parameter, $value),
            default => throw new RuntimeHttpException('PARAMETER_TYPE_UNSUPPORTED', 'Tipo de parametro nao suportado.', 422, [
                'code' => $parameter->getCode(),
                'dataType' => $parameter->getDataType(),
            ]),
        };
    }

    private function normalizeString(SystemParameter $parameter, mixed $value): string
    {
        if (!is_scalar($value)) {
            $this->invalidValue($parameter, 'Valor textual invalido.');
        }

        return (string) $value;
    }

    private function normalizeInteger(SystemParameter $parameter, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int) $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        $this->invalidValue($parameter, 'Valor inteiro invalido.');
    }

    private function normalizeDecimal(SystemParameter $parameter, mixed $value): float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric(str_replace(',', '.', $value)))) {
            $this->invalidValue($parameter, 'Valor decimal invalido.');
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function normalizeBoolean(SystemParameter $parameter, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) && in_array($value, [0, 1], true)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'sim', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'nao', 'não', 'no', 'off'], true)) {
                return false;
            }
        }

        $this->invalidValue($parameter, 'Valor booleano invalido.');
    }

    private function normalizeDate(SystemParameter $parameter, mixed $value): string
    {
        $date = $this->parseDate($parameter, $value);

        return $date->format('Y-m-d');
    }

    private function normalizeDateTime(SystemParameter $parameter, mixed $value): string
    {
        $date = $this->parseDate($parameter, $value);

        return $date->format(DATE_ATOM);
    }

    private function normalizeOption(SystemParameter $parameter, mixed $value): string
    {
        if (!is_scalar($value)) {
            $this->invalidValue($parameter, 'Opcao invalida.');
        }
        $code = trim((string) $value);
        $this->assertOptionList($parameter);
        if (!$this->options->findActiveByCode($parameter->getOptionList(), $code)) {
            throw new RuntimeHttpException('PARAMETER_OPTION_INVALID', 'Opcao do parametro nao encontrada ou inativa.', 422, [
                'code' => $parameter->getCode(),
                'option' => $code,
            ]);
        }

        return $code;
    }

    /**
     * @return string[]
     */
    private function normalizeMultiOption(SystemParameter $parameter, mixed $value): array
    {
        if (!is_array($value)) {
            $this->invalidValue($parameter, 'Lista de opcoes invalida.');
        }
        $codes = array_values(array_unique(array_map('strval', $value)));
        $this->assertOptionList($parameter);
        $active = $this->options->findActiveByCodes($parameter->getOptionList(), $codes);
        $activeCodes = array_fill_keys(array_map(fn ($option) => $option->getCode(), $active), true);
        $invalid = array_values(array_filter($codes, fn (string $code): bool => !isset($activeCodes[$code])));
        if ($invalid) {
            throw new RuntimeHttpException('PARAMETER_OPTION_INVALID', 'Opcao do parametro nao encontrada ou inativa.', 422, [
                'code' => $parameter->getCode(),
                'options' => $invalid,
            ]);
        }

        return $codes;
    }

    private function assertOptionList(SystemParameter $parameter): void
    {
        if (!$parameter->getOptionList()) {
            throw new RuntimeHttpException('PARAMETER_OPTION_LIST_REQUIRED', 'Parametro exige lista de opcoes vinculada.', 422, [
                'code' => $parameter->getCode(),
                'dataType' => $parameter->getDataType(),
            ]);
        }
        if (!$parameter->getOptionList()->isEnabled()) {
            throw new RuntimeHttpException('PARAMETER_OPTION_LIST_DISABLED', 'Lista de opcoes do parametro esta inativa.', 422, [
                'code' => $parameter->getCode(),
                'optionList' => $parameter->getOptionList()->getCode(),
            ]);
        }
    }

    private function parseDate(SystemParameter $parameter, mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return new \DateTimeImmutable($value->format(DATE_ATOM));
        }
        if (!is_scalar($value)) {
            $this->invalidValue($parameter, 'Data invalida.');
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            $this->invalidValue($parameter, 'Data invalida.');
        }
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function invalidValue(SystemParameter $parameter, string $message): never
    {
        throw new RuntimeHttpException('PARAMETER_VALUE_INVALID', $message, 422, [
            'code' => $parameter->getCode(),
            'dataType' => $parameter->getDataType(),
        ]);
    }
}
