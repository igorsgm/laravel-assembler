<?php

namespace App\Traits;

use RuntimeException;
use Symfony\Component\Process\Process;

trait ProcessHelper
{
    /**
     * @var string
     */
    public $directory;

    /**
     * @var string
     */
    public $projectBaseName;

    /**
     * @var string
     */
    public $projectPath;

    /**
     * @param  string  $directoryName
     */
    public function setDirectoryAndPath($directoryName)
    {
        $this->directory = $directoryName;
        $this->projectBaseName = basename($directoryName);
        $this->projectPath = $directoryName !== '.' ? getcwd().'/'.$directoryName : '.';
    }

    /**
     * Builds the string for a formatted confirmation question
     *
     * @param  string  $question
     * @param  string  $comment
     * @return string
     */
    public function buildQuestionText($question, $comment = '')
    {
        $question = "<fg=white> $question</>";

        if (! empty($comment)) {
            $question .= PHP_EOL.' <fg=#a9a9a9>'.$comment.'</>';
        }

        return $question;
    }

    /**
     * Builds the string for a command without console output
     *
     * @param  string  $command
     * @return string
     */
    public function buildNoOutputCommand($command = '')
    {
        return trim($command).' > '.(PHP_OS_FAMILY == 'Windows' ? 'NUL' : '/dev/null 2>&1');
    }

    /**
     * Run the given commands inside the project's directory
     *
     * @param  string|array  $commands
     * @param  false  $ignoreOptions
     * @param  bool  $silent
     * @return Process
     */
    public function execOnProject($commands, $ignoreOptions = false, $silent = false)
    {
        return $this->exec((array) $commands, $this->projectPath, $ignoreOptions, $silent);
    }

    /**
     * Run the given commands.
     *
     * @param  array|string  $commands
     * @param  string|null  $cwd The working directory or null to use the working dir of the current PHP process
     * @param  bool  $ignoreOptions
     * @return Process
     */
    public function exec($commands, $cwd, $ignoreOptions, $silent)
    {
        if (! $ignoreOptions && ! $this->getOutput()->isDecorated()) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if (! $ignoreOptions && $this->input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        if ($silent) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $this->buildNoOutputCommand($value);
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $cwd, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->getOutput()->writeln('Warning: '.$e->getMessage());
            }
        }

        $process->run(function ($type, $line) {
            $this->getOutput()->write('    '.$line);
        });

        return $process;
    }

    /**
     * The copy command based on OS type
     *
     * @return string
     */
    public function copy()
    {
        return PHP_OS_FAMILY == 'Windows' ? 'copy ' : 'cp ';
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd().'/composer.phar';

        if (file_exists($composerPath)) {
            return '"'.PHP_BINARY.'" '.$composerPath;
        }

        return 'composer';
    }

    /**
     * @param $projectPath
     * @return mixed
     */
    public function getProjectComposerFile($projectPath)
    {
        $composer = file_get_contents($projectPath.'/composer.json');

        return json_decode($composer, true);
    }

    /**
     * @param  string  $binFileNameWithParams
     * @return string
     */
    public function vendorBin($binFileNameWithParams)
    {
        return '.'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.$binFileNameWithParams;
    }
}
