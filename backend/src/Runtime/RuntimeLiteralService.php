<?php

namespace App\Runtime;

use App\Repository\SystemLiteralTranslationRepository;

class RuntimeLiteralService
{
    public function __construct(
        private readonly SystemLiteralTranslationRepository $translations,
    ) {
    }

    /**
     * @return array{locale: string, literals: array<string, string>, count: int}
     */
    public function bundle(string $locale): array
    {
        $normalizedLocale = trim($locale) !== '' ? trim($locale) : 'pt-BR';
        $rows = $this->translations->findEnabledByLocale($normalizedLocale);
        $literals = [];
        foreach ($rows as $row) {
            $code = trim($row->getCode());
            if ($code === '') {
                continue;
            }
            $literals[$code] = $row->getText();
        }

        return [
            'locale' => $normalizedLocale,
            'literals' => $literals,
            'count' => count($literals),
        ];
    }
}
