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
- Data/hora da ultima atualizacao deve ficar compacta, sem texto longo, ao lado do botao de favorito quando houver espaco.
- Botoes de ajuda, log e tema devem ser compactos, com icone e tooltip.

## Login

- Tela de login deve ter appbar superior com logo/nome da empresa.
- Frase de seguranca e texto de apresentacao do login devem ficar no appbar e continuar visiveis no mobile.
- O formulario deve ficar em painel simples, com labels acima dos campos.
- Campos minimos: usuario, senha, manter logado, esqueci a senha e entrar.
- Campo de senha deve ter opcao compacta para exibir/ocultar a senha.
- O campo de assinante nao aparece no login; quando `subscriber.enabled=true`, a selecao ocorre apos validar usuario/senha e somente se o usuario tiver mais de um assinante ou for administrador.
- Usuario administrador deve escolher, apos autenticar e apos eventual selecao de assinante, se entra na area principal ou na area administrativa.
- Recuperacao de senha deve abrir janela Kendo para solicitar token/instrucoes e redefinir senha, sem `alert`, `confirm` ou `prompt`.
- OAuth/OIDC pode aparecer como botao externo quando o provedor estiver habilitado no backend; LDAP/SSO/local sao resolvidos pelo cadastro do usuario.
- A imagem lateral e opcional e deve desaparecer no mobile para priorizar o formulario.
- Nao usar `alert`, `confirm` ou `prompt`; mensagens de validacao e recuperacao usam Kendo.

## Pagina inicial

- A pagina inicial pode ser montada pelo `HomeEngine` a partir de JSON.
- Usar appbar superior global com titulo do app, titulo do programa corrente e acoes compactas.
- O logo da empresa vem de `app.logo.url`; o JSON define se o titulo/subtitulo do app continuam ao lado do logo.
- Na pagina inicial, o seletor claro/escuro fica apenas no appbar global.
- Programa chamado pelo menu nao deve duplicar o seletor claro/escuro da pagina inicial.
- Usar Kendo TreeView no menu lateral para grupos de programas e programas filhos.
- O TreeView do menu lateral deve usar `kendo.data.HierarchicalDataSource`.
- Para editores de leiaute hierarquico, como importacao/exportacao com registros pai, filhos e totalizadores, preferir `Kendo TreeView` com painel lateral de propriedades do no selecionado.
- Acima do TreeView, usar Kendo ComboBox para selecionar sistema/modulo quando houver mais de um.
- O ComboBox de modulos deve iniciar em `Todos` quando `navigation.initialModuleId` nao vier preenchido; se vier um modulo valido, inicia nesse modulo.
- O menu lateral deve permitir filtro por nome e filtro por favoritos.
- A appbar global deve ter botao compacto para favoritar/desfavoritar o programa corrente quando habilitado.
- Favorito marcado deve usar fundo amarelo no botao da pagina corrente; os itens do menu nao exibem marcador de favorito.
- A appbar global deve ter botao compacto para expandir/recolher o menu lateral quando `layout.sidebar.collapsible` permitir.
- A appbar global pode exibir o assinante corrente como badge compacto quando `currentSubscriber` existir e `layout.appbar.showCurrentSubscriber` nao for `false`.
- Se o assinante corrente representar o banco principal, o badge deve exibir `Principal` com cor mais destacada.
- Quando `layout.appbar.subscriberSwitch.enabled` estiver habilitado, clicar no badge do assinante abre uma janela Kendo para troca de assinante.
- A troca de assinante pode usar lista `availableSubscribers`, endpoint `layout.appbar.subscriberSwitch.endpoints.change` e opcionalmente `programId` ou `url` para fluxo dedicado.
- Em producao, o endpoint da troca de assinante deve usar `endpointId` ou `actionId`, nunca URL livre.
- Quando `layout.appbar.chat.enabled=true`, a appbar global deve exibir botao compacto de chat e abrir o componente Kendo Chat em janela com ComboBox para selecionar o usuario destinatario.
- Quando `layout.appbar.support.enabled=true`, a appbar global deve exibir botao compacto de atendimento; a janela deve ter ComboBox de setor, abrir chat quando houver atendente online no setor selecionado e exibir formulario de solicitacao com setor travado quando nao houver disponibilidade.
- Ao clicar no suporte, a janela e as APIs devem receber o programa corrente no contexto para permitir atendimento de erro por codigo de programa.
- Quando `layout.appbar.aiChat.enabled=true`, a appbar global deve exibir outro botao compacto para chat de IA, sem ComboBox de destinatario.
- Quando `layout.appbar.notifications.enabled=true`, a appbar global deve exibir botao compacto para a central de notificacoes. Se nao houver endpoint proprio, a central pode agregar `alerts`, `requests` e `jobs` habilitados.
- Quando houver endpoint proprio, a central deve aceitar `list` e opcionalmente `ack`, para permitir marcacao de leitura por destinatario sem sair da janela.
- Quando `layout.appbar.alerts.enabled=true`, a appbar global deve exibir botao compacto de sino e abrir alertas em janela.
- Quando `layout.appbar.requests.enabled=true`, a appbar global deve exibir botao compacto de solicitacoes e abrir solicitacoes em janela.
- Quando `layout.appbar.jobs.enabled=true`, a appbar global deve exibir botao compacto de jobs concluidos, com badge de quantidade e janela/lista apontando para "Meus Jobs" quando houver `programId`.
- Menu do usuario logado fica no canto direito da appbar global, com avatar/iniciais e opcoes vindas do JSON.
- Quando a sessao estiver impersonada, a Home deve mostrar aviso fixo abaixo do appbar com usuario alvo, administrador original e botao Kendo para encerrar a simulacao.
- Ao clicar em programa que abre na area central, recolher o menu lateral.
- Em viewport mobile, iniciar o menu lateral recolhido.
- Cada programa do TreeView deve ter botao compacto para abrir em nova aba.
- Programas podem abrir por modos fechados: `iframe`, `crud`, `process`, `custom` ou `html`.
- `crud` deve instanciar o `CrudEngine` dentro da area central.
- `process` deve instanciar o `ProcessEngine` dentro da area central.
- `custom` deve instanciar um renderer fechado que aceite apenas `custom.mode` e `custom.entryUrl` relativos ao proprio sistema.
- Programas abertos dentro da Home nao devem duplicar cabecalho interno; a appbar global mostra titulo, versao, subtitulo, ultima atualizacao, ajuda, logs, atualizar e tema.
- `html` deve ser sanitizado e nao pode executar scripts/eventos vindos do JSON.
- `iframe` deve usar URL configurada e titulo acessivel.

## Processamento

- Tela de processamento deve ser separada do CRUD.
- Parametros devem ser declarativos, com labels acima dos campos.
- O botao principal deve ser `Processar`, com icone.
- Ao processar, exibir mensagem de andamento.
- O acompanhamento deve usar SSE quando disponivel e polling como fallback.
- Retornos fechados permitidos: mensagem, grid Kendo, link de relatorio ou job iniciado em segundo plano.
- O appbar global da Home deve avisar quando um job terminar e permitir abrir "Meus Jobs".
- Em producao, endpoints de processamento e status devem usar `endpointId` ou `actionId`.
- Nao aceitar template livre, `eval`, `Function` ou JavaScript vindo do JSON.

## Toolbar do grid

- Botoes principais usam Kendo Button com icones.
- Acoes comuns: Incluir, Filtros, Atualizar, Ordenacao, Agrupamento, Leiaute, Imprimir, Acoes em massa.
- Atualizar deve usar icone `arrow-rotate-cw`.
- Acoes em massa so aparecem se existirem acoes cadastradas.
- Imprimir/exportar so aparece se existirem opcoes.
- Opcoes de leiaute, ordenacao e agrupamento devem abrir em janela, nao ocupar espaco fixo na toolbar.
- Janelas de salvar leiaute, filtro, ordenacao e agrupamento devem permitir marcar "Usar em todos os assinantes" quando a preferencia puder ser global do usuario.
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
- `crud.form.concurrencyWarning` pode exibir aviso Kendo antes de ativar Alterar ou Excluir no formulario ou direto pelo grid, para orientar sobre controle de uso concorrente do registro antes do semaforo real.
- Quando o formulario estiver em inclusao/alteracao com dados modificados e nao salvos, qualquer fechamento ou troca de programa deve pedir confirmacao em janela Kendo antes de descartar alteracoes.
- Quando o backend retornar semaforo de registro, o formulario deve respeitar `block`, avisar em `warn`, renovar heartbeat e liberar o lock ao salvar, cancelar ou fechar.
- Botoes configurados do formulario podem abrir pagina do backend em janela/nova aba e enviar valores atuais do formulario por `query` ou `post`, usando apenas campos declarados no JSON.
- Abas `crud.form.tabs[].type=linkedPage` devem injetar outra pagina CRUD no proprio DOM por `screenId`, com botao Kendo de Atualizar, sem iframe e sem URL livre.
- Em `pageType=master_detail`, `createFlow.mode=parentFirst` mantem os filhos bloqueados ate o pai estar salvo e selecionado.
- Em `pageType=master_detail`, `createFlow.mode=draftWithChildren` deve abrir janela Kendo unica para pai e filhos em rascunho, com tabs para cada filho e confirmacao unica por endpoint transacional.

## Editor administrativo

- Quando uma tela web deixar de ser apenas CRUD/configuracao simples e virar modelador, preferir layout de editor com `Splitter`, arvore lateral, painel central de edicao e painel lateral de contexto.
- Em editores administrativos, o painel lateral pode alternar entre preview, propriedades, relacionamentos, historico, comparativo e diagnostico.
- Reordenacao visual de itens estruturais deve usar arrastar e soltar ou controles equivalentes, com feedback visual de alvo e ordem.
- Validacao incremental deve marcar o item quebrado na propria lista/tabela e refletir contagem de pendencias na navegacao lateral.
- Quando houver risco de edicao concorrente, o backend deve oferecer lock com heartbeat e o frontend deve mostrar sobreposicao de somente leitura no painel afetado.

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
- Consistencias retornadas pelo backend em `validation` devem usar janela Kendo quando bloqueantes ou confirmaveis, marcar campos informados em `validation.messages[].field` e aplicar apenas `effects` seguros.
- Gravacoes bem sucedidas tambem podem retornar `effects` seguros, como `showMessage`, por exemplo para informar que um job assincrono foi agendado.
- Evitar mensagens sobrepostas.
- Evitar verde forte com azul forte.
- Botoes em janelas devem ficar alinhados a esquerda.
- Mensagens runtime como `notice`, `request_logout` e `force_logout` devem ser interceptadas pelo shell/CRUD; preferir SSE com polling como fallback. `force_logout` bloqueia a interface e para heartbeats.

## Seguranca

- Tela em producao deve ser carregada por `screenId`, nao por URL livre.
- Entradas de producao ficam em `production/`; demos continuam nas paginas atuais.
- Entradas de producao nao devem carregar mock, JSON local de `examples/` ou scripts inline de inicializacao.
- URLs livres em APIs e acoes devem ser substituidas por `endpointId` ou `actionId`.
- O backend e quem valida usuario, tenant, permissao, campos permitidos e acao solicitada.
- O JSON continua apenas declarativo: sem JavaScript, `eval`, `Function`, `template` livre ou HTML bruto inseguro.
- Valores enviados para paginas do backend por botoes do formulario sao conveniencia de UI; o backend deve revalidar permissao, tenant e quais campos pode aceitar.
