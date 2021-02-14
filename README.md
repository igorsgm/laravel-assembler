<h1 align="center">🧰 Laravel Assembler</h1>

<p align="center">An extended <a href="https://laravel.com/docs/8.x/installation#the-laravel-installer" target="_blank">Laravel Installer</a> CLI that gives you the power to scaffold a new Laravel project and set up a git repository <b>in a single command</b>.</p>

<hr/>

## ✨ Features
- **Simple** setup process
- Integrate with [Laravel IDE Helper Generator](https://github.com/barryvdh/laravel-ide-helper) to improve code completion in your IDE   
- Integrate with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) to improve the code quality of your project
- Setup a **GitHub repository** with and easy to customize README
- Setup [Gitflow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) 
- Create a git pre-commit-hook to validate PHPCS before committing a code  
- Custom **composer scripts** to make your live simpler
- [Laravel Valet](https://laravel.com/docs/master/valet) integration: secure your project with SSL
- Open the project automatically in your browser + PhpStorm once the installation finishes 

In a single script!

Don't want to use any of the above features? **No problem**, all are optional.

------

## 0️⃣ Requirements

- Mandatory:
    - PHP 7.3 or higher
    - Composer

    > Make sure to place Composer's system-wide vendor bin directory in your $PATH so the `laravel-assembler` executable can be located by your system.
    This directory exists in different locations based on your operating system; however, some common locations include:
    > - macOS: `$HOME/.composer/vendor/bin`
    > - Windows: `%USERPROFILE%\AppData\Roaming\Composer\vendor\bin`
    > - GNU / Linux Distributions: `$HOME/.config/composer/vendor/bin` or `$HOME/.composer/vendor/bin`

- Optional:
    - [GitHub CLI](https://cli.github.com) with your user properly [logged in](https://cli.github.com/manual/gh_auth_login): to create the repository for your new project -- *the logged in user is going to be the owner of the repo*.
    - [gitflow-avh](https://github.com/petervanderdoes/gitflow-avh): to start git flow in your project.
    - [Laravel Valet](https://laravel.com/docs/master/valet): to set up a SSL certificate and open the project in the browser automatically.
    - [Jetbrains CLI's](https://www.jetbrains.com/help/phpstorm/working-with-the-ide-features-from-command-line.html) Launcher for a standalone instance: if you wish to open the project immediately on PhpStorm.   

## 1️⃣ Installation

```sh
composer global require igorsgm/laravel-assembler
```

## 🚀 Creating a new project

```sh
laravel-assembler new my-cool-project-name
```
- It will ask you a few questions during the process to help you get started and generate a new laravel project accordingly to your preferences.
- When the script finishes you should have a `my-cool-project-name` folder the path that you run your script.
- *Voilà!*

## ⚙️ Available commands

<p align="center"><img src="https://user-images.githubusercontent.com/14129843/107869503-c57b7a00-6e43-11eb-9298-7de408074fdb.png" /></p>
