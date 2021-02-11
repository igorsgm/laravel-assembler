<?php

namespace App\Commands;

use App\Helpers\CommandHelper;
use Laravel\Installer\Console\NewCommand;
use LaravelZero\Framework\Commands\Command;

class NewExtendedCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'new
                            {name : The name of the app (required)}
                            {--dev : Installs the latest "development" release}
                            {--jet : Installs the Laravel Jetstream scaffolding}
                            {--stack= : The Jetstream stack that should be installed}
                            {--teams : Indicates whether Jetstream should be scaffolded with team support}
                            {--prompt-jetstream : Issues a prompt to determine if Jetstream should be installed}
                            {--f|force : Forces install even if the directory already exists}
                            ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel application (Extended Version)';

    /**
     * @var CommandHelper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $projectPath;

    /**
     * @var mixed
     */
    protected $composerFile;

    /**
     * @var mixed
     */
    protected $newComposerFile;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(CommandHelper $commandHelper)
    {
        $this->projectPath = $commandHelper->projectDirectory($this->argument('name'));
        $this->helper = $commandHelper->setData($this->input, $this->output, $this->projectPath);

        $this->installLaravelTask();
        $this->devDependenciesTasks();
        $this->composerFileTasks();
    }

    /** Execute the Laravel Installation script from laravel/installer
     * @see https://github.com/laravel/installer
     * @return int
     */
    protected function installLaravelTask()
    {
        $this->task("💻 Installing Laravel", function () {
            $options = collect($this->options())
                ->filter()->mapWithKeys(function ($value, $key) {
                    return ["--{$key}" => $value];
                })->toArray();

            $this->call(NewCommand::class, array_merge(
                ['name' => $this->argument('name')],
                $options
            ));

            $this->composerFile = $this->helper->getProjectComposerFile($this->projectPath);
            $this->newComposerFile = $this->composerFile;

            return true;
        });
    }

    /**
     * All the tasks related to the dev dependencies
     */
    protected function devDependenciesTasks()
    {
        $devPackagesToInstall = [''];
        $optimizeScripts = [];

        if ($installPHPCS = $this->confirm('Install PHP_CodeSniffer?', true)) {
            $devPackagesToInstall[] = 'squizlabs/php_codesniffer';
        }

        if ($installIDEHelper = $this->confirm('Install Laravel IDE Helper Generator?', true)) {
            $devPackagesToInstall[] = 'barryvdh/laravel-ide-helper';
        }

        if (!empty($devPackagesToInstall)) {
            $this->task("📚 Installing Additional Dev Dependencies", function () use ($devPackagesToInstall) {
                $this->newLine(2);
                $packages = implode(' ', $devPackagesToInstall);
                return $this->helper->execOnProject($this->helper->findComposer() . ' require --dev ' . $packages);
            });

            $this->newLine();
        }

        if ($installPHPCS) {
            $this->newComposerFile['scripts']['phpcs'] = './vendor/bin/phpcs --standard=phpcs.xml';
            $this->newComposerFile['scripts']['phpcbf'] = './vendor/bin/phpcbf --standard=phpcs.xml';
            $optimizeScripts[] = "@phpcbf";

            $this->task("📂 Creating phpcs.xml file", function () {
                return $this->helper->execOnProject((PHP_OS_FAMILY == 'Windows' ? 'copy ' : 'cp ') . base_path() . '/assets/phpcs.xml ' . $this->projectPath);
            });
        }

        if ($installIDEHelper) {
            array_unshift($optimizeScripts,
                "@php artisan optimize:clear --ansi --no-interaction",
                "@php artisan ide-helper:eloquent",
                "@php artisan ide-helper:generate",
                "@php artisan ide-helper:meta",
                "@php artisan ide-helper:models --write-mixin --ansi --no-interaction"
            );

            $this->task("📂 Publishing vendor config files", function () {
                $this->newLine();
                return $this->helper->execOnProject(PHP_BINARY . ' artisan vendor:publish --provider="Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" --tag=config');
            });
        }

        if (!empty($optimizeScripts) && $this->confirm('Install optimization scripts on composer.json?', true)) {
            $this->newComposerFile['scripts']['optimize'] = $optimizeScripts;
        }
    }

    /**
     * Tasks related to update the composer.json file of the projects with new scripts
     * @return bool
     */
    protected function composerFileTasks()
    {
        if ($this->composerFile === $this->newComposerFile) {
            return true;
        }

        $this->task("> Updating composer.json", function () {
            $newComposerString = json_encode($this->newComposerFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return file_put_contents($this->projectPath . '/composer.json', $newComposerString);
        });
    }
}
