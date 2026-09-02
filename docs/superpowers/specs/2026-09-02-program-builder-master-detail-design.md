# Program Builder: programas mestre-detalhe

## Objetivo

Permitir que o Program Builder gere, valide, publique e tenha o preview de programas
`master_detail`. A definicao publicada deve ser consumida pelo `MasterDetailEngine`
sem HTML, JavaScript, templates ou URLs livres no metadado.

O primeiro uso sera o pedido de venda. Depois da capacidade estar pronta, produtos e
pessoas serao programas CRUD publicados pelo Builder e o pedido sera um programa
`master_detail` tambem publicado pelo Builder. Os tres casos servirao para testar a
renderizacao de objetos construidos por metadados.

## Escopo da primeira fase

### Novo tipo de pagina

O seletor de tipo de programa aceitara `master_detail`. Ao seleciona-lo, o Builder
mostrara uma configuracao declarativa propria, em vez de um formulario manual:

- entidade mestre;
- uma ou mais entidades-filhas ja modeladas;
- para cada filha: titulo, campo FK que referencia o mestre, campos/colunas exibidos
  e totais declarativos;
- modo de inclusao `parentFirst` ou `draftWithChildren`;
- no modo conjunto, operacao segura `createGraph`;
- titulos, permissoes e `screenId` conforme o padrao dos outros programas.

As selecoes ficam limitadas a entidades e campos existentes. A configuracao nao
aceita URL, SQL, codigo, template ou JavaScript arbitrario.

### Definicao publicada

O backend gerara `pageType=master_detail` no formato esperado pelo
`MasterDetailEngine`. A definicao conterá `master`, `details[]` e `createFlow`, com
operacoes referenciadas por `endpointId`. A resolucao de caminhos, autorizacao e
execucao continuam no runtime Symfony.

O schema, os validadores do frontend e do backend e o preview do Program Builder
aceitarao esse novo tipo. Publicacao, versao, historico, governanca e permissoes
continuam usando o fluxo ja existente para programas.

### Fonte de dados e contrato futuro

O frontend recebe somente o `screenId` publicado e chama os endpoints runtime
fechados. Nos testes iniciais, esses endpoints sao atendidos por um adaptador mock
no backend/runtime. O mock expora as operacoes de leitura e escrita do mestre e dos
filhos, alem de `createGraph`:

```json
{
  "cabecalho": {},
  "itens": [],
  "parcelas": []
}
```

O adaptador sera substituivel pelo middleware PHP que encaminhara o mesmo contrato
ao motor Progress ou, nos casos apropriados, ao API Platform. Metadados e
componentes visuais nao precisam mudar nessa troca.

## Validacoes

O Builder deve recusar:

- mestre ou filha inexistente;
- filha sem FK valida para o mestre;
- repeticao da mesma filha;
- coluna ou total que nao pertença a filha selecionada;
- `draftWithChildren` sem `createGraph` configurado;
- programa sem `screenId`, permissao ou entidade mestre.

O runtime preserva validacao fechada de permissao e operacao por `endpointId`.
Erros de negocio sao retornados como contrato estruturado por campo/colecao, nunca
como mensagens do navegador.

## Validacao grafica e automatizada

Antes de considerar a fase concluida, os testes devem confirmar:

1. o preview do Builder representa mestre, abas filhas e o modo escolhido;
2. a publicacao gera uma tela que o `MasterDetailEngine` consegue carregar pelo
   `screenId`;
3. em desktop, o mestre e as abas filhas ficam visiveis, selecionaveis e utilizaveis;
4. em largura reduzida, a tela mantem leitura, navegacao entre abas e acoes de
   inclusao sem sobreposicao;
5. a inclusao conjunta envia um unico `createGraph` e mostra corretamente erros do
   runtime;
6. testes unitarios/backend cobrem a geracao, a validacao e a publicacao da
   definicao; testes Playwright cobrem a interface publicada.

As validacoes visuais serao feitas nas entradas locais `file:///C:/construtor-pg/`
quando aplicavel e na tela de producao local carregada por `screenId` quando o
runtime estiver disponivel.

## Fase posterior: exemplos de dominio

Depois da primeira fase:

- Produto: CRUD gerado pelo Builder, com codigo, descricao, tipo, unidade, preco,
  estoque minimo e situacao.
- Pessoa: CRUD gerado pelo Builder, com PF/PJ, documento, contatos e endereco.
- Pedido de venda: `master_detail` gerado pelo Builder, com cabecalho, cliente,
  condicao de pagamento, itens e parcelas.

Os testes verificarao a renderizacao pelos metadados publicados e as operacoes do
mock. Essa fase atualizara exemplos e a matriz de paridade demo/producao.

## Fora de escopo desta fase

- Implementar o projeto separado do motor Progress.
- Conectar a um servidor Progress real.
- Alterar a biblioteca `kendo/`.
- Criar telas HTML especificas para produto, pessoa ou pedido.
