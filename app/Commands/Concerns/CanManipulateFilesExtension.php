<?php

namespace App\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait CanManipulateFilesExtension
{
    protected function getNamespace(): string
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

        $config = File::getRequire($configFile);

        return $config['namespace'];
    }

    protected function getViewNamespace(): string
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

        $config = File::getRequire($configFile);

        return $config['view_namespace'];
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
