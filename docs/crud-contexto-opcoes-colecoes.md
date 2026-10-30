# Contexto de navegação, opções remotas e coleções API

O CRUD existente aceita links por linha e contexto de navegação declarados no rascunho do programa. Não são uma fronteira de autorização: o runtime mantém suas permissões habituais e a API de negócio valida as relações.

## Links e contexto

~~~json
{"rowLinks":[{"id":"empresas","label":"Empresas","screenId":"ciw.empresas-despesa","parameters":[{"field":"despesa_id","sourceField":"despesa_id"}]}]}
~~~

O destino usa somente a entrada própria production/app.html, com screenId e context__despesa_id. A tela destino declara contextFields:[{field:"despesa_id",filter:true,createDefault:true,readonly:true,required:true}]. Campos de contexto são integer positivo int32. Ausência obrigatória ou valor inválido interrompe a abertura. O filtro contextual permanece ao limpar filtros comuns; a inclusão recebe o valor e o formulário o apresenta sem edição. Este readonly é da interface, sem remover o campo da escrita API.

## Opções e coleções

Campos de entidade API podem declarar options.editor:"dropdown" e options.api.optionsSource:{operationCode:"opcoes_empresas",valueField:"empresa_id",textField:"nome"}. A operação precisa ser list/custom da mesma fonte cadastrada. O builder publica options.<campo> com permissão read existente e configuração de campo fixa. O navegador envia apenas o pedido ao endpoint conhecido; não escolhe URL, operação ou nomes de tabela. Credenciais continuam no servidor. Descrição null nas opções usa o ID como texto de apresentação; objetos e listas continuam recusados. Campos booleanos copiados, incluindo a flag de imposto, exigem 0/1 ou boolean conhecido.

Coleções usam dataType:"json", options.editor:"multiselect" e options.api.collection:{itemType:"integer"}. O runtime gera type:"array", mantém JSON arrays, aceita [] para limpar e exige no máximo 100 IDs positivos int32 distintos. Objetos, null, strings, booleanos, duplicatas e limites inválidos são recusados antes da chamada externa. Mapping jsonPath/writePath continua válido. Arrays substituem integralmente arrays do template, incluindo a lista vazia.

optionsSource.excludeCurrent:true envia id inteiro opcional à operação fixa: usa o único contexto da navegação quando existir, ou a chave do registro. Seu objetivo é excluir a própria despesa das escolhas; a API valida autorreferências novamente. Em contextos múltiplos use operações específicas sem essa opção.

optionsSource.copyFields:{eh_imposto:"log_imposto"} copia somente campos de apresentação declarados readonly e sem chave primária. O backend retorna fields:{eh_imposto:true} nas opções, convertendo 0/1 ou boolean para o tipo boolean de destino. O formulário carrega inclusive dropdown contextual readonly, permitindo inicializar condições na inclusão a partir do pai consultado. Não há campo arbitrário de escrita por esse caminho. Falha de lookup impede salvar até reabrir o formulário após corrigir a consulta.

## Formulário

options.visibleWhen e options.enabledWhen aceitam somente {field:"campo_existente",operator:"eq",value:true} (valor escalar). Reutilizam o avaliador declarativo do formulário. As condições são reavaliadas ao abrir, carregar opções e mudar os valores; não limpam os dados. options.readonlyOnEdit:true trava o campo depois da inclusão. options.defaultValue preserva 0 e false; coleção inicia com []. scale de campo decimal é emitido como decimals e formato nN. Dropdown de campo integer conserva números, inclusive opções estáticas 0/1.

Campos readonly de apresentação são omitidos em create/update; a chave primária segue no update. A API preserva valores omitidos conforme seu contrato. Não declarar readonly global em um campo pai que precisa ser enviado na inclusão.

## Verificação local

- PHP: vendor/phpunit/phpunit/phpunit --do-not-cache-result; testes específicos RuntimeApiCollectionsOptionsTest e ProgramBuilderContextCollectionsTest.
- Edge: node tests/frontend/crud-context-collections-smoke.mjs.
- Regressões: crud-grid-batch-smoke.mjs, program-builder-batch-smoke.mjs, crud-form-submit-guard-smoke.mjs e render-gate-smoke.mjs. Os dois últimos usam Chromium por padrão; nesta máquina foram executados com launch.channel msedge por adaptação em memória do executor.

O core não publica automaticamente telas nem fontes API e não escreve registros de negócio durante esses testes. A importação/publicação dos programas usa o fluxo oficial de governança do builder.
