# Gestão de e-mails SES

## Objetivo

Disponibilizar no Construtor PG uma consulta somente leitura aos eventos da tabela PostgreSQL `notificacoes`, com indicadores operacionais e grade Kendo filtrada pelo indicador selecionado.

## Escopo

- A fonte externa será configurada exclusivamente por `EMAIL_ANALYTICS_DATABASE_URL`; não haverá credencial em código, metadado ou frontend.
- A API normalizará `cod_tipo` e, quando necessário, `json_evento.eventType` ou `json_evento.notificationType`, nos eventos SES `SEND`, `DELIVERY`, `BOUNCE`, `REJECT`, `RENDERING_FAILURE`, `DELIVERY_DELAY`, `COMPLAINT`, `OPEN`, `CLICK` e `SUBSCRIPTION`.
- O dashboard terá cartões para enviados, entregues, falhas, atrasados, reclamações, aberturas e cliques. Cada cartão passará o conjunto de tipos para o Kendo Grid.
- A grade terá paginação, ordenação, filtros, reordenação e redimensionamento; mostrará data/hora, tipo, remetente, destinatário, título e IDs de mensagem.

## Arquitetura

Um repositório PHP isolado consulta a conexão externa com parâmetros vinculados e lista fechada de ordenações. Um controlador expõe os endpoints de resumo e registros, e uma página Kendo usa `DataSource` remoto para atualizar a grade ao clicar nos cartões. O módulo é somente leitura.

## Fora do escopo

Não há envio de e-mail, alteração da tabela, publicação de metadados no runtime, nem armazenamento de dados do SES no banco interno.
