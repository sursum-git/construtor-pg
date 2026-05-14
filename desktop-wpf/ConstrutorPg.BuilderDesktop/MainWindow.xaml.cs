using System.Windows;
using ConstrutorPg.BuilderDesktop.ViewModels;

namespace ConstrutorPg.BuilderDesktop;

public partial class MainWindow : Window
{
    public MainWindow()
    {
        InitializeComponent();
        DataContext = new MainViewModel();
    }

    private void ExplorerTreeView_OnSelectedItemChanged(object sender, RoutedPropertyChangedEventArgs<object> e)
    {
        if (DataContext is not MainViewModel viewModel)
        {
            return;
        }

        viewModel.SelectedNode = e.NewValue as ExplorerNodeViewModel;
    }
}
