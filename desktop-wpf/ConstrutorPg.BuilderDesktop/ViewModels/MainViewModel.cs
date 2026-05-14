using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Text.Json;
using System.Windows;
using ConstrutorPg.BuilderDesktop.Models;

namespace ConstrutorPg.BuilderDesktop.ViewModels;

public sealed class MainViewModel : ViewModelBase
{
    private readonly EmptySelectionViewModel _emptySelection = new();
    private ExplorerNodeViewModel? _selectedNode;
    private string _previewJson = string.Empty;
    private string _statusMessage = "MVP carregado em memoria.";

    public MainViewModel()
    {
        AddModuleCommand = new RelayCommand(AddModule);
        AddEntityCommand = new RelayCommand(AddEntity);
        AddFieldCommand = new RelayCommand(AddField);
        RefreshPreviewCommand = new RelayCommand(() => RefreshPreview());
        CopyPreviewCommand = new RelayCommand(CopyPreview);

        LoadSampleWorkspace();
        RefreshPreview();
    }

    public ObservableCollection<ExplorerNodeViewModel> RootNodes { get; } = [];

    public RelayCommand AddModuleCommand { get; }

    public RelayCommand AddEntityCommand { get; }

    public RelayCommand AddFieldCommand { get; }

    public RelayCommand RefreshPreviewCommand { get; }

    public RelayCommand CopyPreviewCommand { get; }

    public ExplorerNodeViewModel? SelectedNode
    {
        get => _selectedNode;
        set
        {
            if (!SetProperty(ref _selectedNode, value))
            {
                return;
            }

            RaisePropertyChanged(nameof(SelectedPayload));
            RaisePropertyChanged(nameof(SelectedTitle));
            RaisePropertyChanged(nameof(SelectedDescription));
        }
    }

    public object SelectedPayload => SelectedNode?.Payload ?? _emptySelection;

    public string SelectedTitle => SelectedNode?.Title ?? "Nenhum item selecionado";

    public string SelectedDescription => SelectedNode?.Kind switch
    {
        "module" => "Modulo estrutural com faixa numerica, abreviacao e entidades filhas.",
        "entity" => "Entidade com contexto de tabela, codigo de programa e configuracoes de persistencia.",
        "field" => "Campo tecnico da entidade com propriedades de tipo, obrigatoriedade e editabilidade.",
        _ => "Selecione um modulo, entidade ou campo para editar o contexto."
    };

    public string PreviewJson
    {
        get => _previewJson;
        private set => SetProperty(ref _previewJson, value);
    }

    public string StatusMessage
    {
        get => _statusMessage;
        private set => SetProperty(ref _statusMessage, value);
    }

    private void LoadSampleWorkspace()
    {
        var module = new BuilderModuleModel
        {
            Code = "cadastros",
            Name = "Cadastros Gerais",
            Abbreviation = "cd",
            NumberStart = 101,
            NumberEnd = 199,
            Enabled = true
        };
        module.PropertyChanged += HandleModelChanged;

        var entity = new BuilderEntityModel
        {
            Code = "tipo_produto",
            Name = "Tipo de Produto",
            EntityType = "persistence",
            TableName = "t101",
            ProgramCode = "cd0101",
            CreatePhysicalTable = true,
            VersionedMaster = false
        };
        entity.PropertyChanged += HandleModelChanged;

        var idField = new BuilderFieldModel
        {
            Code = "id_tipo_produto",
            Label = "Codigo",
            DataType = "integer",
            ColumnName = "id_tipo_produto",
            Length = 0,
            Required = true,
            PrimaryKey = true,
            ReadOnly = true
        };
        idField.PropertyChanged += HandleModelChanged;

        var nameField = new BuilderFieldModel
        {
            Code = "c_descr_tipo_produto",
            Label = "Descricao",
            DataType = "string",
            ColumnName = "c_descr_tipo_produto",
            Length = 120,
            Required = true,
            PrimaryKey = false,
            ReadOnly = false
        };
        nameField.PropertyChanged += HandleModelChanged;

        entity.Fields.Add(idField);
        entity.Fields.Add(nameField);
        module.Entities.Add(entity);

        var moduleNode = CreateModuleNode(module);
        RootNodes.Add(moduleNode);
        SelectedNode = moduleNode;
    }

    private ExplorerNodeViewModel CreateModuleNode(BuilderModuleModel module)
    {
        var moduleNode = new ExplorerNodeViewModel("module", "M", module);
        foreach (var entity in module.Entities)
        {
            moduleNode.Children.Add(CreateEntityNode(entity));
        }

        return moduleNode;
    }

    private ExplorerNodeViewModel CreateEntityNode(BuilderEntityModel entity)
    {
        var entityNode = new ExplorerNodeViewModel("entity", "E", entity);
        foreach (var field in entity.Fields)
        {
            entityNode.Children.Add(CreateFieldNode(field));
        }

        return entityNode;
    }

    private ExplorerNodeViewModel CreateFieldNode(BuilderFieldModel field)
    {
        return new ExplorerNodeViewModel("field", "F", field);
    }

    private void AddModule()
    {
        var nextIndex = RootNodes.Count + 1;
        var module = new BuilderModuleModel
        {
            Code = $"modulo_{nextIndex:00}",
            Name = $"Modulo {nextIndex:00}",
            Abbreviation = $"m{nextIndex:0}",
            NumberStart = nextIndex * 100,
            NumberEnd = (nextIndex * 100) + 99,
            Enabled = true
        };
        module.PropertyChanged += HandleModelChanged;

        var node = CreateModuleNode(module);
        RootNodes.Add(node);
        SelectedNode = node;
        RefreshPreview("Modulo adicionado no workspace em memoria.");
    }

    private void AddEntity()
    {
        var moduleNode = ResolveModuleNode(SelectedNode);
        if (moduleNode?.Payload is not BuilderModuleModel module)
        {
            StatusMessage = "Selecione um modulo para adicionar uma entidade.";
            return;
        }

        var nextIndex = module.Entities.Count + 1;
        var entity = new BuilderEntityModel
        {
            Code = $"entidade_{nextIndex:00}",
            Name = $"Entidade {nextIndex:00}",
            EntityType = "persistence",
            TableName = $"t{module.NumberStart + nextIndex - 1}",
            ProgramCode = $"{module.Abbreviation}{module.NumberStart + nextIndex - 1:0000}",
            CreatePhysicalTable = true,
            VersionedMaster = false
        };
        entity.PropertyChanged += HandleModelChanged;

        module.Entities.Add(entity);
        var entityNode = CreateEntityNode(entity);
        moduleNode.Children.Add(entityNode);
        SelectedNode = entityNode;
        RefreshPreview("Entidade adicionada ao modulo selecionado.");
    }

    private void AddField()
    {
        var entityNode = ResolveEntityNode(SelectedNode);
        if (entityNode?.Payload is not BuilderEntityModel entity)
        {
            StatusMessage = "Selecione uma entidade para adicionar um campo.";
            return;
        }

        var nextIndex = entity.Fields.Count + 1;
        var field = new BuilderFieldModel
        {
            Code = $"c_campo_{nextIndex:00}",
            Label = $"Campo {nextIndex:00}",
            DataType = "string",
            ColumnName = $"c_campo_{nextIndex:00}",
            Length = 120,
            Required = false,
            PrimaryKey = false,
            ReadOnly = false
        };
        field.PropertyChanged += HandleModelChanged;

        entity.Fields.Add(field);
        var fieldNode = CreateFieldNode(field);
        entityNode.Children.Add(fieldNode);
        SelectedNode = fieldNode;
        RefreshPreview("Campo adicionado na entidade selecionada.");
    }

    private void RefreshPreview(string? successStatus = null)
    {
        var payload = RootNodes
            .Select(node => node.Payload)
            .OfType<BuilderModuleModel>()
            .Select(module => new
            {
                module.Code,
                module.Name,
                module.Abbreviation,
                module.NumberStart,
                module.NumberEnd,
                module.Enabled,
                Entities = module.Entities.Select(entity => new
                {
                    entity.Code,
                    entity.Name,
                    entity.EntityType,
                    entity.TableName,
                    entity.ProgramCode,
                    entity.CreatePhysicalTable,
                    entity.VersionedMaster,
                    Fields = entity.Fields.Select(field => new
                    {
                        field.Code,
                        field.Label,
                        field.DataType,
                        field.ColumnName,
                        field.Length,
                        field.Required,
                        field.PrimaryKey,
                        field.ReadOnly
                    })
                })
            });

        PreviewJson = JsonSerializer.Serialize(payload, new JsonSerializerOptions
        {
            WriteIndented = true
        });
        StatusMessage = successStatus ?? "Preview JSON atualizado.";
    }

    private void CopyPreview()
    {
        if (string.IsNullOrWhiteSpace(PreviewJson))
        {
            StatusMessage = "Nao ha preview para copiar.";
            return;
        }

        Clipboard.SetText(PreviewJson);
        StatusMessage = "Preview JSON copiado para a area de transferencia.";
    }

    private ExplorerNodeViewModel? ResolveModuleNode(ExplorerNodeViewModel? node)
    {
        if (node is null)
        {
            return null;
        }

        if (node.Payload is BuilderModuleModel)
        {
            return node;
        }

        return RootNodes.FirstOrDefault(root => root.Children.Contains(node) || root.Children.Any(child => child.Children.Contains(node)));
    }

    private ExplorerNodeViewModel? ResolveEntityNode(ExplorerNodeViewModel? node)
    {
        if (node is null)
        {
            return null;
        }

        if (node.Payload is BuilderEntityModel)
        {
            return node;
        }

        return RootNodes
            .SelectMany(root => root.Children)
            .FirstOrDefault(child => child == node || child.Children.Contains(node));
    }

    private void HandleModelChanged(object? sender, PropertyChangedEventArgs e)
    {
        RaisePropertyChanged(nameof(SelectedTitle));
        RefreshPreview();
    }
}
