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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this
     */
    public function setInputAndOutput(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        return $this;
    }

    /**
     * @param $projectName
     * @return string
     */
    public function projectPath($projectName)
    {
        return $projectName !== '.' ? getcwd() . '/' . $projectName : '.';
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
     * @param array $commands
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return Process
     */
    public function exec($commands)
    {
        return parent::runCommands($commands, $this->input, $this->output);
    }

    /**
     * Replace the given string in the given file.
     *
     * @param string $search
     * @param string $replace
     * @param string $file
     * @return string
     */
    public function replaceInFile(string $search, string $replace, string $file)
    {
        return parent::replaceInFile($search, $replace, $file);
    }
}
