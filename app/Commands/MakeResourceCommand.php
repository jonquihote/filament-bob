<?php

namespace App\Commands;

use App\Commands\Concerns\CanManipulateFilesExtension;
use Filament\Commands\Concerns;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class MakeResourceCommand extends Command
{
    use Concerns\CanIndentStrings;
    use Concerns\CanManipulateFiles;
    use Concerns\CanValidateInput;
    use CanManipulateFilesExtension;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:resource
                                {name?}
                                {--soft-deletes}
                                {--view}
                                {--S|simple}
                                {--F|force}
                            ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Creates a Filament resource class and default page classes.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model = (string) Str::of($this->argument('name') ?? $this->askRequired('Model (e.g. `BlogPost`)', 'name'))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\');

        if (blank($model)) {
            $model = 'Resource';
        }

        $modelClass = (string) Str::of($model)->afterLast('\\');
        $modelNamespace = Str::of($model)->contains('\\') ?
            (string) Str::of($model)->beforeLast('\\') :
            '';
        $pluralModelClass = (string) Str::of($modelClass)->pluralStudly();

        $resource = "{$model}Resource";
        $resourceClass = "{$modelClass}Resource";
        $resourceNamespace = $modelNamespace;
        $listResourcePageClass = "List{$pluralModelClass}";
        $manageResourcePageClass = "Manage{$pluralModelClass}";
        $createResourcePageClass = "Create{$modelClass}";
        $editResourcePageClass = "Edit{$modelClass}";
        $viewResourcePageClass = "View{$modelClass}";

        $baseResourcePath = collect([
            getcwd(),
            config('bob.paths.resources'),
            $resource,
        ])->implode(DIRECTORY_SEPARATOR);

        $resourcePath = "{$baseResourcePath}.php";
        $resourcePagesDirectory = "{$baseResourcePath}/Pages";
        $listResourcePagePath = "{$resourcePagesDirectory}/{$listResourcePageClass}.php";
        $manageResourcePagePath = "{$resourcePagesDirectory}/{$manageResourcePageClass}.php";
        $createResourcePagePath = "{$resourcePagesDirectory}/{$createResourcePageClass}.php";
        $editResourcePagePath = "{$resourcePagesDirectory}/{$editResourcePageClass}.php";
        $viewResourcePagePath = "{$resourcePagesDirectory}/{$viewResourcePageClass}.php";

        if (! $this->option('force') && $this->checkForCollision([
            $resourcePath,
            $listResourcePagePath,
            $manageResourcePagePath,
            $createResourcePagePath,
            $editResourcePagePath,
            $viewResourcePagePath,
        ])) {
            return static::INVALID;
        }

        $pages = '';
        $pages .= '\'index\' => Pages\\'.($this->option('simple') ? $manageResourcePageClass : $listResourcePageClass).'::route(\'/\'),';

        if (! $this->option('simple')) {
            $pages .= PHP_EOL."'create' => Pages\\{$createResourcePageClass}::route('/create'),";

            if ($this->option('view')) {
                $pages .= PHP_EOL."'view' => Pages\\{$viewResourcePageClass}::route('/{record}'),";
            }

            $pages .= PHP_EOL."'edit' => Pages\\{$editResourcePageClass}::route('/{record}/edit'),";
        }

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        $relations = '';

        if ($this->option('simple')) {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        } else {
            $relations .= PHP_EOL.'public static function getRelations(): array';
            $relations .= PHP_EOL.'{';
            $relations .= PHP_EOL.'    return [';
            $relations .= PHP_EOL.'        //';
            $relations .= PHP_EOL.'    ];';
            $relations .= PHP_EOL.'}'.PHP_EOL;
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $eloquentQuery = '';

        if ($this->option('soft-deletes')) {
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';

            $eloquentQuery .= PHP_EOL.PHP_EOL.'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL.'{';
            $eloquentQuery .= PHP_EOL.'    return parent::getEloquentQuery()';
            $eloquentQuery .= PHP_EOL.'        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL.'            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL.'        ]);';
            $eloquentQuery .= PHP_EOL.'}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $this->decoratedCopyStubToApp('Resource', $resourcePath, [
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'formSchema' => $this->indentString('//', 4),
            'model' => $model === 'Resource' ? 'Resource as ResourceModel' : $model,
            'modelClass' => $model === 'Resource' ? 'ResourceModel' : $modelClass,
            'namespace' => $this->getNamespace().'Filament\\Resources'.($resourceNamespace !== '' ? "\\{$resourceNamespace}" : ''),
            'pages' => $this->indentString($pages, 3),
            'relations' => $this->indentString($relations, 1),
            'resource' => $resource,
            'resourceClass' => $resourceClass,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 4),
            'tableColumns' => $this->indentString('//', 4),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make(),' : '//',
                4,
            ),
        ]);

        if ($this->option('simple')) {
            $this->decoratedCopyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $manageResourcePageClass,
            ]);
        } else {
            $this->decoratedCopyStubToApp('ResourceListPage', $listResourcePagePath, [
                'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $listResourcePageClass,
            ]);

            $this->decoratedCopyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\CreateRecord',
                'baseResourcePageClass' => 'CreateRecord',
                'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view')) {
                $this->decoratedCopyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages",
                    'resource' => $resource,
                    'resourceClass' => $resourceClass,
                    'resourcePageClass' => $viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->decoratedCopyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'actions' => $this->indentString($editPageActions, 3),
                'namespace' => $this->getNamespace()."Filament\\Resources\\{$resource}\\Pages",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $editResourcePageClass,
            ]);
        }

        $this->info("Successfully created {$resource}!");

        return static::SUCCESS;
    }
}
