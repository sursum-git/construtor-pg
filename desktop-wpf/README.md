# MVP Desktop WPF

Este diretorio contem um MVP separado do construtor desktop.

Objetivo desta primeira entrega:

- validar a experiencia de arvore de objetos;
- validar painel contextual de propriedades;
- validar preview JSON da estrutura;
- manter a aplicacao web intacta.

Escopo atual:

- app WPF simples;
- dados em memoria;
- sem gravacao no backend;
- sem dependencia de Telerik;
- sem impactar `program-builder.html`.

Requisitos para executar:

- Windows;
- .NET SDK 8.0 ou superior;
- Visual Studio 2022 ou `dotnet build`.

Projeto principal:

- `desktop-wpf/ConstrutorPg.BuilderDesktop.sln`

Passos sugeridos:

1. Instalar o SDK do .NET 8.
2. Abrir a solution no Visual Studio.
3. Executar o projeto `ConstrutorPg.BuilderDesktop`.

Entregas desta etapa:

- explorador em arvore de modulos, entidades e campos;
- editor contextual de propriedades;
- comandos basicos para adicionar modulo, entidade e campo;
- preview JSON em memoria para validar o modelo.
