<h2 align="center">projectName</h2>

<p align="center">Your project description here</p>

<hr/>

## Installation

> <h4>Before you start</h4> Make sure you have ...

#### 1) Cloning 
`git clone ...`

#### 2) Step 2

#### 3) Installing dependencies & configuring Laravel
- Inside projectName folder
    
    ```sh
    cp .env.example .env
    php artisan key:generate
    rm -rf vendor node_modules
    git checkout composer.json composer.lock
    composer install
    composer optimize
    php artisan migrate:fresh --seed
    npm install && npm run dev
    ```

### Voilà!
Now you should be able to access [https://your-project-url/](https://your-project-url/) in your browser.

<br>

## Pull requests

- This repo follows the [Gitflow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- If you are working on a feature branch with other team members, create a  `feature/your-feature-name` branch and send your PR to the develop branch.
- The project has a Git Hook set up to avoid code be committed if it's not passing the [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) rules.
    - So if your commit attempt fails with a message like:
        ```sh
         ---------------------------------------------------------------
         PHPCBF CAN FIX THE 2 MARKED SNIFF VIOLATIONS AUTOMATICALLY
         ---------------------------------------------------------------
        ```
      You can run: `composer phpcbf`
    - And to identify Code smell issues, run: `composer phpcs`

<br>

## Stack

- [Laravel 8](https://laravel.com/)
