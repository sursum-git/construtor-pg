# Padroes de UI Kendo

Este arquivo resume os padroes visuais e comportamentais ja definidos no projeto.

## Tema

- Fundo neutro.
- Azul discreto em titulos, links, badges, bordas leves e botoes primarios.
- Tema claro/escuro vem de configuracao global JSON.
- A troca claro/escuro deve afetar grid, filtros, botoes, inputs, chips e janelas.
- Evitar paleta muito azul, muito roxa, muito bege ou muito escura sem necessidade.

## Cabecalho

- No desktop, ocupar uma linha quando houver espaco.
- Mostrar versao apenas se informada no JSON.
- Mostrar ajuda apenas se informada no JSON/config global.
- Mostrar logs apenas se houver URL/path configurado.
- Data/hora da ultima atualizacao deve ficar em badge compacto, sem texto longo.
- Botoes de ajuda, log e tema devem ser compactos, com icone e tooltip.

## Pagina inicial

- A pagina inicial pode ser montada pelo `HomeEngine` a partir de JSON.
- Usar appbar superior global com titulo do app, titulo do programa corrente e acoes compactas.
- O logo da empresa vem de `app.logo.url`; o JSON define se o titulo/subtitulo do app continuam ao lado do logo.
- Na pagina inicial, o seletor claro/escuro fica apenas no appbar global.
- Programa chamado pelo menu nao deve duplicar o seletor claro/escuro da pagina inicial.
- Usar Kendo TreeView no menu lateral para grupos de programas e programas filhos.
- O TreeView do menu lateral deve usar `kendo.data.HierarchicalDataSource`.
- Acima do TreeView, usar Kendo ComboBox para selecionar sistema/modulo quando houver mais de um.
- O ComboBox de modulos deve iniciar em `Todos` quando `navigation.initialModuleId` nao vier preenchido; se vier um modulo valido, inicia nesse modulo.
- O menu lateral deve permitir filtro por nome e filtro por favoritos.
- A appbar global deve ter botao compacto para favoritar/desfavoritar o programa corrente quando habilitado.
- Favorito marcado deve usar fundo amarelo no botao da pagina corrente; os itens do menu nao exibem marcador de favorito.
- A appbar global deve ter botao compacto para expandir/recolher o menu lateral quando `layout.sidebar.collapsible` permitir.
- Quando `layout.appbar.chat.enabled=true`, a appbar global deve exibir botao compacto de chat e abrir o componente Kendo Chat em janela com ComboBox para selecionar o usuario destinatario.
- Quando `layout.appbar.support.enabled=true`, a appbar global deve exibir botao compacto de atendimento; a janela deve ter ComboBox de setor, abrir chat quando houver atendente online no setor selecionado e exibir formulario de solicitacao com setor travado quando nao houver disponibilidade.
- Quando `layout.appbar.aiChat.enabled=true`, a appbar global deve exibir outro botao compacto para chat de IA, sem ComboBox de destinatario.
- Quando `layout.appbar.alerts.enabled=true`, a appbar global deve exibir botao compacto de sino e abrir alertas em janela.
- Quando `layout.appbar.requests.enabled=true`, a appbar global deve exibir botao compacto de solicitacoes e abrir solicitacoes em janela.
- Menu do usuario logado fica no canto direito da appbar global, com avatar/iniciais e opcoes vindas do JSON.
- Ao clicar em programa que abre na area central, recolher o menu lateral.
- Em viewport mobile, iniciar o menu lateral recolhido.
- Cada programa do TreeView deve ter botao compacto para abrir em nova aba.
- Programas podem abrir por modos fechados: `iframe`, `crud` ou `html`.
- `crud` deve instanciar o `CrudEngine` dentro da area central.
- Programas abertos dentro da Home nao devem duplicar cabecalho interno; a appbar global mostra titulo, versao, subtitulo, ultima atualizacao, ajuda, logs, atualizar e tema.
- `html` deve ser sanitizado e nao pode executar scripts/eventos vindos do JSON.
- `iframe` deve usar URL configurada e titulo acessivel.

## Toolbar do grid

- Botoes principais usam Kendo Button com icones.
- Acoes comuns: Incluir, Filtros, Atualizar, Ordenacao, Agrupamento, Leiaute, Imprimir, Acoes em massa.
- Atualizar deve usar icone `arrow-rotate-cw`.
- Acoes em massa so aparecem se existirem acoes cadastradas.
- Imprimir/exportar so aparece se existirem opcoes.
- Opcoes de leiaute, ordenacao e agrupamento devem abrir em janela, nao ocupar espaco fixo na toolbar.
- No mobile, a toolbar do grid deve recolher filtros, atualizar, ordenacao, agrupamento, leiaute, imprimir e acoes em massa atras de um botao compacto.

## Grid

- Usar Kendo Grid real.
- Coluna de acoes deve ser a primeira.
- Acoes de linha devem ficar em menu compacto.
- Duplo clique na linha abre formulario em consulta.
- Grid deve suportar paginacao, ordenacao, filtro nativo, menu de coluna, redimensionamento e reordenacao quando configurado.
- Congelamento de coluna e recurso desktop.
- Colunas iniciam descongeladas, salvo leiaute salvo explicito.
- Cadeado fechado significa congelada; aberto significa nao congelada.
- Clicar no cadeado nao pode ordenar nem mover coluna.

## Filtros

- Filtro principal abre em janela Kendo.
- Pode abrir automaticamente na entrada da pagina.
- Pode esperar o usuario clicar em Filtrar antes de carregar o grid.
- Pode abrir maximizado por `crud.filter.maximizeFilter`.
- Appbar inferior fixa, com botoes alinhados a esquerda.
- Labels acima dos campos.
- Campos um abaixo do outro.
- Operador e range devem ficar na mesma linha do campo quando aplicavel.
- Pode ter abas.
- Filtros salvos devem usar janela para informar nome e padrao.
- Nao usar prompt nativo.
- "Sem filtro salvo" foi substituido por "Livre".

## Filtros aplicados

- Quando habilitado, mostrar chips com os filtros aplicados.
- Pode ser ocultado por configuracao.
- Clicar em um chip abre janela para editar apenas aquele filtro.
- Deve existir limpar filtros.

## Formulario

- Formulario abre em popup Kendo.
- Pode abrir maximizado.
- Appbar do cabecalho: Incluir, Alterar, Excluir, Logs, Imprimir, Outras acoes.
- Appbar do rodape: Confirmar e Cancelar alinhados a esquerda.
- Cancelar nao deve sair da pagina; deve desativar a acao corrente.
- Ao alterar, Incluir e Excluir ficam desabilitados.
- Ao abrir por duplo clique, entra em consulta sem ativar acoes.
- Confirmar e Cancelar ficam desabilitados na consulta por duplo clique.
- Botoes Anterior/Proximo usam apenas icones.
- Navegacao e comportamento de fechar ao salvar/cancelar sao opcionais no JSON.

## Campos do formulario

- Suporte a multiplas abas.
- Primeira aba deve mostrar conteudo sem clique adicional.
- Labels acima dos campos.
- Campo pode ser readonly ou renderizado como label.
- IDs devem ser unicos: `formId-campoId`.
- Se o campo nao tiver id, gerar `formId-nomeDoCampo`.

## Eventos do formulario

- Eventos podem chamar APIs.
- Nao executar JavaScript livre vindo do backend.
- API pode retornar efeitos seguros:
  - setValue;
  - clearValue;
  - readonly;
  - enabled;
  - disabled;
  - visible;
  - show;
  - hide;
  - required;
  - setOptions;
  - reloadOptions;
  - showMessage.
- Exemplo: ao mudar UF, recarregar Cidade.
- Exemplo: ao mudar tipo de pessoa, esconder/mostrar aba de pessoa juridica.

## Situacao e etapas

- Campo de situacao fica abaixo da appbar do cabecalho.
- Label pequeno acima.
- Se houver muitos steps, priorizar atual e permitir rolagem horizontal.
- Ao clicar em uma fase, consultar historico.
- Formulario por etapas permite:
  - etapas configuradas;
  - campos por etapa;
  - obrigatorios para avancar;
  - permissoes por etapa;
  - log por etapa.

## Mobile

- Mobile first.
- Header nao pode embolar data/hora, ajuda e logs.
- Grid mobile pode:
  - manter grid com colunas reduzidas;
  - usar card/template seguro.
- Template mobile nao aceita HTML livre do backend.
- Backend pode escolher template fechado e indicar campos por area.
- Botoes de grid no mobile devem usar apenas icones quando necessario.
- Formulario mobile deve ajustar largura sem sobra lateral direita.

## Mensagens

- Nao usar `alert`, `confirm` ou `prompt` nativos.
- Usar mensagens/janelas Kendo.
- Evitar mensagens sobrepostas.
- Evitar verde forte com azul forte.
- Botoes em janelas devem ficar alinhados a esquerda.
