# Contributing

Welcome! 🎉 We're excited that you're interested in contributing to the AI module. Whether you're a seasoned Drupal developer or just getting started, this guide will help you set up your environment and get involved.

## Setting Up Your Local Environment with DDEV

To make development easy and consistent, we recommend using [DDEV](https://ddev.com/get-started/) (version 1.24.0 or later). DDEV helps you quickly spin up a local Drupal environment.

## DDEV for your local environment

**Getting started:**

Run the following commands to set up your local environment with DDEV:

1. Create a new directory and enter it:
   ```shell
   mkdir ai-dev && cd ai-dev
   ```
2. Configure DDEV for a Drupal 11 project:
   ```shell
   ddev config --project-type=drupal11 --docroot=web --php-version=8.3 --corepack-enable
   ```
3. Add the Drupal Suite add-on:
   ```shell
   ddev add-on get lussoluca/ddev-drupal-suite
   ```
4. Use personal ssh keys for git operations:
   ```shell
   ddev auth ssh
   ```
5. Initialize Drupal:
   ```shell
   ddev drupal-init 11.2.2
   ```
6. Install the AI module and development recipe:
   ```shell
   ddev drupal-get-module ai 1.2.x
   ddev drupal-get-recipe ai_dev_recipe 1.0.x
   ```

This is handy when you want to test the most recent updates or contribute to ongoing development.

During setup, DDEV will prompt you for an API key for your preferred AI provider (currently OpenAI and Anthropic are supported). Just follow the instructions in your terminal.

Once everything is set up, you can launch your site with:
```shell
ddev launch
```
Or simply visit the URL that DDEV prints out.

To see all running services, use:
```shell
ddev describe
```

### Working with Multiple Modules

Often, you’ll need to develop or test several AI-related modules at the same time. DDEV makes this easy! Just run the `ddev drupal-get-module` command for each module you want to add to your environment. For example:

```shell
ddev drupal-get-module ai 1.2.x
ddev drupal-get-module ai_agents 1.2.x
ddev drupal-get-module ai_translate 1.2.x
```

This approach lets you work on multiple modules side-by-side, which is especially helpful for cross-module features, debugging, or reviewing changes. You can add or remove modules as needed without affecting your main setup.

**Tip:**
If you omit the version when running `ddev drupal-get-module`, DDEV will fetch the module’s default branch (usually the latest development version). For example:

```shell
ddev drupal-get-module ai
```

## Development tools

Some tools are available to help you with development:

### `phpcs`

You can check your code for Drupal standards compliance using the `ddev phpcs` command. This tool validates your module and optionally fixes issues.

**Usage:**
```shell
ddev phpcs <module_name> [fix]
```
Replace `<module_name>` with the machine name of your module (e.g., `ai`).

**Examples:**
```shell
ddev phpcs ai           # Checks the 'ai' module for coding standards
ddev phpcs ai fix       # Automatically fixes fixable issues in the 'ai' module
```

**Notes:**
- The module name must start with a lowercase letter and only contain lowercase letters, numbers, and underscores.
- If your module has a custom `phpcs.xml` file in its directory, it will be used for the check. Otherwise, a default configuration will be downloaded and used.
- The command provides a full report, summary, and source of any issues found.

### `phpstan`

You can analyze your code for bugs and potential issues using the `ddev phpstan` command. This tool runs PHPStan on your module and reports any problems found.

**Usage:**
```shell
ddev phpstan <module_name>
```
Replace `<module_name>` with the machine name of your module (e.g., `ai`).

**Example:**
```shell
ddev phpstan ai           # Analyzes the 'ai' module for bugs and issues
```

**Notes:**
- The module name must start with a lowercase letter and only contain lowercase letters, numbers, and underscores.
- The command uses your module's custom `phpstan.neon` configuration file if present.
- The analysis is run with a memory limit of 256M to avoid out-of-memory errors.

### `phpunit`

You can run tests for your module using the `ddev phpunit` command. This tool runs PHPUnit on your module and reports the results.

**Usage:**
```shell
ddev phpunit <module_name>
```
Replace `<module_name>` with the machine name of your module (e.g., `ai`).

**Example:**
```shell
ddev phpunit ai           # Runs all tests for the 'ai' module
```

**Notes:**
- The module name must start with a lowercase letter and only contain lowercase letters, numbers, and underscores.
- If your module has a custom `phpunit.xml.dist` file, it will be used for configuration. Otherwise, the command will bootstrap Drupal tests and run all custom module tests.
- Temporary configuration files are cleaned up automatically after the tests run.

If you want to run a specific test, you can use the `--filter` option:
```shell
ddev phpunit <module_name> --filter testName
```

### Selenium for Functional Tests
A Selenium hub is available to run functional tests. You can use the `ddev launch :7900` command to open the Selenium VNC viewer and see the tests running in the browser.
