using System.Collections.ObjectModel;

namespace ConstrutorPg.BuilderDesktop.Models;

public sealed class BuilderEntityModel : NotifyBase
{
    private string _code = string.Empty;
    private string _name = string.Empty;
    private string _entityType = "persistence";
    private string _tableName = string.Empty;
    private string _programCode = string.Empty;
    private bool _createPhysicalTable = true;
    private bool _versionedMaster;

    public string Code
    {
        get => _code;
        set => SetProperty(ref _code, value);
    }

    public string Name
    {
        get => _name;
        set => SetProperty(ref _name, value);
    }

    public string EntityType
    {
        get => _entityType;
        set => SetProperty(ref _entityType, value);
    }

    public string TableName
    {
        get => _tableName;
        set => SetProperty(ref _tableName, value);
    }

    public string ProgramCode
    {
        get => _programCode;
        set => SetProperty(ref _programCode, value);
    }

    public bool CreatePhysicalTable
    {
        get => _createPhysicalTable;
        set => SetProperty(ref _createPhysicalTable, value);
    }

    public bool VersionedMaster
    {
        get => _versionedMaster;
        set => SetProperty(ref _versionedMaster, value);
    }

    public ObservableCollection<BuilderFieldModel> Fields { get; } = [];
}
