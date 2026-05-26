<?php

declare(strict_types=1);

$token = bin2hex(random_bytes(32));
$hash = password_hash($token, PASSWORD_DEFAULT);

echo "Token interno SaaS:\n";
echo $token . "\n\n";
echo "Valor para installer_activation_service_token.token_hash:\n";
echo $hash . "\n\n";
echo "Guarde o token em cofre seguro. Depois de sair desta tela, use apenas o hash no cadastro admin.instalacao-tokens.\n";
