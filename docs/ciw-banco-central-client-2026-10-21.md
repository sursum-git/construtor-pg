# Cliente Banco Central e comando Buscar cotação

Publicado em 21/10/2026, complementando o cadastro manual de cotações:

- Consulta/formulário: **ciw.cotacoes-bc / cd0103 / 1.0.1**, com acesso por linha **Buscar cotação**.
- Processo nativo: **ciw.cotacoes-bc-buscar / cd0104 / 1.0.0**. Também disponível na Home em CIW → Cadastros e parâmetros → Buscar cotação no Banco Central.
- A tela mostra o parâmetro Série com valor inicial **1**. O servidor aceita somente esse valor. Abrir a tela não consulta nem grava; a execução exige permissão e confirmação Kendo.

## Cliente PHP reutilizável

Canônico: `D:/opencode/construtor-pg/backend/src/Ciw/BancoCentralSgsClient.php`, serviço Symfony com autowiring. Uso em outro serviço PHP:

```php
use App\Ciw\BancoCentralSgsClient;

// Receber BancoCentralSgsClient pelo construtor do serviço.
$cotacao = $bancoCentral->fetchLatestDollar();
// ['serie'=>1, 'data'=>'AAAA-MM-DD', 'valor'=>numero, 'nome_serie'=>texto]
```

O cliente só consulta; não grava nem mantém cache. Usa o [endpoint oficial SGS](https://api.bcb.gov.br/dados/serie/bcdata.sgs.1/dados/ultimos/1?formato=json), série 1, dólar americano de venda. Host, caminho e série são fixos no backend. Timeout de conexão de 3 segundos, total de 10 segundos, corpo limitado a 64 KiB, sem redirecionamentos. TLS e hostname verificados; no PHP Windows usa as CAs nativas do sistema quando disponíveis. Não desabilitar TLS por falta de `curl.cainfo`.

Exige HTTP200 e exatamente uma observação JSON com data válida e valor positivo de até cinco casas. Retorna a **data da observação do BC**, separada do instante da busca. Resposta inválida ou indisponível gera erro antes de chamar o Progress; não grava valor zero nem registro com sucesso falso.

## Gravação e auditoria

A operação cadastrada `hist_cotacoes_bc_import` pertence à fonte existente `motor_progress_ciw_cadastros`, tipo custom POST, com `allowProcess=true` e corpo estático `operation=import-bc`, `resource=hist_cotacoes_bc`, mesma rota `/SursumPrograms/ciw-cadastros`.

`RuntimeApiEntityActionService` valida permissão `.process`, os dois opt-ins, parâmetros, entidade e binding fechado. O navegador envia apenas `serie=1`; data, valor e nome vêm exclusivamente do cliente PHP. Não pode escolher URL, fornecer cotação, executar outra série nem forjar o ator.

`CiwCotacoesService.p` reutiliza a mesma criação, sequência privada, transação raiz, validação e snapshot do cadastro manual. Na importação grava `log_manual=false`, `log_ok=true`, série1, nome, valor, data oficial e instante da busca. O registro automático não pode ser alterado/excluído pelo CRUD manual. Não há invalidação de custo nessa importação, pois ela nunca grava a série9999.

Cada execução confirmada acrescenta uma observação, preservando o botão legado que chama a busca forçada. Não modifica cotações existentes. Não replica o comportamento legado de atribuir cotação anterior à data da busca nem o de inserir zero em falha.

Após commit ABL, a transação PHP recebe o ID e registra usuário da sessão, anterior vazio, imagem atual completa e operação `import-bc`. O Logs do registro em `ciw.cotacoes-bc` inclui o evento desse processo por alias restrito à tela, evento, recurso, operação, ID confirmado e tenant. Logs Exclusão mantém somente exclusões; a importação não entra nele. Os logs do próprio processo também usam o visualizador nativo.

## Validação e limites

- Cliente PHP executado contra o BC real com TLS validado, inclusive pela instância de serviço efetivamente injetada no runtime Symfony. Somente leitura.
- OpenEdge12.2 no `.42`: quatro fontes sem erros, **107 verificações por combinação** AMERICAN/EUROPEAN × UTF-8/ISO8859-1; mais107 por cultura com participante no layout publicado. Zero bancos conectados. Inclui importação automática, imagens/flags, precisão e bloqueio posterior de edição/exclusão.
- **612 testes PHP / 3643 asserções** na suíte canônica. Inclui indisponibilidade/JSON/datas/valores inválidos, parâmetros forjados, série9999, permissão, nenhuma chamada ao Progress quando o BC falha, auditoria e escopo dos logs por tenant/registro.
- Revisão independente final sem defeitos remanescentes. Preview e definição HTTP publicada passaram no `ProcessDefinitionValidator` canônico com parâmetro inteiro Série, obrigatório, valor inicial1.
- Publicado somente `CiwCotacoesService.p/.r` no compartilhamento137 via111; seis artefatos relacionados conferidos por hash, sessões med ociosas recarregadas por JMX. Instância **sursum-api/med/9911**. Sem restart, schema, nova rota, configuração/token ou mudança de permissões humanas.
- PASOE real recusou importação com valor zero antes de criar registro. Runtime real recusou série9999 e valor enviado pelo navegador; consulta, atalho, confirmação e operação cadastrada conferidos.

Não foi executada a gravação automática de uma cotação de negócio para demonstração. O sucesso com persistência automática foi exercitado em TTs; o CRUD manual real e sua limpeza já tinham sido comprovados na entrega anterior. Não reaproveitar nem repetir o teste manual removido para validar este processo. Conferência visual humana e distribuição adicional de permissões continuam etapas próprias.

## Fontes e recibos

Candidatos finais: `integration-src/ciw-bc-client/`. Canônicos PHP em `construtor-pg/backend/src/{Ciw,Runtime}`, testes correspondentes, metadados em `construtor-pg/integrations/ciw-bc-client`. ABL canônico em `motor-progress/v2/sursum-api/programs/CiwCotacoesService.p`, teste em `tests/abl/ciw-cotacoes/CiwCotacoesTest.p`. O serviço desse diretório substitui o snapshot manual anterior; os demais programas permanecem compartilhados.

Recibos privados: `.runtime/ciw-bc-client-20261021/`, incluindo `compiled122/`, `php-full.log`, `active-manifest.json`, `med-session-refresh.json`, `runtime-client-live.json`, `runtime-verification.json`, publicações Builder e Home, manifests canônicos e `final-verification.json`. Não copiar configurações privadas, tokens ou credenciais para exemplos/memória.

Foi investigado suporte a processo sem parâmetros. A exceção experimental do Builder foi **integralmente revertida por hash**; solução final usa o parâmetro Série existente. Nenhum validador/frontend/Kendo foi alterado.
