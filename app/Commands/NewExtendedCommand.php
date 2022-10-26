<?php

namespace App\Commands;

use App\Helpers\BaseNewCommand;
use App\Traits\ProcessHelper;
use App\Traits\TasksHandler;
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
     * @var array
     */
    private $additionalComposerPackages;

    /**
     * Determines if the GitHub repository to the project was created
     *
     * @var array
     */
    protected $repositoryCreated = false;

    /**
     * @var bool
     */
    private $inputGitInitialize = false;

    /**
     * @var bool
     */
    private $inputGitCreatePreCommitHook = false;

    /**
     * @var bool
     */
    private $inputGitCreateRepo = false;

    /**
     * @var bool
     */
    private $inputGitStartGitFlow = false;

    /**
     * @var bool
     */
    private $inputInstallComposerScripts = false;

    /**
     * @var bool
     */
    private $inputSecureValet = false;

    /**
     * @var bool
     */
    private $inputOpenProjectOnPhpStorm = false;

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
        $this->additionalComposerPackages = config('app.additional-composer-packages.require-dev');
        $this->setDirectoryAndPath($this->argument('name'));

        $this->newLine();

        $this->askAdditionalComposerPackagesQuestions()
            ->askAdditionalFrontEndQuestions()
            ->askGitQuestions()
            ->askLocalEnvQuestions();

        $this->warn(' ✨  Let the Magic Begin.');
        $this->newLine();

        if ($this->taskInstallLaravel()) {
            $this->runGitHubTasks();
            $this->runComposerDevDependenciesTasks();
            $this->runNpmDependenciesTasks();
            $this->runComposerFileTasks();

            if ($this->repositoryCreated) {
                $this->taskPushChangesToGitHub();
            }

            if ($this->inputGitStartGitFlow) {
                $this->taskStartGitFlow();
            }

            $this->runOpenProjectTasks();

            $this->newLine();
            $this->getOutput()->writeln('  <bg=blue;fg=white> INFO </> <fg=cyan>Application 100% ready! Build something amazing....</>'.PHP_EOL);
            $this->warn(' ✨  Mischief Managed.');
        }
    }

    private function askAdditionalComposerPackagesQuestions()
    {
        $this->getOutput()->writeln('  <bg=blue;fg=white> ADDITIONAL COMPOSER PACKAGES </>'.PHP_EOL);
        foreach ($this->additionalComposerPackages as $devDependency) {
            $package = $devDependency['package'];
            $defaultAnswer = isset($devDependency['default-answer']) ? $devDependency['default-answer'] : true;

            if ($this->confirmQuestion('Include '.($devDependency['title'] ?? $package).'?', '', $defaultAnswer)) {
                $this->devPackagesToInstall[] = $package;
            }
        }

        $this->inputInstallComposerScripts = $this->confirmQuestion('Install custom scripts on composer.json?', 'To be easier to run Pint, PHPCS or generate ide-helper files.', true);

        return $this;
    }

    private function askAdditionalFrontEndQuestions()
    {
        $this->getOutput()->writeln('  <bg=blue;fg=white> ADDITIONAL FRONT-END SETUP </>'.PHP_EOL);

        $this->inputInstallTailwindCSS = $this->confirmQuestion('Install <fg=green>Tailwind CSS</>?', '', true);
        $this->inputInstallESLintAndPrettier = $this->confirmQuestion('Install <fg=green>ESLint</> and <fg=green>Prettier</>?', '', true);
        $this->inputInstallBladeFormatter = $this->confirmQuestion('Install <fg=green>Blade Formatter</>?', 'https://npmjs.com/package/blade-formatter', true);
        $this->inputInstallAlpineJs = $this->confirmQuestion('Install <fg=green>Alpine.js</>?', 'https://alpinejs.dev', false);

        return $this;
    }

    public function askGitQuestions()
    {
        $this->getOutput()->writeln('  <bg=blue;fg=white> GIT SETUP </>'.PHP_EOL);

        if ($this->inputGitInitialize = $this->confirmQuestion('Initialize git?', '', true)) {
            if ($this->isToInstallPackage('phpcs')) {
                $this->inputGitCreatePreCommitHook = $this->confirmQuestion('Create <fg=green>pre-commit-hook</>?', 'To validate PHPCS before committing a code.', true);
            }

            $this->inputGitCreateRepo = $this->confirmQuestion('Create GitHub repository for <fg=green>'.$this->projectBaseName.'</>?', 'GitHub CLI required. Check: https://cli.github.com', true);

            if ($this->inputGitCreateRepo) {
                $this->inputGitStartGitFlow = $this->confirmQuestion('Start git flow for <fg=green>'.$this->projectBaseName.'</>?', 'gitflow-avh required. Check: https://github.com/petervanderdoes/gitflow-avh', true);
            }
        }

        return $this;
    }

    public function askLocalEnvQuestions()
    {
        $this->getOutput()->writeln('  <bg=blue;fg=white> LOCAL ENVIRONMENT SETUP </>'.PHP_EOL);

        $this->inputSecureValet = $this->confirmQuestion('Apply local SSL to <fg=green>'.$this->projectBaseName.'</>?', 'Laravel Valet required. Check https://laravel.com/docs/master/valet', true);
        $this->inputOpenProjectOnPhpStorm = $this->confirmQuestion('Open <fg=green>'.$this->projectBaseName.'</> on PhpStorm?', 'Jetbrains CLI required. Check https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html');
    }

    /**
     * All the tasks related to the dev dependencies
     */
    public function runComposerDevDependenciesTasks()
    {
        $optimizeScripts = [];

        if (! empty($this->devPackagesToInstall)) {
            $this->taskInstallComposerDevPackages($this->devPackagesToInstall);
        }

        $this->composerFile = $this->getProjectComposerFile($this->projectPath);
        $this->newComposerFile = $this->composerFile;

        $this->newComposerFile['scripts']['pint'] = $this->vendorBin('pint');

        if ($this->isToInstallPackage('phpcs')) {
            $this->newComposerFile['scripts']['phpcs'] = $this->vendorBin('phpcs --standard=phpcs.xml');
            $this->newComposerFile['scripts']['phpcbf'] = $this->vendorBin('phpcbf --standard=phpcs.xml');
            $optimizeScripts[] = '@phpcbf';

            $this->taskCreatePhpCsXmlFile($this->projectPath);
        } else {
            $optimizeScripts[] = '@pint';
        }

        if ($this->isToInstallPackage('ide-helper')) {
            array_unshift($optimizeScripts,
                '@php artisan optimize:clear --ansi --no-interaction',
                '@php artisan ide-helper:eloquent',
                '@php artisan ide-helper:generate',
                '@php artisan ide-helper:meta',
                '@php artisan ide-helper:models --write-mixin --ansi --no-interaction'
            );

            $this->taskGenerateIdeHelperFiles();
            $this->taskPublishVendorConfigFiles([$this->additionalComposerPackages['ide-helper']['provider']]);
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
     * All the tasks related to the dev dependencies
     */
    public function runNpmDependenciesTasks()
    {
        // Run npm install if not executed yet
        if (! is_dir($this->projectPath.'/node_modules') &&
            (
                $this->inputInstallTailwindCSS ||
                $this->inputInstallESLintAndPrettier ||
                $this->inputInstallBladeFormatter ||
                $this->inputInstallAlpineJs
            )
        ) {
            $this->taskNpmInstall();
        }

        if ($this->inputInstallTailwindCSS && $this->taskInstallTailwindCSS()) {
            $this->commitChanges('Tailwind CSS installed');
        }

        if ($this->inputInstallESLintAndPrettier && $this->taskInstallESLintAndPrettier()) {
            $this->commitChanges('ESLint and Prettier installed');
        }

        if ($this->inputInstallBladeFormatter && $this->taskInstallBladeFormatter()) {
            $this->commitChanges('Blade Formatter installed');
        }

        if ($this->inputInstallAlpineJs && $this->taskInstallAlpineJs()) {
            $this->commitChanges('Alpine.js installed');
        }
    }

    /**
     * Tasks related to Git/GitHub. All the questions are made first and then the tasks are executed in sequence.
     * The code looks a bit uglier but the console output looks better doing in this way.
     *
     * @return bool
     */
    public function runGitHubTasks()
    {
        $this->taskUpdateGitIgnore();

        if (! $this->inputGitInitialize) {
            return false;
        }

        if ($this->inputGitCreateRepo) {
            $this->repositoryCreated = $this->taskCreatePrivateGitHubRepository($this->projectBaseName);
        }

        if ($this->inputGitCreatePreCommitHook) {
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
    public function runComposerFileTasks()
    {
        if (! $this->inputInstallComposerScripts) {
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
    public function runOpenProjectTasks()
    {
        if ($this->inputSecureValet && $this->taskValetInstallSSL($this->projectBaseName)) {
            $this->taskValetOpenProjectOnBrowser($this->projectBaseName);
        }

        if ($this->inputOpenProjectOnPhpStorm) {
            $this->taskLoadProjectOnPhpStorm();
            $this->newLine();
        }
    }
}
