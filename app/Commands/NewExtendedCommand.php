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
     * List of packages that will be installed with the script
     * @var string
     */
    private $devPackagesToInstall;

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
        $this->gitHubTasks();
        $this->composerFileTasks();
    }

    /** Execute the Laravel Installation script from laravel/installer
     * @see https://github.com/laravel/installer
     * @return int
     */
    protected function installLaravelTask()
    {
        $this->task("💻 INSTALLING LARAVEL", function () {
            $this->newLine();
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
        $this->devPackagesToInstall = [];
        $optimizeScripts = [];

        if ($installPHPCS = $this->confirm('Install PHP_CodeSniffer?', true)) {
            $this->devPackagesToInstall[] = 'squizlabs/php_codesniffer';
        }

        if ($installIDEHelper = $this->confirm('Install Laravel IDE Helper Generator?', true)) {
            $this->devPackagesToInstall[] = 'barryvdh/laravel-ide-helper';
        }

        if (!empty($this->devPackagesToInstall)) {
            $this->task("📚 INSTALLING ADDITIONAL DEV DEPENDENCIES", function () {
                $this->newLine(2);
                $packages = implode(' ', $this->devPackagesToInstall);
                return $this->helper->execOnProject($this->helper->findComposer() . ' require --dev ' . $packages);
            });

            $this->newLine();
        }

        if ($installPHPCS) {
            $this->newComposerFile['scripts']['phpcs'] = './vendor/bin/phpcs --standard=phpcs.xml';
            $this->newComposerFile['scripts']['phpcbf'] = './vendor/bin/phpcbf --standard=phpcs.xml';
            $optimizeScripts[] = "@phpcbf";

            $this->task("📂 CREATING phpcs.xml FILE", function () {
                return $this->helper->execOnProject($this->helper->copy() . base_path() . '/assets/phpcs.xml ' . $this->projectPath);
            });

            $this->newLine();

            $this->task("EXECUTING PHPCS", function () {
                return $this->helper->execOnProject($this->newComposerFile['scripts']['phpcbf']);
            });

            $this->newLine(2);
        }

        if ($installIDEHelper) {
            array_unshift($optimizeScripts,
                "@php artisan optimize:clear --ansi --no-interaction",
                "@php artisan ide-helper:eloquent",
                "@php artisan ide-helper:generate",
                "@php artisan ide-helper:meta",
                "@php artisan ide-helper:models --write-mixin --ansi --no-interaction"
            );

            $this->task("📂 PUBLISHING VENDOR CONFIG FILES", function () {
                $this->newLine();
                return $this->helper->execOnProject(PHP_BINARY . ' artisan vendor:publish --provider="Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" --tag=config');
            });

            $this->newLine();
        }

        if (!empty($optimizeScripts) && $this->confirm('Install optimization scripts on composer.json?', true)) {
            $this->newComposerFile['scripts']['post-update-cmd'] = [
                "Illuminate\\Foundation\\ComposerScripts::postUpdate",
                "@optimize"
            ];
            $this->newComposerFile['scripts']['optimize'] = $optimizeScripts;
        }
    }

    protected function gitHubTasks()
    {
        $this->info('=============== GITHUB ===============');
        $this->task("UPDATING PROJECT'S .gitignore", function () {
            $this->newLine();
            return $this->helper->execOnProject([
                'echo ".idea/ \n.phpunit.result.cache \n.phpstorm.meta.php \n_ide_helper.php \n_ide_helper_models.php" >> .gitignore'
            ]);
        });

        $this->newLine();
        if ($this->confirm('Create GitHub repository for ' . $this->argument('name') . '? (GitHub CLI required. Check: https://cli.github.com/)', true)) {
            $this->helper->execOnProject('git init');
            $this->newLine();

            if (in_array('squizlabs/php_codesniffer', $this->devPackagesToInstall)) {
                if ($this->confirm('Create a "pre-commit-hook" to validate PHPCS before committing a code?', true)) {
                    $installHooksScript = [
                        $this->helper->copy() . 'pre-commit-hook.sh .git/hooks/pre-commit',
                        'chmod +x .git/hooks/pre-commit',
                        'chmod +x pre-commit-hook.sh'
                    ];

                    $this->task('CREATING PHPCS "pre-commit-hook"', function () use ($installHooksScript) {
                        $this->newLine();
                        $this->helper->execOnProject(array_merge(
                                [$this->helper->copy() . base_path() . '/assets/pre-commit-hook.sh ' . $this->projectPath],
                                $installHooksScript
                            )
                        );
                    });

                    $this->newComposerFile['scripts']['install-hooks'] = $installHooksScript;
                    $this->newComposerFile['scripts']['pre-install-cmd'] = $this->newComposerFile['scripts']['post-install-cmd'] = ['@install-hooks'];
                }
            }

            $this->task('CREATING REPOSITORY', function () {
                $this->newLine();
                $this->helper->execOnProject([
                    'git add .',
                    'git commit -m "Initial commit" --no-verify',
                    'gh repo create ' . $this->argument('name') . ' --private -y',
                    'git push -u origin master'
                ]);
            });

            $this->newLine();
        }
    }

    /**
     * Tasks related to update the composer.json file of the projects with new scripts.
     * @return bool
     */
    protected function composerFileTasks()
    {
        if ($this->composerFile === $this->newComposerFile) {
            return true;
        }

        $orderedScripts = [
            'post-autoload-dump',
            'post-root-package-install',
            'post-create-project-cmd',
            'post-update-cmd',
            'install-hooks',
            'pre-install-cmd',
            'post-install-cmd',
            'phpcs',
            'phpcbf',
            'optimize'
        ];

        // Making sure that the scripts will come in a nice order
        $scripts = [];
        foreach ($orderedScripts as $scriptName) {
            if (array_key_exists($scriptName, $this->newComposerFile['scripts'])) {
                $scripts[$scriptName] = $this->newComposerFile['scripts'][$scriptName];
            }
        }

        $this->newComposerFile['scripts'] = $scripts;

        $this->task("🆙 UPDATING composer.json", function () {
            $newComposerString = json_encode($this->newComposerFile,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return file_put_contents($this->projectPath . '/composer.json', $newComposerString);
        });
    }
}
