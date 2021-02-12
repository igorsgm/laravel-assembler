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
     * @var array
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

        $this->newLine();
        if ($this->installLaravelTask()) {
            $this->devDependenciesTasks();
            $this->gitHubTasks();
            $this->composerFileTasks();
        }
    }

    /** Execute the Laravel Installation script from laravel/installer
     * @see https://github.com/laravel/installer
     * @return int
     */
    protected function installLaravelTask()
    {
        return $this->task(' ➤  💻 <fg=cyan>Installing Laravel</>', function () {
            $options = collect($this->options())
                ->filter()->mapWithKeys(function ($value, $key) {
                    return ["--{$key}" => $value];
                })->toArray();

            $this->call(NewCommand::class, array_merge(
                ['name' => $this->argument('name')],
                $options
            ));

            return file_exists($this->projectPath);
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
            $this->task(' ➤  📚 <fg=cyan>Installing additional dev dependencies</>', function () {
                $packages = implode(' ', $this->devPackagesToInstall);
                return $this->helper->execOnProject($this->helper->findComposer() . ' require --dev --quiet ' . $packages)
                    ->isSuccessful();
            });
        }

        $this->composerFile = $this->helper->getProjectComposerFile($this->projectPath);
        $this->newComposerFile = $this->composerFile;

        if ($installPHPCS) {
            $this->newComposerFile['scripts']['phpcs'] = '.' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcs --standard=phpcs.xml';
            $this->newComposerFile['scripts']['phpcbf'] = '.' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcbf --standard=phpcs.xml';
            $optimizeScripts[] = "@phpcbf";

            $this->task(' ➤  📄 <fg=cyan>Creating phpcs.xml file</>', function () {
                return $this->helper->execOnProject($this->helper->copy() . base_path() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'phpcs.xml ' . $this->projectPath)
                    ->isSuccessful();
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

            $this->task(' ➤  📂 <fg=cyan>Publishing vendor config files</>', function () {
                return $this->helper->execOnProject(PHP_BINARY . ' artisan vendor:publish --provider="Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" --tag=config --quiet')
                    ->isSuccessful();
            });
        }

        if (!empty($optimizeScripts)) {
            $this->newComposerFile['scripts']['post-update-cmd'] = [
                "Illuminate\\Foundation\\ComposerScripts::postUpdate",
                "@optimize"
            ];
            $this->newComposerFile['scripts']['post-autoload-dump'][] = "@optimize";
            $this->newComposerFile['scripts']['optimize'] = $optimizeScripts;
        }
    }

    /**
     * Tasks related to Git/GitHub. All the questions are made first and then the tasks are executed in sequence.
     * The code looks a bit uglier but the console output looks better doing in this way.
     *
     * @return bool
     */
    protected function gitHubTasks()
    {
        $this->task(' ➤  📄 <fg=cyan>Updating .gitignore</>', function () {
            return $this->helper->execOnProject([
                'echo ".idea/ \n.phpunit.result.cache \n.phpstorm.meta.php \n_ide_helper.php \n_ide_helper_models.php" >> .gitignore'
            ])->isSuccessful();
        });

        $this->newLine();

        // ASKING QUESTIONS
        if (!$this->confirm('Initialize git?', true)) {
            return true;
        }

        $createPreCommitHook = false;
        if (in_array('squizlabs/php_codesniffer', $this->devPackagesToInstall)) {
            $createPreCommitHook = $this->confirm('Create a "pre-commit-hook" to validate PHPCS before committing a code?',
                true);
        }

        if ($createRepo = $this->confirm('Create GitHub repository for "' . $this->argument('name') . "\"?" . PHP_EOL . " (GitHub CLI required. Check: https://cli.github.com/)", true)) {
            $startGitFlow = $this->confirm('Start git-flow for "' . $this->argument('name') . "\"?" . PHP_EOL . " (gitflow-avh required. Check: https://github.com/petervanderdoes/gitflow-avh/)", true);
        }

        // EXECUTING TASKS
        $this->task(' ➤  ☁️  <fg=cyan>Initializing git</>', function () {
            return $this->helper->execOnProject('git init --quiet')->isSuccessful();
        });

        if ($createPreCommitHook) {
            $preCommitHookPath = '.git' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'pre-commit';
            $installHooksScript = [
                $this->helper->copy() . 'pre-commit-hook.sh ' . $preCommitHookPath,
                'chmod +x ' . $preCommitHookPath,
                'chmod +x pre-commit-hook.sh'
            ];

            $this->task(' ➤  ☁️  <fg=cyan>Creating phpcs "pre-commit-hook"</>', function () use ($installHooksScript) {
                return $this->helper->execOnProject(array_merge(
                        [$this->helper->copy() . base_path() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'pre-commit-hook.sh ' . $this->projectPath],
                        $installHooksScript
                    )
                )->isSuccessful();
            });

            $this->newComposerFile['scripts']['install-hooks'] = $installHooksScript;
            $this->newComposerFile['scripts']['pre-install-cmd'] = $this->newComposerFile['scripts']['post-install-cmd'] = ['@install-hooks'];
        }

        if ($createRepo) {
            $this->task(' ➤  ☁️  <fg=cyan>Creating private repository</>', function () {
                $this->newLine();
                return $this->helper->execOnProject([
                    'git add .',
                    'git commit -m "Initial commit" --no-verify --quiet',
                    'gh repo create ' . $this->argument('name') . ' --private -y',
                    'git push -u origin master --quiet'
                ])->isSuccessful();
            });
        }

        if ($startGitFlow) {
            $this->task(' ➤  ☁️  <fg=cyan>Starting git flow</>', function () {
                $this->newLine();
                return $this->helper->execOnProject('git flow init -d')->isSuccessful();
            });
        }
    }

    /**
     * Tasks related to update the composer.json file of the projects with new scripts.
     * @return bool
     */
    protected function composerFileTasks()
    {
        if ($this->composerFile === $this->newComposerFile ||
            !$this->confirm('Install custom optimization scripts on composer.json?', true)
        ) {
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

        return $this->task(' ➤  🆙 <fg=cyan>Updating composer.json</>', function () {
            $newComposerString = json_encode($this->newComposerFile,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return file_put_contents($this->projectPath . '/composer.json', $newComposerString);
        });
    }

    /**
     * OVERRIDE to always ask the question using the white color.
     * Just for styling :P
     * Confirm a question with the user.
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        return parent::confirm("❓<fg=white> $question</>", $default);
    }
}
