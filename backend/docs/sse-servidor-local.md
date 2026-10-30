# SSE no servidor PHP local

O servidor embutido `php -S` usado no ambiente Windows local atende uma requisição por vez. Um SSE aberto por cada aba/iframe mantinha o worker ocupado pelo timeout de 45 segundos e colocava consultas de metadados/dados na fila; a janela podia permanecer vazia até o stream terminar.

`RuntimeController` encerra `/api/runtime/events` e os SSE permitidos de chat/suporte depois de uma rodada autenticada quando `PHP_SAPI === 'cli-server'`, antes do `sleep`. Mensagens e keepalive são enviados normalmente; EventSource reconecta pelo `retry` existente (5 segundos no canal global, 3 segundos nos canais de endpoint). A verificação de sessão, revogação, dispatcher e flush de mensagens não foi removida.

Outros SAPIs, incluindo FPM de produção, mantêm o loop e o timeout configurados. Não é necessário mudar portas, número de processos, metadados de telas ou bibliotecas frontend.

`RuntimeCliServerEventsTest` inicia somente um PHP embutido isolado em porta aleatória. Antes da correção, o teste reproduziu timeout de leitura e bloqueio da segunda requisição. Depois, verifica fim do stream, disponibilidade imediata da próxima requisição, canal de chat, revogação de sessão e manutenção do polling temporizado fora do SAPI `cli-server`.
