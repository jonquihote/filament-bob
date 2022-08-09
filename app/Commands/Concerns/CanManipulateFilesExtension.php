<?php

namespace App\Commands\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait CanManipulateFilesExtension
{
    protected function getNamespace(): string
    {
        $config = $this->getConfigArray();

        return $config['namespace'];
    }

    protected function getViewNamespace(): string
    {
        $config = $this->getConfigArray();

        return $config['view_namespace'];
    }

    protected function getViewPath()
    {
        $config = $this->getConfigArray();

        return Arr::get($config, 'paths.views') ?? config('bob.paths.views');
    }

    protected function getConfigFile(): string
    {
        $configFile = collect([
            getcwd(),
            'bob.config.php',
        ])->implode(DIRECTORY_SEPARATOR);

        if (! File::exists($configFile)) {
            $configFile = collect([
                __DIR__,
                '..',
                '..',
                'bob.config.php',
            ])->implode(DIRECTORY_SEPARATOR);
        }

        return $configFile;
    }

    protected function getConfigArray(): array
    {
        return File::getRequire($this->getConfigFile());
    }

    protected function decoratedCopyStubToApp(string $stub, string $targetPath, array $replacements = []): void
    {
        if (isset($replacements['view'])) {
            $replacements['view'] = $this->getViewNamespace().$replacements['view'];
        }

        $this->copyStubToApp($stub, $targetPath, $replacements);

        $namespace = (string) Str::of($replacements['namespace'])->before('Filament\\Resources\\');

        $stub = Str::of(File::get($targetPath));

        $stub = $stub->replace('App\\', $namespace);

        $stub = (string) $stub;

        $this->writeFile($targetPath, $stub);
    }
}
