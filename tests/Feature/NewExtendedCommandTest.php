<?php

test('💻 Installed Laravel', function () {
    $this->deleteScaffoldDirectoryIfExists();

    $this->artisan('new ' . $this->scaffoldDirectoryName . ' --quiet -f')
        ->expectsConfirmation($this->buildQuestionText('Include PHP_CodeSniffer?'), 'yes')
        ->expectsConfirmation($this->buildQuestionText('Include Laravel IDE Helper?'), 'yes')
        ->expectsConfirmation($this->buildQuestionText('Initialize git?'), 'yes')
        ->expectsConfirmation($this->buildQuestionText('Create <fg=green>pre-commit-hook</>?', 'To validate PHPCS before committing a code.'), 'yes')
        ->expectsConfirmation($this->buildQuestionText('Create GitHub repository for <fg=green>' . $this->scaffoldDirectoryName . '</>?', 'GitHub CLI required. Check: https://cli.github.com'), 'no')
        ->expectsConfirmation($this->buildQuestionText('Install custom scripts on composer.json?', 'To be easier to run PHPCS or generate ide-helper files.'), 'yes')
        ->expectsConfirmation($this->buildQuestionText('Apply local SSL to <fg=green>' . $this->scaffoldDirectoryName . '</>?', 'Laravel Valet required. Check https://laravel.com/docs/8.x/valet'), 'no')
        ->expectsConfirmation($this->buildQuestionText('Open <fg=green>' . $this->scaffoldDirectoryName . '</> on PhpStorm?', 'Jetbrains CLI required. Check https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html'), 'no')
        ->assertExitCode(0);

    $this->assertDirectoryExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'vendor');
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . '.env');
});

test('📚 Installed additional dev dependencies', function () {
    $this->assertDirectoryExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'vendor/barryvdh/laravel-ide-helper');
    $this->assertDirectoryExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'vendor/squizlabs/php_codesniffer');
});

test('📄 Created phpcs.xml file', function () {
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'phpcs.xml');
});

test('📑 Generated IDE Helper files', function () {
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . '.phpstorm.meta.php');
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . '_ide_helper.php');
});

test('📂 Published vendor config files', function () {
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'config/ide-helper.php');
});

test('📄 Updated .gitignore', function () {
    $gitignore = file_get_contents($this->scaffoldDirectory . DIRECTORY_SEPARATOR . '.gitignore');

    $filesIgnoredToCheck = [
        '.idea/',
        '.phpunit.result.cache',
        '.phpstorm.meta.php',
        '_ide_helper.php',
        '_ide_helper_models.php'
    ];

    foreach ($filesIgnoredToCheck as $file) {
        $this->assertStringContainsString($file, $gitignore);
    }
});

test('☁️ Initialized git', function () {
    $this->assertDirectoryExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . '.git');
});

test('☁️ Created pre-commit-hook.sh file', function () {
    $this->assertFileExists($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'pre-commit-hook.sh');
});

test('📃 Updated README.md file', function () {
    $expectedReadMe = str_replace('projectName', $this->scaffoldDirectoryName, Storage::get('README.md'));
    $projectReadMe = file_get_contents($this->scaffoldDirectory . DIRECTORY_SEPARATOR . 'README.md');

    $this->assertEquals($expectedReadMe, $projectReadMe);
});

test('🆙 Updated composer.json', function () {
    $composer = $this->getProjectComposerFile($this->scaffoldDirectory);
    $this->assertIsArray($composer);

    $scriptsToCheck = [
        'post-update-cmd',
        'install-hooks',
        'pre-install-cmd',
        'post-install-cmd',
        'phpcs',
        'phpcbf',
        'optimize'
    ];

    foreach ($scriptsToCheck as $script) {
        $this->assertArrayHasKey($script, $composer['scripts']);
    }

    $this->assertArrayHasKey('squizlabs/php_codesniffer', $composer['require-dev']);
    $this->assertArrayHasKey('barryvdh/laravel-ide-helper', $composer['require-dev']);
});
