using System.Collections.ObjectModel;

namespace ConstrutorPg.BuilderDesktop.Models;

public sealed class BuilderModuleModel : NotifyBase
{
    private string _code = string.Empty;
    private string _name = string.Empty;
    private string _abbreviation = string.Empty;
    private int _numberStart;
    private int _numberEnd;
    private bool _enabled = true;

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

    public string Abbreviation
    {
        get => _abbreviation;
        set => SetProperty(ref _abbreviation, value);
    }

    public int NumberStart
    {
        get => _numberStart;
        set => SetProperty(ref _numberStart, value);
    }

    public int NumberEnd
    {
        get => _numberEnd;
        set => SetProperty(ref _numberEnd, value);
    }

    public bool Enabled
    {
        get => _enabled;
        set => SetProperty(ref _enabled, value);
    }

    public ObservableCollection<BuilderEntityModel> Entities { get; } = [];
}
