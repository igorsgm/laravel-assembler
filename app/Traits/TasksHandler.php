<?php

namespace App\Traits;

use Illuminate\Support\Arr;
use Laravel\Installer\Console\NewCommand;
use Storage;

trait TasksHandler
{
    /** Execute the Laravel Installation script from laravel/installer
     * @see https://github.com/laravel/installer
     *
     * @return int
     */
    protected function taskInstallLaravel()
    {
        return $this->task(' ➤  💻 <fg=cyan>Installing Laravel</>', function () {
            $options = collect($this->options())
                ->filter()->mapWithKeys(function ($value, $key) {
                    return ["--{$key}" => $value];
                })->toArray();

            $this->call(NewCommand::class, array_merge([
                'name' => $this->directory,
                '--branch' => 'master',
                '--git' => $this->inputGitInitialize ?? false,
            ],
                Arr::except($options, ['--git', '--github'])
            ));

            if ($projectCreated = file_exists($this->projectPath)) {
                $this->getOutput()->writeln('  <bg=blue;fg=white> INFO </> <fg=cyan>Actually... Let\'s set up a few things more</> 🛠'.PHP_EOL);
            }

            return $projectCreated;
        });
    }

    /**
     * @param  array  $packages
     * @return mixed
     */
    public function taskInstallComposerDevPackages(array $packages)
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing additional Composer dependencies</>', function () use ($packages) {
            $packages = implode(' ', $packages);
            $command = $this->baseLaravelInstaller->findComposer()." require --dev {$this->quietFlag()} ".$packages;

            return $this->execOnProject($command)->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskNpmInstall()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing npm dependencies</>', function () {
            return $this->execOnProject('npm install', true, ! $this->isVerbose())->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskNpmRunBuild()
    {
        return $this->task(' ➤  📚 <fg=cyan>Building npm assets</>', function () {
            return $this->execOnProject('npm run build', true, ! $this->isVerbose())->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskInstallTailwindCss()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing Tailwind CSS</>', function () {
            $installation = $this->execOnProject([
                'npm install -D tailwindcss postcss autoprefixer @tailwindcss/aspect-ratio @tailwindcss/forms @tailwindcss/typography',
                'npx tailwindcss init -p',
            ], true, ! $this->isVerbose());

            if ($installation->isSuccessful()) {
                file_put_contents($this->projectFilePath('/tailwind.config.js'), Storage::get('tailwind.config.js'));

                $tailwindDirectives = [
                    '@tailwind base;',
                    '@tailwind components;',
                    '@tailwind utilities;',
                ];

                return file_put_contents($this->projectFilePath('/resources/css/app.css'), implode("\n", $tailwindDirectives)."\n", FILE_APPEND);
            }

            return false;
        });
    }

    /**
     * @return bool
     * @read https://vueschool.io/articles/vuejs-tutorials/eslint-and-prettier-with-vite-and-vue-js-3/
     */
    public function taskInstallESLintAndPrettier()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing ESLint and Prettier</>', function () {
            $installation = $this->execOnProject([
                'npm install --save-dev --save-exact prettier',
                'echo {}> .prettierrc.json',
                'npm install --save-dev eslint',
                'npm install prettier prettier-plugin-organize-attributes -D',
            ], true, ! $this->isVerbose());

            if ($installation->isSuccessful()) {
                return file_put_contents($this->projectFilePath('/.prettierrc.json'), Storage::get('.prettierrc.json'));
            }

            return false;
        });
    }

    /**
     * @return bool
     */
    public function taskInstallBladeFormatter()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing Blade Formatter</>', function () {
            return $this->execOnProject([
                'npm install --save-dev blade-formatter',
                $this->copy().Storage::path('.bladeformatterrc.json')." $this->projectPath",
            ], true, ! $this->isVerbose())->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskInstallAlpineJs()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing Alpine.js</>', function () {
            $installation = $this->execOnProject([
                'npm install alpinejs',
            ], true, ! $this->isVerbose());

            if ($installation->isSuccessful()) {
                $alpineDirectives = [
                    "import Alpine from \"alpinejs\";\n",
                    "window.Alpine = Alpine;\n",
                    "Alpine.start();\n",
                ];

                return file_put_contents($this->projectFilePath('/resources/js/bootstrap.js'), implode("\n", $alpineDirectives)."\n", FILE_APPEND);
            }
        });
    }

    /**
     * @return bool
     */
    public function taskAddValetSSHSupportToVite()
    {
        return $this->task(' ➤  📚 <fg=cyan>Making Vite support Valet SSH</>', function () {
            $viteEnvKeys = implode("\n", [
                'VITE_APP_URL="${APP_URL}"',
            ])."\n";

            return file_put_contents($this->projectFilePath('.env'), $viteEnvKeys, FILE_APPEND) &&
                file_put_contents($this->projectFilePath('.env.example'), $viteEnvKeys, FILE_APPEND) &&
                file_put_contents($this->projectFilePath('vite.config.js'), Storage::get('vite.config.js'));
        });
    }

    /**
     * @param  string  $projectPath
     * @return mixed
     */
    public function taskCreatePhpCsXmlFile($projectPath)
    {
        return $this->task(' ➤  📄 <fg=cyan>Creating phpcs.xml file</>', function () use ($projectPath) {
            $command = $this->copy().Storage::path('phpcs.xml')." $projectPath";

            return $this->execOnProject($command, true)->isSuccessful();
        });
    }

    /**
     * @param  string  $projectPath
     * @return mixed
     */
    public function taskGenerateIdeHelperFiles()
    {
        return $this->task(' ➤  📑 <fg=cyan>Generating IDE Helper files</>', function () {
            $quiet = $this->quietFlag();

            return $this->execOnProject([
                PHP_BINARY." artisan ide-helper:eloquent {$quiet}",
                PHP_BINARY." artisan ide-helper:generate {$quiet}",
                PHP_BINARY." artisan ide-helper:meta {$quiet}",
            ])->isSuccessful();
        });
    }

    /**
     * @param  array  $providers
     */
    public function taskPublishVendorConfigFiles(array $providers)
    {
        $commands = [];
        foreach ($providers as $provider) {
            $commands[] = PHP_BINARY.' artisan vendor:publish --provider="'.$provider.'" --tag=config '.$this->quietFlag();
        }

        $this->task(' ➤  📂 <fg=cyan>Publishing vendor config files</>', function () use ($commands) {
            return $this->execOnProject($commands)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskUpdateGitIgnore()
    {
        return $this->task(' ➤  📄 <fg=cyan>Updating .gitignore</>', function () {
            $itemsToIgnore = [
                '.idea/',
                '.phpunit.result.cache',
                '.phpstorm.meta.php',
                '_ide_helper.php',
                '_ide_helper_models.php',
                '.prettierignore',
            ];

            return file_put_contents($this->projectFilePath('/.gitignore'), implode("\n", $itemsToIgnore)."\n", FILE_APPEND);
        });
    }

    /**
     * @param  array  $installHooksScript
     * @return mixed
     */
    public function taskCreatePhpCsPreCommitHook()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Creating phpcs "pre-commit-hook"</>', function () {
            $preCommitHookPath = '.git'.DIRECTORY_SEPARATOR.'hooks'.DIRECTORY_SEPARATOR.'pre-commit';
            $installHooksScript = [
                $this->copy().'pre-commit-hook.sh '.$preCommitHookPath,
                'chmod +x '.$preCommitHookPath,
                'chmod +x pre-commit-hook.sh',
            ];

            $commands = array_merge(
                [$this->copy().Storage::path('pre-commit-hook.sh')." $this->projectPath"],
                $installHooksScript
            );

            $this->newComposerFile['scripts']['install-hooks'] = $installHooksScript;
            $this->newComposerFile['scripts']['pre-install-cmd'] = $this->newComposerFile['scripts']['post-install-cmd'] = ['@install-hooks'];

            return $this->execOnProject($commands, true)->isSuccessful();
        });
    }

    /**
     * Create a Git repository and commit the base Laravel skeleton.
     *
     * @param $repoName
     * @return bool
     */
    public function taskCreatePrivateGitHubRepository($repoName)
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Creating private repository</>', function () use ($repoName) {
            $this->newLine();
            $process = $this->baseLaravelInstaller->pushToGitHub($repoName, $this->projectPath, $this->input, $this->getOutput());

            return $process->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskStartGitFlow()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Starting git flow</>', function () {
            return $this->execOnProject('git flow init -d', true, ! $this->isVerbose())->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskUpdateReadmeFile()
    {
        return $this->task(' ➤  📃 <fg=cyan>Updating README.md</>', function () {
            $readMe = str_replace('projectName', $this->projectBaseName, Storage::get('README.md'));

            return file_put_contents($this->projectFilePath('/README.md'), $readMe);
        });
    }

    /**
     * @param  array  $composerFile
     * @return mixed
     */
    public function taskUpdateComposerFile()
    {
        return $this->task(' ➤  🆙 <fg=cyan>Updating composer.json</>', function () {
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

            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            $newComposerString = json_encode($this->newComposerFile, $jsonOptions);

            return file_put_contents($this->projectFilePath('/composer.json'), $newComposerString);
        });
    }

    /**
     * @return mixed
     */
    public function taskLaravelPint()
    {
        return $this->task(' ➤  🍺 <fg=cyan>Executing Pint</>', function () {
            $command = $this->vendorBin('pint');

            return $this->execOnProject($command, true, ! $this->isVerbose())->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskPushChangesToGitHub()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Pushing last changes</>', function () {
            $this->newLine();
            $branch = $this->baseLaravelInstaller->defaultBranch();

            return $this->execOnProject([
                "git push -u origin {$branch} {$this->quietFlag()}",
            ], true)->isSuccessful();
        });
    }

    /**
     * @param  string  $directory
     * @return mixed
     */
    public function taskValetInstallSSL($directory)
    {
        return $this->task(' ➤  🔐  <fg=cyan>Applying local SSL to '.$directory.'</>',
            function () use ($directory) {
                $this->newLine();
                $this->line('<fg=#a9a9a9>Your sudo password may be requested at this step.</>');

                return $this->execOnProject('valet secure '.$directory, true, ! $this->isVerbose())->isSuccessful();
            });
    }

    /**
     * @param  string  $directory
     * @return mixed
     */
    public function taskValetOpenProjectOnBrowser($directory)
    {
        return $this->task(' ➤  🌎 <fg=cyan>Opening '.$directory.' in your browser</>',
            function () use ($directory) {
                return $this->execOnProject('valet open '.$directory, true)->isSuccessful();
            });
    }

    /**
     * @see https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html#toolbox
     *
     * @return mixed
     */
    public function taskLoadProjectOnPhpStorm()
    {
        $phpStormsByOS = [
            'Windows' => 'phpstorm.bat',
            'Linux' => 'phpstorm',
            'Darwin' => 'open -a "PhpStorm.app"',
        ];

        return $this->task(' ➤  🖥 <fg=cyan>Loading project on PhpStorm</>', function () use ($phpStormsByOS) {
            return $this->execOnProject($phpStormsByOS[PHP_OS_FAMILY].' .', true)->isSuccessful();
        });
    }
}
