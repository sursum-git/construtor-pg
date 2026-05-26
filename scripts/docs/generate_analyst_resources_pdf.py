from __future__ import annotations

from datetime import datetime
from pathlib import Path
from typing import Iterable

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    Flowable,
    KeepTogether,
    ListFlowable,
    ListItem,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[2]
OUT_MD = ROOT / "docs" / "recursos-sistema-analista.md"
OUT_PDF = ROOT / "docs" / "recursos-sistema-analista.pdf"


TITLE = "Construtor PG - Recursos existentes do sistema"
SUBTITLE = "Documento detalhado para analista de sistemas"
VERSION = "Atualizado em 26/05/2026"


class Rule(Flowable):
    def __init__(self, color=colors.HexColor("#2f5f8f"), width=1.1):
        super().__init__()
        self.color = color
        self.width = width
        self.height = 0.2 * cm

    def draw(self):
        self.canv.setStrokeColor(self.color)
        self.canv.setLineWidth(self.width)
        self.canv.line(0, 0, self.width_available, 0)

    def wrap(self, availWidth, availHeight):
        self.width_available = availWidth
        return availWidth, self.height


def styles():
    base = getSampleStyleSheet()
    base.add(
        ParagraphStyle(
            name="CoverTitle",
            parent=base["Title"],
            fontName="Helvetica-Bold",
            fontSize=25,
            leading=31,
            textColor=colors.HexColor("#17324d"),
            alignment=TA_CENTER,
            spaceAfter=14,
        )
    )
    base.add(
        ParagraphStyle(
            name="CoverSub",
            parent=base["Normal"],
            fontName="Helvetica",
            fontSize=13,
            leading=18,
            textColor=colors.HexColor("#41566b"),
            alignment=TA_CENTER,
            spaceAfter=20,
        )
    )
    base.add(
        ParagraphStyle(
            name="H1x",
            parent=base["Heading1"],
            fontName="Helvetica-Bold",
            fontSize=17,
            leading=22,
            textColor=colors.HexColor("#17324d"),
            spaceBefore=8,
            spaceAfter=8,
            keepWithNext=True,
        )
    )
    base.add(
        ParagraphStyle(
            name="H2x",
            parent=base["Heading2"],
            fontName="Helvetica-Bold",
            fontSize=12.5,
            leading=16,
            textColor=colors.HexColor("#2f5f8f"),
            spaceBefore=8,
            spaceAfter=5,
            keepWithNext=True,
        )
    )
    base.add(
        ParagraphStyle(
            name="Bodyx",
            parent=base["BodyText"],
            fontName="Helvetica",
            fontSize=9.2,
            leading=13.2,
            textColor=colors.HexColor("#24313d"),
            spaceAfter=5,
        )
    )
    base.add(
        ParagraphStyle(
            name="Smallx",
            parent=base["BodyText"],
            fontName="Helvetica",
            fontSize=8,
            leading=11,
            textColor=colors.HexColor("#445566"),
        )
    )
    base.add(
        ParagraphStyle(
            name="Callout",
            parent=base["BodyText"],
            fontName="Helvetica",
            fontSize=9,
            leading=12.5,
            textColor=colors.HexColor("#203040"),
            backColor=colors.HexColor("#edf4fa"),
            borderColor=colors.HexColor("#a9c7dd"),
            borderWidth=0.7,
            borderPadding=8,
            leftIndent=0,
            rightIndent=0,
            spaceBefore=5,
            spaceAfter=8,
        )
    )
    return base


S = styles()


def p(text: str, style: str = "Bodyx") -> Paragraph:
    return Paragraph(text.replace("\n", "<br/>"), S[style])


def bullets(items: Iterable[str]) -> ListFlowable:
    return ListFlowable(
        [ListItem(p(item), leftIndent=10) for item in items],
        bulletType="bullet",
        leftIndent=16,
        bulletFontName="Helvetica",
        bulletFontSize=7,
    )


def table(rows: list[list[str]], widths: list[float] | None = None) -> Table:
    data = [[p(cell, "Smallx") for cell in row] for row in rows]
    t = Table(data, colWidths=widths, hAlign="LEFT", repeatRows=1)
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#17324d")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("GRID", (0, 0), (-1, -1), 0.3, colors.HexColor("#b8c7d3")),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f6f8fa")]),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return t


def section(story, title: str, intro: str | None = None):
    story.append(p(title, "H1x"))
    story.append(Rule())
    if intro:
        story.append(p(intro))


def subsection(story, title: str):
    story.append(p(title, "H2x"))


def add_md(lines: list[str], text: str = ""):
    lines.append(text)


def md_bullets(lines: list[str], items: Iterable[str]):
    for item in items:
        lines.append(f"- {item}")
    lines.append("")


def build_content():
    story = []
    md = []

    story.append(Spacer(1, 2.8 * cm))
    story.append(p(TITLE, "CoverTitle"))
    story.append(p(SUBTITLE, "CoverSub"))
    story.append(p(VERSION, "CoverSub"))
    story.append(Spacer(1, 1.0 * cm))
    story.append(
        p(
            "Objetivo: oferecer a um analista de sistemas uma visao ampla e operacional "
            "dos recursos ja existentes, dos fluxos principais, das areas administrativas, "
            "dos controles de seguranca e dos pontos que devem ser validados em homologacao.",
            "Callout",
        )
    )
    story.append(PageBreak())

    add_md(md, f"# {TITLE}")
    add_md(md, "")
    add_md(md, f"**{SUBTITLE}**")
    add_md(md, "")
    add_md(md, f"**{VERSION}**")
    add_md(md, "")
    add_md(md, "Este documento consolida os recursos existentes ate agora no sistema.")
    add_md(md, "")

    section(
        story,
        "1. Visao geral do produto",
        "O Construtor PG e um motor de sistemas por metadados. A ideia central e permitir "
        "que telas, processos, programas administrativos, integracoes e regras operacionais "
        "sejam declarados, publicados e executados de forma controlada, com backend decidindo "
        "o que o usuario pode acessar e frontend apenas renderizando definicoes autorizadas.",
    )
    add_md(md, "## 1. Visao geral do produto")
    add_md(
        md,
        "O Construtor PG e um motor de sistemas por metadados. O backend decide e o frontend renderiza definicoes autorizadas.",
    )
    add_md(md, "")

    overview_rows = [
        ["Area", "O que existe hoje", "Valor para analise"],
        ["Frontend dinamico", "CRUD Engine, Home Engine, Process Engine e Custom Page Engine em HTML simples com Kendo UI/jQuery locais.", "Permite validar comportamento de telas sem recompilar a aplicacao."],
        ["Backend runtime", "Symfony/API Platform/PostgreSQL com screenId, endpointId, autenticacao, sessao, auditoria, jobs e permissoes.", "Centraliza seguranca, dados, autorizacao e rastreabilidade."],
        ["Construtor", "Program Builder para modelar entidades, campos, programas, APIs, regras, historico e publicacao.", "Analista consegue transformar requisitos em metadados revisaveis."],
        ["Governanca", "Solicitacao, grant, bundle de testes, aprovacao, rebase de overlay, retencao e auditoria.", "Controla alteracoes em programas padrao e customizacoes por assinante."],
        ["Operacao", "Provisionamento, instalador, licencas, atualizacoes, integridade, jobs, usuarios, permissoes e parametros.", "Cobre ciclo de vida de ambiente e sustentacao."],
    ]
    story.append(table(overview_rows, [3.1 * cm, 7.5 * cm, 6.5 * cm]))
    story.append(Spacer(1, 0.2 * cm))
    story.append(
        p(
            "Principio arquitetural: nenhum JSON de producao deve executar JavaScript livre, "
            "usar template livre, expor URL arbitraria ou substituir validacao backend. "
            "O frontend monta a experiencia, mas permissao, tenant, dados e transicoes ficam no backend.",
            "Callout",
        )
    )
    add_md(md, "### Resumo executivo")
    for row in overview_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]} Valor: {row[2]}")
    add_md(md, "")

    section(story, "2. Stack tecnica e decisoes fechadas")
    add_md(md, "## 2. Stack tecnica e decisoes fechadas")
    tech_items = [
        "Frontend em HTML simples, sem build inicial, usando Kendo UI for jQuery local e jQuery local.",
        "Tema principal atual: `kendo/styles/default-urban.css`.",
        "Backend em Symfony/API Platform com PostgreSQL, Doctrine, Messenger e comandos CLI.",
        "Producao inicial usa `screenId` para carregar telas autorizadas e `endpointId/actionId` para acoes.",
        "A pasta `kendo/` e biblioteca de terceiro e nao deve ser alterada.",
        "Cultura e mensagens devem permanecer em pt-BR.",
        "Nao usar `alert`, `confirm` ou `prompt` nativos; confirmacoes devem ser componentes Kendo.",
        "Nao permitir `eval`, `Function`, template livre ou JavaScript vindo do JSON.",
    ]
    story.append(bullets(tech_items))
    md_bullets(md, tech_items)
    story.append(
        table(
            [
                ["Componente", "Tecnologia/arquivo", "Observacao"],
                ["CRUD Engine", "src/crud-engine/*", "Renderiza grid, filtros, formulario, toolbar, layout, mensagens e chamadas runtime."],
                ["Home Engine", "src/home-engine/*", "Monta appbar, menu lateral, notificacoes, jobs, suporte e abertura de programas."],
                ["Process Engine", "src/process-engine/*", "Executa processos declarativos por parametros com acompanhamento por SSE/polling."],
                ["Program Builder", "program-builder.html / production/program-builder.html", "Modela entidades, programas, APIs, regras, historico e publicacao."],
                ["Backend", "backend/", "Symfony com runtime, admin, autenticacao, migrations, comandos e jobs."],
                ["Instaladores", "installer/", "Executaveis Go por perfil de instalacao."],
            ],
            [3.2 * cm, 5.2 * cm, 8.7 * cm],
        )
    )

    section(story, "3. Entradas e navegacao principal")
    add_md(md, "## 3. Entradas e navegacao principal")
    nav_rows = [
        ["Entrada", "Uso", "Status atual"],
        ["login.html / production/login.html", "Autenticacao, manter logado, recuperacao de senha, selecao de assinante e escolha de area administrativa.", "Demo visual e producao ligada ao backend real."],
        ["home.html / production/home.html", "Shell principal com menu, appbar, notificacoes, jobs, chat, suporte e abertura de programas.", "Home por JSON/screenId com estado local versionado."],
        ["index.html", "Demo principal de clientes.", "Uso demonstrativo."],
        ["exemplos.html", "Indice central de exemplos e variacoes.", "Uso para validacao local."],
        ["program-builder.html / production/program-builder.html", "Construtor visual de programas.", "Interface administrativa principal para autoria."],
        ["production/app.html?screenId=...", "Entrada generica de producao para CRUD, process e custom.", "Carrega somente definicoes autorizadas pelo backend."],
        ["production/install.html", "Instalacao inicial apos ativacao pelo executavel.", "Recusa execucao sem sessao local valida."],
    ]
    story.append(table(nav_rows, [4.8 * cm, 7.0 * cm, 5.3 * cm]))
    for row in nav_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]} Status: {row[2]}")
    add_md(md, "")

    section(story, "4. Login, usuarios, assinantes e sessao")
    add_md(md, "## 4. Login, usuarios, assinantes e sessao")
    story.append(
        p(
            "O login ja possui suporte operacional para usuario/senha local, preparacao para LDAP, SSO e OAuth/OIDC, "
            "recuperacao de senha, manter logado, selecao de assinante e escolha de area para administrador.",
        )
    )
    login_items = [
        "Autenticacao por `/api/auth/login` com token Bearer vinculado a `runtime_user_session`.",
        "Usuarios ficam em `auth_user`; provedores em `auth_provider_config`.",
        "Selecao de assinante usa `auth_subscriber`, `auth_user_subscriber` e desafio temporario.",
        "Administrador pode escolher area principal ou administrativa apos login.",
        "Manter logado usa `auth_remember_token` e endpoint `/api/auth/remember`.",
        "Recuperacao de senha usa `auth_password_reset_token`.",
        "Logout, token invalido, sessao revogada, expiracao e force logout limpam contexto local.",
    ]
    story.append(bullets(login_items))
    md_bullets(md, login_items)
    story.append(
        table(
            [
                ["Tela administrativa", "Finalidade"],
                ["admin.usuarios", "Cadastro de usuarios, status, grupos, permissoes e origem de acesso."],
                ["admin.usuario-assinantes", "Vinculo usuario-assinante e contexto multi-tenant permitido."],
                ["admin.permissoes", "Manutencao focada em grupos e permissoes."],
                ["admin.sessoes", "Consulta de sessoes e acao fechada para derrubar sessao."],
            ],
            [5.0 * cm, 12.1 * cm],
        )
    )

    section(story, "5. Home e experiencia operacional")
    add_md(md, "## 5. Home e experiencia operacional")
    home_items = [
        "Appbar com usuario corrente, assinante corrente e recursos contextuais.",
        "Menu lateral por modulos com busca, favoritos e persistencia do ultimo contexto.",
        "Abertura de programas por CRUD, process, custom ou iframe controlado.",
        "Central de notificacoes com filtros de severidade, categoria, acao requerida e nao lidas.",
        "Jobs no appbar, com abertura para consulta administrativa.",
        "Chat entre usuarios e atendimento com historico persistido e eventos SSE.",
        "Suporte por setor, atendente online ou solicitacao persistida.",
        "Reabertura automatica do ultimo painel contextual quando aplicavel.",
    ]
    story.append(bullets(home_items))
    md_bullets(md, home_items)

    section(story, "6. CRUD Engine e operacao de telas")
    add_md(md, "## 6. CRUD Engine e operacao de telas")
    crud_rows = [
        ["Recurso", "Descricao funcional"],
        ["Grid Kendo", "Paginacao, ordenacao, filtros, acoes de linha, exportacao, agrupamento e selecao."],
        ["Filtros", "Janela de filtros, filtros salvos, filtros aplicados e edicao de filtros aplicados."],
        ["Layout", "Persistencia de layout, ordenacao, agrupamento e template mobile por usuario/tenant."],
        ["Formulario", "Popup com abas, etapas, situacao, logs, impressao, outras acoes e eventos seguros."],
        ["Validacao backend", "Contrato `validation` + `effects`, destaque de campos e confirmacao por token quando necessario."],
        ["Concorrencia", "Semaforo/lock, heartbeat, aviso de concorrencia e protecao contra perda de dados."],
        ["Mensagens", "SSE com fallback por polling para eventos runtime e force logout."],
        ["Mobile", "Modo colunas e template/card seguro, sem template livre vindo do JSON."],
    ]
    story.append(table(crud_rows, [4.2 * cm, 12.9 * cm]))
    for row in crud_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "7. Process Engine e jobs assincronos")
    add_md(md, "## 7. Process Engine e jobs assincronos")
    process_items = [
        "Telas `process` coletam parametros declarativos e chamam endpoint fechado.",
        "Acompanhamento pode ocorrer por SSE ou polling.",
        "Resultado pode ser mensagem, grid, documento/relatorio ou job iniciado.",
        "Backend possui Symfony Messenger/Doctrine em PostgreSQL.",
        "Jobs sao rastreados em `runtime_async_job`.",
        "Exemplos atuais: `cliente.email_confirmation`, `cliente.whatsapp_welcome` e `clientes.processamento`.",
        "Tela `admin.jobs` consulta jobs assincronos.",
    ]
    story.append(bullets(process_items))
    md_bullets(md, process_items)

    section(story, "8. Program Builder")
    add_md(md, "## 8. Program Builder")
    story.append(
        p(
            "O Program Builder e o principal recurso para construir sistemas. Ele permite modelar modulos, entidades, "
            "campos, programas, regras, APIs, historicos, publicacao, governanca e integracoes sem aceitar script livre do usuario.",
        )
    )
    builder_rows = [
        ["Capacidade", "Detalhe"],
        ["Modulos estruturais", "Cadastro de abreviacao e faixa numerica para validar codigo de programa."],
        ["Entidades", "`persistence`, `query`, `io` e `api`."],
        ["Campos", "Tipos, obrigatoriedade, readonly, defaults, FKs, chaves unicas e nomenclatura."],
        ["Tabela fisica", "Criacao/alteracao controlada, rename, defaults, nullability, precision/scale e rollback."],
        ["Importacao PostgreSQL", "Lista, inspeciona e importa tabelas existentes como entidade + rascunho CRUD."],
        ["Importacao JSON externo", "Valida `entityDraft + programDraft` antes de carregar para revisao."],
        ["Assistente IA", "Chat interno com provider mock/openai_compatible, validacao backend e carga de rascunho."],
        ["API/Odoo", "Entidades API readonly ou CRUD previsivel; Odoo readonly por XML-RPC/JSON-RPC."],
        ["Historico", "Entidades mestres versionadas e snapshots em `runtime_entity_record_version`."],
        ["Codificacao customizada", "Campo `custom_code` com padrao declarativo ou metodo restrito no backend."],
        ["Regras", "Declarativas ou classe/metodo em namespace fechado, com mensagens por literais."],
    ]
    story.append(table(builder_rows, [4.8 * cm, 12.3 * cm]))
    for row in builder_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "9. Governanca de programas")
    add_md(md, "## 9. Governanca de programas")
    governance_items = [
        "Programas possuem ownership e politica de customizacao: `standard`, `customer_overlay`, `customer_custom`.",
        "Programa padrao pode exigir request, grant, bundle de testes e aprovacao final.",
        "Grant revogado/congelado derruba lock de autoria e bloqueia continuidade.",
        "Rebase assistido de overlay classifica conflitos como ok, warning ou blocked.",
        "Conflito leve exige confirmacao explicita; conflito critico bloqueia.",
        "Retencao da governanca possui preview, aplicacao, historico e comparativo antes/depois.",
        "Comandos operacionais: `app:governance:monitor`, `app:governance:operations`, `app:governance:cleanup-history`.",
    ]
    story.append(bullets(governance_items))
    md_bullets(md, governance_items)
    story.append(
        table(
            [
                ["Tela", "Uso"],
                ["admin.programa-governanca", "Operar requests, grants, bundles, aprovacoes, retencao e rebase."],
                ["admin.programa-grants-operacao", "Entrada focada para grants."],
                ["admin.programa-aprovacoes-operacao", "Entrada focada para aprovacoes."],
                ["admin.programa-retencao-operacao", "Ajuste rapido da retencao."],
                ["admin.programa-retencao-historico-operacao", "Historico persistido da retencao."],
                ["admin.programa-auditoria-operacao", "Timeline e sinais operacionais."],
                ["admin.programa-overlays-operacao", "Overlays, congelamento e rebase."],
                ["admin.programa-overlay-versoes-operacao", "Historico, comparacao e publicacao de versoes de overlay."],
            ],
            [5.4 * cm, 11.7 * cm],
        )
    )

    section(story, "10. Multi-tenant, assinantes e isolamento")
    add_md(md, "## 10. Multi-tenant, assinantes e isolamento")
    tenant_items = [
        "Login pode exigir selecao de assinante quando `subscriber.enabled` estiver ativo.",
        "Entidades persistentes aceitam `subscriberIsolation.mode=none|subscriber_column`.",
        "`subscriber_column` injeta filtro de assinante em read/get e limita update/delete.",
        "`none` exige confirmacao explicita de tabela global compartilhada.",
        "Provisionamento registra deployment mode, ambiente principal e ambiente runtime.",
        "Modos de deployment: `shared_program_shared_db`, `shared_program_dedicated_db`, `dedicated_stack`, `onprem_remote`.",
        "Tela `admin.assinante-ambientes` mostra matriz operacional e catalogo de isolamento.",
    ]
    story.append(bullets(tenant_items))
    md_bullets(md, tenant_items)

    section(story, "11. Integracoes, importacao e exportacao")
    add_md(md, "## 11. Integracoes, importacao e exportacao")
    integration_rows = [
        ["Item", "Detalhe"],
        ["Tela", "`admin.integracoes` com editor visual, TreeView, preview e historico."],
        ["Origem", "Entidade persistence, API generica, API Odoo readonly e XML declarativo."],
        ["Destino", "Entidade local, API JSON previsivel, CSV, XML e TXT layout."],
        ["TXT", "Posicional fixo, delimitado e hierarquico com record/group/totalizer."],
        ["XML", "Namespaces, atributos, filhos repetitivos, recordPath, xpath e vinculo pai/filho."],
        ["Historico", "Execucoes, versoes do mapping, agendamentos e exportacao do payload."],
        ["Seguranca", "Sem transformacao por JavaScript; uso de contratos fechados."],
    ]
    story.append(table(integration_rows, [4.2 * cm, 12.9 * cm]))
    for row in integration_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "12. Administracao runtime")
    add_md(md, "## 12. Administracao runtime")
    admin_rows = [
        ["Tela", "Funcao"],
        ["admin.parametros", "Define parametros do sistema, tipos, listas e metadados."],
        ["admin.parametro-valores", "Valores vigentes globais ou por contexto."],
        ["admin.listas-opcoes / admin.opcoes", "Listas fechadas e opcoes reutilizaveis."],
        ["admin.literais", "Literais e traducoes por locale para frontend/backend."],
        ["admin.notificacoes", "Cadastro de notificacoes runtime."],
        ["admin.notificacao-destinatarios", "Acompanhamento de entrega e leitura."],
        ["admin.integridade", "Monitor de assinaturas estruturais e reassinatura controlada."],
        ["admin.transacoes / admin.logs-transacoes", "Auditoria de operacoes runtime."],
        ["admin.jobs", "Consulta de jobs assincronos."],
    ]
    story.append(table(admin_rows, [5.4 * cm, 11.7 * cm]))
    for row in admin_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "13. Integridade, auditoria e rastreabilidade")
    add_md(md, "## 13. Integridade, auditoria e rastreabilidade")
    integrity_items = [
        "Transacoes registram `programVersion`, `builderProgramVersionId`, `builderEntityVersionId`, `screenDefinitionVersion` e `schemaFingerprint`.",
        "Tambem registram ambiente, identidade do banco, tipo de customizacao, grant, request, approval e bundle de teste quando aplicavel.",
        "Assinatura estrutural em `system_record_integrity` cobre programas, entidades, campos, endpoints, overlays, parametros, opcoes, integracoes e outros registros sensiveis.",
        "Comandos: `app:integrity:check`, `app:integrity:monitor`, `app:integrity:resign`.",
        "Reassinatura controlada registra motivo, usuario, horario, hash anterior e status antes/depois.",
    ]
    story.append(bullets(integrity_items))
    md_bullets(md, integrity_items)

    section(story, "14. Instalacao, licencas e provisionamento")
    add_md(md, "## 14. Instalacao, licencas e provisionamento")
    install_rows = [
        ["Recurso", "Detalhe"],
        ["Executaveis Go", "Quatro binarios: builder/subscriber para Linux e Windows; perfil compilado."],
        ["Precheck", "Valida dependencias por modo; ERRO bloqueia e AVISO permite continuar registrado."],
        ["Ativacao central", "Codigo do assinante, e-mail de confirmacao, sessao curta e manifesto autorizado."],
        ["Licencas", "`admin.instalacao-licencas` controla e-mail, perfil, modo, validade, status e limite."],
        ["Tokens internos", "`admin.instalacao-tokens` controla tokens para provisionamento SaaS sem e-mail manual."],
        ["Operacoes da central", "`admin.central-operacoes` consolida painel operacional, auditoria, revogacao, tentativas/bloqueio, chaves, artefatos, saude dos assinantes e notificacoes derivadas."],
        ["Bloqueio de tentativas", "Codigos de e-mail invalidos sao bloqueados por requisicao conforme `APP_INSTALLER_ACTIVATION_MAX_ATTEMPTS` e `APP_INSTALLER_ACTIVATION_BLOCK_MINUTES`."],
        ["Pagina web", "`production/install.html` mostra ativacao e executa etapas finais."],
        ["Docker Linux", "`app` com Nginx/PHP-FPM/Supervisor e `database` PostgreSQL 16."],
        ["Worker", "No Docker fica inativo por padrao; ativar com `APP_WORKER_ENABLED=1` apos instalacao."],
        ["Reinstalacao", "Exige nova ativacao, senha do instalador e confirmacao explicita."],
    ]
    story.append(table(install_rows, [4.3 * cm, 12.8 * cm]))
    for row in install_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")
    story.append(
        p(
            "A pagina de instalacao nao pede mais token de liberacao. O bloqueio e feito pela sessao local criada pelo executavel. "
            "Sem sessao valida, `/api/install/run` recusa a instalacao.",
            "Callout",
        )
    )

    section(story, "15. Atualizacoes do sistema")
    add_md(md, "## 15. Atualizacoes do sistema")
    update_items = [
        "Tela `admin.atualizacoes` le manifesto, avalia dependencias e aplica releases por job.",
        "Tela `admin.atualizacoes-assinantes` consulta historico por assinante.",
        "Releases aceitam `requiresVersionMin`, `requiresAppliedUpdates`, `replaces`, `category`, `autoApply`, `breakingLevel` e `steps`.",
        "Politica da release declara backup, manutencao, auto apply, anuencia, bloqueio de proximas e internet obrigatoria.",
        "Manifesto passa por validacao de coerencia: dependencias inexistentes, replaces invalidos, ciclos e auto-referencia.",
        "SaaS pode usar rollout por janela, batches/canario e orquestrador externo assinado.",
        "On-premise usa runner `update-onprem.sh|ps1` e politicas criticas de warn/block, auto/prompt/download_only.",
        "Programas `standard`, `customer_overlay` e `customer_custom` respeitam regras diferentes de atualizacao.",
    ]
    story.append(bullets(update_items))
    md_bullets(md, update_items)

    section(story, "16. Seguranca funcional")
    add_md(md, "## 16. Seguranca funcional")
    security_rows = [
        ["Regra", "Impacto"],
        ["screenId em producao", "Frontend pede uma tela conhecida; backend devolve apenas definicao autorizada."],
        ["endpointId/actionId", "Acoes passam por identificadores fechados, evitando URL livre no JSON."],
        ["Sem JS livre", "JSON nao pode injetar `eval`, `Function`, template livre ou script."],
        ["Permissao backend", "Permissao visual nao substitui validacao de usuario, tenant, registro e transicao."],
        ["Auth required", "`AUTH_REQUIRED=1` recusa runtime sem token valido."],
        ["Segredos", "Tokens e chaves devem ficar em parametros/variaveis mascaradas."],
        ["Instalacao", "Executavel + central + sessao curta reduzem risco de liberacao manual indevida."],
    ]
    story.append(table(security_rows, [4.4 * cm, 12.7 * cm]))
    for row in security_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "17. Roteiro sugerido para analise funcional")
    add_md(md, "## 17. Roteiro sugerido para analise funcional")
    validation_rows = [
        ["Ordem", "Trilha", "Resultado esperado"],
        ["1", "Instalacao/licenca", "Ambiente ativado, precheck ok, pagina install liberada e sistema instalado."],
        ["2", "Login/sessao", "Usuario entra, seleciona assinante e area correta."],
        ["3", "Home", "Menu, appbar, notificacoes, jobs e contexto funcionam."],
        ["4", "CRUD base", "Grid, filtro, formulario, validacoes e acoes passam."],
        ["5", "Processos/jobs", "Processamento por parametros e job assincrono acompanham corretamente."],
        ["6", "Program Builder", "Modulo, entidade, programa, preview e publicacao funcionam."],
        ["7", "Governanca", "Request, grant, teste, aprovacao, publish e rebase cobertos."],
        ["8", "Integracoes", "Mapping, preview, execucao, historico e agendamento validados."],
        ["9", "Integridade", "Monitor sem invalidez ou com fluxo de reassinatura controlado."],
        ["10", "Atualizacoes", "Manifesto, precheck, simulacao, apply, rollback e historico por assinante."],
    ]
    story.append(table(validation_rows, [1.4 * cm, 5.0 * cm, 10.7 * cm]))
    for row in validation_rows[1:]:
        add_md(md, f"{row[0]}. **{row[1]}**: {row[2]}")
    add_md(md, "")

    section(story, "18. Evidencias que o analista deve coletar")
    add_md(md, "## 18. Evidencias que o analista deve coletar")
    evidence_items = [
        "Ambiente usado: demo local, producao local, backend real, on-premise ou SaaS.",
        "Usuario, perfil, grupos e assinante usados em cada trilha.",
        "`screenId` e programa aberto.",
        "Passos executados e resultado esperado x obtido.",
        "Screenshots dos fluxos principais.",
        "Erros separados por tipo: funcional, permissao, dado, ambiente, documentacao ou seguranca.",
        "Quando houver instalacao: licenca usada, modo, precheck, ativacao, data/hora e resultado.",
        "Quando houver publicacao: versao, request, grant, bundle de teste e aprovacao.",
        "Quando houver update: release, politica, simulacao, aplicacao, rollback e impacto em overlays.",
    ]
    story.append(bullets(evidence_items))
    md_bullets(md, evidence_items)

    section(story, "19. Mapa rapido de programas e telas")
    add_md(md, "## 19. Mapa rapido de programas e telas")
    map_rows = [
        ["Grupo", "Telas principais"],
        ["Acesso", "production/login.html, admin.usuarios, admin.usuario-assinantes, admin.permissoes, admin.sessoes"],
        ["Navegacao", "production/home.html, production/app.html?screenId=..."],
        ["Operacao diaria", "cadastros.clientes, admin.jobs, telas CRUD publicadas pelo builder"],
        ["Construcao", "production/program-builder.html"],
        ["Governanca", "admin.programa-governanca e entradas focadas de grants, aprovacoes, retencao, auditoria e overlays"],
        ["Administracao", "admin.parametros, admin.parametro-valores, admin.literais, admin.notificacoes, admin.integridade"],
        ["Integracoes", "admin.integracoes"],
        ["Provisionamento", "admin.assinante-ambientes, admin.instalacao-licencas, admin.instalacao-tokens, admin.central-operacoes, production/install.html"],
        ["Atualizacoes", "admin.atualizacoes, admin.atualizacoes-assinantes"],
    ]
    story.append(table(map_rows, [4.0 * cm, 13.1 * cm]))
    for row in map_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "20. Pontos de atencao para proximas homologacoes")
    add_md(md, "## 20. Pontos de atencao para proximas homologacoes")
    attention_items = [
        "Confirmar se migrations e `app:seed-runtime-metadata` foram executados no ambiente alvo.",
        "Confirmar SMTP real antes de validar ativacao por e-mail e recuperacao de senha.",
        "Confirmar `AUTH_REQUIRED=1` quando validar producao real.",
        "Confirmar worker ativo quando a trilha envolver jobs, provisionamento ou atualizacao.",
        "Confirmar se o ambiente e central SaaS quando validar telas administrativas restritas ao central.",
        "Confirmar se a porta Docker publicada nao conflita com outro servico local.",
        "Confirmar se licencas de instalacao usam limites coerentes para o contrato do assinante.",
        "Confirmar se tabelas globais foram explicitamente justificadas no builder.",
        "Confirmar se programa padrao nao foi alterado sem governanca quando a politica exigir gate.",
    ]
    story.append(bullets(attention_items))
    md_bullets(md, attention_items)

    section(story, "21. Checklist detalhado de validacao por area")
    add_md(md, "## 21. Checklist detalhado de validacao por area")
    checklist_rows = [
        ["Area", "Validacoes minimas"],
        ["Instalacao", "Licenca ativa, perfil correto, modo autorizado, e-mail recebido, precheck sem ERRO, sessao local gravada, install finalizado."],
        ["Central operacional", "Painel sem alerta critico, chaves fortes, artefatos configurados, token SaaS valido, auditoria e saude dos assinantes revisadas."],
        ["Login", "Senha valida, senha invalida, manter logado, recuperar senha, limpar sessao local, expiracao e logout."],
        ["Assinante", "Selecao apos login, troca pela Home quando habilitada, permissao por assinante e fallback para principal."],
        ["Home", "Menu, favoritos, busca, ultimo programa, notificacoes, jobs, chat, suporte e persistencia de contexto."],
        ["CRUD", "Read, get, create, update, delete, validacoes, concorrencia, filtros, layout, exportacao e mobile."],
        ["Processo", "Parametros obrigatorios, validacao, inicio, acompanhamento, cancelamento/erro, retorno e documento."],
        ["Builder", "Modulo, entidade, campo, tabela, regra, preview, diagnostico, publish e abertura por screenId."],
        ["Governanca", "Request, grant, lock, bundle de testes, aprovacao, publish, auditoria e retencao."],
        ["Overlay", "Criacao por assinante, rebase, conflito leve, conflito bloqueante, congelamento e publish."],
        ["Integracao", "Cadastro de mapping, preview, execucao, historico, versao, agenda e exportacao."],
        ["Integridade", "Monitor, item valido, item invalido, reassinatura, log e comando CLI."],
        ["Atualizacao", "Check, simulacao, precheck, anuencia, aplicacao, rollback, impacto em programa e timeline."],
    ]
    story.append(table(checklist_rows, [3.0 * cm, 14.1 * cm]))
    for row in checklist_rows[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]}")
    add_md(md, "")

    section(story, "22. Matriz de recursos do Program Builder")
    add_md(md, "## 22. Matriz de recursos do Program Builder")
    builder_matrix = [
        ["Frente", "Recursos existentes", "O que o analista deve observar"],
        ["Modulo", "Abreviacao, faixa numerica, agrupamento estrutural.", "Codigo de programa precisa estar dentro da faixa e seguir padrao."],
        ["Entidade persistence", "Tabela fisica, campos, PK, FKs, defaults, unique, readonly, situacao.", "Sem metadados completos o runtime nao deve inferir tela automaticamente."],
        ["Entidade api", "API cadastrada, operacoes de lista/detalhe/escrita, jsonPath, Odoo readonly.", "Validar se contrato externo e previsivel e se nao ha transformacao livre."],
        ["Programa CRUD", "Grid, formulario, filtros, permissoes, endpointId, preview.", "Confirmar se a definicao publicada abre em producao por screenId."],
        ["Programa custom", "Entrada relativa iframe/htmlUrl autorizada.", "Nao aceitar URL externa livre em producao."],
        ["Programa process", "Parametros, endpoint process/status, retorno fechado.", "Confirmar job e acompanhamento por SSE/polling."],
        ["Historico", "Snapshot de mestre e referencia historica em transacional.", "Validar que registro antigo continua mostrando dado da epoca."],
        ["Codificacao", "Pattern declarativo, sequencia, assistente seguro, metodo estatico restrito.", "Valor final deve ser gerado no backend."],
        ["Regras", "requiredWhen, class_method, ordem, fase, continueOnError.", "Mensagens devem preferir messageKey/messageParams."],
        ["Publicacao", "Draft, published, archived, duplicacao, rollback e gate de ambiente.", "Publicar programa padrao pode exigir governanca."],
    ]
    story.append(table(builder_matrix, [3.4 * cm, 7.2 * cm, 6.5 * cm]))
    for row in builder_matrix[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]} Observar: {row[2]}")
    add_md(md, "")

    section(story, "23. Matriz de instalacao e operacao")
    add_md(md, "## 23. Matriz de instalacao e operacao")
    install_matrix = [
        ["Cenario", "Fluxo", "Ponto critico"],
        ["Linux Docker on-premise", "Executavel Linux, precheck Docker, ativacao, compose, pagina install.", "Docker, Compose, portas, registry, disco, DNS e relogio."],
        ["Linux nativo on-premise", "Executavel Linux, precheck PHP/Composer/PostgreSQL, pacote assinado, servico local.", "PHP 8.4, extensoes, psql/pg_dump/pg_restore, systemd e permissoes."],
        ["Windows teste", "Executavel Windows, native mode, servidor local simples.", "Nao e producao; nao oferecer modo Docker."],
        ["Docker SaaS", "Orquestrador usa token interno, manifesto autorizado e stack do assinante.", "Sem e-mail manual; token e assinatura precisam estar corretos."],
        ["Reinstalacao", "Nova ativacao, senha do instalador e confirmacao explicita.", "Exigir backup ou justificativa operacional."],
        ["Atualizacao on-premise", "Runner local consulta manifesto, baixa pacote, aplica steps e recria containers se preciso.", "Politica critica warn/block e modo auto/prompt/download_only."],
        ["Rollout SaaS", "Central despacha plano para orquestrador externo por HTTP assinado.", "Batches, janela, canario, bloqueio temporario e auditoria."],
    ]
    story.append(table(install_matrix, [4.0 * cm, 7.4 * cm, 5.7 * cm]))
    for row in install_matrix[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]} Ponto critico: {row[2]}")
    add_md(md, "")

    section(story, "24. Matriz de seguranca e controles")
    add_md(md, "## 24. Matriz de seguranca e controles")
    controls_matrix = [
        ["Controle", "Como funciona", "Risco mitigado"],
        ["screenId", "Backend resolve tela conhecida e autorizada.", "Carga de JSON livre ou tela nao autorizada."],
        ["endpointId", "Acoes passam por identificadores publicados.", "Chamada direta a URL livre pelo JSON."],
        ["Auth Bearer", "Sessao runtime vinculada ao token.", "Uso anonimo indevido quando AUTH_REQUIRED=1."],
        ["Permissao", "Backend valida tela, endpoint, tenant, usuario e registro.", "Permissao apenas visual no frontend."],
        ["Tenant", "Assinante selecionado filtra ou limita operacoes.", "Vazamento entre assinantes."],
        ["Integridade", "Assinatura estrutural detecta alteracao fora do fluxo.", "Mudanca manual de metadados sensiveis."],
        ["Governanca", "Request, grant, teste e aprovacao para programa padrao.", "Alteracao padrao sem controle."],
        ["Instalador", "Executavel, licenca, e-mail, sessao curta e prova assinada.", "Instalacao liberada por alteracao simples de env."],
        ["Update", "Manifesto assinado, pacote assinado, politica e precheck.", "Aplicacao de release incoerente ou nao autorizada."],
    ]
    story.append(table(controls_matrix, [3.6 * cm, 7.3 * cm, 6.2 * cm]))
    for row in controls_matrix[1:]:
        add_md(md, f"- **{row[0]}**: {row[1]} Mitiga: {row[2]}")
    add_md(md, "")

    section(story, "25. Perguntas que o analista deve responder")
    add_md(md, "## 25. Perguntas que o analista deve responder")
    questions = [
        "O sistema esta sendo validado em demo, producao local, SaaS ou on-premise?",
        "O login usado representa corretamente usuario comum, administrador e assinante?",
        "A Home mostra os programas esperados para o perfil testado?",
        "As telas administrativas aparecem apenas para usuarios autorizados?",
        "As entidades persistentes por assinante possuem filtro correto ou foram assumidas como globais com justificativa?",
        "O Program Builder consegue transformar um requisito novo em entidade, programa, preview e tela publicada?",
        "O fluxo de governanca impede alteracao indevida em programa padrao?",
        "As integracoes usam contratos fechados e historico de execucao suficiente?",
        "As atualizacoes respeitam cadeia, anuencia, backup, manutencao e impacto em customizacoes?",
        "A instalacao exige licenca, ativacao, precheck e sessao local antes da tela web?",
        "As evidencias coletadas permitem reproduzir cada erro encontrado?",
    ]
    story.append(bullets(questions))
    md_bullets(md, questions)

    story.append(PageBreak())
    section(story, "26. Apendice: comandos e arquivos de referencia")
    add_md(md, "## 26. Apendice: comandos e arquivos de referencia")
    command_rows = [
        ["Finalidade", "Comando/arquivo"],
        ["Bootstrap", "php backend/bin/console app:install:bootstrap"],
        ["Criar assinante", "php backend/bin/console app:subscriber:create"],
        ["Publicar defaults", "php backend/bin/console app:runtime:publish-defaults"],
        ["Seed runtime", "php backend/bin/console app:seed-runtime-metadata --no-interaction"],
        ["Worker", "php backend/bin/console messenger:consume async -vv"],
        ["Integridade", "php backend/bin/console app:integrity:monitor --fail-on-invalid"],
        ["Governanca", "php backend/bin/console app:governance:operations"],
        ["Atualizacao", "php backend/bin/console app:update:check / app:update:apply <versao>"],
        ["Instalador", "installer/build.ps1 ou installer/build.sh"],
        ["Docker", "docker compose build / docker compose up -d"],
        ["Manual instalacao", "docs/manual-instalacao.md"],
        ["Roteiro analista", "docs/roteiro-validacao-funcional-analista.md"],
    ]
    story.append(table(command_rows, [4.2 * cm, 12.9 * cm]))
    for row in command_rows[1:]:
        add_md(md, f"- **{row[0]}**: `{row[1]}`")
    add_md(md, "")

    return story, "\n".join(md).strip() + "\n"


def header_footer(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 7.5)
    canvas.setFillColor(colors.HexColor("#657786"))
    if doc.page > 1:
        canvas.drawString(1.7 * cm, 1.15 * cm, "Construtor PG - Recursos existentes do sistema")
        canvas.drawRightString(19.3 * cm, 1.15 * cm, f"Pagina {doc.page}")
    canvas.restoreState()


def main():
    story, markdown = build_content()
    OUT_MD.write_text(markdown, encoding="utf-8")
    doc = SimpleDocTemplate(
        str(OUT_PDF),
        pagesize=A4,
        rightMargin=1.7 * cm,
        leftMargin=1.7 * cm,
        topMargin=1.6 * cm,
        bottomMargin=1.7 * cm,
        title=TITLE,
        author="Construtor PG",
        subject=SUBTITLE,
    )
    doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
    print(OUT_PDF)
    print(OUT_MD)


if __name__ == "__main__":
    main()
