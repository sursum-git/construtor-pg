namespace ConstrutorPg.BuilderDesktop.Models;

public sealed class BuilderFieldModel : NotifyBase
{
    private string _code = string.Empty;
    private string _label = string.Empty;
    private string _dataType = "string";
    private string _columnName = string.Empty;
    private int _length = 120;
    private bool _required;
    private bool _primaryKey;
    private bool _readOnly;

    public string Code
    {
        get => _code;
        set => SetProperty(ref _code, value);
    }

    public string Label
    {
        get => _label;
        set => SetProperty(ref _label, value);
    }

    public string DataType
    {
        get => _dataType;
        set => SetProperty(ref _dataType, value);
    }

    public string ColumnName
    {
        get => _columnName;
        set => SetProperty(ref _columnName, value);
    }

    public int Length
    {
        get => _length;
        set => SetProperty(ref _length, value);
    }

    public bool Required
    {
        get => _required;
        set => SetProperty(ref _required, value);
    }

    public bool PrimaryKey
    {
        get => _primaryKey;
        set => SetProperty(ref _primaryKey, value);
    }

    public bool ReadOnly
    {
        get => _readOnly;
        set => SetProperty(ref _readOnly, value);
    }
}
