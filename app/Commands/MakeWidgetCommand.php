<?php

namespace App\Commands;

use App\Commands\Concerns\CanManipulateFilesExtension;
use Filament\Commands\Concerns;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class MakeWidgetCommand extends Command
{
    use Concerns\CanManipulateFiles;
    use Concerns\CanValidateInput;
    use CanManipulateFilesExtension;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:widget
                                {name?}
                                {--R|resource=}
                                {--C|chart}
                                {--T|table}
                                {--S|stats-overview}
                                {--F|force}
                            ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Creates a Filament widget class.';

    public function handle(): int
    {
        $widget = (string) Str::of($this->argument('name') ?? $this->askRequired('Name (e.g. `BlogPostsChart`)', 'name'))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $widgetClass = (string) Str::of($widget)->afterLast('\\');
        $widgetNamespace = Str::of($widget)->contains('\\') ?
            (string) Str::of($widget)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;

        $resourceInput = $this->option('resource') ?? $this->ask('(Optional) Resource (e.g. `BlogPostResource`)');

        if ($resourceInput !== null) {
            $resource = (string) Str::of($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\');

            if (! Str::of($resource)->endsWith('Resource')) {
                $resource .= 'Resource';
            }

            $resourceClass = (string) Str::of($resource)
                ->afterLast('\\');
        }

        $view = Str::of($widget)
            ->prepend($resource === null ? 'filament\\widgets\\' : "filament\\resources\\{$resource}\\widgets\\")
            ->explode('\\')
            ->map(fn ($segment) => Str::kebab($segment))
            ->implode('.');

        $path = collect([
            config('bob.paths.src'),
            (string) Str::of($widget)
                ->prepend($resource === null ? 'Filament\\Widgets\\' : "Filament\\Resources\\{$resource}\\Widgets\\")
                ->replace('\\', '/')
                ->append('.php'),
        ])->implode(DIRECTORY_SEPARATOR);

        $viewPath = collect([
            config('bob.paths.views'),
            (string) Str::of($view)
                ->replace('.', '/')
                ->append('.blade.php'),
        ])->implode(DIRECTORY_SEPARATOR);

        if (! $this->option('force') && $this->checkForCollision([
            $path,
            ($this->option('stats-overview') || $this->option('chart')) ?: $viewPath,
        ])) {
            return static::INVALID;
        }

        if ($this->option('chart')) {
            $chart = $this->choice(
                'Chart type',
                [
                    'Bar chart',
                    'Bubble chart',
                    'Doughnut chart',
                    'Line chart',
                    'Pie chart',
                    'Polar area chart',
                    'Radar chart',
                    'Scatter chart',
                ],
            );

            $this->decoratedCopyStubToApp('ChartWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? $this->getNamespace()."Filament\\Resources\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $this->getNamespace().'Filament\\Widgets'.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'chart' => Str::studly($chart),
            ]);
        } elseif ($this->option('table')) {
            $this->decoratedCopyStubToApp('TableWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? $this->getNamespace()."Filament\\Resources\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $this->getNamespace().'Filament\\Widgets'.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } elseif ($this->option('stats-overview')) {
            $this->decoratedCopyStubToApp('StatsOverviewWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? $this->getNamespace()."Filament\\Resources\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $this->getNamespace().'Filament\\Widgets'.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } else {
            $this->decoratedCopyStubToApp('Widget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? $this->getNamespace()."Filament\\Resources\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $this->getNamespace().'Filament\\Widgets'.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'view' => $view,
            ]);

            $this->copyStubToApp('WidgetView', $viewPath);
        }

        $this->info("Successfully created {$widget}!");

        if ($resource !== null) {
            $this->info("Make sure to register the widget in `{$resourceClass}::getWidgets()`, and then again in `getHeaderWidgets()` or `getFooterWidgets()` of any `{$resourceClass}` page.");
        }

        return static::SUCCESS;
    }
}
