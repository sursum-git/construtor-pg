<?php

namespace App\Auth;

final class PasswordPolicy
{
    /**
     * @return array{status: 'ok'|'error', message: string}
     */
    public static function evaluateInitialAdminPassword(string $password, string $subscriberCode = '', string $adminUsername = ''): array
    {
        $checks = [
            preg_match('/[a-z]/', $password) === 1,
            preg_match('/[A-Z]/', $password) === 1,
            preg_match('/\d/', $password) === 1,
            preg_match('/[^a-zA-Z0-9]/', $password) === 1,
            mb_strlen($password) >= 14,
            $subscriberCode === '' || stripos($password, $subscriberCode) === false,
            $adminUsername === '' || stripos($password, $adminUsername) === false,
        ];

        if (in_array(false, $checks, true)) {
            return [
                'status' => 'error',
                'message' => 'A senha inicial precisa ter pelo menos 14 caracteres, maiuscula, minuscula, numero, simbolo e nao pode repetir usuario ou codigo do assinante.',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Credencial inicial atende a politica minima.',
        ];
    }
}
