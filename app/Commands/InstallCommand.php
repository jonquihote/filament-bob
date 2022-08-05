<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

class InstallCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'install';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Install Bob\'s configuration file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sourceFile = collect([
            __DIR__,
            '..',
            '..',
            'bob.config.php',
        ])->implode(DIRECTORY_SEPARATOR);

        $namespaceFile = collect([
            getcwd(),
            'bob.config.php',
        ])->implode(DIRECTORY_SEPARATOR);

        if (! File::exists($namespaceFile)) {
            File::copy($sourceFile, $namespaceFile);
        }
    }
}
