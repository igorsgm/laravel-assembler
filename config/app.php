<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => 'Laravel Assembler',

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | This value determines the "version" your application is currently running
    | in. You may want to follow the "Semantic Versioning" - Given a version
    | number MAJOR.MINOR.PATCH when an update happens: https://semver.org.
    |
    */

    'version' => app('git.version'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. This can be overridden using
    | the global command line "--env" option when calling commands.
    |
    */

    'env' => 'development',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Packages to Install
    |--------------------------------------------------------------------------
    |
    | This array contains the list of composer packages that the user will
    | be asked whether they wish to install or not.
    | The packages under 'require-dev' will be installed as --dev only.
    |
    */

    'additional-packages' => [

        'require-dev' => [
            'phpcs' => [
                'title' => 'PHP_CodeSniffer',
                'package' => 'squizlabs/php_codesniffer',
                'default-answer' => false,
            ],
            'ide-helper' => [
                'title' => 'Laravel IDE Helper',
                'package' => 'barryvdh/laravel-ide-helper',
                'provider' => 'Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider',
                'default-answer' => true,
            ],
        ],
    ],

];
