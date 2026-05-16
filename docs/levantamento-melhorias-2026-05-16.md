# Levantamento de melhorias identificadas

Data: 2026-05-16
Projeto: `C:\construtor-pg`

## Objetivo

Consolidar as melhorias ainda recomendadas no projeto, depois das frentes recentes de:

- governanca e publicacao governada;
- rastreabilidade em transacoes;
- versionamento com programas padrao, overlays e programas especificos;
- integridade estrutural;
- notificacoes administrativas;
- importacao/exportacao;
- Home, chat, suporte, SSE e central de notificacoes;
- propriedades tecnicas e evolucoes do Program Builder.

O foco desta lista e apoiar o planejamento. Nao e um backlog fechado nem uma ordem obrigatoria.

## Leitura rapida

### Prioridade alta

1. Commit e push da frente atual
2. E2E maior do fluxo governado real
3. Resolucao assistida no rebase de overlays
4. Tela administrativa dedicada para grants e approvals
5. Fechamento final da cobertura de integridade estrutural
6. Auditoria mais forte para reassinatura e publish governado

### Prioridade media

7. Timeline visual de governanca
8. Tela dedicada de retencao
9. Notificacoes administrativas com navegacao/filtro ainda mais contextual
10. Politica de ambiente aplicada em mais operacoes sensiveis
11. Testes integrados maiores de publish, freeze, revoke e consumo
12. Diff de rebase com resolucao por campo/secao

### Prioridade estrutural

13. Refatoracao adicional do Program Builder
14. Revisao do modulo de import/export para manter crescimento controlado
15. Monitor operacional mais consolidado para integracoes
16. Revisao final de UX administrativa para reduzir operacao via CRUD generico

## Lista detalhada

## 1. Git e fechamento operacional

### 1.1 Commit e push da frente atual

- **Problema**: o workspace ja acumulou muitas alteracoes locais e varias frentes foram implementadas em sequencia.
- **Risco**:
  - perda de rastreabilidade;
  - maior chance de conflito local;
  - dificuldade para revisar ou reverter por bloco funcional.
- **Melhoria**:
  - fechar essa frente em um ou mais commits coerentes;
  - idealmente separar por blocos:
    - governanca/integridade/versionamento;
    - import/export;
    - Home/notificacoes/chat;
    - documentacao/manual.
- **Impacto**: alto
- **Esforco**: baixo

## 2. Governanca e publicacao

### 2.1 E2E maior do fluxo governado real

- **Estado atual**: ja existe smoke relevante do builder governado e da tela dedicada.
- **Falta**:
  - um E2E mais longo e mais proximo do uso real, cobrindo:
    - request;
    - grant;
    - freeze;
    - reactivate;
    - revoke;
    - lock;
    - bundle de testes;
    - approval;
    - publish;
    - consumo do grant.
- **Motivo**: hoje a cobertura esta boa por partes, mas esse fluxo e critico demais para depender so de testes menores.
- **Impacto**: alto
- **Esforco**: medio

### 2.2 Tela administrativa dedicada para grants e approvals

- **Estado atual**: existe CRUD administrativo e dashboard no builder.
- **Falta**:
  - uma superficie focada para operar:
    - grant;
    - approval;
    - freeze;
    - revoke;
    - consume;
    - historico recente.
- **Motivo**: grant e approval sao operacoes sensiveis. Quanto menos depender de CRUD generico, menor o erro operacional.
- **Impacto**: alto
- **Esforco**: medio

### 2.3 Timeline visual de governanca

- **Estado atual**: existem request, grant, bundle, approval e logs.
- **Falta**:
  - uma linha do tempo unica por programa/versao mostrando:
    - solicitacao;
    - aprovacao do request;
    - grant;
    - freeze/revoke;
    - execucao dos testes;
    - aprovacao final;
    - publish;
    - reassinatura, quando houver.
- **Motivo**: melhora auditoria humana e suporte.
- **Impacto**: medio
- **Esforco**: medio

### 2.4 Tela dedicada de retencao

- **Estado atual**: a retencao ja existe por parametros e pelo painel do builder.
- **Falta**:
  - uma tela propria para:
    - visualizar valores atuais;
    - explicar o impacto;
    - aplicar alteracoes com mais seguranca;
    - eventualmente rodar simulacao de limpeza.
- **Impacto**: medio
- **Esforco**: medio

### 2.5 Politica de ambiente em mais operacoes sensiveis

- **Estado atual**: o gate de ambiente esta forte no publish e em manutencoes especificas.
- **Falta revisar extensao** para:
  - reassinatura;
  - rebase;
  - publicacao de overlay;
  - manutencoes administrativas mais perigosas.
- **Impacto**: medio
- **Esforco**: medio

## 3. Overlays e rebase

### 3.1 Resolucao assistida no rebase

- **Estado atual**: o sistema ja compara, classifica e mostra diff por secao/campo.
- **Falta**:
  - resolver por campo ou por secao antes do commit do rebase;
  - escolher explicitamente:
    - manter base;
    - manter overlay;
    - usar rebase sugerido;
    - resolver manualmente em pontos suportados.
- **Motivo**: hoje o diagnostico esta forte; o passo seguinte e reduzir decisao manual fora da ferramenta.
- **Impacto**: alto
- **Esforco**: medio/alto

### 3.2 Diff de rebase ainda mais explicativo

- **Falta**:
  - destacar de forma ainda mais clara:
    - merge automatico;
    - conflito leve;
    - conflito bloqueante;
    - origem da divergencia;
    - impacto no publish/runtime.
- **Impacto**: medio
- **Esforco**: medio

### 3.3 Testes integrados de transicoes ilegais

- **Falta endurecer**:
  - `standard -> customer_*`;
  - `customer_* -> standard`;
  - criacao indevida de `standard` por contexto de assinante;
  - ativacao simultanea indevida de custom + overlay.
- **Impacto**: alto
- **Esforco**: medio

## 4. Integridade estrutural e auditoria

### 4.1 Revisao final da cobertura de integridade

- **Estado atual**: a cobertura cresceu bastante.
- **Falta**:
  - revisar se ainda resta alguma tabela estrutural sensivel fora da assinatura;
  - revisar tabelas auxiliares de publish/runtime/parametrizacao critica.
- **Impacto**: alto
- **Esforco**: medio

### 4.2 Auditoria mais forte da reassinatura

- **Estado atual**: ja existe motivo, usuario e before/after basico.
- **Falta**:
  - trilha administrativa ainda mais clara;
  - vinculo forte com transacao/log administrativo;
  - evidenciar impacto;
  - talvez exigir justificativa mais estruturada por tipo de caso.
- **Impacto**: alto
- **Esforco**: medio

### 4.3 Painel consolidado de violacoes

- **Estado atual**: `admin.integridade` existe.
- **Falta**:
  - agrupamento por tipo de tabela;
  - prioridade;
  - recorrencia;
  - acoes recomendadas;
  - historico de reincidencia.
- **Impacto**: medio
- **Esforco**: medio

### 4.4 Automacao periodica de integridade

- **Estado atual**: existe comando.
- **Falta**:
  - rotina agendada oficial;
  - politica de notificacao;
  - comportamento padrao em caso de erro.
- **Impacto**: medio
- **Esforco**: baixo/medio

## 5. Home e notificacoes

### 5.1 Navegacao contextual ainda mais forte

- **Estado atual**: a Home ja abre telas administrativas com query segura.
- **Falta**:
  - abrir a tela ja focando item, aba, filtro e contexto quando suportado;
  - padronizar isso em todas as notificacoes administrativas.
- **Impacto**: medio
- **Esforco**: medio

### 5.2 Notificacoes administrativas com acao direta mais rica

- **Falta**:
  - abrir grant especifico;
  - abrir approval especifico;
  - abrir request especifico;
  - abrir violacao especifica de integridade;
  - abrir programa governado diretamente na aba correta.
- **Impacto**: medio
- **Esforco**: medio

### 5.3 Politica de deduplicacao e reciclagem de notificacoes

- **Estado atual**: ja foi corrigido o caso de unicidade no monitor.
- **Falta**:
  - definir politica clara por tipo de notificacao:
    - recicla;
    - reabre;
    - cria nova;
    - fecha antiga.
- **Impacto**: medio
- **Esforco**: medio

## 6. Importacao e exportacao

### 6.1 Monitor operacional consolidado de integracoes

- **Estado atual**: `admin.integracoes` evoluiu bastante.
- **Falta**:
  - uma visao operacional mais consolidada com:
    - ultimas execucoes;
    - falhas;
    - tempo;
    - agendamentos vencidos;
    - mapeamentos mais problemáticos.
- **Impacto**: medio
- **Esforco**: medio

### 6.2 Refatoracao adicional do modulo de import/export

- **Motivo**: a frente cresceu rapido e tende a continuar crescendo.
- **Alvos provaveis**:
  - mais separacao entre:
    - loader;
    - renderer;
    - writer;
    - executor;
    - historico;
    - agendamento.
- **Impacto**: medio
- **Esforco**: medio/alto

### 6.3 XML e layouts ricos ainda mais maduros

- **Possiveis proximos passos**:
  - atributos e namespaces mais complexos;
  - estruturas repetitivas mais profundas;
  - importacao XML mais robusta;
  - diff/versionamento mais amigavel de mapeamentos.
- **Impacto**: medio
- **Esforco**: medio/alto

## 7. Program Builder e UX administrativa

### 7.1 Refatoracao adicional do Program Builder

- **Estado atual**: ja houve separacoes parciais.
- **Falta**:
  - continuar reduzindo concentracao de responsabilidade;
  - separar ainda mais:
    - shell;
    - governanca;
    - rebase;
    - editor de entidade;
    - editor de programa;
    - integracoes;
    - API/Odoo;
    - IA.
- **Impacto**: alto
- **Esforco**: alto

### 7.2 Reducao de operacao por CRUD generico

- **Estado atual**: varias areas sensiveis ainda usam CRUD administrativo como fallback.
- **Melhoria**:
  - criar superficies mais fechadas para os pontos criticos.
- **Impacto**: medio
- **Esforco**: medio

### 7.3 Gate visual ainda mais guiado

- **Falta**:
  - um fluxo quase wizard para publicacao governada;
  - destacar o proximo bloqueio real;
  - reduzir ida e volta manual entre dialogs.
- **Impacto**: medio
- **Esforco**: medio

## 8. Testes e qualidade

### 8.1 Suite integrada maior de governanca

- **Falta**:
  - testes maiores e menos fragmentados da governanca end-to-end.
- **Impacto**: alto
- **Esforco**: medio

### 8.2 Cobertura maior de telas administrativas reais

- **Falta reforcar E2E** em:
  - `admin.programa-governanca`;
  - grants/approvals dedicados quando existirem;
  - retencao dedicada quando existir;
  - rebase assistido.
- **Impacto**: medio
- **Esforco**: medio

### 8.3 Revisao de testes de integridade apos manutencao generica

- **Motivo**: sempre que uma tabela estrutural puder ser editada pelo CRUD generico, a assinatura precisa permanecer sincronizada.
- **Melhoria**:
  - reforcar testes para create/update/delete dessas superfícies.
- **Impacto**: alto
- **Esforco**: medio

## 9. Planejamento sugerido

## Fase 1 - fechamento e estabilizacao

1. Commit e push da frente atual
2. E2E maior do fluxo governado real
3. Revisao final da cobertura de integridade
4. Auditoria mais forte da reassinatura

## Fase 2 - operacao administrativa

5. Tela dedicada para grants e approvals
6. Tela dedicada de retencao
7. Timeline visual de governanca
8. Notificacoes administrativas com acao contextual mais forte

## Fase 3 - maturidade de overlays

9. Resolucao assistida no rebase
10. Diff de rebase ainda mais explicativo
11. Testes integrados de transicoes ilegais

## Fase 4 - consolidacao estrutural

12. Refatoracao adicional do Program Builder
13. Refatoracao adicional de import/export
14. Monitor operacional consolidado de integracoes

## Observacoes finais

- O projeto avancou bastante em capacidade, mas agora o risco principal e complexidade operacional.
- As melhorias mais importantes neste momento nao sao mais de “feature bruta”; sao de:
  - fechamento;
  - governanca;
  - validacao;
  - operacao segura;
  - organizacao da complexidade.
- Se for preciso cortar escopo, o melhor corte hoje e adiar UX secundaria e focar:
  - integridade;
  - governanca;
  - testes end-to-end;
  - fechamento em Git.
