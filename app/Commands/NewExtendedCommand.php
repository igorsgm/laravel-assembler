<?php

namespace App\Commands;

use App\Traits\ProcessHelper;
use App\Traits\TasksHandler;
use Laravel\Installer\Console\NewCommand;
use LaravelZero\Framework\Commands\Command;

class NewExtendedCommand extends Command
{
    use ProcessHelper;
    use TasksHandler;

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
     * @var array
     */
    protected $composerFile;

    /**
     * @var array
     */
    protected $newComposerFile;

    /**
     * List of packages that will be installed with the script
     * @var array
     */
    protected $devPackagesToInstall = [];

    /**
     * Determines if the github repository to the project was created
     * @var array
     */
    protected $repositoryCreated = false;

    /**
     * @var bool
     */
    private $gitInitialize = false;

    /**
     * @var bool
     */
    private $gitCreatePreCommitHook = false;

    /**
     * @var bool
     */
    private $gitCreateRepo = false;

    /**
     * @var bool
     */
    private $gitStartGitFlow = false;

    /**
     * @var bool
     */
    private $installComposerScripts = false;

    /**
     * @var array
     */
    private $additionalPackages;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->additionalPackages = config('app.additional-packages.require-dev');
        $this->setDirectoryAndPath($this->argument('name'));

        $this->newLine();

        foreach ($this->additionalPackages as $devDependency) {
            $package = $devDependency['package'];
            if ($this->confirm('Include ' . ($devDependency['title'] ?? $package) . '?', true)) {
                $this->devPackagesToInstall[] = $package;
            }
        }

        if ($this->gitInitialize = $this->confirm('Initialize git?', true)) {
            if (in_array($this->additionalPackages['phpcs']['package'], $this->devPackagesToInstall)) {
                $this->gitCreatePreCommitHook = $this->confirm('Create a "pre-commit-hook" to validate PHPCS before committing a code?', true);
            }

            if ($this->gitCreateRepo = $this->confirm('Create GitHub repository for "' . $this->directory . "\"?" . PHP_EOL . " (GitHub CLI required. Check: https://cli.github.com/)", true)) {
                $this->gitStartGitFlow = $this->confirm('Start git flow for "' . $this->directory . "\"?" . PHP_EOL . " (gitflow-avh required. Check: https://github.com/petervanderdoes/gitflow-avh/)", true);
            }
        }

        $this->installComposerScripts = $this->confirm('Install custom scripts on composer.json?', true);

        $this->warn(' ✨ Let the Magic Begin.');

        if ($this->installLaravelTask()) {
            $this->devDependenciesTasks();
            $this->gitHubTasks();
            $this->composerFileTasks();
            $this->openProjectTasks();

            $this->warn(' ➤  Application 100% ready! Build something amazing.');
            $this->warn(' ✨ Mischief Managed.');
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
                ['name' => $this->directory],
                $options
            ));

            if ($projectCreated = file_exists($this->projectPath)) {
                $this->warn("Actually... Let's set up a few things more 🛠");
            }

            return $projectCreated;
        });
    }

    /**
     * All the tasks related to the dev dependencies
     */
    public function devDependenciesTasks()
    {
        $optimizeScripts = [];

        if (!empty($this->devPackagesToInstall)) {
            $this->taskInstallDevPackages($this->devPackagesToInstall);
        }

        $this->composerFile = $this->getProjectComposerFile($this->projectPath);
        $this->newComposerFile = $this->composerFile;

        if (in_array($this->additionalPackages['phpcs']['package'], $this->devPackagesToInstall)) {
            $this->newComposerFile['scripts']['phpcs'] = '.' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcs --standard=phpcs.xml';
            $this->newComposerFile['scripts']['phpcbf'] = '.' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcbf --standard=phpcs.xml';
            $optimizeScripts[] = "@phpcbf";

            $this->taskCreatePhpCsXmlFile($this->projectPath);
        }

        if (in_array($this->additionalPackages['ide-helper']['package'], $this->devPackagesToInstall)) {
            array_unshift($optimizeScripts,
                "@php artisan optimize:clear --ansi --no-interaction",
                "@php artisan ide-helper:eloquent",
                "@php artisan ide-helper:generate",
                "@php artisan ide-helper:meta",
                "@php artisan ide-helper:models --write-mixin --ansi --no-interaction"
            );

            $this->taskGenerateIdeHelperFiles();
            $this->taskPublishVendorConfigFiles([$this->additionalPackages['ide-helper']['provider']]);
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
    public function gitHubTasks()
    {
        $this->taskUpdateGitIgnore();

        if (!$this->gitInitialize) {
            return false;
        }

        $this->taskInitializeGit();

        if ($this->gitCreatePreCommitHook) {
            $preCommitHookPath = '.git' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'pre-commit';
            $installHooksScript = [
                $this->copy() . 'pre-commit-hook.sh ' . $preCommitHookPath,
                'chmod +x ' . $preCommitHookPath,
                'chmod +x pre-commit-hook.sh'
            ];

            $this->taskCreatePhpCsPreCommitHook($installHooksScript);

            $this->newComposerFile['scripts']['install-hooks'] = $installHooksScript;
            $this->newComposerFile['scripts']['pre-install-cmd'] = $this->newComposerFile['scripts']['post-install-cmd'] = ['@install-hooks'];
        }

        if ($this->gitCreateRepo) {
            $this->repositoryCreated = $this->taskCreatePrivateGitHubRepository();
        }

        if ($this->gitStartGitFlow) {
            $this->taskStartGitFlow();
        }
    }

    /**
     * Tasks related to update the composer.json file of the projects with new scripts.
     */
    public function composerFileTasks()
    {
        if (!$this->installComposerScripts) {
            return false;
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

        if ($this->repositoryCreated) {
            $this->taskCommitChangesToGitHub('composer.json scripts updated.');
        }

        $this->taskUpdateComposerFile($this->newComposerFile);
    }

    /**
     * Perform Valet and PhpStorm IDE actions
     */
    public function openProjectTasks()
    {
        $this->newLine();
        $this->warn(' ➤  Application 99% ready...');
        $this->newLine();

        $secureValet = $this->confirm('Apply SSL to the project?' . PHP_EOL . '(Laravel Valet required. Check https://laravel.com/docs/8.x/valet)', true);
        $openProjectOnPhpStorm = $this->confirm('Open project on PhpStorm?' . PHP_EOL . '(Jetbrains CLI required. Check https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html)', true);

        $valetSecured = $secureValet && $this->taskValetInstallSSL($this->directory);
        if ($valetSecured) {
            $this->taskValetOpenProjectOnBrowser($this->directory);
        }

        if ($openProjectOnPhpStorm) {
            $this->taskLoadProjectOnPhpStorm();
            $this->newLine();
        }
    }
}
