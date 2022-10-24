<?php

namespace App\Helpers;

use Laravel\Installer\Console\NewCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class BaseNewCommand extends NewCommand
{
    /**
     * OVERRIDE
     * Return the local machine's default Git branch if set or default to `main`.
     *
     * @return string
     */
    public function defaultBranch()
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'master';
    }

    /**
     * OVERRIDE
     * Commit any changes in the current working directory.
     *
     * @param  string  $message
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function commitChanges(string $message, string $directory, InputInterface $input, OutputInterface $output)
    {
        parent::commitChanges($message, $directory, $input, $output);
    }

    /**
     * OVERRIDE
     * Create a GitHub repository and push the git log to it.
     *
     * @param  string  $name
     * @param  string  $directory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function pushToGitHub(string $name, string $directory, InputInterface $input, OutputInterface $output)
    {
        parent::pushToGitHub($name, $directory, $input, $output);
    }

    /**
     * OVERRIDE
     * Get the composer command for the environment.
     *
     * @return string
     */
    public function findComposer()
    {
        return parent::findComposer();
    }

    /**
     * OVERRIDE
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $file
     * @return void
     */
    public function replaceInFile(string $search, string $replace, string $file)
    {
        parent::replaceInFile($search, $replace, $file);
    }
}
