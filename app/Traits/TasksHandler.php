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
                '--git' => $this->gitInitialize ?? false,
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

            return $this->execOnProject($this->baseLaravelInstaller->findComposer().' require --dev --quiet '.$packages)
                ->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskNpmInstall()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing npm dependencies</>', function () {
            return $this->execOnProject('npm install', true, true)->isSuccessful();
        });
    }

    /**
     * @return bool
     */
    public function taskInstallTailwindCss()
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing Tailwind CSS</>', function () {
            $installation = $this->execOnProject([
                'npm install -D tailwindcss postcss autoprefixer',
                'npx tailwindcss init -p',
            ], true, true);

            if ($installation->isSuccessful()) {
                file_put_contents($this->projectPath.'/tailwind.config.js', Storage::get('tailwind.config.js'));

                $tailwindDirectives = [
                    '@tailwind base;',
                    '@tailwind components;',
                    '@tailwind utilities;',
                ];

                return file_put_contents($this->projectPath.'/resources/css/app.css', implode("\n", $tailwindDirectives)."\n", FILE_APPEND);
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
            ], true, true);

            if ($installation->isSuccessful()) {
                return file_put_contents($this->projectPath.'/.prettierrc.json', Storage::get('.prettierrc.json'));
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
            ], true, true)->isSuccessful();
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
            ], true, true);

            if ($installation->isSuccessful()) {
                $alpineDirectives = [
                    "import Alpine from \"alpinejs\";\n",
                    "window.Alpine = Alpine;\n",
                    "Alpine.start();\n",
                ];

                return file_put_contents($this->projectPath.'/resources/js/bootstrap.js', implode("\n", $alpineDirectives)."\n", FILE_APPEND);
            }
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
            return $this->execOnProject([
                PHP_BINARY.' artisan ide-helper:eloquent --quiet',
                PHP_BINARY.' artisan ide-helper:generate --quiet',
                PHP_BINARY.' artisan ide-helper:meta --quiet',
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
            $commands[] = PHP_BINARY.' artisan vendor:publish --provider="'.$provider.'" --tag=config --quiet';
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

            return file_put_contents($this->projectPath.'/.gitignore', implode("\n", $itemsToIgnore)."\n", FILE_APPEND);
        });
    }

    /**
     * @param  array  $installHooksScript
     * @return mixed
     */
    public function taskCreatePhpCsPreCommitHook(array $installHooksScript)
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Creating phpcs "pre-commit-hook"</>',
            function () use ($installHooksScript) {
                $commands = array_merge(
                    [$this->copy().Storage::path('pre-commit-hook.sh')." $this->projectPath"],
                    $installHooksScript
                );

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
            return $this->execOnProject('git flow init -d', true, true)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskUpdateReadmeFile($projectName, $projectPath)
    {
        return $this->task(' ➤  📃 <fg=cyan>Updating README.md</>', function () use ($projectName, $projectPath) {
            $readMe = str_replace('projectName', $projectName, Storage::get('README.md'));

            return file_put_contents($projectPath.'/README.md', $readMe);
        });
    }

    /**
     * @param  array  $composerFile
     * @return mixed
     */
    public function taskUpdateComposerFile(array $composerFile)
    {
        return $this->task(' ➤  🆙 <fg=cyan>Updating composer.json</>', function () use ($composerFile) {
            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            $newComposerString = json_encode($composerFile, $jsonOptions);

            return file_put_contents($this->projectPath.'/composer.json', $newComposerString);
        });
    }

    /**
     * @return mixed
     */
    public function taskLaravelPint()
    {
        return $this->task(' ➤  🍺 <fg=cyan>Executing Pint</>', function () {
            $command = $this->vendorBin('pint');

            return $this->execOnProject($command, true, true)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskPushChangesToGitHub()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Committing last changes</>', function () {
            $this->newLine();
            $branch = $this->baseLaravelInstaller->defaultBranch();

            return $this->execOnProject([
                "git push -u origin {$branch} --quiet",
            ], true)->isSuccessful();
        });
    }

    /**
     * @param  string  $directory
     * @return mixed
     */
    public function taskValetInstallSSL($directory)
    {
        return $this->task(' ➤  🔐  <fg=cyan>Applying local SSL to "'.$directory.'"</>',
            function () use ($directory) {
                $this->newLine();
                $this->line('<fg=#a9a9a9>Your sudo password may be requested at this step.</>');

                return $this->execOnProject('valet secure '.$directory, true, true)->isSuccessful();
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
