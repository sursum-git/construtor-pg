using System.Collections.ObjectModel;
using System.ComponentModel;
using ConstrutorPg.BuilderDesktop.Models;

namespace ConstrutorPg.BuilderDesktop.ViewModels;

public sealed class ExplorerNodeViewModel : ViewModelBase
{
    public ExplorerNodeViewModel(string kind, string glyph, object payload)
    {
        Kind = kind;
        Glyph = glyph;
        Payload = payload;
        Subscribe(payload);
    }

    public string Kind { get; }

    public string Glyph { get; }

    public object Payload { get; }

    public ObservableCollection<ExplorerNodeViewModel> Children { get; } = [];

    public string Title => Payload switch
    {
        BuilderModuleModel module => string.IsNullOrWhiteSpace(module.Name) ? "(modulo sem nome)" : module.Name,
        BuilderEntityModel entity => string.IsNullOrWhiteSpace(entity.Name) ? "(entidade sem nome)" : entity.Name,
        BuilderFieldModel field => string.IsNullOrWhiteSpace(field.Label) ? "(campo sem label)" : field.Label,
        _ => Kind
    };

    private void Subscribe(object payload)
    {
        if (payload is INotifyPropertyChanged notify)
        {
            notify.PropertyChanged += (_, _) => RaisePropertyChanged(nameof(Title));
        }
    }
}
