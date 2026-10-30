# Painel do Container — metadados e publicação

Estado atual16/10/2026: consulta1.0.2 e **painel1.0.4/14abas publicados**. Cabeçalho update/delete, Despesas1.0.1 CRUD individual, Gerar Versão/op1015, Fechar Custos/op1016 e Sincronizar Impostos/op1017. op1012 de iol.inicial-gerencial foi preservado. Arquivos *-candidate.json conservam o nome de preparação, mas seus conteúdos foram publicados e validados; não repetir creates. painel-1.0.4-builder-draft-candidate.json e despesas-1.0.1-builder-draft-candidate.json representam os drafts atuais. Os demais JSONs versionados antigos são históricos.

As fontes cabecalho/despesas/processos complementam as três fontes de leitura existentes. Segredos são resolvidos somente no estado privado. Custos/versões/fechamento reutilizam os serviços centrais e registram logs mestre/filhos com autoria da sessão e valores anteriores/atuais. Abertura de aba não executa.

Guia e evidências: [comandos e logs](D:/opencode/conversao-sc/docs/painel-container-comandos.md), [retomada](D:/opencode/conversao-sc/context/RETOMADA_2026-10-16.md) e [contratos dos JSONs](COMMANDS-INTEGRATION.md). Compilação62fontes12.2/2167checks por cultura, teste real autorizado e limpeza0 em12tabelas concluídos. Permanecem fora desta entrega manutenção de itens, associações, distribuição manual e anexos binários. As tabelas e notas seguintes documentam a preparação original readonly.
| Arquivo | Programa/tela | Campos |
|---|---|---|
| `api-source.json` | Fonte proposta `motor_progress_container_painel`, dez operações read/get de cinco recursos | — |
| `painel-builder-draft.json` | `op1001` / `ciw.container-painel` | 50, dos quais 43 no formulário Dados |
| `itens-builder-draft.json` | `op1002` / `ciw.container-itens` | 22 |
| `despesas-builder-draft.json` | `op1003` / `ciw.container-despesas` | 20 |
| `anexos-builder-draft.json` | `op1004` / `ciw.container-anexos` | 8 |
| `versoes-builder-draft.json` | `op1005` / `ciw.container-versoes-custo` | 23 |
| `containers-query-1.0.1-builder-draft.json` | Cópia da consulta `op1000` / `ciw.containers` com link para o painel | 23 |

Os códigos op1001–op1005 estavam disponíveis no bootstrap consultado durante a preparação; conferir novamente antes do cadastro. A consulta canônica existente não foi alterada por esta preparação.

Os campos físicos e tipos vêm do dicionário em `../schema/`, restritos à allowlist de `../ContainersPainelReadService.p`. Os campos derivados seguem o procedimento `BuildRow` desse serviço. `container_id` público corresponde a `espec.pp-container.nr-container`; nos filhos corresponde ao vínculo `container_id`.

Campos reservados `char-1`, `dec-1`, `int-1`, `log-1`, `data-1` e dados técnicos de usuário/origem existem na entidade do painel, mas não aparecem no formulário. Memória de cálculo e histórico bruto também ficam fora das colunas/formulários das filhas. Labels/opções coincidentes reutilizam os metadados da consulta existente; os demais labels foram extraídos dos fontes legados disponíveis ou descritos a partir do campo do domínio.

O painel usa `formOptions.mode=embedded`, `openOnLoadField=container_id`, `returnScreenId=ciw.containers`, contexto obrigatório e abas `linkedPage` das quatro filhas, somente leitura. O código genérico correspondente está no projeto canônico `construtor-pg`, documentado em `docs/crud-formulario-contextual.md`.

Todas as operações usam `/SursumPrograms/containers-painel`, `requestContextFields:["container_id"]` e template estático `operation`/`resource`. O marcador `__RUNTIME_SECRET__` deve ser resolvido somente no ambiente privado de importação. Salvar a fonte e atualizar os vínculos das entidades pelo fluxo oficial.

Esta etapa não representa o painel completo: Resumo, Pagtos, NFs, Comparativos e Rastro, edição e processos de negócio continuam como trabalho subsequente. Anexos nesta etapa são metadados de consulta; `tem_arquivo` não habilita download/upload.

## Ajuste de navegação preparado: consulta 1.0.2

`containers-query-1.0.2-builder-draft.json` é uma cópia preparada para revisão, **não importada/publicada por esta preparação**. Mantém a entidade e as 20 colunas da consulta e configura `rowLinks[0].position="start"`, colocando o link Painel depois das ações e antes dos dados. A versão 1.0.1 e os metadados canônicos não foram sobrescritos. A publicação e o estado atual das telas devem ser conferidos na memória principal do projeto.

