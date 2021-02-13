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
    public $projectPath;

    /**
     * @param string $directoryName
     */
    public function setDirectoryAndPath($directoryName)
    {
        $this->directory = $directoryName;
        $this->projectPath = $directoryName !== '.' ? getcwd() . '/' . $directoryName : '.';
    }

    /**
     * OVERRIDE to always ask the question using the white color.
     * Just for styling :P
     * Confirm a question with the user.
     *
     * @param string $question
     * @param bool $default
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        return parent::confirm("❓<fg=white> $question</>", $default);
    }

    /**
     * Run the given commands inside the project's directory
     *
     * @param string|array $commands
     * @param false $ignoreOptions
     * @return Process
     */
    public function execOnProject($commands, $ignoreOptions = false)
    {
        return $this->exec($commands, $this->projectPath, $ignoreOptions);
    }

    /**
     * Run the given commands.
     *
     * @param array|string $commands
     * @param string|null $cwd The working directory or null to use the working dir of the current PHP process
     * @param bool $ignoreOptions
     * @return Process
     */
    public function exec($commands, $cwd = null, $ignoreOptions = false)
    {
        if (!is_array($commands)) {
            $commands = [$commands];
        }

        if (!$ignoreOptions && $this->input->getOption('no-ansi')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value . ' --no-ansi';
            }, $commands);
        }

        if (!$ignoreOptions && $this->input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (substr($value, 0, 5) === 'chmod') {
                    return $value;
                }

                return $value . ' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $cwd, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('Warning: ' . $e->getMessage());
            }
        }

        $process->run(function ($type, $line) {
            $this->output->write('    ' . $line);
        });

        return $process;
    }

    /**
     * The copy command based on OS type
     * @return string
     */
    public function copy()
    {
        return (PHP_OS_FAMILY == 'Windows' ? 'copy ' : 'cp ');
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd() . '/composer.phar';

        if (file_exists($composerPath)) {
            return '"' . PHP_BINARY . '" ' . $composerPath;
        }

        return 'composer';
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
}
