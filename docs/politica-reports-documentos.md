# Politica de uso: reports, special_document e engines externas

Este documento define onde cada tipo de saida deve ser implementado no produto.

## Regra principal

- use `reports` quando o problema for consulta formatada, leitura, impressao e exportacao;
- use `special_document` quando o problema for um documento interno com visual mais fechado;
- use `regulated_document` quando o problema exigir trilha separada de preparo, emissao, hash, artefato e conferencia, sem ainda prometer homologacao final;
- use engine externa ou modulo especializado quando houver exigencia legal, homologacao ou layout rigido de alto controle.

## O que entra em `reports`

Entram aqui os casos em que o documento e essencialmente uma consulta estruturada:

- pedido de compra;
- orcamento;
- contas a pagar;
- contas a receber;
- posicao de estoque;
- extrato de movimentacao;
- espelho de cadastro;
- romaneio simples;
- relatorio gerencial por cliente, produto, vendedor, periodo ou centro de custo;
- relatorio analitico com filtros, agrupamentos, subtotal e total geral.

Criticos de aceite para `reports`:

- layout em blocos fechados;
- tabela principal previsivel;
- agrupamento de ate 3 niveis;
- sem coordenada absoluta por campo;
- sem obrigacao legal de reproduzir formulario oficial;
- PDF, impressao e exportacao podem seguir renderer controlado do proprio produto.

## O que entra em `special_document`

Entram aqui documentos internos com visual mais controlado que um relatorio comum, mas ainda sem homologacao externa:

- documento interno com cara fechada de formulario;
- etiqueta padronizada do sistema;
- boleto interno nao homologado;
- espelho operacional de nota/documento;
- layout interno especifico por modulo, desde que siga renderer controlado.

Criticos de aceite para `special_document`:

- contrato fechado por metadado;
- perfis visuais controlados;
- sem template livre;
- sem JavaScript vindo do JSON;
- sem prometer conformidade fiscal, bancaria ou grafica industrial.

## O que entra em `regulated_document`

Entram aqui documentos que ja precisam de pipeline proprio, storage separado e conferencia posterior, mas ainda sem amarrar a primeira engine homologada final:

- base fiscal interna com payload canonico e artefato emitido;
- base bancaria interna com trilha de hash e retencao;
- base logistica controlada com emissao, artefato e auditoria forte;
- qualquer documento quase homologado que precise separar preparo, renderizacao, emissao e conferencia.

Criticos de aceite para `regulated_document`:

- `pageType` proprio;
- schema fechado;
- `prepare`, `render`, `issue`, `verify` e `artifact`;
- storage separado da auditoria simples;
- hash e artefato opcionais por politica do documento;
- pagina administrativa e pagina de conferencia;
- sem template livre, sem HTML livre e sem JavaScript vindo do metadado.

## O que exige engine externa ou modulo especializado

Nao deve entrar em `reports` nem ser tratado como resolvido apenas por `special_document` quando existir:

- DANFE oficial;
- DACTE oficial;
- boleto homologado por banco;
- formulario fiscal oficial;
- etiqueta industrial com coordenada de impressao rigida;
- documento com codigo de barras/QR e regras normativas obrigatorias;
- sub-relatorio sofisticado;
- layout com posicionamento absoluto detalhado;
- renderer dependente de homologacao ou especificacao externa obrigatoria.

Nesses casos, o correto e usar:

- engine externa;
- modulo fiscal/bancario especifico;
- ou trilha especializada futura com contrato proprio.

## Matriz pratica

| Caso | Camada recomendada | Observacao |
| --- | --- | --- |
| Pedido de compra | `reports` | Cabe bem em cabecalho, itens, totais e impressao |
| Orcamento | `reports` | Mesmo perfil de relatorio operacional |
| Contas a pagar/receber | `reports` | Tabular, filtros, agrupamentos e totais |
| Posicao de estoque | `reports` | Operacional ou analitico |
| Extrato de movimentacao | `reports` | Ordenacao, filtro e agrupamento previsiveis |
| Relatorio gerencial | `reports` | Principal caso da camada |
| Espelho de cadastro | `reports` | Desde que caiba em blocos fechados |
| Romaneio simples | `reports` | Sem exigencia de layout rigido |
| Documento interno formatado | `special_document` | Visual mais fechado que relatorio comum |
| Etiqueta padronizada | `special_document` | Desde que use renderer controlado |
| Boleto interno nao homologado | `special_document` | Sem prometer conformidade bancaria |
| Espelho visual de DANFE | `special_document` | Apenas uso interno, nao fiscal oficial |
| Documento fiscal/bancario/logistico com hash, artefato e conferencia separados | `regulated_document` | Base quase homologada, ainda sem engine final oficial |
| DANFE oficial | engine externa/modulo fiscal | Exige conformidade legal |
| Boleto homologado | engine externa/modulo bancario | Exige homologacao e regras bancarias |
| Etiqueta industrial rigida | engine dedicada | Pode exigir coordenadas fisicas precisas |
| Formulario fiscal oficial | engine externa/modulo fiscal | Fora do escopo das camadas nativas |
| Layout livre absoluto | engine dedicada | O produto nao usa designer livre nesta frente |

## Decisao de produto

- `reports` nao deve virar designer universal;
- `special_document` nao deve prometer conformidade fiscal ou bancaria sem engine propria;
- `regulated_document` e a trilha correta quando o documento ja exige pipeline proprio e storage separado, mas ainda nao chegou na engine homologada final;
- quando houver duvida, preferir manter o caso em `reports` apenas se ele continuar sendo consulta formatada;
- se o caso comecar a exigir formulario oficial, homologacao, coordenada absoluta ou renderer especializado, tirar da camada `reports`.

## Consequencia para implementacao

Antes de criar uma tela nova, responder estas perguntas:

1. O usuario precisa consultar dados, agrupar, imprimir ou exportar?
   - se sim, com alta chance e `reports`.
2. O usuario precisa de um documento interno com visual mais fechado?
   - se sim, avaliar `special_document`.
3. O usuario precisa de preparo, emissao, hash, artefato e conferencia separados, mas ainda sem exigir engine homologada final?
   - se sim, avaliar `regulated_document`.
4. Existe obrigacao legal, fiscal, bancaria ou de impressao rigida?
   - se sim, a trilha correta e modulo especializado ou engine externa.

## Estado atual do projeto

Hoje o repositorio ja suporta:

- `reports` nativo para operacionais e analiticos;
- `special_document` com renderer controlado para perfis fechados;
- `regulated_document` com pipeline proprio, storage separado, hash, artefato e conferencia;
- bloqueio explicito de `danfe`, `dacte`, `boleto`, `label/etiqueta` na trilha de `reports`.

O que ainda nao se deve prometer como entregue apenas com a camada atual:

- DANFE fiscal homologado;
- boleto bancario homologado;
- renderer industrial com coordenada livre;
- designer documental livre.
