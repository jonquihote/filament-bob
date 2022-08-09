<?php

namespace App\Commands;

use App\Commands\Concerns\CanManipulateFilesExtension;
use Filament\Commands\Concerns;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePageCommand extends Command
{
    use Concerns\CanManipulateFiles;
    use Concerns\CanValidateInput;
    use CanManipulateFilesExtension;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:page
                                {name?}
                                {--R|resource=}
                                {--T|type=}
                                {--F|force}
                            ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Creates a Filament page class and view.';

    public function handle(): int
    {
        $page = (string) Str::of($this->argument('name') ?? $this->askRequired('Name (e.g. `Settings`)', 'name'))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $pageClass = (string) Str::of($page)->afterLast('\\');
        $pageNamespace = Str::of($page)->contains('\\') ?
            (string) Str::of($page)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $resourceInput = $this->option('resource') ?? $this->ask('(Optional) Resource (e.g. `UserResource`)');

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

            $resourcePage = $this->option('type') ?? $this->choice(
                'Which type of page would you like to create?',
                [
                    'custom' => 'Custom',
                    'ListRecords' => 'List',
                    'CreateRecord' => 'Create',
                    'EditRecord' => 'Edit',
                    'ViewRecord' => 'View',
                    'ManageRecords' => 'Manage',
                ],
                'custom',
            );
        }

        $view = Str::of($page)
            ->prepend($resource === null ? 'filament\\pages\\' : "filament\\resources\\{$resource}\\pages\\")
            ->explode('\\')
            ->map(fn ($segment) => Str::kebab($segment))
            ->implode('.');

        $path = collect([
            config('bob.paths.src'),
            (string) Str::of($page)
                ->prepend($resource === null ? 'Filament\\Pages\\' : "Filament\\Resources\\{$resource}\\Pages\\")
                ->replace('\\', '/')
                ->append('.php'),
        ])->implode(DIRECTORY_SEPARATOR);

        $viewPath = collect([
            $this->getViewPath(),
            (string) Str::of($view)
                ->replace('.', '/')
                ->append('.blade.php'),
        ])->implode(DIRECTORY_SEPARATOR);

        $files = array_merge(
            [$path],
            $resourcePage === 'custom' ? [$viewPath] : [],
        );

        if (! $this->option('force') && $this->checkForCollision($files)) {
            return static::INVALID;
        }

        if ($resource === null) {
            $this->decoratedCopyStubToApp('Page', $path, [
                'class' => $pageClass,
                'namespace' => $this->getNamespace().'Filament\\Pages'.($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'view' => $view,
            ]);
        } else {
            $this->decoratedCopyStubToApp($resourcePage === 'custom' ? 'CustomResourcePage' : 'ResourcePage', $path, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\'.($resourcePage === 'custom' ? 'Page' : $resourcePage),
                'baseResourcePageClass' => $resourcePage === 'custom' ? 'Page' : $resourcePage,
                'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages".($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'view' => $view,
            ]);
        }

        if ($resource === null || $resourcePage === 'custom') {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->info("Successfully created {$page}!");

        if ($resource !== null) {
            $this->info("Make sure to register the page in `{$resourceClass}::getPages()`.");
        }

        return static::SUCCESS;
    }
}
