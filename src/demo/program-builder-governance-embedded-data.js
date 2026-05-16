(function(global) {
  "use strict";

  global.ProgramBuilderGovernanceEmbeddedData = {
    bootstrap: {
      currentUser: {
        userId: "analista",
        name: "Analista"
      },
      modules: [
        { code: "cadastros", name: "Cadastros", abbreviation: "cd", numberStart: 1000, numberEnd: 1999, enabled: true }
      ],
      entities: [
        { code: "cliente", name: "Cliente", entityType: "persistence" }
      ],
      apiSources: [],
      programs: [
        { code: "cd1001", title: "Cadastro governado", programOrigin: "standard", ownerScope: "system" },
        { code: "cd1001-tenant", title: "Overlay governado", programOrigin: "customer_overlay", ownerScope: "subscriber" }
      ]
    },
    entityDefinition: {
      code: "cliente",
      name: "Cliente",
      entityType: "persistence",
      tableName: "t_cliente",
      flags: {
        createTable: true,
        allowTableRename: true,
        allowColumnRename: true,
        dropRemovedColumns: false,
        situationEnabled: true,
        versioningEnabled: true,
        versioningDeduplicate: true
      },
      fields: [
        { code: "id", label: "ID", dataType: "integer", columnName: "id", required: true, primaryKey: true, options: { unique: true, readonly: true } },
        { code: "nome", label: "Nome", dataType: "string", columnName: "nome", length: 120, required: true, options: {} }
      ],
      rules: [],
      uniqueKeys: []
    },
    standardProgramResponse: {
      program: {
        code: "cd1001",
        title: "Cadastro governado",
        module: "cadastros",
        programType: "custom",
        screenId: "cad.clientes",
        status: "draft",
        programOrigin: "standard",
        ownerScope: "system",
        customizationPolicy: "overlay_only",
        subscriberId: null,
        baseProgramCode: null,
        baseProgramVersionId: null,
        upgradeFrozen: false,
        frozenReason: null,
        updatedAt: "2026-05-15T09:00:00-03:00"
      },
      versions: [
        {
          id: 501,
          programCode: "cd1001",
          programTitle: "Cadastro governado",
          module: "cadastros",
          pageType: "custom",
          builderEntityCode: "cliente",
          screenId: "cad.clientes",
          version: "2.0.0",
          status: "draft",
          subtitle: "Fluxo governado de exemplo",
          icon: "user",
          permissionPrefix: "cad.clientes",
          allowCreate: true,
          allowUpdate: true,
          allowDelete: false,
          changeSummary: "Exemplo local do gate governado.",
          programOrigin: "standard",
          ownerScope: "system",
          customizationPolicy: "overlay_only",
          subscriberId: null,
          baseProgramCode: null,
          baseProgramVersionId: null,
          upgradeFrozen: false,
          frozenReason: null,
          governance: {
            requiresGovernance: true,
            request: null,
            grant: null,
            approval: null
          },
          customMode: "iframe",
          customEntryUrl: "/production/app.html?screenId=admin.clientes",
          customFrameTitle: "Cadastro governado",
          builderConfig: {
            publicationPolicy: {
              allowedDatabaseEnvironments: ["test"]
            },
            customMode: "iframe",
            customEntryUrl: "/production/app.html?screenId=admin.clientes",
            customFrameTitle: "Cadastro governado"
          },
          generatedDefinition: {
            schemaVersion: "1.0",
            pageType: "custom",
            screenId: "cad.clientes",
            program: {
              title: "Cadastro governado",
              version: "2.0.0",
              programOrigin: "standard",
              ownerScope: "system"
            },
            custom: {
              mode: "iframe",
              entryUrl: "/production/app.html?screenId=admin.clientes"
            }
          },
          publishedAt: null,
          createdAt: "2026-05-15T09:00:00-03:00",
          updatedAt: "2026-05-15T09:00:00-03:00"
        }
      ]
    },
    overlayVersion: {
      id: 702,
      programCode: "cd1001-tenant",
      programTitle: "Overlay governado",
      module: "cadastros",
      pageType: "custom",
      builderEntityCode: "cliente",
      screenId: "cad.clientes.tenant",
      version: "2.0.0-overlay",
      status: "draft",
      subtitle: "Customizacao do assinante",
      icon: "user",
      permissionPrefix: "cad.clientes.tenant",
      allowCreate: true,
      allowUpdate: true,
      allowDelete: false,
      changeSummary: "Overlay do assinante.",
      programOrigin: "customer_overlay",
      ownerScope: "subscriber",
      customizationPolicy: "overlay_only",
      subscriberId: "tenant-a",
      baseProgramCode: "cd1001",
      baseProgramVersionId: 500,
      upgradeFrozen: false,
      frozenReason: null,
      governance: {
        requiresGovernance: false,
        request: null,
        grant: null,
        approval: null
      },
      customMode: "iframe",
      customEntryUrl: "/production/app.html?screenId=admin.clientes",
      customFrameTitle: "Overlay governado",
      builderConfig: {
        customMode: "iframe",
        customEntryUrl: "/production/app.html?screenId=admin.clientes",
        customFrameTitle: "Overlay governado"
      },
      generatedDefinition: {
        schemaVersion: "1.0",
        pageType: "custom",
        screenId: "cad.clientes.tenant",
        program: {
          title: "Overlay governado",
          version: "2.0.0-overlay",
          programOrigin: "customer_overlay",
          ownerScope: "subscriber"
        }
      },
      publishedAt: null,
      createdAt: "2026-05-15T09:10:00-03:00",
      updatedAt: "2026-05-15T09:10:00-03:00"
    },
    overlayPreview: {
      status: "warning",
      reason: "A base e o overlay alteraram a secao program.",
      overlayId: 700,
      overlayVersionId: 701,
      customizationKind: "customer_overlay",
      currentBaseVersionId: 500,
      currentBaseVersion: "1.9.0",
      targetBaseVersionId: 501,
      targetBaseVersion: "2.0.0",
      baseChangedKeys: ["program", "custom"],
      overlayChangedKeys: ["program"],
      conflicts: ["program"],
      oldBaseDefinition: {
        program: { title: "Cadastro governado", subtitle: "Versao 1.9" },
        custom: { entryUrl: "/production/app.html?screenId=admin.clientes" }
      },
      targetBaseDefinition: {
        program: { title: "Cadastro governado v2", subtitle: "Versao 2.0" },
        custom: { entryUrl: "/production/app.html?screenId=admin.clientes" }
      },
      currentResolvedDefinition: {
        program: { title: "Cadastro governado cliente", subtitle: "Personalizado" },
        custom: { entryUrl: "/production/app.html?screenId=admin.clientes" }
      },
      definitionOverrides: {
        program: { title: "Cadastro governado cliente", subtitle: "Personalizado" }
      },
      sections: [
        { key: "custom", baseChanged: true, overlayChanged: false, conflict: false },
        { key: "program", baseChanged: true, overlayChanged: true, conflict: true }
      ],
      rebasedDefinition: {
        program: { title: "Cadastro governado cliente", subtitle: "Personalizado" },
        custom: { entryUrl: "/production/app.html?screenId=admin.clientes" }
      }
    },
    currentLock: {
      id: 900,
      scopeType: "program",
      scopeCode: "cd1001",
      grantId: null,
      token: "lock-governado"
    }
  };
})(window);
