# Contributing to WP Pinch

Thank you for your interest in contributing to WP Pinch. Whether you're reporting a bug, suggesting a feature, improving documentation, or writing code, your contributions are valued and appreciated. This guide outlines the process and expectations for contributing. We're glad to have another lobster in the reef.

**AI coding assistants:** Read [AGENTS.md](AGENTS.md) first. It explains WP Pinch's architecture, extension points (abilities, governance, hooks), and how to add or improve features.

**Website:** [wp-pinch.com](https://wp-pinch.com) | **Repository:** [GitHub](https://github.com/RegionallyFamous/wp-pinch)

## Code of Conduct

This project adheres to a Code of Conduct. By participating, you are expected to uphold this standard. Please read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) before contributing.

## Reporting Bugs

If you encounter a bug, please [open an issue](https://github.com/RegionallyFamous/wp-pinch/issues/new) on GitHub. To help us resolve the issue efficiently, include the following information:

- A clear and descriptive title.
- Steps to reproduce the issue.
- Expected behavior versus actual behavior.
- Your environment details: WordPress version, PHP version, browser (if applicable), and any relevant plugin or theme conflicts.
- Screenshots or error logs, if available.

Please search [existing issues](https://github.com/RegionallyFamous/wp-pinch/issues) before opening a new one to avoid duplicates.

## Suggesting Features

Feature suggestions are welcome. To propose a new feature, [open a feature request](https://github.com/RegionallyFamous/wp-pinch/issues/new?template=feature_request.md) on GitHub. Please include:

- A clear description of the problem the feature would solve.
- Your proposed solution or approach.
- Any alternatives you have considered.
- Additional context, such as mockups or references to similar implementations.

## Development Setup

### Prerequisites

- WordPress 6.9+
- PHP 8.1+
- Node.js 20+
- Composer
- Docker (required by `@wordpress/env`)

### Getting Started

1. **Fork and clone the repository:**

   ```bash
   git clone https://github.com/your-username/wp-pinch.git
   cd wp-pinch
   ```

2. **Install JavaScript dependencies:**

   ```bash
   npm install
   ```

3. **Install PHP dependencies:**

   ```bash
   composer install
   ```

4. **Start the local WordPress environment:**

   ```bash
   npx wp-env start
   ```

   This spins up a Docker-based WordPress instance using the configuration in `.wp-env.json`.

5. **Build assets:**

   For a one-time production build:

   ```bash
   npm run build
   ```

   For development with file watching and automatic rebuilds:

   ```bash
   npm run start
   ```

6. **Access the local site:**

   Open [http://localhost:8888](http://localhost:8888) in your browser. The default admin credentials are `admin` / `password`.

## Quality System

WP Pinch uses a multi-layer quality system that automatically catches issues before they reach `main`. Nothing merges unless every check passes.

### One-Time Setup

After cloning, install the git pre-commit hook:

```bash
make setup-hooks
```

This runs PHPCS and PHPStan on every commit automatically. You'll be stopped from committing code with violations.

### Running Checks

Run everything (same as CI) with one command:

```bash
make check
```

Or run individual tools:

| Command | What it does |
|---|---|
| `make lint` | PHPCS + ESLint + Stylelint |
| `make phpstan` | PHPStan level 6 static analysis |
| `make test` | PHPUnit test suite (requires WP test environment) |
| `make test-coverage` | PHPUnit with HTML coverage report (requires PCOV or Xdebug; report in `build/coverage/index.html`) |
| `make check` | All of the above at once |
| `make lint-fix` | Auto-fix PHPCS violations |

### Test coverage

To generate a local coverage report, install [PCOV](https://github.com/pcov/pcov) or Xdebug, then run:

```bash
make test-coverage
```

Open `build/coverage/index.html` in a browser. CI does not run coverage (to keep job time down); use this to find untested code before submitting a PR.

### E2E tests (Playwright)

End-to-end tests run against a real WordPress instance (admin and chat block flows). They are not run in CI. Before submitting a PR that touches the Pinch Chat block or WP Pinch admin UI, run them locally:

```bash
npx wp-env start
npm run test:e2e
```

Specs live in `tests/e2e/`. Use the same `admin` / `password` credentials as the local site.

### Load testing (k6)

A [k6](https://k6.io/) script exercises the REST API (chat, status, health) under load. Run it manually when you change performance-critical paths or before a major release.

**Prerequisites:** Install [k6](https://k6.io/docs/get-started/installation/). Have a running WordPress site with WP Pinch configured and a user that can authenticate (e.g. application password or basic auth).

**Example (after starting wp-env):**

```bash
k6 run --env BASE_URL=http://localhost:8888 \
       --env WP_USER=admin \
       --env WP_PASS=password \
       tests/load/k6-chat.js
```

For basic auth with a token, set `WP_AUTH_TOKEN` to the base64-encoded `user:password` and ensure the script uses it (see `tests/load/k6-chat.js`). Thresholds (e.g. p95 latency, error rate) are defined in the script.

### CI Pipeline

Every push and PR runs several checks. All must pass to merge:

1. **PHPCS + PHPStan** — WordPress coding standards, security sniffs, and level 6 static analysis (PHP 8.1/8.2/8.3)
2. **PHPUnit** — 160+ tests across PHP 8.1/8.2/8.3 + WP latest and WP 6.9
3. **JS / CSS lint + build** — ESLint, Stylelint, asset compilation, and JS unit tests
4. **Dependency audit** — Composer and npm security audits
5. **CodeQL** — Static application security testing (SAST) on PHP
6. **Dependency review** (PRs only) — Fails if the PR adds a dependency with a known vulnerability

### Performance Profiling

The local development environment includes [Query Monitor](https://querymonitor.com/). After starting `wp-env`, activate it from the Plugins page. Use it to identify slow queries, excessive hook calls, and other performance issues before they become bugs.

```bash
make wp-env-start
```

## Bug Fix Workflow (Test-First)

Every bug fix must follow this process. No exceptions.

1. **Write a failing test first.** Before touching any code, write a PHPUnit test in the appropriate `tests/test-*.php` file that reproduces the bug. Run `make test` and confirm it fails.

2. **Fix the code.** Make the smallest change that fixes the bug.

3. **Run `make check`.** Confirm the test now passes and no other checks broke.

4. **Commit both the test and the fix together.** The commit message should reference the issue number.

This ensures every bug becomes a permanent regression test. The test suite grows organically from real issues and becomes stronger over time.

### Example

Say you find that `execute_list_comments()` returns the wrong total count:

```php
// tests/test-abilities.php — add a test that fails:
public function test_list_comments_total_not_affected_by_pagination(): void {
    $post_id = $this->factory->post->create();
    $this->factory->comment->create_many( 5, array( 'comment_post_ID' => $post_id ) );

    $result = Abilities::execute_list_comments(
        array( 'per_page' => 2, 'page' => 1, 'post_id' => $post_id )
    );

    // Bug: total returns 2 instead of 5.
    $this->assertEquals( 5, $result['total'] );
}
```

Run `make test` — the test fails. Fix the bug. Run `make check` — everything passes. Commit.

## Coding Standards

### PHP

All PHP code must conform to the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). The project uses a `phpcs.xml.dist` configuration that includes WordPress, WordPress-Extra, and WordPress-Security rulesets.

```bash
composer lint        # Check for violations
composer lint:fix    # Auto-fix violations
```

### JavaScript

JavaScript is linted using the ESLint configuration provided by `@wordpress/scripts`. Run `npm run lint:js` to check for issues and `npm run lint:js -- --fix` to auto-fix where possible.

### CSS

CSS is linted using the Stylelint configuration provided by `@wordpress/scripts`. Run `npm run lint:css` to check for issues and `npm run lint:css -- --fix` to auto-fix where possible.

## Pull Request Process

1. **Create a feature branch** from `main`:

   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Write or update tests** to cover your changes. All new functionality should include corresponding test coverage.

3. **Follow the coding standards** described above. Ensure linting and tests pass before submitting.

4. **Write clear, descriptive commit messages.** Each commit should represent a logical unit of change. Use the imperative mood (e.g., "Add validation for settings input" rather than "Added validation").

5. **Push your branch** and [open a pull request](https://github.com/RegionallyFamous/wp-pinch/pulls) against the `main` branch.

6. **Describe your changes** in the pull request description. Include:
   - What the change does and why it is needed.
   - Any related issue numbers (e.g., "Closes #42").
   - Steps to test the change, if applicable.

7. A maintainer will review your pull request. Please be responsive to feedback and make requested changes in a timely manner.

## Release Process

Releases are managed by project maintainers. The general process is:

1. Merge approved pull requests into `main`.
2. Update the version number in the plugin header, `readme.txt`, and any other relevant files.
3. Update the changelog.
4. **Run `make zip`** — this builds JS/CSS assets and creates the distributable zip. The zip must include the `build/` directory or users will get 404 errors for admin.js/admin.css.
5. Tag the release and publish. Attach the generated zip (e.g. `wp-pinch-1.0.1.zip`) to the GitHub release. For "latest" download compatibility, consider also attaching a copy named `wp-pinch.zip`.

Contributors do not need to worry about versioning or releases — simply target the `main` branch with your pull requests.

## Dependencies and licenses

WP Pinch is GPL-2.0-or-later. All Composer dependencies (Action Scheduler, Jetpack Autoloader, PHPUnit, WPCS, PHPStan, etc.) are used in a way that is compatible with that license. npm dependencies are predominantly MIT/BSD/Apache-2.0/ISC and are compatible with the plugin’s distribution. We do not ship proprietary or GPL-incompatible code. If you add a new dependency, ensure its license is compatible with GPL-2.0-or-later before submitting a PR.

## License

WP Pinch is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html). By submitting a contribution to this project, you agree that your contribution will be licensed under the same terms. You retain copyright over your contributions, but grant the project and its users the rights described in the license.
