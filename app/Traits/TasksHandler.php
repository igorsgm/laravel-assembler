<?php

namespace App\Traits;

trait TasksHandler
{
    /**
     * @param array $packages
     * @return mixed
     */
    public function taskInstallDevPackages(array $packages)
    {
        return $this->task(' ➤  📚 <fg=cyan>Installing additional dev dependencies</>', function () use ($packages) {
            $packages = implode(' ', $packages);
            return $this->execOnProject($this->findComposer() . ' require --dev --quiet ' . $packages)
                ->isSuccessful();
        });
    }

    /**
     * @param string $projectPath
     * @return mixed
     */
    public function taskCreatePhpCsXmlFile($projectPath)
    {
        return $this->task(' ➤  📄 <fg=cyan>Creating phpcs.xml file</>', function () use ($projectPath) {
            $command = $this->copy() . base_path() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'phpcs.xml ' . $projectPath;
            return $this->execOnProject($command, true)->isSuccessful();
        });
    }

    /**
     * @param string $projectPath
     * @return mixed
     */
    public function taskGenerateIdeHelperFiles()
    {
        return $this->task(' ➤  📄 <fg=cyan>Generating IDE Helper files</>', function () {
            return $this->execOnProject([
                PHP_BINARY . ' artisan ide-helper:eloquent --quiet',
                PHP_BINARY . ' artisan ide-helper:generate --quiet',
                PHP_BINARY . ' artisan ide-helper:meta --quiet',
            ])->isSuccessful();
        });
    }

    /**
     * @param array $providers
     */
    public function taskPublishVendorConfigFiles(array $providers)
    {
        $commands = [];
        foreach ($providers as $provider) {
            $commands[] = PHP_BINARY . ' artisan vendor:publish --provider="' . $provider . '" --tag=config --quiet';
        }

        $this->task(' ➤  📄 <fg=cyan>Publishing vendor config files</>', function () use ($commands) {
            return $this->execOnProject($commands)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskUpdateGitIgnore()
    {
        return $this->task(' ➤  📄 <fg=cyan>Updating .gitignore</>', function () {
            $command = 'echo ".idea/ \n.phpunit.result.cache \n.phpstorm.meta.php \n_ide_helper.php \n_ide_helper_models.php" >> .gitignore';
            return $this->execOnProject($command, true)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskInitializeGit()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Initializing git</>', function () {
            return $this->execOnProject('git init --quiet')->isSuccessful();
        });
    }

    /**
     * @param array $installHooksScript
     * @return mixed
     */
    public function taskCreatePhpCsPreCommitHook(array $installHooksScript)
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Creating phpcs "pre-commit-hook"</>',
            function () use ($installHooksScript) {
                $commands = array_merge(
                    [$this->copy() . base_path() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'pre-commit-hook.sh ' . $this->projectPath],
                    $installHooksScript
                );
                return $this->execOnProject($commands, true)->isSuccessful();
            });
    }

    /**
     * @return mixed
     */
    public function taskCreatePrivateGitHubRepository()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Creating private repository</>', function () {
            $this->newLine();
            return $this->execOnProject([
                'git add .',
                'git commit -m "Initial commit" --no-verify --quiet',
                'gh repo create ' . $this->directory . ' --private -y',
                'git push -u origin master --quiet'
            ], true)->isSuccessful();
        });
    }

    /**
     * @return mixed
     */
    public function taskStartGitFlow()
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Starting git flow</>', function () {
            $command = 'git flow init -d > ' . (PHP_OS_FAMILY == 'Windows' ? 'NUL' : '/dev/null 2>&1');
            return $this->execOnProject($command, true)->isSuccessful();
        });
    }

    /**
     * @param array $composerFile
     * @return mixed
     */
    public function taskUpdateComposerFile(array $composerFile)
    {
        return $this->task(' ➤  🆙 <fg=cyan>Updating composer.json</>', function () use ($composerFile) {
            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            $newComposerString = json_encode($composerFile, $jsonOptions);

            return file_put_contents($this->projectPath . '/composer.json', $newComposerString);
        });
    }

    /**
     * @param string $message
     * @return mixed
     */
    public function taskCommitChangesToGitHubMaster($message)
    {
        return $this->task(' ➤  ☁️  <fg=cyan>Committing last changes</>', function () use ($message) {
            return $this->execOnProject([
                'git checkout master --quiet',
                'git add .',
                'git commit -m "' . $message . '" --no-verify --quiet',
                'git push origin master --quiet'
            ], true)->isSuccessful();
        });
    }

    /**
     * @param string $directory
     * @return mixed
     */
    public function taskValetInstallSSL($directory)
    {
        return $this->task(' ➤  ⏳ <fg=cyan>Applying local SSL to "' . $directory . '"</>', function () use ($directory) {
                $this->newLine();
                $this->line('<fg=#a9a9a9>Your sudo password may be requested at this step.</>');
                return $this->execOnProject('valet secure ' . $directory, true)->isSuccessful();
            });
    }

    /**
     * @param string $directory
     * @return mixed
     */
    public function taskValetOpenProjectOnBrowser($directory)
    {
        return $this->task(' ➤  🌎 <fg=cyan>Opening ' . $directory . ' in your browser</>',
            function () use ($directory) {
                return $this->execOnProject('valet open ' . $directory, true)->isSuccessful();
            });
    }

    /**
     * @see https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html#toolbox
     * @return mixed
     */
    public function taskLoadProjectOnPhpStorm()
    {
        $phpStormsByOS = [
            'Windows' => 'phpstorm.bat',
            'Linux' => 'phpstorm',
            'Darwin' => 'open -a "PhpStorm.app"',
        ];

        return $this->task(' ➤  🖥  <fg=cyan>Loading project on PhpStorm</>', function () use ($phpStormsByOS) {
            return $this->execOnProject($phpStormsByOS[PHP_OS_FAMILY] . ' .', true)->isSuccessful();
        });
    }
}
