<?php

namespace App\Commands;

use App\Helpers\BaseNewCommand;
use App\Traits\ProcessHelper;
use App\Traits\TasksHandler;
use Illuminate\Support\Arr;
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
                            {--git : Initialize a Git repository}
                            {--branch= : The branch that should be created for a new repository}
                            {--github= : Create a new repository on GitHub}
                            {--organization= : The GitHub organization to create the new repository for}
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
     * @var BaseNewCommand
     */
    public $baseLaravelInstaller;

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
     *
     * @var array
     */
    protected $devPackagesToInstall = [];

    /**
     * Determines if the github repository to the project was created
     *
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
        $this->warn("
                                 _     _
    /\                          | |   | |
   /  \   ___ ___  ___ _ __ ___ | |__ | | ___ _ __
  / /\ \ / __/ __|/ _ \ '_ ` _ \| '_ \| |/ _ \ '__|
 / ____ \\\__ \__ \  __/ | | | | | |_) | |  __/ |
/_/    \_\___/___/\___|_| |_| |_|_.__/|_|\___|_|

        ");

        $this->baseLaravelInstaller = resolve(BaseNewCommand::class);
        $this->additionalPackages = config('app.additional-packages.require-dev');
        $this->setDirectoryAndPath($this->argument('name'));

        $this->newLine();

        foreach ($this->additionalPackages as $devDependency) {
            $package = $devDependency['package'];
            $question = $this->buildQuestionText('Include '.($devDependency['title'] ?? $package).'?');
            $defaultAnswer = isset($devDependency['default-answer']) ? $devDependency['default-answer'] : true;
            if ($this->confirm($question, $defaultAnswer)) {
                $this->devPackagesToInstall[] = $package;
            }
        }

        $question = $this->buildQuestionText('Initialize git?');
        if ($this->gitInitialize = $this->confirm($question, true)) {
            if (in_array($this->additionalPackages['phpcs']['package'], $this->devPackagesToInstall)) {
                $question = $this->buildQuestionText('Create <fg=green>pre-commit-hook</>?',
                    'To validate PHPCS before committing a code.');
                $this->gitCreatePreCommitHook = $this->confirm($question, true);
            }

            $question = $this->buildQuestionText('Create GitHub repository for <fg=green>'.$this->projectBaseName.'</>?',
                'GitHub CLI required. Check: https://cli.github.com');
            if ($this->gitCreateRepo = $this->confirm($question, true)) {
                $question = $this->buildQuestionText('Start git flow for <fg=green>'.$this->projectBaseName.'</>?',
                    'gitflow-avh required. Check: https://github.com/petervanderdoes/gitflow-avh');
                $this->gitStartGitFlow = $this->confirm($question, true);
            }
        }

        $question = $this->buildQuestionText('Install custom scripts on composer.json?',
            'To be easier to run Pint, PHPCS or generate ide-helper files.');
        $this->installComposerScripts = $this->confirm($question, true);

        $this->warn(' ✨  Let the Magic Begin.');
        $this->newLine();

        if ($this->installLaravelTask()) {
            $this->gitHubTasks();
            $this->devDependenciesTasks();
            $this->composerFileTasks();
            $this->taskPushChangesToGitHub();

            if ($this->gitStartGitFlow) {
               $this->taskStartGitFlow();
            }

            $this->openProjectTasks();

            $this->getOutput()->writeln('  <bg=blue;fg=white> INFO </> <fg=cyan>Application 100% ready! Build something amazing....</>'.PHP_EOL);
            $this->warn(' ✨  Mischief Managed.');
        }
    }

    /** Execute the Laravel Installation script from laravel/installer
     * @see https://github.com/laravel/installer
     *
     * @return int
     */
    protected function installLaravelTask()
    {
        return $this->task(' ➤  💻 <fg=cyan>Installing Laravel</>', function () {
            $options = collect($this->options())
                ->filter()->mapWithKeys(function ($value, $key) {
                    return ["--{$key}" => $value];
                })->toArray();

            $this->call(NewCommand::class, array_merge([
                'name' => $this->directory,
                '--branch' => 'master',
                '--git' => $this->gitInitialize ?? false,
            ],
                Arr::except($options, ['--git', '--branch', '--github', '--organization'])
            ));

            if ($projectCreated = file_exists($this->projectPath)) {
                $this->getOutput()->writeln('  <bg=blue;fg=white> INFO </> <fg=cyan>Actually... Let\'s set up a few things more</> 🛠'.PHP_EOL);
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

        if (! empty($this->devPackagesToInstall)) {
            $this->taskInstallDevPackages($this->devPackagesToInstall);
        }

        $this->composerFile = $this->getProjectComposerFile($this->projectPath);
        $this->newComposerFile = $this->composerFile;

        $this->newComposerFile['scripts']['pint'] = $this->vendorBin('pint');

        if (in_array($this->additionalPackages['phpcs']['package'], $this->devPackagesToInstall)) {
            $this->newComposerFile['scripts']['phpcs'] = $this->vendorBin('phpcs --standard=phpcs.xml');
            $this->newComposerFile['scripts']['phpcbf'] = $this->vendorBin('phpcbf --standard=phpcs.xml');
            $optimizeScripts[] = '@phpcbf';

            $this->taskCreatePhpCsXmlFile($this->projectPath);
        } else {
            $optimizeScripts[] = '@pint';
        }

        if (in_array($this->additionalPackages['ide-helper']['package'], $this->devPackagesToInstall)) {
            array_unshift($optimizeScripts,
                '@php artisan optimize:clear --ansi --no-interaction',
                '@php artisan ide-helper:eloquent',
                '@php artisan ide-helper:generate',
                '@php artisan ide-helper:meta',
                '@php artisan ide-helper:models --write-mixin --ansi --no-interaction'
            );

            $this->taskGenerateIdeHelperFiles();
            $this->taskPublishVendorConfigFiles([$this->additionalPackages['ide-helper']['provider']]);
        }

        if (! empty($optimizeScripts)) {
            $this->newComposerFile['scripts']['post-update-cmd'] = [
                'Illuminate\\Foundation\\ComposerScripts::postUpdate',
                '@optimize',
            ];
            $this->newComposerFile['scripts']['post-autoload-dump'][] = '@optimize';
            $this->newComposerFile['scripts']['optimize'] = $optimizeScripts;
        }

        $this->taskLaravelPint();

        $this->commitChanges('Composer Dev Packages installed + Pint executed');
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

        if (! $this->gitInitialize) {
            return false;
        }

        if ($this->gitCreateRepo) {
            $this->repositoryCreated = $this->taskCreatePrivateGitHubRepository($this->projectBaseName);
        }

        if ($this->gitCreatePreCommitHook) {
            $preCommitHookPath = '.git'.DIRECTORY_SEPARATOR.'hooks'.DIRECTORY_SEPARATOR.'pre-commit';
            $installHooksScript = [
                $this->copy().'pre-commit-hook.sh '.$preCommitHookPath,
                'chmod +x '.$preCommitHookPath,
                'chmod +x pre-commit-hook.sh',
            ];

            if ($this->taskCreatePhpCsPreCommitHook($installHooksScript)) {
                $this->commitChanges('PHPCS pre-commit-hook created');
            }

            $this->newComposerFile['scripts']['install-hooks'] = $installHooksScript;
            $this->newComposerFile['scripts']['pre-install-cmd'] = $this->newComposerFile['scripts']['post-install-cmd'] = ['@install-hooks'];
        }

        if ($this->taskUpdateReadmeFile($this->projectBaseName, $this->projectPath)) {
            $this->commitChanges('README updated');
        }
    }

    /**
     * Tasks related to update the composer.json file of the projects with new scripts.
     */
    public function composerFileTasks()
    {
        if (! $this->installComposerScripts) {
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
            'pint',
            'optimize',
        ];

        // Making sure that the scripts will come in a nice order
        $scripts = [];
        foreach ($orderedScripts as $scriptName) {
            if (array_key_exists($scriptName, $this->newComposerFile['scripts'])) {
                $scripts[$scriptName] = $this->newComposerFile['scripts'][$scriptName];
            }
        }

        $this->newComposerFile['scripts'] = $scripts;

        if ($this->taskUpdateComposerFile($this->newComposerFile)) {
            $this->commitChanges('composer.json scripts updated.');
        }
    }

    /**
     * Perform Valet and PhpStorm IDE actions
     */
    public function openProjectTasks()
    {
        $this->newLine();
        $this->getOutput()->writeln('  <bg=blue;fg=white> INFO </> <fg=cyan>Application 99% ready...</>'.PHP_EOL);

        $question = $this->buildQuestionText('Apply local SSL to <fg=green>'.$this->projectBaseName.'</>?',
            'Laravel Valet required. Check https://laravel.com/docs/master/valet');
        $secureValet = $this->confirm($question, true);

        $question = $this->buildQuestionText('Open <fg=green>'.$this->projectBaseName.'</> on PhpStorm?',
            'Jetbrains CLI required. Check https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html');
        $openProjectOnPhpStorm = $this->confirm($question, true);

        $valetSecured = $secureValet && $this->taskValetInstallSSL($this->projectBaseName);
        if ($valetSecured) {
            $this->taskValetOpenProjectOnBrowser($this->projectBaseName);
        }

        if ($openProjectOnPhpStorm) {
            $this->taskLoadProjectOnPhpStorm();
            $this->newLine();
        }
    }
}
