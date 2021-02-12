<?php

namespace App\Helpers;

use Laravel\Installer\Console\NewCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CommandHelper extends NewCommand
{
    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * @var string
     */
    protected $projectPath;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this
     */
    public function setData(InputInterface $input, OutputInterface $output, $projectPath)
    {
        $this->input = $input;
        $this->output = $output;
        $this->projectPath = $projectPath;

        return $this;
    }

    /**
     * @param $projectName
     * @return string
     */
    public function projectDirectory($projectName)
    {
        return $projectName !== '.' ? getcwd() . '/' . $projectName : '.';
    }

    /**
     * @param $projectPath
     * @return mixed
     */
    public function getProjectComposerFile($projectPath)
    {
        $composer = file_get_contents($projectPath . '/composer.json');
        return json_decode($composer, true);
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    public function findComposer()
    {
        return parent::findComposer();
    }

    /**
     * Run the given commands.
     *
     * @param string|array $commands
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return Process
     */
    public function exec($commands)
    {
        if (!is_array($commands)) {
            $commands = [$commands];
        }

        return parent::runCommands($commands, $this->input, $this->output);
    }

    /**
     * Run the given commands.
     *
     * @param string|array $commands
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return Process
     */
    public function execOnProject($commands)
    {
        if (!is_array($commands)) {
            $commands = [$commands];
        }

        foreach ($commands as $key => $command) {
            $commands[$key] = 'cd ' . $this->projectPath . ' && ' . $command;
        }

        return parent::runCommands($commands, $this->input, $this->output);
    }

    public function copy()
    {
        return (PHP_OS_FAMILY == 'Windows' ? 'copy ' : 'cp ');
    }

    /**
     * @see https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html#toolbox
     * @return string
     */
    public function findPhpStorm()
    {
        $commands = [
            'Windows' => 'phpstorm.bat',
            'Linux' => 'phpstorm',
            'Darwin' => 'open -a "PhpStorm.app"',
        ];

        return $commands[PHP_OS_FAMILY] . ' ';
    }
}
