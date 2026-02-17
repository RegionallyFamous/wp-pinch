# Contributing to WP Pinch

Thanks for your interest in contributing. Whether you're reporting a bug, suggesting a feature, or writing code, we're glad to have you.

**AI coding assistants:** Read [AGENTS.md](AGENTS.md) first. It explains WP Pinch's architecture, extension points (abilities, governance, hooks), and how to add or improve features.

## How to contribute

**Bugs and ideas:** [Open an issue](https://github.com/RegionallyFamous/wp-pinch/issues/new). Describe what you're seeing or what you'd like to see. For bugs, include steps to reproduce and your environment (WordPress version, PHP version) if relevant. We'll figure it out from there. I'm the main maintainer; response times vary.

**Code:** Fork, make your changes, run the quality gate (below), and open a pull request. The [PR template](.github/PULL_REQUEST_TEMPLATE.md) has a short checklist.

## Code of Conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold it.

## Development setup

- **Prerequisites:** WordPress 6.9+, PHP 8.1+, Node.js 20+, Composer, Docker (for wp-env).
- **Clone** the repo, then:

  ```bash
  composer install
  npm install
  npx wp-env start
  npm run build
  ```

- **Local site:** [http://localhost:8888](http://localhost:8888) (default admin: `admin` / `password`).

## Quality gate

Before pushing, run:

```bash
make check
```

That runs PHPCS, PHPStan, and lint on the test files. All must pass.

For full CI parity (including PHPUnit), you need the WordPress test environment. See the [Developer Guide](wiki/Developer-Guide.md) for wp-env and test setup. Then run:

```bash
make ci
```

Optional: `make setup-hooks` installs a pre-commit hook that runs `make check` on each commit.

Spot a typo or outdated doc? A PR or issue is welcome.

## License

WP Pinch is GPL-2.0-or-later. By contributing, you agree that your contribution will be licensed under the same terms.
