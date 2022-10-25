<?php

namespace Tests;

use App\Traits\ProcessHelper;
use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    use ProcessHelper;

    /**
     * @var string
     */
    public $scaffoldDirectory;

    /**
     * @var string
     */
    public $scaffoldDirectoryName = 'tests-output/my-app';

    /**
     * @var string
     */
    public $scaffoldProjectBaseName;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->scaffoldProjectBaseName = basename($this->scaffoldDirectoryName);
        $this->scaffoldDirectory = __DIR__.'/../'.$this->scaffoldDirectoryName;

        return $app;
    }

    public function deleteScaffoldDirectoryIfExists()
    {
        if (file_exists($this->scaffoldDirectory)) {
            if (PHP_OS_FAMILY == 'Windows') {
                exec("rd /s /q \"$this->scaffoldDirectory\"");
            } else {
                exec("rm -rf \"$this->scaffoldDirectory\"");
            }
        }
    }
}
