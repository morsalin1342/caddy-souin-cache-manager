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
    npm install
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

This project uses PHPUnit for backend testing. To run the tests, use the following command:

```shell
./vendor/bin/phpunit
```

Please ensure that all tests are passing before submitting a pull request. If you're adding a new feature, please include tests for it.

## Pull Request Process

1.  Ensure any install or build dependencies are removed before the end of the layer when doing a build.
2.  Update the `README.md` with details of changes to the interface, this includes new environment variables, exposed ports, useful file locations and container parameters.
3.  Increase the version numbers in any examples and the `README.md` to the new version that this Pull Request would represent. The versioning scheme we use is [SemVer](http://semver.org/).
4.  You may merge the Pull Request in once you have the sign-off of two other developers, or if you do not have permission to do that, you may request the second reviewer to merge it for you.

Thank you for your contribution!
