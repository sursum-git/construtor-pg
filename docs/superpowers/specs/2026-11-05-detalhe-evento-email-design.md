# Detalhe de evento de e-mail

## Objetivo

Permitir que o usuário veja, ao dar duplo clique em uma linha, a situação entregue ou falhada do evento AWS SES e sua explicação; o grid deve exibir data e controles em português brasileiro.

## Decisões

- A API extrai somente um resumo do JSON SNS/SES e não entrega o JSON bruto ao navegador.
- O Kendo Window mostra status, ocorrência e motivo técnico (tipo/subtipo de bounce ou resposta SMTP).
- O grid abre sem filtro para mostrar os registros do dia; os cards passam a filtrar depois do clique.
- Datas usam `dd/MM/yyyy HH:mm:ss` e a cultura carregada é `pt-BR`.

## Abordagens consideradas

- Exibir o JSON completo: descartado, pois é difícil de ler e pode expor informações desnecessárias.
- Interpretar o JSON somente no navegador: descartado, pois duplica a regra de SES e envia dados brutos.
- Resumir na API e abrir uma janela Kendo: escolhida por ser segura, clara e alinhada ao grid existente.
