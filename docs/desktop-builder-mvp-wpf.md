# MVP desktop WPF

Este documento registra o primeiro MVP desktop separado do construtor.

Diretorio:

- `desktop-wpf/`

Objetivo desta etapa:

- validar se a experiencia de autoria complexa funciona melhor em desktop;
- manter `program-builder.html` e o restante do web intactos;
- preparar base para, depois, integrar com o backend Symfony existente.

Escopo implementado:

- solution WPF separada;
- arvore de objetos com modulo, entidade e campo;
- painel contextual de propriedades;
- comandos em memoria para incluir modulo, entidade e campo;
- preview JSON da estrutura corrente;
- sem persistencia, sem login e sem chamadas HTTP nesta etapa.

Arquitetura do MVP:

- `MainWindow`: shell com 3 areas;
- esquerda: arvore da estrutura;
- centro: resumo e preview JSON;
- direita: propriedades contextuais;
- `MainViewModel`: estado principal da sessao;
- modelos simples para modulo, entidade e campo.

Limites atuais:

- requer Windows;
- requer SDK do .NET 8 ou superior para build;
- nao usa Telerik;
- nao conversa com backend;
- nao faz versionamento nem publicacao.

Proximos passos naturais, se a linha desktop for aprovada:

1. login e configuracao de endpoint;
2. leitura real de modulos e entidades pelo backend;
3. salvar entidade/programa;
4. preview vindo da API;
5. historico, diff e publicacao.
