# Contributing to Caddy Souin Cache Manager

First off, thank you for considering contributing to this project! Your help is greatly appreciated.

## How Can I Contribute?

*   **Reporting Bugs**: If you find a bug, please open an issue and provide as much detail as possible, including steps to reproduce, your WordPress version, and your Caddy/Souin configuration.
*   **Suggesting Enhancements**: If you have an idea for a new feature or an improvement to an existing one, open an issue to discuss it.
*   **Pull Requests**: If you're ready to contribute code, you can open a pull request.

## Development Setup

To get started with development, you'll need to have Node.js, npm, and Composer installed.

1.  **Clone the repository**:
    ```shell
    git clone https://github.com/morsalin1342/caddy-souin-cache-manager.git
    cd caddy-souin-cache-manager
    ```

2.  **Install PHP dependencies**:
    ```shell
    composer install
    ```

3.  **Install JavaScript dependencies**:
    ```shell
    npm ci
    ```

4.  **Start the development server**:
    This will watch for changes in the `assets/src` directory and automatically recompile the assets.
    ```shell
    npm run dev
    ```

5.  **Build for production**:
    When you're ready to create a production build, run:
    ```shell
    npm run build
    ```

## Running Tests

### Unit tests

The PHP code is unit tested with PHPUnit, using [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) to stand in for WordPress functions, so no WordPress install is needed:

```shell
composer install
composer test        # or ./vendor/bin/phpunit
composer lint        # php -l on every PHP file
```

### End-to-end tests

`tests/e2e/run.sh` starts WordPress, MariaDB and Caddy with the Souin cache handler in Docker, caches pages through Caddy, then publishes, unpublishes, trashes and deletes posts with WP-CLI and checks which cache keys are left. It also covers the `wp souin` commands and the settings REST endpoint.

```shell
composer install
tests/e2e/run.sh             # tears the stack down when done
KEEP=1 tests/e2e/run.sh      # leaves it running on http://localhost:18080
```

It needs Docker with Compose. The Caddy image defaults to `morsalin1342/caddy:latest`; set `CADDY_IMAGE` to use any other Caddy build that includes the cache handler.

### CI

Every push and pull request runs `.github/workflows/ci.yml`: `php -l` and the unit tests on PHP 8.1 to 8.5, `composer audit`, `npm ci` with the asset build, and `npm audit --audit-level=high`. The end-to-end tests are not run in CI; run them locally when you change purging.

Please make sure the tests pass before submitting a pull request, and add tests for new behaviour.

## Pull Request Process

1.  Keep each pull request to one change, and describe how you tested it.
2.  Update `README.md` when you change behaviour, settings or `wp souin` commands.
3.  Add an entry to `changelog.txt`. Version numbers are bumped when a release is made.

Thank you for your contribution!
